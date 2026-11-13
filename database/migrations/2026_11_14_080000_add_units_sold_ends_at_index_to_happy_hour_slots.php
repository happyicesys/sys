<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server log audit 2026-11-14.
 *
 * HappyHourPlanner::closeEnded() runs every minute: `units_sold IS NULL AND ends_at <= now ORDER BY
 * ends_at LIMIT 300`. No index led with `units_sold`, so each run read all ~320k slots and sorted them
 * (0.7–0.8 s on sys) to find the few hundred still open. This index makes it a short range read in
 * order. Added online (ALGORITHM=INPLACE, writes continue); no code reads it by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->index(['units_sold', 'ends_at'], 'happy_hour_slots_units_sold_ends_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('happy_hour_slots', function (Blueprint $table) {
            $table->dropIndex('happy_hour_slots_units_sold_ends_at_index');
        });
    }
};
