<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actual payment date for the Paid action on Customer Summary rows.
 *
 * paid_at remains the audit timestamp (WHEN the Paid button was clicked);
 * paid_date is the business-level payment date entered by the user in the
 * Paid confirmation popup. Left empty in the popup → defaults to today.
 * Cleared together with paid_at when the row is marked Unpaid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'paid_date')) {
                $table->date('paid_date')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('customer_period_summaries', 'paid_date')) {
                $table->dropColumn('paid_date');
            }
        });
    }
};
