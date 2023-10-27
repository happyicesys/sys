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
            $table->dropColumn('vend_channel_code');
            $table->dropColumn('vend_channel_id');
            $table->dropColumn('delivery_product_mapping_vend_channel_id');
            $table->integer('subtotal_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            //
        });
    }
};
