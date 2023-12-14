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
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->json('vend_json')->nullable()->after('vend_code');
        });

        Schema::table('delivery_platform_order_items', function (Blueprint $table) {
            $table->json('product_json')->nullable()->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('vend_json');
        });

        Schema::table('delivery_platform_order_items', function (Blueprint $table) {
            $table->dropColumn('product_json');
        });
    }
};
