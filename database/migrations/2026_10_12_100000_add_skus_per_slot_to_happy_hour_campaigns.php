<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several SKUs on offer at the same time (Brian, 2026-10-12): `skus_per_slot` of the top `sku_count`
 * run together for a slot, the next group the slot after, looping. A machine may now hold several
 * slots with one start time, one per SKU, so the backstop unique key gains `product_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->unsignedTinyInteger('skus_per_slot')->default(1)->after('sku_count');
        });
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->unique(['vend_id', 'starts_at', 'product_id']);
            $table->dropUnique(['vend_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->unique(['vend_id', 'starts_at']);
            $table->dropUnique(['vend_id', 'starts_at', 'product_id']);
        });
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropColumn('skus_per_slot');
        });
    }
};
