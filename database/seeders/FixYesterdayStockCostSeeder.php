<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixYesterdayStockCostSeeder extends Seeder
{
    public function run(): void
    {
        // Use app timezone so it matches your reporting logic
        $tz = config('app.timezone', 'UTC');
        $yesterday = Carbon::yesterday($tz)->toDateString(); // YYYY-MM-DD

        // Build the same date expression you use elsewhere
        $dateSql = "DATE(CONCAT(sc.year,'-',LPAD(sc.month,2,'0'),'-',LPAD(sc.day,2,'0')))";

        DB::beginTransaction();
        try {
            // Single SQL UPDATE using a correlated subquery to grab the latest unit cost per product
            // (prefers is_current, then newest date_from, then created_at)
            $updated = DB::update("
                UPDATE stock_count_items AS sci
                JOIN stock_counts AS sc ON sc.id = sci.stock_count_id
                SET sci.stock_cost_amount = COALESCE((
                    SELECT uc2.cost
                    FROM unit_costs uc2
                    WHERE uc2.product_id = sci.product_id
                    ORDER BY uc2.is_current DESC, uc2.date_from DESC, uc2.created_at DESC
                    LIMIT 1
                ), 0) * (sci.qty_vend + sci.qty_warehouse)
                WHERE {$dateSql} = ?
            ", [$yesterday]);

            DB::commit();

            // Optional console output
            $this->command?->info("Fixed stock_cost_amount for {$updated} item(s) dated {$yesterday}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error("Failed: ".$e->getMessage());
            throw $e;
        }
    }
}
