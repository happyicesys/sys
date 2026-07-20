<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-set payout override for a row sent to Site Settlement.
 *
 * When an admin uses the per-row "Send to Settlement" popup they can set the
 * final amount to be paid out (defaulting to the auto Net Loc Fee). That figure
 * is frozen here; the settlement total, CIMB export and the eventual ledger
 * credit all read this column when present, falling back to the computed
 * Net Loc Fee (location_fees_cents - external_subsidize_cents) when null.
 * Nullable - a null means "use the auto Net Loc Fee".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            $table->integer('settlement_amount_cents')->nullable()->after('commission_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            $table->dropColumn('settlement_amount_cents');
        });
    }
};
