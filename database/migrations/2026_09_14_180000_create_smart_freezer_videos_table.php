<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per video push from Zijia (smart-freezer supplier). The payload shape
 * is not agreed yet, so the body is kept verbatim (raw_body) and the few fields we can
 * already recognise (order no, device id, URLs, our vend) are lifted out beside
 * it. Not `attachments`: that table needs a known parent, a local_url, and caps
 * full_url at 255 chars — too short for a signed object-storage URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_videos', function (Blueprint $table) {
            $table->id();
            $table->string('supplier', 32)->default('zijia');
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            // The order number the freezer APK gave the host's orderOpenDoor
            // ("SF-<vendCode>-<epochSeconds>-<seq>"), when the push carries it.
            $table->string('order_no', 128)->nullable()->index();
            $table->string('device_id', 128)->nullable()->index();
            $table->json('video_urls')->nullable();
            // Parsed body (MySQL JSON reorders keys) + the exact bytes received.
            $table->json('payload');
            $table->mediumText('raw_body')->nullable();
            $table->string('content_type', 128)->nullable();
            $table->string('source_ip', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_freezer_videos');
    }
};
