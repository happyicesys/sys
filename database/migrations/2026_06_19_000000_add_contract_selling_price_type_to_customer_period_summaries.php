<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the Ref Price tier (RP) on a locked Customer Summary row.
 *
 * customers.selling_price_type is a live field — the "RPx" badge on the
 * Summary page reads it directly, so a later RP change retroactively
 * rewrote the badge on already-locked (settled) months. This column
 * snapshots the RP at lock time, exactly like the contract terms
 * (contract_commission_type / _value / _ps_term) already do, so a locked
 * row keeps the RP it was billed under.
 *
 * Written by every lock writer:
 *   - CustomerController::applyLockToSummary (single + batch Lock)
 *   - LockCustomerSummaryHistorical (bulk historical lock command)
 *
 * Nullable on purpose: rows locked BEFORE this change have no captured RP.
 * The resource + export fall back to the live customer value for those, so
 * the badge never blanks (no backfill — historical RP is unrecoverable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'contract_selling_price_type')) {
                $table->integer('contract_selling_price_type')->nullable()->after('contract_ps_term');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'contract_selling_price_type')) {
                $table->dropColumn('contract_selling_price_type');
            }
        });
    }
};
