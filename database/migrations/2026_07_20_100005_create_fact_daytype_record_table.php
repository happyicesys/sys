<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nightly materialized fact: the all-time company daily-sales RECORD per
 * day-type bucket, since the reporting floor (config('calendar.record_floor'),
 * default 2026-04-01).
 *
 * Exactly 3 rows (Weekday / Fri_or_PH_eve / Weekend_or_PH). Built by
 * App\Services\Reporting\DailyFactsBuilder::refreshDayTypeRecords() — a cheap
 * full recompute over gp_metrics grouped by dim_calendar.day_type_bucket, so
 * it can never drift. Lets the report state "today is a record Weekend" with a
 * single lookup.
 *
 *   record_sales_cents  the highest company daily sales seen for the bucket.
 *   record_date         the date that set it.
 *   driver_note         holiday_name of record_date (if any), else null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_daytype_record', function (Blueprint $table) {
            $table->string('day_type_bucket', 24)->primary(); // Weekday | Fri_or_PH_eve | Weekend_or_PH
            $table->bigInteger('record_sales_cents')->default(0);
            $table->date('record_date')->nullable();
            $table->string('driver_note')->nullable();
            $table->timestamp('computed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_daytype_record');
    }
};
