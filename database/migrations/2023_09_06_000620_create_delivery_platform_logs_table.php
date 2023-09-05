<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_platform_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('amount')->nullable();
            $table->bigInteger('delivery_platform_id')->index();
            $table->bigInteger('delivery_platform_operator_id')->index();
            $table->json('error_json')->nullable();
            $table->datetime('order_completed_at')->nullable();
            $table->datetime('order_created_at')->nullable();
            $table->string('ref_id')->nullable();
            $table->string('order_id')->nullable();
            $table->json('order_json')->nullable();
            $table->json('request_history_json')->nullable();
            $table->json('response_history_json')->nullable();
            $table->integer('status')->default(1)->index();
            $table->integer('vend_channel_code');
            $table->bigInteger('vend_channel_id')->index();
            $table->integer('vend_code')->index();
            $table->bigInteger('vend_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_logs');
    }
};
