<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Payrallel (T05) terminal is a card_terminal_unit like any NETS one: its SN is the
 * terminal_id and its Payrallel access token lives on the unit (encrypted). Binding the
 * unit to a smart freezer on Setting/Edit switches that freezer's card rail to it, and
 * remote_card_terminals records which unit it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->text('access_token')->nullable()->after('auresys_terminal_id');
        });

        Schema::table('remote_card_terminals', function (Blueprint $table) {
            $table->unsignedBigInteger('card_terminal_unit_id')->nullable()->after('vend_id');
            $table->index('card_terminal_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('remote_card_terminals', function (Blueprint $table) {
            $table->dropIndex(['card_terminal_unit_id']);
            $table->dropColumn('card_terminal_unit_id');
        });

        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
