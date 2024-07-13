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
        Schema::create('product_mapping_product_mapping', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_mapping_id')->unsigned();
            $table->bigInteger('upcoming_product_mapping_id')->unsigned();
            $table->timestamps();

            $table->unique(['product_mapping_id', 'upcoming_product_mapping_id'], 'product_mapping_upcoming_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_mapping_product_mapping');
    }
};
