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
        Schema::table('delivery_platform_campaigns', function (Blueprint $table) {
            $table->dropColumn('platform_campaign_type');
            $table->dropColumn('platform_campaign_scope');
            $table->dropColumn('platform_campaign_value');
            $table->integer('min_amount')->default(0);
            $table->string('platform_ref_id')->nullable();
            $table->integer('total_redeemable_count')->nullable();
            $table->integer('total_redeemable_count_per_user')->nullable();
            $table->string('user_type')->nullable();
        });

        Schema::table('delivery_product_mapping_bulks', function (Blueprint $table) {
            $table->bigInteger('delivery_platform_campaign_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaigns', function (Blueprint $table) {
            $table->dropColumn('user_type');
            $table->dropColumn('min_amount');
            $table->dropColumn('total_redeemable_count');
            $table->dropColumn('total_redeemable_count_per_user');
        });

        Schema::table('delivery_product_mapping_bulks', function (Blueprint $table) {
            $table->dropColumn('delivery_platform_campaign_id');
        });
    }
};
