<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The manager-approval step was removed from the refund workflow — verifying
     * a claim now moves it straight to the payout-ready "approved" gate. This:
     *   1. Folds any ticket parked in the intermediate "verified" /
     *      "pending_approval" states into "approved" so none get stranded
     *      (their old forward buttons are gone).
     *   2. Drops the now-unused manager-approval columns.
     * Idempotent on the data side; safe to re-run.
     */
    public function up(): void
    {
        DB::table('refund_tickets')
            ->whereIn('status', ['verified', 'pending_approval'])
            ->update(['status' => 'approved', 'updated_at' => now()]);

        Schema::table('refund_tickets', function (Blueprint $table) {
            foreach (['submitted_for_approval_by', 'submitted_for_approval_at', 'manager_approved_by', 'manager_approved_at'] as $col) {
                if (Schema::hasColumn('refund_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // Re-add the columns (status normalisation is intentionally one-way).
        Schema::table('refund_tickets', function (Blueprint $table) {
            $table->bigInteger('submitted_for_approval_by')->nullable();
            $table->timestamp('submitted_for_approval_at')->nullable();
            $table->bigInteger('manager_approved_by')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
        });
    }
};
