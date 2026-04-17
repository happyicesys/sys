<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a covering index on vend_channels(vend_id, is_active, capacity, product_id).
     *
     * The vc_stock query in VendController filters vend_channels by:
     *   vend_id IN (...) + is_active = 1 + capacity > 0
     * and then joins to products via product_id.
     *
     * The existing idx_vc_vid_active_cap covers the first three columns but does NOT
     * include product_id, so MySQL must do a heap row-lookup for every matched channel
     * just to get product_id for the products JOIN. With 50 vends × ~20 channels each,
     * that is ~1000 random I/O operations that can be eliminated entirely by including
     * product_id in the index (InnoDB can read it directly from the index leaf node).
     */
    public function up(): void
    {
        if (!Schema::hasIndex('vend_channels', 'idx_vc_vid_active_cap_prod')) {
            Schema::table('vend_channels', function (Blueprint $table) {
                $table->index(['vend_id', 'is_active', 'capacity', 'product_id'], 'idx_vc_vid_active_cap_prod');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('vend_channels', 'idx_vc_vid_active_cap_prod')) {
            Schema::table('vend_channels', function (Blueprint $table) {
                $table->dropIndex('idx_vc_vid_active_cap_prod');
            });
        }
    }
};
