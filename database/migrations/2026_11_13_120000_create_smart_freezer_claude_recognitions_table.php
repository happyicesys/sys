<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claude's own count of a freezer door session, run beside Zijia's AI on the same videos so the two can be
 * compared (Brian, 2026-11-13). Shadow only: nothing reads it to charge, refund or move stock.
 * One row per Zijia recognition row; the cost of each call is kept in integer micro-USD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_freezer_claude_recognitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('smart_freezer_recognition_id')->unique('sf_claude_recognition_unique');
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->string('session_ref', 64)->nullable()->index();
            $table->unsignedBigInteger('vend_transaction_id')->nullable()->index();
            $table->string('status', 20)->index();
            $table->string('status_reason', 255)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('effort', 16)->nullable();
            $table->json('frames')->nullable();
            $table->json('items')->nullable();
            $table->json('answer')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->boolean('needs_review')->nullable();
            $table->string('verdict', 20)->nullable()->index();
            $table->json('verdict_lines')->nullable();
            $table->string('stop_reason', 32)->nullable();
            $table->string('message_id', 64)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('cache_write_tokens')->nullable();
            $table->unsignedInteger('cache_read_tokens')->nullable();
            $table->unsignedBigInteger('cost_usd_micros')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_freezer_claude_recognitions');
    }
};
