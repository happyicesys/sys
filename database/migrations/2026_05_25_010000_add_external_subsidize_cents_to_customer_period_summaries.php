<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the External Subsidize amount (in cents) onto each monthly
 * customer_period_summaries row.
 *
 * Why store it per-period instead of reading the live customer value:
 *   - History must lock. Once a month is aggregated, the subsidy used for
 *     that month is frozen; later contract edits only affect future months.
 *   - location_earning_cents (a.k.a. "Vend Earning") is now computed NET of
 *     the subsidy — Vend Earning = Gross − (Location Fees − External Subsidize).
 *     Storing the cents used keeps that math auditable and lets the Net Loc
 *     Fee column + sorts read a stable per-period value.
 *
 * location_fees_cents stays GROSS (the contract fee). Net Loc Fee is derived
 * as location_fees_cents − external_subsidize_cents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'external_subsidize_cents')) {
                $table->bigInteger('external_subsidize_cents')->default(0)->after('location_earning_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'external_subsidize_cents')) {
                $table->dropColumn('external_subsidize_cents');
            }
        });
    }
};
