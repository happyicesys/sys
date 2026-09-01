<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terminal-settlement sync stamp on the sale itself.
 *
 * Set (in bulk) when the user hits "Sync" on a matched card-settlement report
 * — the row-level link lives on card_settlement_rows.matched_vend_transaction_id;
 * this stamp is the cheap per-sale flag the Sales Transactions grid renders as
 * the "Settle Sync" tick without any join.
 *
 * Nullable with no default and no index → MySQL 8 INSTANT add on the ~4.6M-row
 * table. Deliberately no index yet: the grid only displays it per page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dateTime('card_settlement_synced_at')->nullable()->after('is_retained_credit_settlement');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('card_settlement_synced_at');
        });
    }
};
