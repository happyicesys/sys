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
            // $table->string('platform_ref_id');
            // $table->unsignedBigInteger('delivery_product_mapping_vend_id')->nullable();
            // $table->unsignedBigInteger('delivery_product_mapping_vend_channel_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('platform_ref_id');
            $table->dropColumn('delivery_product_mapping_vend_id');
            $table->dropColumn('delivery_product_mapping_vend_channel_id');
        });
    }
};
