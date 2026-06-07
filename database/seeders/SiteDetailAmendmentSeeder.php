<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Bank;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Customer;
use App\Traits\SearchAddress;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One-time import of the admin-amended "Site detail" sheet (sheet "1") back
 * into the customers / contacts / addresses tables.
 *
 * Source file: database/seeders/data/site_detail_amendments.csv — a raw,
 * value-preserving export of the Excel sheet (404 Active rows). Matching is
 * strictly by customer_id; the seeder only ever UPDATEs.
 *
 * Built lean: everything is prefetched up front —
 *  - OneMap geocodes for every unique postcode via pooled concurrent requests
 *    (batches of 8, ~30s total instead of minutes of serial calls);
 *  - customers / contacts / addresses / banks in 4 bulk queries (no per-row
 *    SELECTs; Eloquent skips UPDATEs for unchanged rows, so re-runs are fast).
 *
 * Per-row handling (as agreed):
 *  - site_name: leading numeric code stripped ("15574 X" → "X").
 *  - Phones (site + billing, main + alt): digits only.
 *  - Remarks: Excel "_x000D_" / \r artifacts removed.
 *  - is_gst_registered / "Billing same": case-insensitive Yes/No.
 *  - Performance Report Email: FIRST email only; is_report_email_enabled set
 *    true when present; untouched when empty.
 *  - Addresses: postcode/unit/BLOCK from the sheet (Excel block wins);
 *    building/street/geocode/map URL from OneMap; OneMap miss → those keys
 *    left untouched.
 *  - Bank: mapped to mark1 banks; unknown names (e.g. "Invoice") are CREATED.
 *  - Account number/name retained as-is (trim only).
 *  - Empty cells for the sheet's other columns mean "cleared" → stored null.
 *
 * Run with:  php artisan db:seed --class=SiteDetailAmendmentSeeder
 */
class SiteDetailAmendmentSeeder extends Seeder
{
    use SearchAddress;

    /** Admin/CMS short bank names → mark1 banks.name. Unknown → created as-is. */
    private const BANK_MAP = [
        'ocbc'                    => 'OCBC Bank',
        'paynow'                  => 'Paynow',
        'uob'                     => 'United Overseas Bank (UOB)',
        'dbs'                     => 'DBS Bank',
        'hsbc'                    => 'HSBC Singapore',
        'maybank'                 => 'Maybank Singapore',
        'standard chartered'      => 'Standard Chartered Bank',
        'standard chartered bank' => 'Standard Chartered Bank',
        'cimb'                    => 'CIMB Bank Singapore',
        'posb'                    => 'POSB Bank',
        'posb bank'               => 'POSB Bank',
        'citibank'                => 'Citibank Singapore',
        'rhb'                     => 'RHB Bank Singapore',
        'rhb bank'                => 'RHB Bank Singapore',
        'gxs'                     => 'GXS Bank',
        'bank of china'           => 'Bank of China (Singapore)',
    ];

    private array $oneMapCache = [];   // postcode => geo array|null
    private array $banksByName = [];   // lowercase banks.name => id
    private array $bankCache   = [];   // lowercase sheet name => id
    private $customers;                // id => Customer
    private $contacts;                 // customer id => Contact (min-id, matches morphOne)
    private array $addressRows = [];   // customer id => [type => Address] (min-id per type)
    private $sgCountryId = null;

    public function run(): void
    {
        $path = database_path('seeders/data/site_detail_amendments.csv');
        if (!is_file($path)) {
            $this->command?->error("CSV not found: {$path}");
            return;
        }

        $this->sgCountryId = optional(Country::where('name', 'Singapore')->first())->id;
        if (!$this->sgCountryId) {
            $this->command?->error('Singapore country row missing — aborting (addresses.country_id is NOT NULL).');
            return;
        }

        $rows = $this->readCsv($path);
        $total = count($rows);

        // ── Prefetch everything up front ──────────────────────────────────
        $postcodes = [];
        foreach ($rows as $row) {
            if (($pc = trim((string) $row['site_postcode'])) !== '') {
                $postcodes[$pc] = true;
            }
            if (strcasecmp(trim($row['billing_same']), 'yes') !== 0
                && ($pc = trim((string) $row['billing_postcode'])) !== '') {
                $postcodes[$pc] = true;
            }
        }
        $postcodes = array_keys($postcodes);

        $this->command?->info("Site detail amendment: {$total} row(s); prefetching " . count($postcodes) . ' OneMap postcodes (8 concurrent)…');
        $this->prefetchOneMap($postcodes);

        $ids = array_map(fn ($r) => (int) $r['customer_id'], $rows);

        $this->customers = Customer::withoutGlobalScopes()->whereIn('id', $ids)->get()->keyBy('id');

        $this->contacts = Contact::where('modelable_type', Customer::class)
            ->whereIn('modelable_id', $ids)
            ->orderBy('id')
            ->get()
            ->unique('modelable_id')   // keep min-id — matches the morphOne the app uses
            ->keyBy('modelable_id');

        Address::where('modelable_type', Customer::class)
            ->whereIn('modelable_id', $ids)
            ->whereIn('type', [Customer::ADDRESS_TYPE_BILLING, Customer::ADDRESS_TYPE_DELIVERY])
            ->orderBy('id')
            ->get()
            ->each(function ($a) {
                $this->addressRows[$a->modelable_id][$a->type] ??= $a; // min-id per type
            });

        foreach (Bank::all() as $bank) {
            $this->banksByName[mb_strtolower($bank->name)] = $bank->id;
        }

        // Pre-resolve every bank name so any creations (e.g. "Invoice") happen
        // up front — the import loop below is then pure cache hits and the
        // progress bar isn't interrupted by "created bank" messages.
        foreach ($rows as $row) {
            $this->resolveBank($row['bank_name']);
        }

        // ── Import ────────────────────────────────────────────────────────
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $output = $this->command?->getOutput();
        $bar = null;
        if ($output && $total > 0) {
            $bar = $output->createProgressBar($total);
            $bar->setFormat(
                " %current%/%max% [%bar%] %percent:3s%%  elapsed:%elapsed:6s% eta:%estimated:-6s%\n"
                . "   customer_id:%cid%  upd:%updated%  skip:%skipped%  fail:%failed%"
            );
            $bar->setMessage('-', 'cid');
            $bar->setMessage('0', 'updated');
            $bar->setMessage('0', 'skipped');
            $bar->setMessage('0', 'failed');
            $bar->start();
        }

        foreach ($rows as $row) {
            $bar?->setMessage((string) ($row['customer_id'] ?? '-'), 'cid');

            try {
                $result = $this->importRow($row);
                $result === 'updated' ? $updated++ : $skipped++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('SiteDetailAmendmentSeeder: row failed', [
                    'customer_id' => $row['customer_id'] ?? null,
                    'error'       => $e->getMessage(),
                ]);
            }

            if ($bar) {
                $bar->setMessage((string) $updated, 'updated');
                $bar->setMessage((string) $skipped, 'skipped');
                $bar->setMessage((string) $failed, 'failed');
                $bar->advance();
            }
        }

        $bar?->finish();
        $this->command?->newLine(2);
        $this->command?->info("Site detail amendment complete. Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}.");
    }

    protected function importRow(array $row): string
    {
        $customer = $this->customers[(int) $row['customer_id']] ?? null;
        if (!$customer) {
            Log::warning('SiteDetailAmendmentSeeder: customer not found', ['customer_id' => $row['customer_id']]);
            return 'skipped';
        }

        // Sanity: warn (but proceed — customer_id is authoritative) if the
        // sheet's person_id no longer matches the DB.
        if ($row['person_id'] !== '' && (string) $customer->person_id !== (string) $row['person_id']) {
            Log::warning('SiteDetailAmendmentSeeder: person_id mismatch', [
                'customer_id' => $customer->id,
                'sheet'       => $row['person_id'],
                'db'          => $customer->person_id,
            ]);
        }

        // ── Field parsing ─────────────────────────────────────────────────
        $siteName = trim(preg_replace('/^\d+\s+/', '', trim($row['site_name'])));

        $statusId = array_search(trim($row['customer_status']), Customer::STATUSES_MAPPING, true);

        $remarks = trim(str_replace(['_x000D_', "\r"], '', $row['site_address_remarks']));

        $gst  = strcasecmp(trim($row['is_gst_registered']), 'yes') === 0;
        $same = strcasecmp(trim($row['billing_same']), 'yes') === 0;

        // Performance Report Email — first address only; enable when present.
        $reportEmail = null;
        if (trim($row['performance_report_email']) !== '') {
            $parts = preg_split('/[\s,;]+/', trim($row['performance_report_email']), -1, PREG_SPLIT_NO_EMPTY);
            $reportEmail = $parts[0] ?? null;
        }

        $bankId = $this->resolveBank($row['bank_name']);

        // ── 1. Customer scalar fields ─────────────────────────────────────
        $update = [
            'name'                        => $siteName !== '' ? $siteName : $customer->name, // never null the required name
            'site_contact_person'         => $this->nullableTrim($row['site_contact_person']),
            'site_phone_number'           => $this->digitsOnly($row['site_phone_number']),
            'site_alt_phone_number'       => $this->digitsOnly($row['site_alt_phone_number']),
            'address_remarks'             => $remarks !== '' ? $remarks : null,
            'is_gst_registered'           => $gst,
            'is_billing_same_as_delivery' => $same,
            'bank_id'                     => $bankId,
            'bank_account_name'           => $this->nullableTrim($row['bank_account_name']),
            'bank_account_number'         => $this->nullableTrim($row['bank_account_number']),
        ];

        if ($statusId !== false) {
            $update['status_id'] = $statusId;
            $update['is_active'] = ($statusId === Customer::STATUS_ACTIVE);
        }

        if ($reportEmail) {
            $update['report_email'] = $reportEmail;
            $update['is_report_email_enabled'] = true;
        }

        $customer->update($update); // no-op query when nothing changed

        // ── 2. Billing Contact ────────────────────────────────────────────
        // Email may carry >1 address; Contact::setEmailAttribute normalises it.
        $contactValues = [
            'name'                 => $this->nullableTrim($row['billing_contact_person']),
            'company'              => $this->nullableTrim($row['billing_company']),
            'email'                => $this->nullableTrim($row['billing_email']),
            'phone_num'            => $this->digitsOnly($row['billing_phone_number']),
            'phone_country_id'     => null,
            'alt_phone_num'        => $this->digitsOnly($row['billing_alt_phone_number']),
            'alt_phone_country_id' => null,
        ];

        $contact = $this->contacts[$customer->id] ?? null;
        if ($contact) {
            $contact->update($contactValues);
        } else {
            $this->contacts[$customer->id] = $customer->contact()->create($contactValues);
        }

        // ── 3. Addresses ──────────────────────────────────────────────────
        $deliveryPayload = $this->addressPayload(
            $row['site_postcode'],
            $row['site_unit_num'],
            $row['site_block_num']
        );

        if ($deliveryPayload) {
            $this->writeAddress($customer, Customer::ADDRESS_TYPE_DELIVERY, $deliveryPayload);
        }

        if ($same) {
            // Mirror delivery → billing so billingAddress() stays valid.
            if ($deliveryPayload) {
                $this->writeAddress($customer, Customer::ADDRESS_TYPE_BILLING, $deliveryPayload);
            }
        } else {
            $billingPayload = $this->addressPayload(
                $row['billing_postcode'],
                $row['billing_unit_num'],
                $row['billing_block_num']
            );

            if ($billingPayload) {
                $this->writeAddress($customer, Customer::ADDRESS_TYPE_BILLING, $billingPayload);
            }
        }

        return 'updated';
    }

    /** Update the prefetched address row for this type, or create it. */
    protected function writeAddress(Customer $customer, int $type, array $payload): void
    {
        $existing = $this->addressRows[$customer->id][$type] ?? null;

        if ($existing) {
            $existing->update($payload); // no-op query when nothing changed
        } else {
            $this->addressRows[$customer->id][$type] = $customer->addresses()->create(
                $payload + ['type' => $type]
            );
        }
    }

    /**
     * Build an address row: postcode/unit/block from the sheet (Excel block
     * wins; falls back to OneMap's when blank), building/street/geocode/map
     * URL from OneMap. On a OneMap miss those keys are omitted so existing
     * values are left untouched.
     */
    protected function addressPayload($postcode, $unit, $block): ?array
    {
        $postcode = trim((string) $postcode);
        if ($postcode === '') {
            return null;
        }

        $geo = $this->oneMapCache[$postcode] ?? null;

        $payload = [
            'postcode'   => $postcode,
            'country_id' => $this->sgCountryId,
            'unit_num'   => $this->nullableTrim($unit),
            'block_num'  => trim((string) $block) !== '' ? trim((string) $block) : ($geo['block_num'] ?? null),
        ];

        if (is_array($geo)) {
            $building = $geo['building'] ?? null;
            if (is_string($building) && in_array(strtoupper(trim($building)), ['NIL', 'NA', ''], true)) {
                $building = null;
            }

            $payload['building']     = $building;
            $payload['street_name']  = $geo['street_name'] ?? null;
            $payload['full_address'] = $geo['full_address'] ?? null;
            $payload['latitude']     = $geo['latitude'] ?? null;
            $payload['longitude']    = $geo['longitude'] ?? null;
            $payload['map_url']      = (!empty($geo['latitude']) && !empty($geo['longitude']))
                ? 'www.google.com/maps?q=' . $geo['latitude'] . ',' . $geo['longitude']
                : null;
        }

        return $payload;
    }

    /**
     * Resolve every unique postcode in one pass: pooled concurrent requests
     * (8 at a time), same browser-mimic headers as the SearchAddress trait,
     * short pause between batches to stay polite.
     */
    protected function prefetchOneMap(array $postcodes): void
    {
        $appUrl = rtrim(config('app.url') ?: 'https://www.onemap.gov.sg', '/');
        $headers = [
            'Referer'    => $appUrl . '/',
            'Origin'     => $appUrl,
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        ];

        foreach (array_chunk($postcodes, 8) as $batch) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($pc) => $pool->as($pc)->withHeaders($headers)->timeout(10)->get($this->endpointUrl, [
                    'searchVal'      => $pc,
                    'returnGeom'     => 'Y',
                    'getAddrDetails' => 'Y',
                ]),
                $batch
            ));

            foreach ($batch as $pc) {
                $geo = null;
                $resp = $responses[$pc] ?? null;

                if ($resp instanceof Response && $resp->successful()) {
                    $first = $resp->json('results.0');
                    if (is_array($first)) {
                        $geo = [
                            'block_num'    => $first['BLK_NO'] ?? null,
                            'street_name'  => $first['ROAD_NAME'] ?? null,
                            'building'     => $first['BUILDING'] ?? null,
                            'full_address' => $first['ADDRESS'] ?? null,
                            'latitude'     => $first['LATITUDE'] ?? null,
                            'longitude'    => $first['LONGITUDE'] ?? null,
                        ];
                    }
                }

                if ($geo === null) {
                    Log::warning('SiteDetailAmendmentSeeder: OneMap miss/fail', ['postcode' => $pc]);
                }

                $this->oneMapCache[$pc] = $geo;
            }

            usleep(200000); // politeness between batches
        }
    }

    /** Map an admin bank name to banks.id, creating unknown ones (per spec). */
    protected function resolveBank($name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $key = mb_strtolower($name);
        if (array_key_exists($key, $this->bankCache)) {
            return $this->bankCache[$key];
        }

        $mark1Name = self::BANK_MAP[$key] ?? $name;

        $bankId = $this->banksByName[mb_strtolower($mark1Name)] ?? $this->banksByName[$key] ?? null;

        if (!$bankId) {
            // New object (e.g. "Invoice") — create it; ops will tidy later.
            $bank = Bank::create([
                'name'       => $mark1Name,
                'country_id' => $this->sgCountryId,
                'is_active'  => true,
            ]);
            $bankId = $bank->id;
            $this->banksByName[mb_strtolower($mark1Name)] = $bankId;
            Log::info('SiteDetailAmendmentSeeder: created new bank', ['name' => $mark1Name]);
            $this->command?->warn(" Created new bank: {$mark1Name}");
        }

        return $this->bankCache[$key] = $bankId;
    }

    protected function digitsOnly($value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return $digits !== '' ? $digits : null;
    }

    protected function nullableTrim($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    /** Read the CSV into header-keyed rows (handles quoted multi-line cells). */
    protected function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        // Explicit args: keeps PHP 8.4's csv-escape deprecation out of the
        // logs; the file has zero backslashes so behaviour is unchanged.
        $header = fgetcsv($handle, null, ',', '"', '\\');

        while (($line = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue;
            }
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }
}
