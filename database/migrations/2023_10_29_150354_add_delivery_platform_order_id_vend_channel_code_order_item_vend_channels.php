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
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_platform_order_id')->after('id');
            $table->string('vend_channel_code')->nullable()->after('vend_channel_id');
            $table->unsignedBigInteger('delivery_product_mapping_item_id')->after('delivery_platform_order_item_id')->nullable();
            $table->integer('qty')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->dropColumn('delivery_platform_order_id');
            $table->dropColumn('vend_channel_code');
            $table->dropColumn('delivery_product_mapping_item_id');
            $table->dropColumn('qty');
        });
    }
};
