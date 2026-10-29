<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grab stock held by open orders is now read from the orders themselves
 * (DeliveryProductMappingService::heldQtyByChannel), joining each recent order to
 * its channel rows. The table had only its primary key (~15k rows, 2026-10-29).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->index('delivery_platform_order_id', 'oivc_order_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->dropIndex('oivc_order_id_idx');
        });
    }
};
