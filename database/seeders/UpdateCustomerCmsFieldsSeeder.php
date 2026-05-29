<?php

namespace Database\Seeders;

use App\Jobs\UpdateCustomerCmsFields;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Backfill the new CMS mirror fields (billing address, company remark, site
 * name, cost rate, payment terms, contact email) onto every mark1 customer
 * that is already linked to CMS via `person_id`.
 *
 * Behaviour (matches the agreed scope):
 *  - ONLY customers that already have a person_id are touched.
 *  - Each is refreshed by re-fetching CMS by that person_id; if CMS has no
 *    record for it, that customer is skipped (no error, no creation).
 *  - No new customers are ever created.
 *  - Only the NEW fields are written — existing fields owned by
 *    SyncVendCustomerCms (name, status, delivery address, contact name /
 *    phone, etc.) are left untouched.
 *
 * Run with:  php artisan db:seed --class=UpdateCustomerCmsFieldsSeeder
 *
 * The real work lives in App\Jobs\UpdateCustomerCmsFields so the per-person
 * mapping logic stays in one place (and can also be dispatched ad-hoc, e.g.
 * after a single customer is re-linked).
 */
class UpdateCustomerCmsFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        Customer::withoutGlobalScopes()
            ->whereNotNull('person_id')
            ->select('id', 'person_id')
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (&$updated, &$skipped, &$failed) {
                foreach ($customers as $customer) {
                    try {
                        $result = (new UpdateCustomerCmsFields($customer->person_id))->handle();

                        if ($result === 'updated') {
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('UpdateCustomerCmsFieldsSeeder: failed for customer', [
                            'customer_id' => $customer->id,
                            'person_id' => $customer->person_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->command?->info("CMS mirror backfill complete. Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}.");
    }
}
