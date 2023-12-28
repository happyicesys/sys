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
        Schema::create('delivery_product_mapping_bulks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_product_mapping_id')->index('del_pro_map_id');
            $table->json('group_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('name')->nullable();
            $table->integer('promo_value')->default(0);
            $table->string('promo_type')->nullable();
            $table->integer('total_qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_product_mapping_bulks');
    }
};
