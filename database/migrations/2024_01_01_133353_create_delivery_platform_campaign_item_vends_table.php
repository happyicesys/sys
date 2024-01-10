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
        Schema::create('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('delivery_platform_campaign_item_id')->unsigned();
            $table->bigInteger('delivery_product_mapping_vend_id')->unsigned();
            $table->index(['delivery_product_mapping_vend_id', 'delivery_platform_campaign_item_id'], 'del_plat_cam_item_id_del_prod_map_vend_id');
            $table->string('platform_ref_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_platform_campaign_item_vends');
    }
};
