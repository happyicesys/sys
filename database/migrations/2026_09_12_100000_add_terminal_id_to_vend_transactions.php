<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The acquirer terminal (NETS TID) that took THIS sale's money, frozen at
 * write time — the sibling of `cashless_mfg`, which freezes the supplier.
 *
 * Bindings (`card_terminal_bindings`) are effective-dated and terminals get
 * moved, so a later rebind must never rewrite what a historical row says.
 * Null for every non-card rail (cash, QR gateways) and for a card sale on a
 * machine with no binding on that day.
 *
 * Same width as `card_terminal_bindings.terminal_id`. Nullable, no default,
 * no index → MySQL 8 INSTANT add on the ~5M-row table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->string('terminal_id', 64)->nullable()->after('cashless_mfg');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('terminal_id');
        });
    }
};
