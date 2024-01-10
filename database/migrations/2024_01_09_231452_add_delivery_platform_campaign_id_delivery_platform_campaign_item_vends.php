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
        Schema::table('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->bigInteger('delivery_platform_campaign_id')->unsigned()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->dropColumn('delivery_platform_campaign_id');
        });
    }
};
