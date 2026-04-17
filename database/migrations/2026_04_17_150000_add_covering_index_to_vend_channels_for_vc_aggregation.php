<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a covering index on vend_channels(vend_id, is_active, capacity, amount, qty).
     *
     * The 'vc' aggregation query in VendController does:
     *   SELECT vend_id, SUM(amount * qty), SUM(amount * capacity)
     *   FROM vend_channels
     *   WHERE vend_id IN (...) AND is_active = 1 AND capacity > 0
     *   GROUP BY vend_id
     *
     * The existing idx_vc_vid_active_cap(vend_id, is_active, capacity) covers the
     * WHERE clause but leaves MySQL doing a heap row-lookup for every matched row
     * to read amount and qty. With those two columns added to the index, MySQL can
     * resolve the entire query — filter, aggregation columns, and GROUP BY — from
     * the index leaf nodes alone, with zero heap access.
     */
    public function up(): void
    {
        if (!Schema::hasIndex('vend_channels', 'idx_vc_vid_active_cap_amount_qty')) {
            Schema::table('vend_channels', function (Blueprint $table) {
                $table->index(
                    ['vend_id', 'is_active', 'capacity', 'amount', 'qty'],
                    'idx_vc_vid_active_cap_amount_qty'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('vend_channels', 'idx_vc_vid_active_cap_amount_qty')) {
            Schema::table('vend_channels', function (Blueprint $table) {
                $table->dropIndex('idx_vc_vid_active_cap_amount_qty');
            });
        }
    }
};
