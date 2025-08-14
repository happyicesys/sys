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
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->integer('cash_sales_amount')->default(0);
            $table->integer('cashless_sales_amount')->default(0);
            $table->integer('coin_float_amount')->default(0);
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('location_type_id')->nullable()->index();
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->unsignedBigInteger('product_mapping_id')->nullable()->index();
            $table->unsignedBigInteger('vend_id')->nullable()->index();
            $table->unsignedInteger('vend_code')->nullable()->index();
            $table->unsignedBigInteger('vend_contract_id')->nullable()->index();
            $table->unsignedBigInteger('vend_model_id')->nullable()->index();
            $table->unsignedBigInteger('vend_prefix_id')->nullable()->index();
            $table->unsignedSmallInteger('day');
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->index('created_at');
            $table->index(['year', 'month', 'day'], 'idx_stock_counts_ymd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
