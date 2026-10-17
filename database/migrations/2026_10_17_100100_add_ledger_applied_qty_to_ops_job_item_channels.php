<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a Smart Freezer Stock In actually moved on the ledger, per line (audit SF-14, 2026-10-17).
 * Both directions clamp at zero, so a return of 5 against a ledger of 3 moves 3, and Undo Stock In
 * must reverse those 3, not the 5 keyed. NULL = not a freezer line, or stocked in before this
 * column (undo then falls back to the refill, as before).
 *
 * Schema only, pushed ahead of the code that writes it (CLAUDE.md "deploy gap").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->integer('ledger_applied_qty')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ops_job_item_channels', function (Blueprint $table) {
            $table->dropColumn('ledger_applied_qty');
        });
    }
};
