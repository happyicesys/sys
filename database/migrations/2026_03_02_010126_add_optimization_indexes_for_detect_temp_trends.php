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
        Schema::table('vend_temps', function (Blueprint $table) {
            // Index to dramatically speed up analyzeTrend queries with ORDER BY value ASC, created_at ASC
            $table->index(['vend_id', 'type', 'value', 'created_at'], 'idx_vt_optimal_trend');
        });

        Schema::table('vend_channel_stock_events', function (Blueprint $table) {
            // Index to avoid temporary table filesort and speed up subquery grouping in checkStockouts
            $table->index(['vend_id', 'vend_channel_id', 'id'], 'idx_vcse_optimal_stockouts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vend_temps', function (Blueprint $table) {
            $table->dropIndex('idx_vt_optimal_trend');
        });

        Schema::table('vend_channel_stock_events', function (Blueprint $table) {
            $table->dropIndex('idx_vcse_optimal_stockouts');
        });
    }
};
