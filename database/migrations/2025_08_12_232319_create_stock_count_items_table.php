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
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('stock_count_id')->index();
            $table->integer('stock_cost_amount')->default(0);
            $table->integer('stock_value_amount')->default(0);
            $table->bigInteger('product_id')->index();
            $table->integer('qty_vend')->nullable()->default(0);
            $table->integer('qty_warehouse')->nullable()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
    }
};
