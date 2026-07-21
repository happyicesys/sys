<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nightly materialized fact: settled sales bucketed by HOUR of day, per site.
 *
 * Grain = date × hour(0-23) × customer_id (+ denormalized location_type_id,
 * cohort). THE KEY MISSING GRAIN — nothing hourly exists in gp_metrics today;
 * this powers every hourly chart in the morning report.
 *
 * Built by App\Services\Reporting\DailyFactsBuilder::buildSalesHourly() from
 * settled vend_transactions (settlement_status = SETTLED, amount > 0), applying
 * the platform's headline success predicate (error code IN (0,6) / NULL /
 * is_multiple) so daily sums reconcile with gp_metrics. Idempotent: rebuilt
 * per-day via delete-by-date then insert.
 *
 * Cents throughout. success_qty = SUM(vend_transactions.success_qty).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_sales_hourly', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour');                // 0-23
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('location_type_id')->nullable();
            $table->string('cohort', 32)->nullable();
            $table->unsignedBigInteger('txns')->default(0);         // settled transactions in the hour
            $table->unsignedBigInteger('success_txns')->default(0); // of which counted as a sale
            $table->unsignedBigInteger('success_qty')->default(0);
            $table->bigInteger('sales_cents')->default(0);          // success amount, cents
            $table->timestamp('computed_at')->nullable();

            $table->unique(['date', 'hour', 'customer_id'], 'uq_fsh_date_hour_customer');
            $table->index(['customer_id', 'date'], 'idx_fsh_customer_date');
            $table->index(['date', 'hour'], 'idx_fsh_date_hour');
            $table->index(['cohort', 'date'], 'idx_fsh_cohort_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_sales_hourly');
    }
};
