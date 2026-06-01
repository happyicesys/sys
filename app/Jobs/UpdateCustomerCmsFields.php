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
 * Scope (per the numbered CMS↔mark1 field map):
 *  - Site Name ← CMS Cust Name (customers.name).
 *  - Delivery Address: OneMap by del_postcode → block/building/street/geocode;
 *    Google Map URL from lat/lng; unit per-side from the CMS address ("#NN-NN").
 *  - Site contact ← CMS Att To / Contact / Alt Contact (customers.site_*).
 *  - Remarks for Address ← CMS raw delivery address (customers.address_remarks).
 *  - Billing "same as delivery": by postcode; same → mirror delivery + flag on;
 *    different → OneMap the billing postcode into its own row.
 *  - Billing Contact: Company ← company, Email ← email (normalised). Contact
 *    person / phones left null (the site contact lives on the customer).
 *  - is_gst_registered ← CMS profile.gst presence.
 *  - cost_rate (Cost Rate %) and payterm (Terms label) mirror from CMS.
 *  - Attachments: CMS person files copied into mark1 storage (see syncAttachments).
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

        $profile = $data->get('profile');
        $attn    = is_array($profile) ? ($profile['attn'] ?? null) : null;
        $gstVal  = is_array($profile) ? ($profile['gst'] ?? null) : null;
        $payterm = $data->get('payterm');

        // Address comparison drives the "Billing same as Delivery" flag.
        // Singapore-only deployment → compare on postcode; a missing billing
        // postcode is treated as "same" (mirror delivery).
        $delPostcode  = $data->get('del_postcode');
        $billPostcode = $data->get('bill_postcode');
        $sameBilling  = !$billPostcode
            || ((string) $delPostcode === (string) $billPostcode);

        // ── 1. Customer scalar fields ─────────────────────────────────────
        // CMS "Att To / Contact / Alt Contact" map to the SITE contact
        // (delivery), which lives on the customer row — NOT the billing contact.
        $customer->update([
            'name'                        => $data->get('name') ?: $customer->name, // #1 Site Name (never null the required name)
            'site_contact_person'         => $attn,                                 // #5 Att To
            'site_phone_number'           => $data->get('contact'),                 // #6 Contact
            'site_alt_phone_number'       => $data->get('alt_contact'),             // #7 Alt Contact
            'address_remarks'             => $data->get('del_address'),             // #8 raw CMS delivery address text
            'is_gst_registered'           => !empty(is_string($gstVal) ? trim($gstVal) : $gstVal),
            'cost_rate'                   => $data->get('cost_rate'),
            // payterm column is varchar(64) — guard against an oversized label.
            'payterm'                     => $payterm !== null ? mb_substr((string) $payterm, 0, 64) : null,
            'is_billing_same_as_delivery' => $sameBilling,                          // #10
        ]);

        // ── 2. Billing Contact — only Company (#9) + Email (#11). Contact
        // person / phones are intentionally left null (the site contact now
        // lives on the customer). contacts.name is nullable via migration.
        $customer->contact()->updateOrCreate(
            [],
            [
                'name'                 => null,
                'company'              => $data->get('company'),
                // Email may carry >1 address; Contact::setEmailAttribute
                // normalises it (split / trim / dedupe → comma-separated).
                'email'                => $data->get('email'),
                'phone_num'            => null,
                'phone_country_id'     => null,
                'alt_phone_num'        => null,
                'alt_phone_country_id' => null,
            ]
        );

        // ── 3. Addresses ──────────────────────────────────────────────────
        // Block / Building / Street / geocode come from OneMap (by postcode).
        // Unit number is extracted from each side's own CMS address; when both
        // are the same place, whichever side actually carries a unit wins.
        $delUnit  = $this->extractUnit($data->get('del_address'));
        $billUnit = $this->extractUnit($data->get('bill_address'));

        $deliveryPayload = $this->buildAddressPayload(
            $delPostcode,
            $data->get('del_address'),
            $sgCountryId,
            $data->get('del_lat'),
            $data->get('del_lng')
        );

        if ($deliveryPayload) {
            $deliveryPayload['unit_num'] = $sameBilling ? ($delUnit ?: $billUnit) : $delUnit;
            $deliveryPayload['map_url']  = $this->mapUrl($deliveryPayload['latitude'] ?? null, $deliveryPayload['longitude'] ?? null);

            $customer->addresses()->updateOrCreate(
                ['type' => Customer::ADDRESS_TYPE_DELIVERY],
                $deliveryPayload
            );
        }

        if ($sameBilling) {
            // Mirror delivery → billing so billingAddress() is valid.
            if ($deliveryPayload) {
                $customer->addresses()->updateOrCreate(
                    ['type' => Customer::ADDRESS_TYPE_BILLING],
                    $deliveryPayload
                );
            }
        } else {
            $billingPayload = $this->buildAddressPayload(
                $billPostcode,
                $data->get('bill_address'),
                $sgCountryId,
                null,
                null
            );

            if ($billingPayload) {
                $billingPayload['unit_num'] = $billUnit;
                $billingPayload['map_url']  = $this->mapUrl($billingPayload['latitude'] ?? null, $billingPayload['longitude'] ?? null);

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

        // unit_num and map_url are set by the caller (per-side rules); this
        // builder only resolves postcode + country + OneMap geocode.
        $payload = [
            'postcode'   => $postcode,
            'country_id' => $sgCountryId,
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
     * Build a Google Maps URL from a lat/lng. Stored WITHOUT a scheme because
     * the Customer form renders the link as '//' + map_url. Null when there is
     * no geocode.
     */
    protected function mapUrl($lat, $lng): ?string
    {
        if (!$lat || !$lng) {
            return null;
        }

        return 'www.google.com/maps?q=' . $lat . ',' . $lng;
    }
}
