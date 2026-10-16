<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Prioritize: SKU will be selected for HH on every other hour" (Brian, 2026-10-16 — to clear stock, or when a
 * supplier funds the discount). `priority_product_ids` lists the campaign's prioritized SKUs; every other slot
 * of the day offers them first. `happy_hour_slots.is_priority` marks a slot picked from that list, so the
 * normal rotation can skip it and a supplier claim can total it. NULL / false everywhere = unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->json('priority_product_ids')->nullable()->after('excluded_product_ids');
        });
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->boolean('is_priority')->default(false)->after('rank');
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->dropColumn('is_priority');
        });
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropColumn('priority_product_ids');
        });
    }
};
