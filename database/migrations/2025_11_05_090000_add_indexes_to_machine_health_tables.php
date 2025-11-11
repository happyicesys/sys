<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vend_channel_stock_events', function (Blueprint $table) {
            $table->index(['vend_channel_id', 'occurred_at'], 'idx_vcse_channel_occurrence');
            $table->index(['occurred_at', 'event_type'], 'idx_vcse_occurrence_type');
        });

        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->index(['vend_channel_error_id', 'created_at'], 'idx_vcel_error_created');
            $table->index(['vend_channel_id', 'created_at'], 'idx_vcel_channel_created');
        });

        Schema::table('vend_temp_metrics', function (Blueprint $table) {
            $table->index(['temp_type', 'period_type', 'period_start'], 'idx_vtm_sensor_period');
            $table->index(['temp_type', 'min_temp_recorded_at', 'max_temp_recorded_at'], 'idx_vtm_sensor_recorded');
        });
    }

    public function down(): void
    {
        Schema::table('vend_channel_stock_events', function (Blueprint $table) {
            $table->dropIndex('idx_vcse_channel_occurrence');
            $table->dropIndex('idx_vcse_occurrence_type');
        });

        Schema::table('vend_channel_error_logs', function (Blueprint $table) {
            $table->dropIndex('idx_vcel_error_created');
            $table->dropIndex('idx_vcel_channel_created');
        });

        Schema::table('vend_temp_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_vtm_sensor_period');
            $table->dropIndex('idx_vtm_sensor_recorded');
        });
    }
};
