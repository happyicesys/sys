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
        Schema::table('categories', function (Blueprint $table) {
            $table->index('category_group_id')->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('category_id')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('is_inventory')->change();
        });

        Schema::table('vend_transactions', function (Blueprint $table) {
            $table->index('product_id')->change();
        });

        Schema::table('vend_bindings', function (Blueprint $table) {
            $table->index('begin_date')->change();
            $table->index(['customer_id', 'vend_id'])->change();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
