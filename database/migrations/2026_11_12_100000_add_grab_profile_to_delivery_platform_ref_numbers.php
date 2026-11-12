<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-11-12: Grab's partner API gives no store address, so a fix made in the GrabMerchant app never reached
 * ConnectVend. Once a day the GrabMerchant website's Store profile page is read for every store and sent here: the
 * name, address (with its postal code) and delivery hours customers see on Grab, and when they were read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->string('grab_store_name')->nullable();
            $table->string('grab_address', 500)->nullable();
            $table->string('grab_postal_code', 10)->nullable();
            $table->string('grab_hours')->nullable();
            $table->dateTime('grab_profile_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_platform_ref_numbers', function (Blueprint $table) {
            $table->dropColumn(['grab_store_name', 'grab_address', 'grab_postal_code', 'grab_hours', 'grab_profile_checked_at']);
        });
    }
};
