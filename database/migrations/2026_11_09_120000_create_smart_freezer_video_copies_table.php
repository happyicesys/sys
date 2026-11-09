<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our own copy of each Zijia door-session video (Brian, 2026-11-09): their files sit on Zijia's
 * Alibaba OSS behind open links with no agreed lifetime, so each one is copied, private, into our
 * Spaces when its push arrives. One row per video URL; AI Recognition plays ours when theirs is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_video_copies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('smart_freezer_video_id')->index();
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->char('url_hash', 40)->unique(); // sha1 of the URL: one copy per file, however often it is pushed
            $table->text('url');
            $table->string('status', 12); // copied | failed
            $table->string('path')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('copied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_freezer_video_copies');
    }
};
