<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our own freezer AI's answer for a door session, sent back by an office Mac over MQTT (Brian, 2026-11-19). One row per
 * Zijia recognition row PER WORKER (the Mac that answered), so two Macs can run side by side. Shadow only: nothing reads
 * it to charge, refund or move stock. Written only by App\Services\SmartFreezer\OwnAi\OwnAiLink.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_own_ai_recognitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('smart_freezer_recognition_id');   // indexed by the unique key below
            $table->string('worker', 32);
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->string('session_ref', 64)->nullable()->index();
            $table->json('items')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->boolean('auto_accept')->nullable();
            $table->string('why_person', 255)->nullable();
            $table->string('model', 64)->nullable();
            $table->json('answer')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('answers')->default(1);
            $table->timestamp('job_sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['smart_freezer_recognition_id', 'worker'], 'sf_own_ai_recognition_worker_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_freezer_own_ai_recognitions');
    }
};
