<?php

namespace App\Services;

use App\Models\Bank;
use App\Traits\SearchAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * On-demand CMS → mark1 person pull — backs the "Pull from CMS" button beside
 * the CMS Linking ID field on the Customer (Site) Create/Edit forms.
 *
 * READ-ONLY, ONE-WAY: fetches GET /api/person/migrate/{personID} from CMS,
 * maps the person to the Customer form shape and returns it as an array. The
 * form is filled client-side and NOTHING is persisted until the user presses
 * Save. There is intentionally no mark1 → CMS write anywhere in this path —
 * CMS stays the source of truth and is edited over there, then re-pulled.
 *
 * The field map + address rules reuse the previously-agreed one-time backfill
 * (the retired UpdateCustomerCmsFields job, removed in 7e59da1ac5):
 *  - Site Name           ← CMS "Cust Name" (people.company)
 *  - Site Contact Person ← CMS "Att To"    (people.name)
 *  - Site Phone / Alt    ← contact / alt_contact
 *  - Remarks for Address ← raw CMS delivery address text (del_address)
 *  - Billing Company     ← com_remark ("Company" in the CMS form)
 *  - Billing Email       ← email
 *  - is_gst_registered   ← CMS profile.gst presence
 *  - Billing same-as-delivery ← delivery vs billing postcode comparison
 *  - Bank name → mark1 banks.id; account_number split into number + holder
 *  - Address: postcode → OneMap (block / building / street / geocode / map
 *    URL — the same autofill the postcode key-in triggers); unit num is NOT
 *    OneMap-derived, it is extracted from the CMS free-text address
 *    ("#NN-NN"). OneMap miss → raw CMS values as fallback.
 */
class CmsPersonPullService
{
    use SearchAddress;

    /**
     * @return array{found: bool, message?: string, data?: array}
     */
    public function pull(int $personId): array
    {
        $baseUrl = config('app.cms_url');
        if (!$baseUrl) {
            return ['found' => false, 'message' => 'CMS_URL is not configured on this deployment.'];
        }

        try {
            $response = Http::timeout(15)->get(rtrim($baseUrl, '/') . '/api/person/migrate/' . $personId);
        } catch (\Throwable $e) {
            Log::warning('CmsPersonPullService: CMS request failed', [
                'person_id' => $personId,
                'error'     => $e->getMessage(),
            ]);

            return ['found' => false, 'message' => 'Could not reach CMS — please try again.'];
        }

        if (!$response->successful()) {
            Log::warning('CmsPersonPullService: CMS returned non-success', [
                'person_id' => $personId,
                'status'    => $response->status(),
            ]);

            return ['found' => false, 'message' => 'CMS request failed (HTTP ' . $response->status() . ').'];
        }

        $collection = $response->collect();

        // Endpoint returns a (possibly empty) list. Empty == person not found
        // or not a vending/DVM/combi customer.
        if (!$collection || !isset($collection[0])) {
            return ['found' => false, 'message' => 'No CMS person found for this ID (or it is not a vending customer).'];
        }

        $data = collect($collection[0]);

        // GST is a company-profile attribute (shared across the profile).
        $profile = $data->get('profile');
        $gstVal  = is_array($profile) ? ($profile['gst'] ?? null) : null;

        // "Billing same as Delivery" by postcode; a missing billing postcode
        // is treated as "same" (mirror delivery), like the old backfill.
        $delPostcode  = $data->get('del_postcode');
        $billPostcode = $data->get('bill_postcode');
        $sameBilling  = !$billPostcode
            || ((string) $delPostcode === (string) $billPostcode);

        // Bank Details — CMS bank name → mark1 banks.id; the combined CMS
        // account_number string is split into number + holder name.
        $bankId = $this->resolveBankId(data_get($data->get('bank'), 'name'));
        [$accountNumber, $accountHolder] = $this->parseAccountNumber($data->get('account_number'));

        // Unit numbers are NOT OneMap-derived — extracted from each side's own
        // CMS free-text address ("#NN-NN"); when both sides are the same
        // place, whichever side actually carries a unit wins.
        $delUnit  = $this->extractUnit($data->get('del_address'));
        $billUnit = $this->extractUnit($data->get('bill_address'));

        $address = $this->buildAddress(
            $delPostcode,
            $data->get('del_address'),
            $data->get('del_lat'),
            $data->get('del_lng')
        );
        if ($address) {
            $address['unit_num'] = $sameBilling ? ($delUnit ?: $billUnit) : $delUnit;
        }

        $billingAddress = null;
        if (!$sameBilling) {
            $billingAddress = $this->buildAddress($billPostcode, $data->get('bill_address'), null, null);
            if ($billingAddress) {
                $billingAddress['unit_num'] = $billUnit;
            }
        }

        return [
            'found' => true,
            'data'  => [
                // NOTE: CMS form labels are counterintuitive — "Cust Name" =
                // `company`, "Company" = `com_remark`, "Att To" = `name`.
                'name'                        => $data->get('company') ?: null,
                'site_contact_person'         => $data->get('name'),
                'site_phone_number'           => $data->get('contact'),
                'site_alt_phone_number'       => $data->get('alt_contact'),
                'address_remarks'             => $data->get('del_address'),
                'is_gst_registered'           => !empty(is_string($gstVal) ? trim($gstVal) : $gstVal),
                'is_billing_same_as_delivery' => $sameBilling,
                'contact'                     => [
                    'company' => $data->get('com_remark'),
                    'email'   => $data->get('email'),
                ],
                'bank_id'                     => $bankId,
                'bank_account_name'           => $accountHolder,
                'bank_account_number'         => $accountNumber,
                'address'                     => $address,
                'billing_address'             => $billingAddress,
            ],
        ];
    }

    /**
     * Build a form-shaped address block: postcode → OneMap for block /
     * building / street / geocode / map URL (identical to the postcode key-in
     * autofill). unit_num is set by the caller. OneMap miss → the raw CMS
     * address text goes into street_name so the row is not left blank.
     */
    protected function buildAddress($postcode, $cmsAddress, $lat, $lng): ?array
    {
        $postcode = trim((string) $postcode);
        if ($postcode === '') {
            return null;
        }

        $geo = null;
        try {
            $geo = $this->getAddressResult($postcode); // OneMap (SearchAddress trait)
        } catch (\Throwable $e) {
            Log::warning('CmsPersonPullService: OneMap lookup failed', [
                'postcode' => $postcode,
                'error'    => $e->getMessage(),
            ]);
        }

        if (is_array($geo)) {
            $building = $geo['building'] ?? null;
            if (is_string($building) && in_array(strtoupper(trim($building)), ['NIL', 'NA', ''], true)) {
                $building = null;
            }

            $latitude  = $geo['latitude'] ?? $lat;
            $longitude = $geo['longitude'] ?? $lng;

            return [
                'postcode'    => $postcode,
                'block_num'   => $geo['block_num'] ?? null,
                'building'    => $building,
                'street_name' => $geo['street_name'] ?? null,
                'latitude'    => $latitude,
                'longitude'   => $longitude,
                'map_url'     => $this->mapUrl($latitude, $longitude),
            ];
        }

        // OneMap miss — fall back to the raw CMS values.
        return [
            'postcode'    => $postcode,
            'block_num'   => null,
            'building'    => null,
            'street_name' => $cmsAddress,
            'latitude'    => $lat,
            'longitude'   => $lng,
            'map_url'     => $this->mapUrl($lat, $lng),
        ];
    }

    /**
     * Pull the unit token ("#NN-NN", kept verbatim including the '#') out of a
     * CMS free-text address such as "2 Woodleigh Ln #01-01".
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
     * Google Maps URL from lat/lng. Stored WITHOUT a scheme because the
     * Customer form renders the link as '//' + map_url.
     */
    protected function mapUrl($lat, $lng): ?string
    {
        if (!$lat || !$lng) {
            return null;
        }

        return 'www.google.com/maps?q=' . $lat . ',' . $lng;
    }

    /**
     * Map a CMS bank name (short form, e.g. "OCBC", "UOB") to a mark1
     * banks.id. Unmapped / unknown names are logged and left null so the user
     * can pick the bank manually.
     */
    protected function resolveBankId(?string $cmsBankName): ?int
    {
        if (!$cmsBankName || strcasecmp(trim($cmsBankName), 'None') === 0) {
            return null;
        }

        // CMS short name (lowercased) → mark1 banks.name.
        $map = [
            'dbs'                     => 'DBS Bank',
            'posb'                    => 'POSB Bank',
            'ocbc'                    => 'OCBC Bank',
            'uob'                     => 'United Overseas Bank (UOB)',
            'maybank'                 => 'Maybank Singapore',
            'hsbc'                    => 'HSBC Singapore',
            'standard chartered bank' => 'Standard Chartered Bank',
            'bank of china'           => 'Bank of China (Singapore)',
            'cimb'                    => 'CIMB Bank Singapore',
            'rhb bank'                => 'RHB Bank Singapore',
            'citibank'                => 'Citibank Singapore',
        ];

        $mark1Name = $map[mb_strtolower(trim($cmsBankName))] ?? null;

        if (!$mark1Name) {
            Log::warning('CmsPersonPullService: unmapped CMS bank — left null', [
                'cms_bank' => $cmsBankName,
            ]);

            return null;
        }

        return optional(Bank::where('name', $mark1Name)->first())->id;
    }

    /**
     * Split the CMS `account_number` (which crams the account number and,
     * often, a holder name into one string) into
     * [account_number, account_holder_name].
     *
     *   "0100881386"                          → ["0100881386", null]
     *   "0100881386 (NAME : ACS OLDHAM HALL)" → ["0100881386", "ACS OLDHAM HALL"]
     *   "0100881386 ACS OLDHAM HALL"          → ["0100881386", "ACS OLDHAM HALL"]
     *   "anything we can't parse"             → [null, "anything we can't parse"]
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseAccountNumber($raw): array
    {
        if ($raw === null) {
            return [null, null];
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return [null, null];
        }

        // 1) Pure number (digits, optional spaces / dashes) → account number.
        if (preg_match('/^[0-9][0-9\s\-]*$/', $raw)) {
            return [$raw, null];
        }

        // 2) "<number> (NAME : holder)" / "<number> NAME: holder" variants.
        if (preg_match('/^([0-9][0-9\s\-]{2,})\s*\(?\s*NAME\s*[:\-]\s*(.+?)\)?\s*$/i', $raw, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        // 3) Leading number then free text → number + the remainder as holder.
        if (preg_match('/^([0-9][0-9\s\-]{2,})\s+(.+)$/', $raw, $m)) {
            $name = preg_replace('/^\(?\s*NAME\s*[:\-]?\s*/i', '', $m[2]);
            $name = trim(rtrim(trim($name), ')'));

            return [trim($m[1]), $name !== '' ? $name : null];
        }

        // 4) Can't confidently parse → everything into the holder name.
        return [null, $raw];
    }
}
