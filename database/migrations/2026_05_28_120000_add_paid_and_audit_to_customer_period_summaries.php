<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid / Unpaid state + reverse-action audit timestamps for Customer Summary rows.
 *
 *   paid_at NULL          → row is unpaid (default)
 *   paid_at set           → row is paid (only meaningful for already-locked rows)
 *
 * last_unpaid_at / last_unlocked_at record WHEN and WHO last reversed those
 * actions, mirroring locked_at / locked_by. The reverse-action stamps are
 * kept around (rather than thrown away) so a row's tooltip can show its full
 * Lock → Paid → Unpaid → Unlock cycle without needing a separate audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_period_summaries', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('locked_by');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'last_unpaid_at')) {
                $table->timestamp('last_unpaid_at')->nullable()->after('paid_by');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'last_unpaid_by')) {
                $table->unsignedBigInteger('last_unpaid_by')->nullable()->after('last_unpaid_at');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'last_unlocked_at')) {
                $table->timestamp('last_unlocked_at')->nullable()->after('last_unpaid_by');
            }
            if (!Schema::hasColumn('customer_period_summaries', 'last_unlocked_by')) {
                $table->unsignedBigInteger('last_unlocked_by')->nullable()->after('last_unlocked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            foreach ([
                'paid_at', 'paid_by',
                'last_unpaid_at', 'last_unpaid_by',
                'last_unlocked_at', 'last_unlocked_by',
            ] as $col) {
                if (Schema::hasColumn('customer_period_summaries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
