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
        Schema::create('order_item_vend_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_platform_order_item_id');
            $table->unsignedBigInteger('delivery_product_mapping_vend_channel_id');
            $table->unsignedBigInteger('vend_channel_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_vend_channels');
    }
};
