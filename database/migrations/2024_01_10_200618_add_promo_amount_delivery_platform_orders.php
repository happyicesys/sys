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
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('cancelled_json');
            $table->dropColumn('driver_arrived_at');
            $table->dropColumn('driver_assigned_at');
            $table->dropColumn('driver_eta_seconds');
            $table->dropColumn('driver_eta_updated_at');
            $table->dropColumn('order_completed_at');
            $table->dropColumn('ref_id');

            $table->json('campaign_json')->nullable()->after('vend_json');
            $table->integer('promo_amount')->default(0)->after('subtotal_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_platform_orders', function (Blueprint $table) {
            $table->dropColumn('campaign_json');
            $table->dropColumn('promo_amount');
        });
    }
};
