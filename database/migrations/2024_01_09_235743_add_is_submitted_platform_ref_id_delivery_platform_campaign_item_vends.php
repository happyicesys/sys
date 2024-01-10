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
            $table->boolean('is_submitted')->default(false);
            // $table->string('platform_ref_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->dropColumn('is_submitted');
            // $table->dropColumn('platform_ref_id');
        });
    }
};
