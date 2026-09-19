<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vends.code_prefix — the letters in front of a machine number that another
 * system owns (CityBox OPS Pro "C6003" → prefix "C", code 6003). NULL for
 * every machine whose number mark1 allocates itself, which is all of them
 * except CityBox chillers as of 2026-09-19. The machine ID shown to people is
 * code_prefix + code (App\Support\VendCode).
 *
 * vends.code stays int: 256 renders, MQTT topics ("CM{code}") and terminal
 * payloads all read it as a number. A prefixed code may share its number with
 * an old unprefixed vend (5001–5004, 6001, 6002 are inactive vending machines),
 * so lookups by a terminal-reported number use Vend::scopeBareCode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->string('code_prefix', 8)->nullable()->after('code');
            $table->index(['code_prefix', 'code'], 'idx_vends_code_prefix_code');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropIndex('idx_vends_code_prefix_code');
            $table->dropColumn('code_prefix');
        });
    }
};
