<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_mappings: smart-freezer planogram support
 *
 * Two additive nullable/defaulted columns, no impact on existing vending-machine
 * mappings (is_smart defaults to 0; basket_layout_json defaults to NULL).
 *
 * is_smart
 *   Marks the mapping as a smart-freezer planogram. The Vend itself decides
 *   "smart vs default" via Vend::isSmart() (vend_model.name = SMART_VEND); this
 *   flag tells the *mapping editor* which layout to render (basket grid vs the
 *   classic channel-row table) and tells write-side code that channel_code
 *   strings will be alphanumeric ("1a", "2b", "3"...) instead of numeric.
 *
 * basket_layout_json
 *   Per-basket division shape for the freezer grid, e.g.
 *     [{ "basket": 1, "divisions": 3 },   // 1a, 1b, 1c
 *      { "basket": 2, "divisions": 2 },   // 2a, 2b
 *      { "basket": 3, "divisions": 0 },   // single slot "3"
 *      ...]
 *   Stored on the mapping (not derived from product_mapping_items) so the
 *   editor can render an empty grid before any product is bound, and so a
 *   basket can legitimately have zero bound items without disappearing.
 *
 * Index on is_smart is cheap and lets future filters (e.g. "show only smart
 * planograms") stay sargable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->boolean('is_smart')->default(false)->after('selling_price_type');
            $table->json('basket_layout_json')->nullable()->after('is_smart');

            $table->index('is_smart', 'product_mappings_is_smart_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_mappings', function (Blueprint $table) {
            $table->dropIndex('product_mappings_is_smart_index');
            $table->dropColumn(['is_smart', 'basket_layout_json']);
        });
    }
};
