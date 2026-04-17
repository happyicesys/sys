<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            // Stores MIN(vend_temps.value) for type=1 over last 48h, updated hourly by DetectTempTrends job.
            // Raw integer (÷10 = °C). NULL means no data in window.
            $table->integer('t1_lowest_48h')->nullable()->after('temp');
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('t1_lowest_48h');
        });
    }
};
