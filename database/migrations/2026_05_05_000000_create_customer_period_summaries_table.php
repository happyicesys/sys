<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_period_summaries
 *
 * Pre-aggregated, one-row-per-(customer, year_month) snapshot used by the
 * Customer Management > Summary page.
 *
 * Computed nightly at 01:00 from gp_metrics + customers (current contract
 * details) so the Summary page never has to scan vend_transactions live.
 *
 * Sign convention for location_fees_cents:
 *   positive = expense (we pay the location)
 *   negative = income  (location pays us, e.g. Subsidized Plan)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_period_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('operator_id')->nullable();

            // YYYY-MM-01 anchor for the month this row represents
            $table->date('year_month');

            // Inclusive period bounds. For a finalised past month these are the
            // first/last day of the month. For the in-progress current month the
            // period_end is "as_of_date" (yesterday) since the month isn't done yet.
            $table->date('period_start');
            $table->date('period_end');

            // True when this row represents the in-progress current month
            // (period_end < last day of the month). Lets the UI badge it.
            $table->boolean('is_current_month')->default(false);

            // The last calendar date covered by this aggregation. Equal to
            // period_end for past months; equal to "yesterday" for current month.
            $table->date('as_of_date');

            // Numbers — all in minor currency units (cents) to match gp_metrics.
            // Sales = revenue (excl GST), per user direction.
            $table->bigInteger('sales_cents')->default(0);
            $table->bigInteger('gross_earning_cents')->default(0); // revenue - unit_cost
            $table->bigInteger('location_fees_cents')->default(0); // see sign convention above
            $table->bigInteger('location_earning_cents')->default(0); // gross_earning - location_fees
            $table->decimal('location_earning_rate', 8, 4)->default(0); // location_earning / sales (fraction, e.g. 0.2500 = 25%)

            $table->unsignedInteger('transaction_count')->default(0);
            $table->unsignedInteger('vend_count')->default(0); // distinct vends bound to this customer in the period

            // Snapshot of contract fields used to compute location_fees_cents
            // (usually the customer's current contract — see seeder/aggregator).
            $table->string('contract_commission_type', 8)->nullable();
            $table->decimal('contract_commission_value', 10, 2)->nullable();
            $table->decimal('contract_commission_value2', 10, 2)->nullable();
            $table->decimal('contract_ps_term', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['customer_id', 'year_month'], 'cps_customer_yearmonth_unique');
            $table->index(['year_month'], 'cps_yearmonth_idx');
            $table->index(['customer_id', 'period_start'], 'cps_customer_periodstart_idx');
            $table->index(['operator_id', 'year_month'], 'cps_operator_yearmonth_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_period_summaries');
    }
};
