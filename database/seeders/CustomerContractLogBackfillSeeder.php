<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerContractLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Backfill customer_contract_logs with one "currently active" row per customer
 * using the customer's CURRENT contract fields.
 *
 * Per user direction: historical period summaries should snapshot the
 * customer's current contract values, since pre-feature history is not
 * available. effective_from is anchored to begin_date when present, falling
 * back to contract_detail_updated_at, then customer.created_at.
 *
 * Idempotent: skips customers that already have a contract log row.
 *
 *   php artisan db:seed --class=CustomerContractLogBackfillSeeder
 */
class CustomerContractLogBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $inserted = 0;
        $skipped = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->select([
                'id',
                'begin_date',
                'created_at',
                'contract_commission_type',
                'contract_commission_value',
                'contract_commission_value2',
                'contract_ps_term',
                'contract_until',
                'contract_auto_renewal',
                'contract_min_commitment_period',
                'contract_notice_period',
                'contract_detail_updated_at',
                'contract_detail_updated_by',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$inserted, &$skipped, $now) {
                $existingIds = CustomerContractLog::query()
                    ->whereIn('customer_id', $customers->pluck('id'))
                    ->pluck('customer_id')
                    ->all();
                $existingSet = array_flip($existingIds);

                $rows = [];
                foreach ($customers as $customer) {
                    if (isset($existingSet[$customer->id])) {
                        $skipped++;
                        continue;
                    }

                    $effectiveFrom = $customer->begin_date
                        ?? $customer->contract_detail_updated_at
                        ?? $customer->created_at
                        ?? $now;
                    $effectiveFrom = Carbon::parse($effectiveFrom);

                    $rows[] = [
                        'customer_id' => $customer->id,
                        'effective_from' => $effectiveFrom,
                        'effective_to' => null,
                        'contract_commission_type' => $customer->contract_commission_type,
                        'contract_commission_value' => $customer->contract_commission_value,
                        'contract_commission_value2' => $customer->contract_commission_value2,
                        'contract_ps_term' => $customer->contract_ps_term,
                        'contract_until' => $customer->contract_until,
                        'contract_auto_renewal' => (bool) $customer->contract_auto_renewal,
                        'contract_min_commitment_period' => $customer->contract_min_commitment_period,
                        'contract_notice_period' => $customer->contract_notice_period,
                        'changed_by' => $customer->contract_detail_updated_by,
                        'source' => 'seeder',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    foreach (array_chunk($rows, 500) as $batch) {
                        CustomerContractLog::query()->insert($batch);
                    }
                    $inserted += count($rows);
                }
            });

        $this->command?->info(sprintf(
            'CustomerContractLogBackfillSeeder: inserted=%d, skipped=%d',
            $inserted,
            $skipped
        ));
    }
}
