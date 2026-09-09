<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NA_ERROR_CODE_PLAN_2026-09-08.md Part 2 (both directions of the NETS ↔ TRADE gap).
 *
 * vend_transactions
 *   card_settlement_row_id   — the report line this row was CREATED from (an
 *                              "orphan": money in the NETS report, no TRADE).
 *                              Mirror of payment_gateway_log_id for the card
 *                              rail; "pre-created by a rail" = either not null
 *                              (VendTransaction::scopeAwaitingTrade).
 *   card_settlement_state    — the reconciler's per-sale verdict, persisted
 *                              once final (reversed / captured / not_captured /
 *                              uncovered / unbound; NULL = not final yet), so
 *                              per-terminal statistics and the liabilities
 *                              list are a GROUP BY, not a 4-table join.
 *   Nullable, no default, NO index (Part 4 item 5): INSTANT adds on the ~5M-row
 *   table; every reader seeks on (vend_id, transaction_datetime) first.
 *
 * card_terminal_units
 *   batch                    — hardware batch from the partner's workbook
 *                              ("Nets #3 (50x)"), the unit of the capability.
 *   is_will_auto_refund      — Brian's flag: does this terminal make a failed
 *                              single-item sale good by itself (void before
 *                              capture / reversal)? NULL = unknown.
 *   auto_refund_flag_source  — seed | manual. The seed (partner Excel) is
 *                              authoritative; a manual override sticks.
 *   auto_refund_stats_json   — observed made-good statistics for the tooltip
 *                              and the weekly contradiction report; never
 *                              flips the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('card_settlement_row_id')->nullable()->after('card_settlement_synced_at');
            $table->string('card_settlement_state', 16)->nullable()->after('card_settlement_row_id');
        });

        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->string('batch', 32)->nullable()->after('remarks');
            $table->tinyInteger('is_will_auto_refund')->nullable()->after('batch');
            $table->string('auto_refund_flag_source', 16)->nullable()->after('is_will_auto_refund');
            $table->json('auto_refund_stats_json')->nullable()->after('auto_refund_flag_source');
        });
    }

    public function down(): void
    {
        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->dropColumn(['card_settlement_row_id', 'card_settlement_state']);
        });
        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->dropColumn(['batch', 'is_will_auto_refund', 'auto_refund_flag_source', 'auto_refund_stats_json']);
        });
    }
};
