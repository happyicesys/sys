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
        Schema::table('delivery_platform_campaign_items', function (Blueprint $table) {
            $table->json('delivery_product_mapping_items_json')->nullable()->after('delivery_platform_campaign_id');
            $table->boolean('is_bulk')->default(false)->after('is_active');
            $table->bigInteger('delivery_product_mapping_bulk_id')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_items', function (Blueprint $table) {
            $table->dropColumn('delivery_product_mapping_items_json');
            $table->dropColumn('is_bulk');
            $table->dropColumn('delivery_product_mapping_bulk_id');
        });
    }
};
