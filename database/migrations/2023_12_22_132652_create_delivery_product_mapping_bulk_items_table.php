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
        Schema::create('delivery_product_mapping_bulk_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_product_mapping_bulk_id')->index('del_pro_map_bulk_id');
            $table->bigInteger('delivery_product_mapping_item_id')->index('del_pro_map_item_id');
            $table->json('sub_category_json')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_product_mapping_bulk_items');
    }
};
