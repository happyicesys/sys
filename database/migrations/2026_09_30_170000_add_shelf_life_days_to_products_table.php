<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.shelf_life_days — how many days a CityBox (Smart Chiller) product keeps, set on
 * Product → Edit in the Smart Chiller section (Brian, 2026-09-30). Informational for now: nothing
 * derives expiry from it yet. Vending and Smart Freezer products leave it blank. NULL = not set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('shelf_life_days')->nullable()->after('chiller_slot_qty');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shelf_life_days');
        });
    }
};
