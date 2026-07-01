<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-operator-per-day rollup of the transactions-index headline aggregates.
 * See the create_vend_transaction_daily_summaries migration and
 * docs/perf/transactions_daily_rollup_spec.md.
 */
class VendTransactionDailySummary extends Model
{
    protected $table = 'vend_transaction_daily_summaries';

    protected $fillable = [
        'operator_id',
        'date',
        'total_count',
        'success_count',
        'cash_count',
        'cashless_terminal_count',
        'qr_payment_count',
        'delivery_platform_success_count',
        'single_qty',
        'success_single_qty',
        'multiple_count_delivery_platform',
        'multiple_count_machine',
        'success_amount',
        'cash_amount',
        'cashless_terminal_amount',
        'qr_payment_amount',
        'delivery_platform_success_amount',
        'total_items',
        'success_items',
        'unreported_gateway_amount_major',
        'computed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'computed_at' => 'datetime',
    ];

    /**
     * The raw additive columns (everything except identity/rate/derived).
     * The read path sums these across days; rates/qty/unreported-merge are
     * derived afterwards, matching the live query.
     */
    public const RAW_SUM_COLUMNS = [
        'total_count',
        'success_count',
        'cash_count',
        'cashless_terminal_count',
        'qr_payment_count',
        'delivery_platform_success_count',
        'single_qty',
        'success_single_qty',
        'multiple_count_delivery_platform',
        'multiple_count_machine',
        'success_amount',
        'cash_amount',
        'cashless_terminal_amount',
        'qr_payment_amount',
        'delivery_platform_success_amount',
        'total_items',
        'success_items',
        'unreported_gateway_amount_major',
    ];
}
