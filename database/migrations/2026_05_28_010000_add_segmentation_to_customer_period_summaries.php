<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enable mid-month contract SEGMENTATION on customer_period_summaries.
 *
 * Until now there was exactly one row per (customer, month), enforced by the
 * unique index `cps_customer_yearmonth_unique`. To report a month that had a
 * mid-month contract change as multiple segments (e.g. 1st–15th on the old
 * contract, 16th–end on the new one), a month must be allowed to hold several
 * rows that share the same `year_month` bucket but have distinct `period_start`
 * dates.
 *
 * Changes:
 *   - Drop the one-row-per-month unique index.
 *   - Make (customer_id, period_start) unique instead — each segment has its
 *     own period_start, so this still prevents true duplicates while allowing
 *     multiple segments per month. (Replaces the old NON-unique index on the
 *     same columns.)
 *   - segment_index            : 0 for a whole-month / first segment, 1,2,… for
 *                                later segments — display + ordering aid.
 *   - contract_log_id          : which customer_contract_logs version this
 *                                segment was computed from (traceability).
 *   - segmentation_overridden  : set when the user has "merged" a month back
 *                                into a single row; tells the aggregator to keep
 *                                it as one row (latest contract) instead of
 *                                re-splitting on the next run.
 *
 * The `year_month` bucket and its index are retained for grouping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'segment_index')) {
                $table->unsignedTinyInteger('segment_index')->default(0)->after('is_current_month');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'contract_log_id')) {
                $table->foreignId('contract_log_id')
                    ->nullable()
                    ->after('contract_ps_term')
                    ->constrained('customer_contract_logs')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('customer_period_summaries', 'segmentation_overridden')) {
                $table->boolean('segmentation_overridden')->default(false)->after('segment_index');
            }
        });

        // Aggregator looks up the set of customers with a mid-month contract
        // change in one query:
        //   SELECT customer_id FROM customer_contract_logs
        //     WHERE effective_from > monthStart AND effective_from <= periodEnd
        // The existing indexes lead with customer_id, so this range scan would
        // fall back to a table scan as the log grows. Add a leading-column
        // index on effective_from to keep `persistMonth` cheap.
        Schema::table('customer_contract_logs', function (Blueprint $table) {
            $exists = collect(\Illuminate\Support\Facades\DB::select(
                "SHOW INDEX FROM customer_contract_logs WHERE Key_name = 'ccl_eff_from_idx'"
            ))->isNotEmpty();
            if (!$exists) {
                $table->index('effective_from', 'ccl_eff_from_idx');
            }
        });

        // Swap the uniqueness rule from (customer_id, year_month) to
        // (customer_id, period_start). Guarded so re-runs don't blow up.
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            try {
                $table->dropUnique('cps_customer_yearmonth_unique');
            } catch (\Throwable $e) {
                // already dropped
            }
            // The original (non-unique) index on the same columns would now be
            // redundant with the unique one; drop it first.
            try {
                $table->dropIndex('cps_customer_periodstart_idx');
            } catch (\Throwable $e) {
                // not present
            }
            $table->unique(['customer_id', 'period_start'], 'cps_customer_periodstart_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_contract_logs', function (Blueprint $table) {
            try {
                $table->dropIndex('ccl_eff_from_idx');
            } catch (\Throwable $e) {
                // not present
            }
        });

        Schema::table('customer_period_summaries', function (Blueprint $table) {
            try {
                $table->dropUnique('cps_customer_periodstart_unique');
            } catch (\Throwable $e) {
                // already dropped
            }
            $table->index(['customer_id', 'period_start'], 'cps_customer_periodstart_idx');
            $table->unique(['customer_id', 'year_month'], 'cps_customer_yearmonth_unique');
        });

        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'contract_log_id')) {
                $table->dropConstrainedForeignId('contract_log_id');
            }
            foreach (['segmentation_overridden', 'segment_index'] as $col) {
                if (Schema::hasColumn('customer_period_summaries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
