<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-11-21: nobody can see a Grab store's live menu, so Grab Menu & Prices shows when each part last changed on
 * Grab. A store link records when Grab last pulled its menu and when the items listed or their prices last changed in
 * what it pulled. A store's Grab name and address can now be typed by a person (no API gives them), so the store
 * records who set them: NULL source = the GrabMerchant website read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dateTime('grab_menu_pulled_at')->nullable();
            $table->dateTime('grab_items_changed_at')->nullable();
            $table->dateTime('grab_prices_changed_at')->nullable();
        });

        Schema::table('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->string('grab_profile_source', 16)->nullable();
            $table->unsignedBigInteger('grab_profile_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_product_mapping_vend', function (Blueprint $table) {
            $table->dropColumn(['grab_menu_pulled_at', 'grab_items_changed_at', 'grab_prices_changed_at']);
        });

        Schema::table('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->dropColumn(['grab_profile_source', 'grab_profile_user_id']);
        });
    }
};
