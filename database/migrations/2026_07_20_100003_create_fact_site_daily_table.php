<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nightly materialized fact: per-site daily sales with the weekday-over-weekday
 * comparison PRE-COMPUTED, so the report's anomaly ranking is a plain
 * filter/sort instead of a run-time self-join.
 *
 * Grain = date × customer_id (+ denormalized location_type_id, cohort,
 * day_type_bucket). Measures rolled up from gp_metrics for the day; the
 * same-day-of-week-prior-week comparison from gp_metrics 7 days earlier.
 *
 * Built by App\Services\Reporting\DailyFactsBuilder::buildSiteDaily(). Reads
 * gp_metrics (already the platform's settled-sale roll-up) so every figure
 * reconciles with Summary/Ops. Idempotent: delete-by-date then insert.
 *
 *   sales_same_dow_prev_wk  site sales on (date - 7 days), cents.
 *   delta_abs               sales_cents - sales_same_dow_prev_wk.
 *   delta_pct               100 * delta_abs / prev  (NULL when prev = 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_site_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('location_type_id')->nullable();
            $table->string('cohort', 32)->nullable();
            $table->string('day_type_bucket', 24)->nullable();
            $table->bigInteger('sales_cents')->default(0);
            $table->bigInteger('gp_cents')->default(0);
            $table->unsignedBigInteger('txns')->default(0);
            $table->unsignedBigInteger('success_qty')->default(0);
            $table->bigInteger('sales_same_dow_prev_wk')->default(0);
            $table->bigInteger('delta_abs')->default(0);
            $table->decimal('delta_pct', 8, 2)->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->unique(['date', 'customer_id'], 'uq_fsd_date_customer');
            $table->index(['customer_id', 'date'], 'idx_fsd_customer_date');
            $table->index(['date', 'cohort'], 'idx_fsd_date_cohort');
            $table->index(['date', 'day_type_bucket'], 'idx_fsd_date_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_site_daily');
    }
};
