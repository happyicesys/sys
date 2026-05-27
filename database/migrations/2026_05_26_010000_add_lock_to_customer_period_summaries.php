<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Action-triggered locking for Customer Summary rows.
 *
 * Previously a month "locked" implicitly the moment it finished (the resource
 * froze completed months and only rendered the current month live). That is
 * now flipped: every month renders LIVE (contract details + derived figures
 * re-computed from the customer's current contract) until a user explicitly
 * locks the row. Locking snapshots that moment's figures + contract details
 * into the stored columns and stamps locked_at / locked_by.
 *
 *   locked_at NULL  → row is live (re-derived on read)
 *   locked_at set   → row is frozen to its stored snapshot
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('job_count');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            foreach (['locked_at', 'locked_by'] as $col) {
                if (Schema::hasColumn('customer_period_summaries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
