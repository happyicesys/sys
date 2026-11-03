<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A terminal with its own data link (the T05) carries a SIM from the same pool
 * machines draw on (`simcards`). Unique because one SIM sits in one terminal;
 * deleting the SIM simply leaves the terminal with none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->foreignId('simcard_id')->nullable()->unique()->constrained('simcards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('card_terminal_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('simcard_id');
        });
    }
};
