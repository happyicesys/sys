<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many pieces of this SKU fit in ONE smart-freezer slot (a basket division).
 *
 * Capacity is a property of the product, not of the cabinet: a box of cones and a tub of Magnum do
 * not fit the same number in the same basket. A freezer's channels are written from its planogram
 * (FreezerChannelSync) and take their capacity from here, so it is set once per SKU instead of per
 * machine. Null = not measured yet, which shows as "—" rather than a made-up par.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('freezer_slot_qty')->nullable()->after('measurement_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('freezer_slot_qty');
        });
    }
};
