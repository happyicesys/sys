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
            $table->json('settings_json')->nullable();
            $table->string('settings_label')->nullable();
            $table->string('settings_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->dropColumn('settings_json');
            $table->dropColumn('settings_label');
            $table->dropColumn('settings_name');
        });
    }
};
