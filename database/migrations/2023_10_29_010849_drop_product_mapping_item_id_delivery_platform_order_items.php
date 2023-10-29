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
        Schema::table('delivery_platform_order_items', function (Blueprint $table) {
            $table->dropColumn('product_mapping_item_id');
            $table->bigInteger('delivery_product_mapping_item_id')->nullable()->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_order_items', function (Blueprint $table) {
            $table->dropColumn('delivery_product_mapping_item_id');
        });
    }
};
