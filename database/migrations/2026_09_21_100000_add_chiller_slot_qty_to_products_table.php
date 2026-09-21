<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.chiller_slot_qty — pieces of this SKU per CityBox chiller channel,
 * the sibling of freezer_slot_qty (Brian, 2026-09-21). Capacity belongs to the
 * SKU, not the cabinet, and a chiller channel takes it when the mapping puts
 * the product on a code; a product on two codes gives that machine twice the
 * capacity, which is intended (two facings).
 *
 * Why it is ours at all: CityBox's own par is NOT writable through their
 * OpenAPI and does not cap anything — a push of 10 against par 5 was accepted
 * by both sides on 2026-09-19 (citybox/EXPERIMENT_STOCK_OVER_PAR_2026-09-19.md).
 * Their par stays display-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('chiller_slot_qty')->nullable()->after('freezer_slot_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('chiller_slot_qty');
        });
    }
};
