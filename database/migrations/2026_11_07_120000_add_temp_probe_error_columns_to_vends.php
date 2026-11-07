<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * (1C) Temperature probe error (Brian, 2026-11-07, machine 2573): one episode per machine, see
 * App\Support\TempProbe. Nullable / defaulted, so adding them is instant and old code ignores them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->timestamp('temp_probe_error_since')->nullable();
            $table->timestamp('temp_probe_ok_since')->nullable();
            $table->string('temp_probe_error_probes', 20)->nullable();
            $table->unsignedTinyInteger('temp_probe_error_level')->default(0);
            $table->timestamp('temp_probe_error_alert_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vends', function (Blueprint $table) {
            $table->dropColumn([
                'temp_probe_error_since', 'temp_probe_ok_since', 'temp_probe_error_probes',
                'temp_probe_error_level', 'temp_probe_error_alert_at',
            ]);
        });
    }
};
