<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machines a campaign's live groups leave out (Brian, 2026-10-30: "how can I exclude those vends if All
 * VM or All Smart Freezers is ticked?"). `excluded_vend_ids` lists them; HappyHourMachineGroups::members()
 * drops them, so they never join through a group. A machine picked by name is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->json('excluded_vend_ids')->nullable()->after('machine_groups');
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_campaigns', function (Blueprint $table) {
            $table->dropColumn('excluded_vend_ids');
        });
    }
};
