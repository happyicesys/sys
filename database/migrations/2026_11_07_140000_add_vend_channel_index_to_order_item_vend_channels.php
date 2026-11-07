<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Held stock on open Grab orders is read per slot (GrabMachineMenu::heldQtyByVendChannel, every menu build and
 * order) and per mapping slot (DeliveryProductMappingService::heldQtyByChannel); neither column had an index, so each
 * read scanned the table (34.5k rows on 2026-11-07). Audit 2026-11-07 GRAB-13. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->index('vend_channel_id', 'oivc_vend_channel_id_idx');
            $table->index('delivery_product_mapping_vend_channel_id', 'oivc_dpmvc_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_vend_channels', function (Blueprint $table) {
            $table->dropIndex('oivc_vend_channel_id_idx');
            $table->dropIndex('oivc_dpmvc_id_idx');
        });
    }
};
