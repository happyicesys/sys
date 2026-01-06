<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_weather_histories', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('weather_code');
            $table->string('icon_code')->nullable();
            $table->timestamps();

            $table->unique(['date', 'latitude', 'longitude'], 'date_lat_lng_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_weather_histories');
    }
};
