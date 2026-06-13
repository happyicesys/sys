<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill: derive the new is_locked / is_paid boolean columns on
 * customer_period_summaries from the existing audit timestamps
 * (locked_at / paid_at), which remain the source of truth.
 *
 *   is_locked = locked_at IS NOT NULL
 *   is_paid   = paid_at   IS NOT NULL
 *
 * Idempotent — safe to re-run any time; it simply re-derives both flags
 * for every row. Run once after the add_is_locked_is_paid migration:
 *
 *   php artisan db:seed --class=BackfillSummaryLockedPaidFlagsSeeder
 */
class BackfillSummaryLockedPaidFlagsSeeder extends Seeder
{
    public function run(): void
    {
        $affected = DB::update("
            UPDATE customer_period_summaries
            SET is_locked = (locked_at IS NOT NULL),
                is_paid   = (paid_at IS NOT NULL)
        ");

        $this->command?->info(sprintf(
            'Backfilled is_locked / is_paid on %d customer_period_summaries row(s).',
            $affected
        ));
    }
}
