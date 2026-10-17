<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A CityBox chiller's ledger baseline — when their count was last READ into our ledger — moves out
 * of `citybox_status_json` into its own column (audit STK-02, 2026-10-17). Six writers save that
 * JSON whole from in-memory copies, so a poll that loaded the vend before a Stock In put the old
 * baseline back, and an order taken before the count was deducted a second time. A column is
 * written only by a monotonic conditional UPDATE and is never part of anyone's stale copy.
 *
 * Schema only, pushed ahead of the code that reads it (CLAUDE.md "deploy gap"); the code push
 * carries the backfill from the JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dateTime('stock_baseline_at')->nullable();
            $table->string('stock_baseline_source', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn(['stock_baseline_at', 'stock_baseline_source']);
        });
    }
};
