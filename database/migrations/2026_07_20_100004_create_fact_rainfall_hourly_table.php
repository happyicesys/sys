<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nightly materialized fact: hourly rainfall per station, summed from the
 * 5-minute weather_readings.
 *
 * Grain = date × hour(0-23) × weather_station_id, rainfall_mm = SUM(value).
 * Built by App\Services\Reporting\DailyFactsBuilder::buildRainfallHourly().
 *
 * Two wins: removes the report's live NEA paginated historical-rainfall fetch,
 * AND preserves rainfall history beyond the ~12 days weather_readings retains
 * (this table is never pruned). Idempotent: delete-by-date then insert.
 *
 * NOTE: backfilling a date older than the weather_readings retention window
 * yields no rows (the source data is already gone) — expected; the value is
 * forward preservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_rainfall_hourly', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour');            // 0-23
            $table->unsignedBigInteger('weather_station_id');
            $table->decimal('rainfall_mm', 8, 2)->default(0);
            $table->unsignedInteger('reading_count')->default(0); // 5-min blocks summed (max 12/hour)
            $table->timestamp('computed_at')->nullable();

            $table->unique(['date', 'hour', 'weather_station_id'], 'uq_frh_date_hour_station');
            $table->index(['weather_station_id', 'date'], 'idx_frh_station_date');
            $table->index(['date', 'hour'], 'idx_frh_date_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_rainfall_hourly');
    }
};
