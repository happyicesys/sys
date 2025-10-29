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
        Schema::create('gp_metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('txn_date');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->unsignedBigInteger('vend_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('category_group_id')->nullable();
            $table->unsignedBigInteger('customer_location_type_id')->nullable();
            $table->unsignedBigInteger('transaction_location_type_id')->nullable();
            $table->unsignedBigInteger('vend_prefix_id')->nullable();
            $table->unsignedBigInteger('vend_contract_id')->nullable();
            $table->unsignedBigInteger('vend_model_id')->nullable();
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_binded_customer')->default(false);
            $table->unsignedBigInteger('sale_count')->default(0);
            $table->unsignedBigInteger('transaction_count')->default(0);
            $table->bigInteger('revenue_cents')->default(0);
            $table->bigInteger('gross_profit_cents')->default(0);
            $table->bigInteger('unit_cost_cents')->default(0);
            $table->timestamps();

            $table->unique([
                'txn_date',
                'operator_id',
                'vend_id',
                'customer_id',
                'product_id',
                'category_id',
                'category_group_id',
                'customer_location_type_id',
                'transaction_location_type_id',
                'vend_prefix_id',
                'vend_contract_id',
                'vend_model_id',
                'is_multiple',
                'is_binded_customer',
            ], 'gp_metrics_unique_dimensions');

            $table->index(['txn_date', 'product_id'], 'gp_metrics_date_product');
            $table->index(['txn_date', 'vend_id'], 'gp_metrics_date_vend');
            $table->index(['txn_date', 'customer_id'], 'gp_metrics_date_customer');
            $table->index(['txn_date', 'operator_id'], 'gp_metrics_date_operator');
            $table->index(['txn_date', 'category_id'], 'gp_metrics_date_category');
            $table->index(['txn_date', 'customer_location_type_id'], 'gp_metrics_date_customer_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gp_metrics');
    }
};
