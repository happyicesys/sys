<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent guard: ensure `customer_period_summary_invoices.summary_snapshot`
 * exists. The original create-table migration (2026_05_10_120000) defines this
 * JSON column, but it was added to that file AFTER the migration had already
 * been applied on some environments — so prod ran the earlier version and
 * ended up with the table minus this column. The Customer Summary > Export
 * Excel endpoint SELECTs `summary_snapshot`, which fails with SQLSTATE[42S22]
 * on those environments.
 *
 * Wrapped in hasColumn() so re-runs (and fresh installs that already created
 * the column via the original migration) are no-ops.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customer_period_summary_invoices', 'summary_snapshot')) {
            Schema::table('customer_period_summary_invoices', function (Blueprint $table) {
                // Position matches the create-table migration so future diffs
                // line up cleanly. Nullable + json — display-only override.
                $table->json('summary_snapshot')->nullable()->after('total_amount_cents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_period_summary_invoices', 'summary_snapshot')) {
            Schema::table('customer_period_summary_invoices', function (Blueprint $table) {
                $table->dropColumn('summary_snapshot');
            });
        }
    }
};
