<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undo Sync flips a report back to review in one transaction, then unwinds every
 * day it touched (ticks, tickets, retained-credit links, states) after the commit.
 * The days still to unwind are recorded here in that same transaction and cleared
 * once the unwind finishes, so a failure part-way leaves a marker that
 * `card-settlement:finish-unwind` re-runs and the 08:30 health email lists
 * (audit CARD-06). NULL = nothing pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_settlement_reports', function (Blueprint $table) {
            $table->json('unwind_pending_json')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('card_settlement_reports', function (Blueprint $table) {
            $table->dropColumn('unwind_pending_json');
        });
    }
};
