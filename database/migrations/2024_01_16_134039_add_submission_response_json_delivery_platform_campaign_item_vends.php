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
            $table->json('submission_response_json')->nullable()->after('is_submitted');
            $table->string('vend_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaign_item_vends', function (Blueprint $table) {
            $table->dropColumn('submission_response_json');
            $table->dropColumn('vend_code');
        });
    }
};
