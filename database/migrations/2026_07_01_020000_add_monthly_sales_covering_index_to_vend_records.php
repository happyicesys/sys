<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dedicated covering index for the Performance Dashboard monthly-sales query
 * (DashboardController::getMonthlySalesQuery).
 *
 * That query and its daily_active subquery need, per row: operator_id + date
 * (filter), vend_id (COUNT DISTINCT + anti-join), location_type_id (group +
 * join), month (group) and total_amount (SUM). The existing
 * idx_operator_date_vend (operator_id, date, vend_id) covers only the first
 * three, so the rest are heap reads.
 *
 * We add a SEPARATE covering index rather than widening idx_operator_date_vend,
 * on purpose: that index is force-used (USE INDEX) by ~8 OTHER dashboard
 * queries, several of which large-range-scan it (e.g. active-machine-graph
 * COUNT DISTINCT over a year). Widening it would ~double its leaf size and slow
 * those scans — a "help one, hurt the rest" trade. Keeping the narrow index for
 * them and a wide covering index for monthly-sales means every query uses its
 * ideal index; the only cost is one extra index on vend_records, which is
 * batch-written (nightly rollup / reconcile), so no other query's reads regress.
 *
 * getMonthlySalesQuery's two USE INDEX hints are repointed to this index.
 * InnoDB builds it online, but vend_records is large — run in a quiet window.
 */
return new class extends Migration
{
    private string $table = 'vend_records';
    private string $index = 'idx_vr_monthly_sales_covering';
    private string $cols = 'operator_id, `date`, vend_id, location_type_id, `month`, total_amount';

    public function up(): void
    {
        if (! $this->indexExists($this->index)) {
            DB::statement("ALTER TABLE {$this->table} ADD INDEX {$this->index} ({$this->cols})");
        }
    }

    public function down(): void
    {
        if ($this->indexExists($this->index)) {
            DB::statement("ALTER TABLE {$this->table} DROP INDEX {$this->index}");
        }
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$this->table}"))
            ->contains(fn ($row) => $row->Key_name === $name);
    }
};
