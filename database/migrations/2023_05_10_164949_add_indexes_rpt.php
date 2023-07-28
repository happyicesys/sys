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
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('country_id')->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('classname')->change();
        });

        Schema::table('category_groups', function (Blueprint $table) {
            $table->index('classname')->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('code')->change();
            $table->index('created_at')->change();
            $table->index('is_active')->change();
        });

        Schema::table('location_types', function (Blueprint $table) {
            $table->index('sequence')->change();
        });

        Schema::table('operator_payment_gateways', function (Blueprint $table) {
            $table->index('operator_id')->change();
            $table->index('payment_gateway_id')->change();
            $table->index('type')->change();
        });

        Schema::table('operator_vend', function (Blueprint $table) {
            $table->index('created_at')->change();
        });

        Schema::table('operators', function (Blueprint $table) {
            $table->index('country_id')->change();
        });

        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->index('order_id')->change();
            $table->index('operator_payment_gateway_id')->change();
            $table->index('payment_gateway_id')->change();
            $table->index('vend_transaction_id')->change();
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->index('classname')->change();
            $table->index('country_id')->change();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->index('code')->change();
            $table->index('created_at')->change();
        });

        Schema::table('product_mapping_items', function (Blueprint $table) {
            $table->index('product_id')->change();
            $table->index('product_mapping_id')->change();
        });

        Schema::table('product_mappings', function (Blueprint $table) {
            $table->index('operator_id')->change();
        });

        Schema::table('product_uoms', function (Blueprint $table) {
            $table->index('is_base_uom')->change();
            $table->index('is_transaction_uom')->change();
            $table->index('product_id')->change();
            $table->index('uom_id')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('code')->change();
            $table->index('category_id')->change();
            $table->index('category_group_id')->change();
            $table->index('operator_id')->change();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->index('base_currency_id')->change();
        });

        Schema::table('statuses', function (Blueprint $table) {
            $table->index('classname')->change();
        });

        Schema::table('unit_costs', function (Blueprint $table) {
            $table->index('created_at')->change();
            $table->index('date_from')->change();
            $table->index('is_current')->change();
            $table->index('product_id')->change();
            $table->index('profile_id')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('profile_id')->change();
            $table->index('operator_id')->change();
        });

        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->index('is_error_cleared')->change();
            $table->index('vend_channel_id')->change();
            $table->index('vend_channel_error_id')->change();
            $table->index('vend_transaction_id')->change();
        });

        Schema::table('vend_channels', function (Blueprint $table) {
            $table->index('is_active')->change();
            $table->index('vend_id')->change();
            $table->index('product_id')->change();
        });

        Schema::table('vend_fans', function (Blueprint $table) {
            $table->index('vend_id')->change();
            $table->index('type')->change();
            $table->index('created_at')->change();
        });

        Schema::table('vend_temps', function (Blueprint $table) {
            $table->index('type')->change();
            $table->index('created_at')->change();
        });

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->index('is_payment_received')->change();
            $table->index('unit_cost_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
