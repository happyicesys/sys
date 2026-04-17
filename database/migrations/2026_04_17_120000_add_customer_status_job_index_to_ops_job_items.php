<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index on (customer_id, status, ops_job_id) to ops_job_items.
     *
     * This targets the slow "last_thirty_days_stock_in" query in VendController which
     * filters ops_job_items by customer_id IN (...) + status >= 3 + status <> 99, then
     * joins to ops_jobs for a 29-day date range. Without this index MySQL scans
     * ops_job_item_channels first and filters down late, causing ~4s query times.
     *
     * With this index MySQL can:
     *   1. Seek directly to matching customer_id + status rows (index range scan)
     *   2. Read ops_job_id from the index itself (no heap lookup for the join key)
     *   3. Join ops_jobs via its PK + date filter (idx_oj_date already covers this)
     *   4. Join ops_job_item_channels via idx_ojic_item_id on the already-small result set
     */
    public function up(): void
    {
        if (!Schema::hasIndex('ops_job_items', 'idx_oji_cust_status_job')) {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->index(['customer_id', 'status', 'ops_job_id'], 'idx_oji_cust_status_job');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('ops_job_items', 'idx_oji_cust_status_job')) {
            Schema::table('ops_job_items', function (Blueprint $table) {
                $table->dropIndex('idx_oji_cust_status_job');
            });
        }
    }
};
