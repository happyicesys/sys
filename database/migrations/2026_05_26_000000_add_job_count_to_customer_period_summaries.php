<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add job_count to customer_period_summaries.
 *
 * "# of Job" on the Customer Summary page — how many ops job items serviced
 * the customer in that month. Pre-aggregated nightly alongside the other
 * monthly numbers (see CustomerSummaryAggregator::persistMonth) so the page
 * never has to scan ops_job_items live. Counts ops_job_items whose parent
 * ops_jobs.status is Stock In (3) / Verified (4), placed in the month by
 * ops_jobs.date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'job_count')) {
                $table->unsignedInteger('job_count')->default(0)->after('vend_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'job_count')) {
                $table->dropColumn('job_count');
            }
        });
    }
};
