<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Waived" state for the Mark as Paid / Waived action on Customer Summary rows.
 *
 * is_waived flags that the location fee for this (locked) period was waived
 * rather than actually paid. waived_remarks carries the mandatory reason the
 * user enters when ticking Waived in the Paid popup.
 *
 * Both ride alongside the existing paid_* columns: a waived row is still
 * recorded through the normal Paid flow (paid_at / paid_date / paid_by stay
 * stamped) and is cleared together with them when the row is marked Unpaid.
 * Waiving does NOT change any money figures — aggregate totals (location fee,
 * vend earning, etc.) are unaffected; this is a status flag only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'is_waived')) {
                $table->boolean('is_waived')->default(false)->after('is_paid');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'waived_remarks')) {
                $table->string('waived_remarks', 1000)->nullable()->after('is_waived');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            foreach (['is_waived', 'waived_remarks'] as $col) {
                if (Schema::hasColumn('customer_period_summaries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
