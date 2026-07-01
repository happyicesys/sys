<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-operator-per-day rollup of the transactions-index headline aggregates.
 *
 * Stores the RAW additive fields the live totals query produces (counts, cent
 * amounts, qty parts, item counts, and the unreported-gateway major-unit sum) —
 * NOT the derived rates or the unreported-merged amounts. The read path (when
 * enabled) sums these across past days + today-live, then runs the SAME
 * derivation (rates, total_qty, unreported merge) on the combined raw totals, so
 * the output is identical to computing live over the whole range.
 *
 * See docs/perf/transactions_daily_rollup_spec.md. Populated by
 * transactions:rollup-daily; parity checked by transactions:rollup-verify. No
 * read path is wired until the verify harness shows an empty diff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vend_transaction_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id');
            $table->date('date');

            // Raw counts (additive)
            $table->unsignedBigInteger('total_count')->default(0);           // COUNT(*)  (== total_transaction_count)
            $table->unsignedBigInteger('success_count')->default(0);         // (== success_payment_count)
            $table->unsignedBigInteger('cash_count')->default(0);
            $table->unsignedBigInteger('cashless_terminal_count')->default(0);
            $table->unsignedBigInteger('qr_payment_count')->default(0);
            $table->unsignedBigInteger('delivery_platform_success_count')->default(0);
            $table->unsignedBigInteger('single_qty')->default(0);
            $table->unsignedBigInteger('success_single_qty')->default(0);
            $table->unsignedBigInteger('multiple_count_delivery_platform')->default(0);
            $table->unsignedBigInteger('multiple_count_machine')->default(0);

            // Raw amounts, minor units / cents (additive) — pre unreported merge
            $table->bigInteger('success_amount')->default(0);
            $table->bigInteger('cash_amount')->default(0);
            $table->bigInteger('cashless_terminal_amount')->default(0);
            $table->bigInteger('qr_payment_amount')->default(0);
            $table->bigInteger('delivery_platform_success_amount')->default(0);

            // Item-level parts (from the vend_transaction_items join, additive)
            $table->unsignedBigInteger('total_items')->default(0);
            $table->unsignedBigInteger('success_items')->default(0);

            // Unreported dispensed gateway revenue — MAJOR units, UNROUNDED sum,
            // so the read path can round(total * 10^exponent) ONCE (matching the
            // live code) instead of per-day (which would drift on rounding).
            $table->decimal('unreported_gateway_amount_major', 18, 4)->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'date'], 'uq_vtds_operator_date');
            $table->index('date', 'idx_vtds_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vend_transaction_daily_summaries');
    }
};
