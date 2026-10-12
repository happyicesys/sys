<?php

namespace Database\Seeders;

use App\Services\Product\SellingPriceHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills `selling_prices.created_by` and `previous_amount` on existing rows from user_logs
 * (Brian, 2026-10-12) — the rows Product → Edit added since the audit began on 2026-07-18. A row
 * older than that, or added by a script, stays NULL: its date is its own `created_at`.
 *
 * Idempotent: only rows whose created_by is still NULL are written, so a re-run never overwrites
 * what a save recorded. `php artisan db:seed --class=SellingPriceAuthorBackfillSeeder`
 */
class SellingPriceAuthorBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $authors = app(SellingPriceHistory::class)->authorsOfAllRows();
        $open = DB::table('selling_prices')->whereNull('created_by')->whereIn('id', array_keys($authors))->pluck('id');

        $filled = 0;
        foreach ($open as $id) {
            $author = $authors[$id];
            if ($author['user_id'] === null) {
                continue;
            }
            $filled += DB::table('selling_prices')->where('id', $id)->whereNull('created_by')->update([
                'created_by' => $author['user_id'],
                'previous_amount' => $author['previous_amount'],
            ]);
        }

        $total = DB::table('selling_prices')->count();
        $this->command?->info("Selling prices: {$filled} backfilled from user_logs; "
            .DB::table('selling_prices')->whereNull('created_by')->count()." of {$total} have no recorded author (set before 2026-07-18).");
    }
}
