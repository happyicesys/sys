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
            $table->dateTime('datetime_from')->nullable();
            $table->dateTime('datetime_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->dropColumn('datetime_from');
            $table->dropColumn('datetime_to');
        });
    }
};
