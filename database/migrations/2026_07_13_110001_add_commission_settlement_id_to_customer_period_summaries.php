<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership link: which site settlement a period-summary row was pushed into.
 * Nullable; retained after paid as the audit link. A row already Mark-Paid can't
 * be pushed, and a row in a settlement is excluded from the on-summary batch flows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_settlement_id')->nullable()->after('paid_by');
            $table->index('commission_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_period_summaries', function (Blueprint $table) {
            $table->dropIndex(['commission_settlement_id']);
            $table->dropColumn('commission_settlement_id');
        });
    }
};
