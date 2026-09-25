<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bindings to the second (Brian, 2026-09-25): a technician swaps a terminal at
 * 14:30, not at midnight, so the matcher resolves each NETS line by ITS time.
 *
 *  - from_at / until_at: DATETIME, [from_at, until_at) — until exclusive,
 *    NULL = open at that end. bound_from / bound_until stay as derived DATE
 *    columns (CardTerminalBinding::saving keeps them in step) for the day-level
 *    screens and SQL that read them.
 *  - source: manual (a person on Setting/Edit), report (NETS evidence — the
 *    Card Settlement fix buttons), import (the 2026-09-02 seed CSV). A report
 *    fix never overrides a manual binding after the moment it was recorded.
 *
 * Backfill keeps today's behaviour exactly: from = bound_from 00:00, until =
 * the day AFTER bound_until 00:00 (the date range was inclusive), and on a
 * swap day the two rows still overlap, where the latest from_at wins — the
 * tie-break the matcher already used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_terminal_bindings', function (Blueprint $table) {
            $table->dateTime('from_at')->nullable()->after('bound_until');
            $table->dateTime('until_at')->nullable()->after('from_at');
            $table->string('source', 16)->default('report')->after('until_at');
            $table->index(['terminal_id', 'from_at'], 'ctb_terminal_from_at_index');
            $table->index(['vend_id', 'from_at'], 'ctb_vend_from_at_index');
        });

        DB::table('card_terminal_bindings')->update([
            'from_at' => DB::raw('IF(bound_from IS NULL, NULL, CAST(bound_from AS DATETIME))'),
            'until_at' => DB::raw('IF(bound_until IS NULL, NULL, DATE_ADD(CAST(bound_until AS DATETIME), INTERVAL 1 DAY))'),
            'source' => DB::raw("CASE WHEN created_by IS NOT NULL THEN 'manual' WHEN created_at < '2026-09-03' THEN 'import' ELSE 'report' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('card_terminal_bindings', function (Blueprint $table) {
            $table->dropIndex('ctb_terminal_from_at_index');
            $table->dropIndex('ctb_vend_from_at_index');
            $table->dropColumn(['from_at', 'until_at', 'source']);
        });
    }
};
