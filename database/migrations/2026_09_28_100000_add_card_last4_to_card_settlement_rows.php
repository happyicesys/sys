<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paying card's last 4 digits, from the NETS "CashCard Application
 * Number (CAN)" column (masked "4628xxxxxxxx1234"; FlashPay prints its full
 * CAN). Only the last 4 are kept — enough to tell two cards apart, which is
 * what a retained-credit case needs (5073, 2026-09-21: the failed $1.70 was
 * card …9265, the $0.70 top-up that took the item was card …2599).
 *
 * Nullable and unindexed: EFTPOS lines carry no number, and every read goes
 * through matched_vend_transaction_id (unique). MySQL 8 adds a trailing
 * nullable column INSTANT — no table copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_settlement_rows', function (Blueprint $table) {
            $table->char('card_last4', 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('card_settlement_rows', function (Blueprint $table) {
            $table->dropColumn('card_last4');
        });
    }
};
