<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "After stock in within X hours, exclude last 7 days top Y bestseller SKU from happy hour" (Brian,
 * 2026-10-16): for `bestseller_hold_hours` after a machine's last Stock In, its top
 * `bestseller_hold_count` SKUs by units sold over the last `bestseller_lookback_days` (7 or 14) are not
 * picked for a slot. 0 hours or 0 SKUs = off, so every existing campaign is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->unsignedSmallInteger('bestseller_hold_hours')->default(0)->after('skip_unavailable');
            $table->unsignedTinyInteger('bestseller_hold_count')->default(0)->after('bestseller_hold_hours');
            $table->unsignedTinyInteger('bestseller_lookback_days')->default(7)->after('bestseller_hold_count');
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropColumn(['bestseller_hold_hours', 'bestseller_hold_count', 'bestseller_lookback_days']);
        });
    }
};
