<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weather ingestion storage (rainfall to start).
 *
 * Two tables, kept deliberately region-agnostic so a future non-SG app can
 * reuse them via a different WeatherProvider implementation:
 *
 *   weather_stations  — static-ish sensor metadata (code, name, lat/lng),
 *                       upserted on every sync so new stations self-register.
 *   weather_readings  — the time series, one row per station per timestamp.
 *
 * `provider` (e.g. "sg") is stored on both so a single instance could hold
 * multiple regions if ever needed, and so region filters stay index-friendly.
 *
 * Idempotency + performance:
 *   - unique(provider, code) on stations       → upsert target.
 *   - unique(weather_station_id, observed_at)   → re-pulling the same 5-min
 *     block is a no-op (insertOrIgnore); also the per-station time-range index.
 *   - index(provider, observed_at)              → region-wide time-window scans.
 *   - index(observed_at)                        → cross-station windows and the
 *     eventual join against vend_transactions timestamps.
 *
 * observed_at is stored in the app timezone (APP_TIMEZONE, default
 * Asia/Singapore) so it lines up 1:1 with vend_transactions timestamps for the
 * later sales-vs-weather analysis. No FK is added to customers/vend_transactions
 * yet — mapping is a later round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_stations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);           // e.g. "sg"
            $table->string('region', 64)->nullable(); // e.g. "Singapore"
            $table->string('code', 32);               // provider station id, e.g. "S218"
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('unit', 16)->default('mm');
            $table->timestamp('last_seen_at')->nullable(); // last sync that reported this station
            $table->timestamps();

            $table->unique(['provider', 'code']);
        });

        Schema::create('weather_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weather_station_id')
                ->constrained('weather_stations')
                ->cascadeOnDelete();
            $table->string('provider', 32);
            $table->dateTime('observed_at');          // app-timezone reading time
            $table->decimal('value', 6, 2)->default(0); // rainfall total for the interval
            $table->string('reading_type', 64)->nullable(); // e.g. "TB1 Rainfall 5 Minute Total F"
            $table->string('unit', 16)->default('mm');
            $table->timestamp('created_at')->nullable(); // insert time only (no updates)

            $table->unique(['weather_station_id', 'observed_at']); // idempotent + per-station time scans
            $table->index(['provider', 'observed_at']);            // region-wide time windows
            $table->index('observed_at');                          // cross-station / future txn join
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_readings');
        Schema::dropIfExists('weather_stations');
    }
};
