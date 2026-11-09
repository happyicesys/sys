<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long each copied Zijia video runs (ffprobe, at copy time), so AI Recognition can show the
 * length of every camera's video the way it shows our own camera-5 clips (Brian, 2026-11-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smart_freezer_video_copies', function (Blueprint $table) {
            $table->unsignedInteger('duration_ms')->nullable()->after('bytes');
        });
    }

    public function down(): void
    {
        Schema::table('smart_freezer_video_copies', function (Blueprint $table) {
            $table->dropColumn('duration_ms');
        });
    }
};
