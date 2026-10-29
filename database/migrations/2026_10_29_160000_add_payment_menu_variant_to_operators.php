<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The machine payment menu follows the machine's OPERATOR (Brian, 2026-10-29):
 * mqtt-config adds `?machineID=<code>` to the menu URL it hands a board, and
 * mark1 adds the methods of the operator's `payment_menu_variant` (matched to
 * `payment_methods.menu_variant`). A machine moved into or out of an operator
 * then follows at its next boot, with no mqtt-config row to edit.
 * NULL = the normal menu. HIMD (MINDEF) starts on "mindef".
 *
 * Its own migration, pushed ahead of the code that reads it: the deploy serves
 * new code before `migrate`, and the menu API is hit by every board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('payment_menu_variant', 32)->nullable()->after('code');
        });

        DB::table('operators')->where('code', 'HIMD')->update(['payment_menu_variant' => 'mindef']);
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn('payment_menu_variant');
        });
    }
};
