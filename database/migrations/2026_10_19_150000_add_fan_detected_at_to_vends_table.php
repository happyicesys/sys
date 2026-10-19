<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fan detection (Brian, 2026-10-19): a machine has a fan once its board has reported a fan speed above
 * App\Support\FanSignal::RUNNING_MIN, and keeps it. vend_fans keeps only ~14 days, so "ever seen" needs
 * its own stamp. New machines start with no fan (was: Yes, which raised 2A on every fan-less board).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->timestamp('fan_detected_at')->nullable()->after('is_fan_enabled');
            $table->boolean('is_fan_enabled')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn('fan_detected_at');
            $table->boolean('is_fan_enabled')->default(true)->change();
        });
    }
};
