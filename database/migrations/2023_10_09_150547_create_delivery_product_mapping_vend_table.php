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
        Schema::create('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_product_mapping_id');
            $table->bigInteger('vend_id');
            $table->index('delivery_product_mapping_id', 'vend_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_product_mapping_vend');
    }
};
