<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logical dimension: one row per site (customer) → its behavioural cohort,
 * location type, and nearest rainfall station.
 *
 * Fully derived and rebuilt every night by App\Services\Reporting\
 * DimensionRebuilder::rebuildSiteCohorts() from customers + their primary
 * address (addresses.type=1) + weather_stations (haversine). Replaces the
 * report's hardcoded site-name matching (Zoo/Sentosa/"Masjid"…) and manual
 * nearest-station picks. Safe to truncate/regenerate.
 *
 * No FKs — kept decoupled like weather_stations/holiday_days so a rebuild never
 * contends with writes on customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_site_cohort', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->primary();
            $table->string('cohort', 32);                       // tourist_leisure | mosque_madrasah | weekday_routine | school_linked | other
            $table->unsignedBigInteger('location_type_id')->nullable();
            $table->string('location_type_name')->nullable();
            $table->unsignedBigInteger('nearest_weather_station_id')->nullable();
            $table->decimal('distance_km', 8, 3)->nullable();   // haversine site → station
            $table->decimal('latitude', 10, 7)->nullable();     // site primary-address coords (audit)
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->index('cohort', 'idx_dsc_cohort');
            $table->index('nearest_weather_station_id', 'idx_dsc_station');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_site_cohort');
    }
};
