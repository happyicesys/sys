<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily copy of each machine's cumulative data-usage counters
 * (vends.internet_data_*), taken by `vend:snapshot-data-usage` at 23:55.
 *
 * The APK deliberately reports CUMULATIVE lifetime MB, not a windowed figure —
 * so any window is a diff of two rows here: last 30 days for a machine is
 * (today's total_mb - the total_mb of the newest row >= 30 days old). A
 * cumulative value lower than an older row's means the ledger reset (APK
 * reinstall / prefs wipe); readers should treat the diff as "at least the new
 * cumulative" rather than negative.
 *
 * One row per machine per day (upsert on vend_id+captured_on, so a rerun is
 * idempotent). Rows are small and bounded (~1 per machine per day); no pruning
 * scheduled — a year of a 500-machine fleet is ~180k rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_data_usage_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vend_id');
            $table->integer('vend_code')->nullable();   // denormalized for display/CSV, no join
            $table->date('captured_on');
            $table->unsignedBigInteger('total_mb');
            $table->unsignedBigInteger('mobile_mb')->nullable();
            $table->unsignedBigInteger('app_mb')->nullable();
            $table->unsignedSmallInteger('ledger_days')->nullable();
            $table->timestamp('created_at')->nullable();

            // Upsert key, and the read pattern: one machine's rows by date.
            $table->unique(['vend_id', 'captured_on'], 'vdus_vend_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_data_usage_snapshots');
    }
};
