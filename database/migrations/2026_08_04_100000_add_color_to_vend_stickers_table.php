<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machine Sticker colour — a 6-digit hex string (#RRGGBB) used to tint the
 * sticker badge wherever it is displayed (Data Management > Machine Sticker,
 * Vend Serial Number index). NULL = no colour picked; the UI then renders the
 * neutral white chip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_stickers', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('desc');
        });
    }

    public function down(): void
    {
        Schema::table('vend_stickers', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
