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
        Schema::create('delivery_platform_order_items', function (Blueprint $table) {
            $table->id();
            $table->integer('amount')->default(0);
            $table->bigInteger('delivery_platform_order_id')->index();
            $table->boolean('is_cancelled')->default(false);
            $table->boolean('is_edited')->default(false);
            $table->bigInteger('product_id')->index();
            $table->bigInteger('product_mapping_item_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_order_items');
    }
};
