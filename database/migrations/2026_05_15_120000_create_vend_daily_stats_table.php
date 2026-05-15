<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vend_daily_stats — generic per-machine, per-day counter table.
 *
 * Designed to capture non-sales hardware / diagnostic / protocol events that
 * the server receives from machines (e.g. PWRON power-on counts). Adding a
 * new metric in future does NOT require a schema change — just dispatch with
 * a new `metric` string.
 *
 * Writes are atomic via `INSERT … ON DUPLICATE KEY UPDATE count = count + 1`
 * keyed on the (vend_id, date, metric) UNIQUE index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vend_id');
            $table->string('vend_code')->index(); // denormalized for fast code-based lookups
            $table->date('date');
            $table->string('metric', 64); // e.g. 'pwron'
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            // Atomic upsert key — one row per (machine, day, metric).
            $table->unique(['vend_id', 'date', 'metric'], 'vend_daily_stats_vend_date_metric_unique');

            // Common query patterns.
            $table->index(['date', 'metric'], 'vend_daily_stats_date_metric_index');
            $table->index(['vend_code', 'date'], 'vend_daily_stats_vend_code_date_index');

            // FK constraint per user preference. Cascade on delete so removing
            // a vend cleans up its stats automatically (these are derivative
            // counters, no business value if the parent vend is gone).
            $table->foreign('vend_id')
                ->references('id')
                ->on('vends')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_daily_stats');
    }
};
