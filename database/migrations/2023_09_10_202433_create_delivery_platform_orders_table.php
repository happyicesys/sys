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
        Schema::create('delivery_platform_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_platform_id')->index();
            $table->bigInteger('delivery_platform_operator_id')->index();
            $table->datetime('driver_arrived_at')->nullable();
            $table->datetime('driver_assigned_at')->nullable();
            $table->json('error_json')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_edited')->default(false);
            $table->datetime('order_completed_at')->nullable();
            $table->datetime('order_created_at')->nullable();
            $table->string('order_id')->nullable()->index();
            $table->json('order_json')->nullable();
            $table->bigInteger('product_mapping_id')->nullable()->index();
            $table->string('ref_id')->nullable();
            $table->json('request_history_json')->nullable();
            $table->json('response_history_json')->nullable();
            $table->string('short_order_id')->index();
            $table->integer('status')->default(1)->index();
            $table->integer('total_amount')->nullable();
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
        Schema::dropIfExists('delivery_platform_orders');
    }
};
