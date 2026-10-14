<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live machine groups on a Happy Hour campaign (Brian, 2026-10-14): "All VM - Big Board" and "All
 * Smart Freezers" instead of binding each machine. `machine_groups` lists the ticked groups;
 * `HappyHourMachineGroups` keeps their members in `happy_hour_campaign_vend` (tagged `via_group`)
 * as machines qualify or stop, so every reader of the pivot keeps working. A row with NULL
 * `via_group` was picked by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->json('machine_groups')->nullable()->after('operator_id');
        });
        Schema::table('happy_hour_campaign_vend', function (Blueprint $table) {
            $table->string('via_group', 32)->nullable()->after('vend_id');
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_campaign_vend', function (Blueprint $table) {
            $table->dropColumn('via_group');
        });
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropColumn('machine_groups');
        });
    }
};
