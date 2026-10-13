<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our OWN door-session recordings from a smart freezer (camera 5 today), uploaded by the freezer
 * app after the door closes (Brian, 2026-10-13). Kept apart from `smart_freezer_videos` on purpose:
 * every URL in that table is sent to Zijia's metered AI, and these are for our own records.
 *
 * One row per (machine, session, camera); a re-upload of the same session replaces the file. The
 * sale is found by `session_ref` = the TRADE's SFREF, the same key RecognitionBadges uses — no
 * column on vend_transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_clips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->string('session_ref', 64);
            $table->unsignedTinyInteger('camera_id');
            $table->string('path');
            $table->unsignedInteger('bytes');
            $table->unsignedInteger('duration_ms')->nullable();
            // The device's own clock (epoch ms in the upload headers): recording start, door close.
            $table->timestamp('recorded_from')->nullable();
            $table->timestamp('door_closed_at')->nullable();
            // What the freezer reported about the recording (pieces, gaps, a camera that refused).
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['vend_id', 'session_ref', 'camera_id']);
            $table->index('session_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_freezer_clips');
    }
};
