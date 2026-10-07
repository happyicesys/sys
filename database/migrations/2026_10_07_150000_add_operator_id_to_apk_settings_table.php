<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner of a UI Setting. OperatorApkSettingScope lets a non-HappyIce viewer
 * see a setting they OWN as well as one bound to their machines; without an
 * owner, a setting with nothing bound yet - every one just created - was
 * invisible to its own creator (EATZ, 2026-10-07: six 404s in five minutes).
 *
 * Nullable and not backfilled: existing rows keep their bound-machine
 * visibility unchanged, and HappyIce staff see every row regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apk_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('operator_id')->nullable()->after('remarks')->index();
        });
    }

    public function down(): void
    {
        Schema::table('apk_settings', function (Blueprint $table) {
            $table->dropIndex(['operator_id']);
            $table->dropColumn('operator_id');
        });
    }
};
