<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brian, 2026-11-12: the instruction a Grab rider needs to find the machine at this Site (where it stands, how to get
 * in), kept with the Site's delivery address next to address_remarks, and offered in Grab Stores' "Update on Grab"
 * window when the store's address is sent to Grab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('grab_driver_instruction')->nullable()->after('address_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('grab_driver_instruction');
        });
    }
};
