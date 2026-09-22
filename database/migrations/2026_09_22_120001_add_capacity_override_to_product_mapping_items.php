<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * product_mapping_items.capacity_override: the "Reality" column on a Smart
 * Freezer / Smart Chiller mapping (Brian, 2026-09-22). The default capacity
 * of a SKU is the product's (freezer_slot_qty / chiller_slot_qty, Product →
 * Edit); this overrides it for one mapping item. NULL = use the product's.
 * Vending machines never read it — a vending slot's capacity is the board's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_mapping_items', function (Blueprint $table) {
            $table->unsignedInteger('capacity_override')->nullable()->after('sequence');
        });
    }

    public function down(): void
    {
        Schema::table('product_mapping_items', function (Blueprint $table) {
            $table->dropColumn('capacity_override');
        });
    }
};
