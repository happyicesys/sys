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
            $table->renameColumn('desc', 'remarks');
            // $table->dropColumn('platform_ref_id');
            $table->dropColumn('total_redeemable_count');
            $table->dropColumn('total_redeemable_count_per_user');
            $table->dropColumn('user_type');
            $table->dropColumn('min_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_campaigns', function (Blueprint $table) {
            //
        });
    }
};
