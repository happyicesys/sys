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
        Schema::create('delivery_product_mapping_items', function (Blueprint $table) {
            $table->id();
            $table->integer('amount')->default(0);
            $table->bigInteger('delivery_product_mapping_id')->index();
            $table->bigInteger('product_mapping_id')->index();
            $table->bigInteger('product_mapping_item_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_product_mapping_items');
    }
};
