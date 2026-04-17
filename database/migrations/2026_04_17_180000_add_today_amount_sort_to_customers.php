<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a stored generated column + composite index to eliminate the JSON filesort
     * in the customer listing (VendController::indexCustomer / CustomerIndex.vue).
     *
     * The default sort on the customer list page is by today's sales amount.
     * Without this, MySQL does:
     *
     *   ORDER BY LENGTH(json_unquote(json_extract(totals_json, "$.today_amount"))) DESC,
     *            json_unquote(json_extract(totals_json, "$.today_amount")) DESC
     *
     * MySQL cannot index a JSON extraction; every page load materialises ALL matching
     * customer rows and does a full filesort before applying LIMIT 50.
     *
     * With a STORED generated column, MySQL maintains `today_amount_sort` on every
     * INSERT/UPDATE of `customers.totals_json` — no cost on reads, tiny cost on writes
     * (totals_json is updated by a background job, not on hot paths).
     *
     * The composite index (is_active, today_amount_sort) lets MySQL:
     *   1. Seek directly to is_active = 1 rows
     *   2. Walk the index in sort order (forward or backward)
     *   3. Stop after 50 rows satisfy the remaining JOIN/WHERE conditions
     *
     * This converts an O(n) filesort into an O(page) index walk for the default view.
     */
    public function up(): void
    {
        // Add stored generated column (updated automatically by InnoDB on totals_json writes)
        if (!$this->columnExists('customers', 'today_amount_sort')) {
            DB::statement("
                ALTER TABLE customers
                ADD COLUMN today_amount_sort BIGINT
                    AS (CAST(JSON_UNQUOTE(JSON_EXTRACT(totals_json, '\$.today_amount')) AS SIGNED))
                    STORED
            ");
        }

        // Composite index: equality on is_active, then range/sort on today_amount_sort
        if (!Schema::hasIndex('customers', 'idx_customers_active_today_amount')) {
            DB::statement("
                ALTER TABLE customers
                ADD INDEX idx_customers_active_today_amount (is_active, today_amount_sort)
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customers', 'idx_customers_active_today_amount')) {
            DB::statement('ALTER TABLE customers DROP INDEX idx_customers_active_today_amount');
        }

        if ($this->columnExists('customers', 'today_amount_sort')) {
            DB::statement('ALTER TABLE customers DROP COLUMN today_amount_sort');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', DB::connection()->getTablePrefix() . $table)
            ->where('column_name', $column)
            ->exists();
    }
};
