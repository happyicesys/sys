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
            $table->dropColumn('min_amount');
            $table->dropColumn('total_count');
            $table->dropColumn('total_count_per_user');
            $table->dropColumn('type');
            $table->dropColumn('value');
            $table->json('settings_json')->nullable()->after('delivery_product_mapping_items_json');
            $table->string('settings_label')->nullable()->after('delivery_product_mapping_items_json');
            $table->string('settings_name')->nullable()->after('delivery_product_mapping_items_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_items', function (Blueprint $table) {
            //
        });
    }
};
