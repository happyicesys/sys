<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logical dimension: one row per calendar date → day-type classification and
 * school/holiday flags. Joins to any fact on an equality (dim_calendar.date =
 * fact.date) instead of a range.
 *
 * Built by App\Services\Reporting\DimensionRebuilder::rebuildCalendar() from
 * holiday_days (public/school-holiday flags) + config('calendar.school_terms')
 * (MOE term ranges). Fully derived — safe to truncate/regenerate.
 *
 *   dow                 0=Sun .. 6=Sat (Carbon dayOfWeek).
 *   day_type_bucket     Weekday | Fri_or_PH_eve | Weekend_or_PH.
 *   is_school           school HOLIDAY / vacation (from holiday_days).
 *   is_school_term      school IN SESSION (from MOE term ranges).
 *   is_madrasah_active  is_school_term AND weekend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_calendar', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedTinyInteger('dow');                 // 0=Sun .. 6=Sat
            $table->string('day_type_bucket', 24);              // Weekday | Fri_or_PH_eve | Weekend_or_PH
            $table->boolean('is_public')->default(false);
            $table->boolean('is_school')->default(false);       // school holiday / vacation
            $table->boolean('is_school_term')->default(false);  // school in session
            $table->boolean('is_madrasah_active')->default(false);
            $table->string('holiday_name')->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->index('day_type_bucket', 'idx_dimcal_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_calendar');
    }
};
