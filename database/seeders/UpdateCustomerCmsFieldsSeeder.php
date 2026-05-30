<?php

namespace Database\Seeders;

use App\Jobs\UpdateCustomerCmsFields;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * ONE-TIME CMS → mark1 backfill of the Delivery Address, Billing Contact and
 * Bank Details (if any) sections, for every mark1 customer already linked to
 * CMS via `person_id`. After this pull, customer details are owned by mark1 —
 * the save-time CMS sync (SyncVendCustomerCms) stays disabled.
 *
 * Behaviour:
 *  - ONLY customers that already have a person_id are touched.
 *  - Delivery address is rebuilt from OneMap (by postcode) with the unit
 *    deduced from the CMS address; billing follows "same as delivery"
 *    (postcode + country) or gets its own row. Billing Contact + GST flag are
 *    mapped from CMS. CMS is the source of truth for this pull (overwrites).
 *  - No new customers are ever created. CMS-missing person_ids are skipped.
 *
 * Run with:  php artisan db:seed --class=UpdateCustomerCmsFieldsSeeder
 *
 * The per-person mapping lives in App\Jobs\UpdateCustomerCmsFields (also
 * dispatchable ad-hoc to re-pull a single customer). Each person triggers 1–2
 * OneMap calls, so a small pause is kept between customers.
 */
class UpdateCustomerCmsFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $baseQuery = Customer::withoutGlobalScopes()->whereNotNull('person_id');
        $total = (clone $baseQuery)->count();

        $output = $this->command?->getOutput();

        if ($total === 0) {
            $this->command?->warn('CMS backfill: no customers with a person_id — nothing to do.');
            return;
        }

        $this->command?->info("CMS backfill: {$total} linked customer(s) to process (≈1–2s each — be patient).");

        // Progress bar shows current/total, %, elapsed/ETA and live tallies so
        // it's obvious the seeder is working and not stuck on a slow CMS/OneMap
        // call. Falls back gracefully when run without a console (e.g. queue).
        $bar = null;
        if ($output) {
            $bar = $output->createProgressBar($total);
            $bar->setFormat(
                " %current%/%max% [%bar%] %percent:3s%%  elapsed:%elapsed:6s% eta:%estimated:-6s%\n"
                . "   person_id:%pid%  upd:%updated%  skip:%skipped%  fail:%failed%"
            );
            $bar->setMessage('-', 'pid');
            $bar->setMessage('0', 'updated');
            $bar->setMessage('0', 'skipped');
            $bar->setMessage('0', 'failed');
            $bar->start();
        }

        $baseQuery
            ->select('id', 'person_id')
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (&$updated, &$skipped, &$failed, $bar) {
                foreach ($customers as $customer) {
                    $bar?->setMessage((string) $customer->person_id, 'pid');

                    try {
                        $result = (new UpdateCustomerCmsFields($customer->person_id))->handle();

                        if ($result === 'updated') {
                            $updated++;
                        } else {
                            $skipped++;
                        }

                        // Be gentle on OneMap (1–2 calls per customer).
                        usleep(200000); // 0.2s
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('UpdateCustomerCmsFieldsSeeder: failed for customer', [
                            'customer_id' => $customer->id,
                            'person_id' => $customer->person_id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    if ($bar) {
                        $bar->setMessage((string) $updated, 'updated');
                        $bar->setMessage((string) $skipped, 'skipped');
                        $bar->setMessage((string) $failed, 'failed');
                        $bar->advance();
                    }
                }
            });

        $bar?->finish();
        $this->command?->newLine(2);
        $this->command?->info("CMS backfill complete. Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}.");
    }
}
