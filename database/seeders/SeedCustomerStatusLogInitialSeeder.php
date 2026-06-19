<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for customer_status_logs.
 *
 * Existing sites (and CMS-synced sites created before the Status History
 * feature) have no log rows, so the Status History popup shows "No status
 * changes recorded yet." even though the site clearly has a status. This seeds
 * ONE baseline entry per site capturing its CURRENT status + the relevant
 * effective date:
 *     Active   -> active_date
 *     Removed  -> removed_date
 *     Inactive -> termination_date (the auto Inactive Date)
 *     other    -> null
 *
 * source = 'seeder' so these are distinguishable from real user changes. Only
 * inserts for customers that have NO existing log row, so it is idempotent and
 * never duplicates or clobbers genuine history.
 *
 * Run with:
 *   php artisan db:seed --class=SeedCustomerStatusLogInitialSeeder
 */
class SeedCustomerStatusLogInitialSeeder extends Seeder
{
    public function run(): void
    {
        $existing = DB::table('customer_status_logs')
            ->distinct()
            ->pluck('customer_id')
            ->flip();

        $now = Carbon::now();
        $inserted = 0;

        DB::table('customers')
            ->select('id', 'status_id', 'active_date', 'removed_date', 'termination_date', 'created_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($existing, $now, &$inserted) {
                $batch = [];
                foreach ($rows as $row) {
                    if (isset($existing[$row->id])) {
                        continue; // already has history
                    }

                    $statusId = (int) $row->status_id;
                    $statusDate = null;
                    if ($statusId === Customer::STATUS_ACTIVE) {
                        $statusDate = $row->active_date;
                    } elseif ($statusId === Customer::STATUS_REMOVED) {
                        $statusDate = $row->removed_date;
                    } elseif ($statusId === Customer::STATUS_INACTIVE) {
                        $statusDate = $row->termination_date;
                    }

                    $batch[] = [
                        'customer_id' => $row->id,
                        'status_id'   => $statusId,
                        'status_date' => $statusDate ? Carbon::parse($statusDate)->toDateString() : null,
                        'changed_by'  => null,
                        'source'      => 'seeder',
                        // created_at reflects the effective date when known so the
                        // "When" column reads naturally; falls back to the site's
                        // creation time, then now.
                        'created_at'  => $statusDate ? Carbon::parse($statusDate) : ($row->created_at ?? $now),
                        'updated_at'  => $now,
                    ];
                    $inserted++;
                }

                if (!empty($batch)) {
                    DB::table('customer_status_logs')->insert($batch);
                }
            });

        $this->command?->info("Status History backfill complete: {$inserted} baseline entr(ies) inserted.");
    }
}
