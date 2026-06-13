<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit boolean state columns for Customer Summary rows.
 *
 * is_locked / is_paid mirror locked_at / paid_at (which remain the audit
 * source of truth). The booleans give reporting/queries a direct flag and
 * surface as dedicated Yes/No columns in the Summary Excel export.
 *
 * Kept in sync at every writer:
 *   - CustomerController lock / unlock / markPaid / markUnpaid
 *   - LockCustomerSummaryHistorical (bulk historical lock command)
 *
 * Existing rows are backfilled by BackfillSummaryLockedPaidFlagsSeeder
 * (run once after migrating):
 *   php artisan db:seed --class=BackfillSummaryLockedPaidFlagsSeeder
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('locked_by');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('paid_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            foreach (['is_locked', 'is_paid'] as $col) {
                if (Schema::hasColumn('customer_period_summaries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
