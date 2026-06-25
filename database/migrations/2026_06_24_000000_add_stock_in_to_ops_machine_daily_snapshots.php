<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a frozen daily Stock-in value to the per-machine snapshot.
 *
 * Like l30d_vend_earning_cents, this is fully reconstructable from history —
 * ops_jobs / ops_job_items / ops_job_item_channels carry a real job date, so the
 * value for any past day is genuine, not "today's value stamped on an old day".
 * It stores THAT machine's stock-in value for completed jobs dated the snapshot
 * day (SUM(actual_qty * vend_channel.amount)), matching the definition the
 * CustomerIndex / Site Summary stock-in figures use.
 *
 * cents, nullable: NULL means "not computed for this row yet" — either a snapshot
 * that predates this feature (until ops:seed-stock-in fills it) or a machine with
 * no completed job that day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->bigInteger('stock_in_cents')->nullable()->after('l30d_vend_earning_cents');
        });
    }

    public function down(): void
    {
        Schema::table('ops_machine_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn('stock_in_cents');
        });
    }
};
