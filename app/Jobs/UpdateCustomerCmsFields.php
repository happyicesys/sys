<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Customer;
use App\Traits\SearchAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ONE-TIME CMS → mark1 backfill for the Delivery Address, Billing Contact and
 * (Bank Details, when present) sections of an already-linked customer.
 *
 * This is NOT a runtime sync. The save-time CMS sync (SyncVendCustomerCms)
 * stays disabled — customers are edited in mark1 from now on. This job is only
 * ever invoked explicitly by UpdateCustomerCmsFieldsSeeder (or an ad-hoc
 * dispatch) to pull the migrated fields across ONCE. Re-running is safe
 * (idempotent updateOrCreate) and overwrites: for this backfill CMS is treated
 * as the source of truth.
 *
 * Scope (agreed):
 *  - Delivery Address: derived from OneMap by `del_postcode` (block / building
 *    / street / lat-lng), Singapore country, with `unit_num` deduced from the
 *    CMS address string (the "#NN-NN" token, kept verbatim incl. the '#').
 *  - Billing "same as delivery": decided by postcode + country. Same → mirror
 *    delivery into billing and tick the flag. Different → a second OneMap call
 *    for the billing postcode and a dedicated billing row.
 *  - Billing Contact: attn → Contact Person, contact → Phone, alt_contact →
 *    Alt Phone, email → Email (normalised), company → Company, SG phone codes.
 *  - is_gst_registered: true when CMS profile.gst is present.
 *  - cost_rate (Cost Rate %) and payterm (Terms label) mirror from CMS.
 *  - Attachments: CMS person files (file_person) are COPIED into mark1's own
 *    storage and recorded as Customer Attachment(s) (so they survive CMS being
 *    retired), de-duplicated by file name. config app.cms_files_url is only a
 *    fallback for any legacy relative file paths.
 *  - Bank Details: CMS has no bank fields → left untouched.
 *
 * Never creates a Customer. Source: CMS GET /api/person/migrate/{personID}.
 */
class UpdateCustomerCmsFields implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SearchAddress;

    protected $personID;

    public function __construct($personID = null)
    {
        $this->personID = $personID;
    }

    /**
     * @return string updated | skipped_no_person_id | skipped_no_base_url |
     *                skipped_no_customer | skipped_no_cms
     */
    public function handle(): string
    {
        if (!$this->personID) {
            return 'skipped_no_person_id';
        }

        $baseUrl = config('app.cms_url');
        if (!$baseUrl) {
            return 'skipped_no_base_url';
        }

        // Never create — only operate on an already-linked customer.
        $customer = Customer::withoutGlobalScopes()
            ->where('person_id', $this->personID)
            ->first();

        if (!$customer) {
            return 'skipped_no_customer';
        }

        $response = Http::get(rtrim($baseUrl, '/') . '/api/person/migrate/' . $this->personID);

        if (!$response->successful()) {
            Log::warning('UpdateCustomerCmsFields: CMS request failed', [
                'person_id' => $this->personID,
                'status' => $response->status(),
            ]);
            return 'skipped_no_cms';
        }

        $collection = $response->collect();

        // Endpoint returns a (possibly empty) list. Empty == person not found /
        // not a vending person → nothing to backfill.
        if (!$collection || !isset($collection[0])) {
            return 'skipped_no_cms';
        }

        $data = collect($collection[0]);

        $sgCountryId = optional(Country::where('name', 'Singapore')->first())->id;

        // ── 1. Billing Contact ────────────────────────────────────────────
        $profile = $data->get('profile');
        $attn    = is_array($profile) ? ($profile['attn'] ?? null) : null;
        $gstVal  = is_array($profile) ? ($profile['gst'] ?? null) : null;

        $altContact = $data->get('alt_contact');

        // contacts.name is NOT NULL. attn is the preferred Contact Person, but
        // it can be blank in CMS — fall back to the CMS customer name, then any
        // existing mark1 contact name, then a placeholder, so we never write
        // null.
        $contactName = $attn
            ?: ($data->get('name')
                ?: (optional($customer->contact)->name ?: 'N/A'));

        $customer->contact()->updateOrCreate(
            [],
            [
                // attn → Contact Person (the person to attention on billing).
                'name'                 => $contactName,
                'company'              => $data->get('company'),
                // Email may carry >1 address; Contact::setEmailAttribute
                // normalises it (split / trim / dedupe → comma-separated).
                'email'                => $data->get('email'),
                'phone_num'            => $data->get('contact'),
                'phone_country_id'     => $sgCountryId,
                'alt_phone_num'        => $altContact ?: null,
                'alt_phone_country_id' => $altContact ? $sgCountryId : null,
            ]
        );

        // ── 2. GST flag + Cost Rate / Terms ───────────────────────────────
        // GST: registered when CMS holds a GST value for the customer's
        // profile. cost_rate (percentage) and payterm (Terms label, e.g.
        // "15 Days after EOM") mirror straight across from CMS.
        $payterm = $data->get('payterm');

        $customer->update([
            'is_gst_registered' => !empty(is_string($gstVal) ? trim($gstVal) : $gstVal),
            'cost_rate'         => $data->get('cost_rate'),
            // payterm column is varchar(64) — guard against an oversized label.
            'payterm'           => $payterm !== null ? mb_substr((string) $payterm, 0, 64) : null,
        ]);

        // ── 3. Addresses ──────────────────────────────────────────────────
        $delPostcode  = $data->get('del_postcode');
        $billPostcode = $data->get('bill_postcode');
        $delCountry   = $this->countryName($data->get('delivery_country'));
        $billCountry  = $this->countryName($data->get('billing_country'));

        // "Same as delivery" decided by postcode + country only (per spec).
        // If billing has no postcode, treat as same (mirror delivery).
        $sameBilling = !$billPostcode
            || ($delPostcode && $billPostcode
                && (string) $delPostcode === (string) $billPostcode
                && strcasecmp((string) $delCountry, (string) $billCountry) === 0);

        $deliveryPayload = $this->buildAddressPayload(
            $delPostcode,
            $data->get('del_address'),
            $sgCountryId,
            $data->get('del_lat'),
            $data->get('del_lng')
        );

        if ($deliveryPayload) {
            $customer->addresses()->updateOrCreate(
                ['type' => Customer::ADDRESS_TYPE_DELIVERY],
                $deliveryPayload
            );
        }

        if ($sameBilling) {
            $customer->update(['is_billing_same_as_delivery' => true]);

            // Mirror delivery → billing so billingAddress() is valid immediately
            // (matches what the server does on a normal save).
            if ($deliveryPayload) {
                $customer->addresses()->updateOrCreate(
                    ['type' => Customer::ADDRESS_TYPE_BILLING],
                    $deliveryPayload
                );
            }
        } else {
            $customer->update(['is_billing_same_as_delivery' => false]);

            $billingPayload = $this->buildAddressPayload(
                $billPostcode,
                $data->get('bill_address'),
                $sgCountryId,
                null,
                null
            );

            if ($billingPayload) {
                $customer->addresses()->updateOrCreate(
                    ['type' => Customer::ADDRESS_TYPE_BILLING],
                    $billingPayload
                );
            }
        }

        // ── 4. Attachments (CMS "File" → mark1 "Attachment(s)") ───────────
        // Reference-only: we record the CMS public URL, not a local copy.
        // Isolated so a file hiccup never aborts the field backfill.
        try {
            $this->syncAttachments($customer, rtrim($baseUrl, '/'));
        } catch (\Throwable $e) {
            Log::warning('UpdateCustomerCmsFields: attachment sync failed', [
                'person_id' => $this->personID,
                'error'     => $e->getMessage(),
            ]);
        }

        return 'updated';
    }

    /**
     * Copy the CMS person's files (file_person) into the customer's
     * Attachment(s) (type = FILE_TYPE_ATTACHMENT).
     *
     * COPY-BYTES: each CMS file is downloaded from its public URL and
     * re-uploaded into mark1's own storage (default disk, public), so the
     * attachments survive CMS being decommissioned. full_url/local_url then
     * point at mark1 storage, not CMS.
     *
     * De-duplicates by file name so re-runs don't pile up rows. Each file is
     * isolated — one bad download is logged and skipped, the rest continue.
     */
    protected function syncAttachments(Customer $customer, string $cmsBaseUrl): void
    {
        $filesBase = config('app.cms_files_url'); // fallback base for legacy relative paths

        $response = Http::get($cmsBaseUrl . '/api/person/files/' . $this->personID);
        if (!$response->successful()) {
            return;
        }

        $files = $response->collect();
        if ($files->isEmpty()) {
            return;
        }

        // Existing attachment names (case-insensitive) to skip duplicates.
        $existing = $customer->attachments()
            ->pluck('name')
            ->filter()
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->all();
        $existing = array_flip($existing);

        foreach ($files as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : null;
            if (!$path) {
                continue;
            }

            // Resolve the fetchable CMS URL. Stored path is normally already an
            // absolute URL; prefix the configured base only for legacy relative
            // paths.
            $sourceUrl = preg_match('#^https?://#i', $path)
                ? $path
                : ($filesBase ? rtrim($filesBase, '/') . '/' . ltrim($path, '/') : null);

            if (!$sourceUrl) {
                // Relative path but no CMS_FILES_URL to resolve it against.
                Log::warning('UpdateCustomerCmsFields: cannot resolve CMS file URL (set CMS_FILES_URL)', [
                    'person_id' => $this->personID,
                    'path'      => $path,
                ]);
                continue;
            }

            // Filename from the URL path component (strips any query string).
            $basename = basename(parse_url($sourceUrl, PHP_URL_PATH) ?: $path);
            $name = (is_array($file) ? ($file['name'] ?? null) : null) ?: $basename;
            $key  = mb_strtolower(trim($name));

            if (isset($existing[$key])) {
                continue; // already copied (or a same-named file exists)
            }

            try {
                $download = Http::timeout(60)->get($sourceUrl);
                if (!$download->successful() || $download->body() === '') {
                    Log::warning('UpdateCustomerCmsFields: file download failed', [
                        'person_id' => $this->personID,
                        'url'       => $sourceUrl,
                        'status'    => $download->status(),
                    ]);
                    continue;
                }

                // Store under a per-customer folder so identical filenames from
                // different customers never collide.
                $dest = 'sys/customers/cms-migrated/' . $customer->id . '/' . $basename;
                Storage::put($dest, $download->body(), 'public');

                $customer->attachments()->create([
                    'type'      => Customer::FILE_TYPE_ATTACHMENT,
                    'name'      => $name,
                    'full_url'  => Storage::url($dest),
                    'local_url' => $dest,
                    'is_active' => true,
                ]);

                $existing[$key] = true; // guard against intra-run duplicates
            } catch (\Throwable $e) {
                Log::warning('UpdateCustomerCmsFields: file copy failed', [
                    'person_id' => $this->personID,
                    'url'       => $sourceUrl,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build an address row payload: OneMap (by postcode) for block / building /
     * street / geocode, Singapore country, and the unit number deduced from the
     * CMS free-text address. Returns null when there is no postcode to resolve.
     */
    protected function buildAddressPayload($postcode, $cmsAddress, $sgCountryId, $lat, $lng): ?array
    {
        // addresses.postcode and addresses.country_id are both NOT NULL — skip
        // (rather than crash) when either is unavailable.
        if (!$postcode) {
            return null;
        }
        if (!$sgCountryId) {
            Log::warning('UpdateCustomerCmsFields: Singapore country row missing — skipping address write', [
                'person_id' => $this->personID,
                'postcode'  => $postcode,
            ]);
            return null;
        }

        $payload = [
            'postcode'   => $postcode,
            'country_id' => $sgCountryId,
            'unit_num'   => $this->extractUnit($cmsAddress),
        ];

        $geo = null;
        try {
            $geo = $this->getAddressResult($postcode); // OneMap (SearchAddress trait)
        } catch (\Throwable $e) {
            Log::warning('UpdateCustomerCmsFields: OneMap lookup failed', [
                'person_id' => $this->personID,
                'postcode'  => $postcode,
                'error'     => $e->getMessage(),
            ]);
        }

        if (is_array($geo)) {
            $building = $geo['building'] ?? null;
            if (is_string($building) && in_array(strtoupper(trim($building)), ['NIL', 'NA', ''], true)) {
                $building = null;
            }

            $payload['block_num']    = $geo['block_num'] ?? null;
            $payload['street_name']  = $geo['street_name'] ?? null;
            $payload['building']     = $building;
            $payload['full_address'] = $geo['full_address'] ?? null;
            $payload['latitude']     = $geo['latitude'] ?? $lat;
            $payload['longitude']    = $geo['longitude'] ?? $lng;
        } else {
            // OneMap miss — fall back to the raw CMS values so the row is not
            // left blank.
            $payload['street_name'] = $cmsAddress;
            $payload['latitude']    = $lat;
            $payload['longitude']   = $lng;
        }

        return $payload;
    }

    /**
     * Pull the unit token ("#NN-NN", kept verbatim including the '#') out of a
     * CMS free-text address such as "2 Woodleigh Ln #01-01". Returns null when
     * there is no unit marker.
     */
    protected function extractUnit($address): ?string
    {
        if (!$address || !is_string($address)) {
            return null;
        }

        if (preg_match('/#\s*[0-9A-Za-z]+(?:-[0-9A-Za-z]+)?/', $address, $m)) {
            return str_replace(' ', '', $m[0]);
        }

        return null;
    }

    /**
     * CMS exposes country as a relation object { id, name }. Pull the name out
     * defensively (it may be an array, object, or absent).
     */
    protected function countryName($country): ?string
    {
        if (is_array($country)) {
            return $country['name'] ?? null;
        }
        if (is_object($country)) {
            return $country->name ?? null;
        }
        return null;
    }
}
