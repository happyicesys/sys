<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a frozen daily L30d VendEarning figure to the per-machine snapshot.
 *
 * Unlike the machine STATE columns (which vends keeps no history of, so a
 * snapshot is always "current state stamped with a date"), the L30d net
 * VendEarning is fully reconstructable from gp_metrics, which keeps the entire
 * transaction history. Storing it per machine per day lets the Ops Performance
 * page show a REAL per-day VendEarning series — and it can be back-seeded
 * accurately for past dates (see ops:seed-vend-earning), not just stamped with
 * today's value.
 *
 * cents, nullable: NULL means "not computed for this row yet" (e.g. a state
 * snapshot that predates this feature, until the seeder fills it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->bigInteger('l30d_vend_earning_cents')->nullable()->after('acb_rev');
        });
    }

    public function down(): void
    {
        Schema::table('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn('l30d_vend_earning_cents');
        });
    }
};
