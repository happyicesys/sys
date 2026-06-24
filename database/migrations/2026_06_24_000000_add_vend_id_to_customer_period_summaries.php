<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-row MACHINE on customer_period_summaries.
 *
 * A site is bound to one vend (machine) at a time, but the bound machine can
 * CHANGE mid-month (a swap to a different vend_code). When that happens the
 * month is split into one row per machine, each row self-contained for its own
 * machine + date range. `vend_id` records which machine a row represents:
 *   - whole-month rows  → the site's current bound vend (or NULL if none),
 *   - machine-split rows → the vend bound during that segment.
 *
 * Nullable + ON DELETE SET NULL so deleting a vend never cascades into billing
 * history. Drives the per-row Machine ID + "New" badge on the Summary page.
 * It is purely descriptive — figures are still aggregated by customer_id over
 * the row's date range (one machine at a time), so a reused vend_id across
 * different customers/timelines is never mixed up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'vend_id')) {
                $table->foreignId('vend_id')
                    ->nullable()
                    ->after('contract_log_id')
                    ->constrained('vends')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'vend_id')) {
                $table->dropConstrainedForeignId('vend_id');
            }
        });
    }
};
