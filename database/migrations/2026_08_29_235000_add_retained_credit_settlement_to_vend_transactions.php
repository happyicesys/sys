<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retained-credit settlement marking (2026-08-29, bench-verified on 2031).
 *
 * When a paid vend fails (SErr 7/9), the VMC/reader banks the payment as
 * credit that survives the driver's error clear, session cancels, VMC
 * restarts and even a mains re-power — and then serves the NEXT card request
 * on the machine instantly, with no card presented (CSHL_ARMED_MS of 0.9–3.3s
 * against 20–25s for every real tap). The APK cannot refuse it (bench-
 * falsified: refusing bricks the card rail because only a dispense consumes
 * the credit), so such trades arrive looking like card sales that no terminal
 * will ever settle.
 *
 * These columns let mark1 book them as what they are — settlement of the
 * machine's preceding failed sale:
 *  - is_retained_credit_settlement: the trade's approval was served from
 *    retained credit (CSHL_ARMED_MS < 5000); no fresh card auth happened.
 *  - retained_credit_settles_txn_id: best-effort link to the failed sale
 *    whose banked payment this vend consumed (most recent prior failed paid
 *    trade on the same machine; null when none is found in the lookback).
 *
 * Forward-only: no backfill. Historical suspect trades remain queryable via
 * JSON_EXTRACT on vend_transaction_json.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->boolean('is_retained_credit_settlement')->default(false)->after('auto_refund_source');
            $table->unsignedBigInteger('retained_credit_settles_txn_id')->nullable()->after('is_retained_credit_settlement');
            $table->index('retained_credit_settles_txn_id', 'vend_transactions_rc_settles_txn_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropIndex('vend_transactions_rc_settles_txn_id_index');
            $table->dropColumn(['is_retained_credit_settlement', 'retained_credit_settles_txn_id']);
        });
    }
};
