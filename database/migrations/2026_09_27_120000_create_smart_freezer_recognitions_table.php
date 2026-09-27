<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One AI recognition of one freezer door session: what mark1 asked Zijia's algorithm, what it
 * answered, and the verdict against the paid sale. Written only by
 * App\Services\SmartFreezer\FreezerRecognitionService.
 *
 * A door session may arrive as several video pushes (one per camera is possible — Zijia has not
 * said), so each `smart_freezer_videos` row points at its recognition rather than the reverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_recognitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            // Their id for the door session; the algorithm echoes it back on the result. UNIQUE:
            // concurrent pushes for one session (one per camera) must land on ONE row, or each would
            // be submitted — and billed — separately with half the videos.
            $table->string('trade_id', 128)->unique();
            // Our txnRef "SF-<vendCode>-<epoch>-<seq>" when it can be found — the key to the sale.
            $table->string('session_ref', 64)->nullable()->index();
            $table->string('device_id', 128)->nullable();
            $table->unsignedBigInteger('vend_transaction_id')->nullable()->index();

            // pending → submitting → submitted → completed | failed
            $table->string('status', 20)->index();
            $table->string('status_reason')->nullable();

            // Their requestId is a 19-digit Long today; room for a UUID-style id if that changes.
            $table->string('request_id', 64)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response')->nullable();

            $table->integer('order_status')->nullable();
            $table->json('items')->nullable();
            $table->string('error_message')->nullable();
            $table->json('callback_payload')->nullable();
            $table->boolean('callback_verified')->nullable();

            $table->string('verdict', 20)->nullable()->index();
            $table->json('verdict_lines')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('smart_freezer_videos', function (Blueprint $table) {
            $table->unsignedBigInteger('smart_freezer_recognition_id')->nullable()->after('vend_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('smart_freezer_videos', function (Blueprint $table) {
            $table->dropIndex(['smart_freezer_recognition_id']);
            $table->dropColumn('smart_freezer_recognition_id');
        });
        Schema::dropIfExists('smart_freezer_recognitions');
    }
};
