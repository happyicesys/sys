<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Auresys-side terminal ID of a Nets-Auresys unit.
 *
 * An Auresys terminal carries TWO identifiers: the 8-digit NETS TID already in
 * `terminal_id` (which is what the MerchantConnect file and the settlement
 * matcher resolve on) and Auresys' own EZ terminal ID, which is what their
 * report is keyed by. Ops had been typing the second one into `remarks` as
 * "EZTID: 25670011"; this column gives it a home so an Auresys report can be
 * matched later without parsing free text.
 *
 * Nullable and NOT unique: only Auresys units have one, and a mistyped
 * duplicate must be fixable on the page rather than blowing up the save.
 * Plain index because the only read that matters is
 * "which unit is EZTID x" — see CardTerminalAuresysTerminalIdSeeder for the
 * one-off backfill out of remarks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->string('auresys_terminal_id', 32)->nullable()->after('terminal_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->dropIndex(['auresys_terminal_id']);
            $table->dropColumn('auresys_terminal_id');
        });
    }
};
