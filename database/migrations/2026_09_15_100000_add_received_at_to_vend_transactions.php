<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When mark1 RECEIVED the TRADE frame — the server clock, as opposed to
 * `transaction_datetime`, which since 2026-09-09 is the frame's own TIME
 * (the VMC board's RTC for keypad sales, the Android clock for touchscreen
 * sales).
 *
 * The NETS settlement report is stamped by the terminal, whose clock agrees
 * with ours, not with the boards. Measured 2026-09-15 on 27k matched lines:
 * server receive time sits 0–30 s after the NETS line for 96 % of keypad
 * sales; the frame TIME only 53 % (board RTCs drift by whole minutes,
 * e.g. 2760 −314 s, 2116 −611 s). But a machine that was offline flushes
 * its outbox in one burst, and then only the frame TIME is right (2502 on
 * 2026-09-03: three sales received together 6 min after the taps). So the
 * matcher needs BOTH anchors, and this column is the second one.
 *
 * Null for rows that were never a live frame: gateway pre-creates, NETS
 * orphans, backdated entries. `created_at` is the historical proxy for rows
 * written before this migration (CardSettlementMatcher::receivedAnchor).
 * Nullable, no default, no index → INSTANT add on the ~5M-row table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dateTime('received_at')->nullable()->after('transaction_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
