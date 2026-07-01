<?php

namespace App\Console\Commands;

use App\Models\PaymentGatewayLog;
use App\Models\VendTransaction;
use App\Models\VendTransactionDailySummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Parity harness for the transactions daily rollup.
 *
 * Compares, over a date range (all operators, no code/customer filter — the only
 * case the rollup ever serves), the RAW additive fields computed LIVE over the
 * whole range against the SUM of the stored per-day summary rows. Because the
 * headline's rates / total_qty / unreported-merge are deterministic functions of
 * these raw fields, raw-field parity guarantees output parity.
 *
 * MUST show an empty diff for a representative range (incl. days with refunds,
 * delivery orders, multi-item and unreported-gateway rows) before the read path
 * is switched to the rollup.
 *
 *   php artisan transactions:rollup-verify --from=2026-05-01 --to=2026-06-30
 */
class VerifyTransactionDailySummaries extends Command
{
    protected $signature = 'transactions:rollup-verify {--from=} {--to=}';

    protected $description = 'Assert the transaction daily rollup matches the live query, field for field.';

    public function handle(): int
    {
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::today()->subDay()->endOfDay();
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::today()->subDays(14)->startOfDay();

        $testingVendIds = DB::table('vends')->where('is_testing', true)->pluck('id')->all();
        $fromStr = $from->toDateTimeString();
        $toStr = $to->toDateTimeString();

        $this->info("Verifying {$from->toDateString()} .. {$to->toDateString()} (all operators)");

        // --- LIVE raw over the whole range --------------------------------------
        $liveMain = VendTransaction::query()
            ->withoutGlobalScopes()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            // Match the rollup / live page: null-operator rows are never counted.
            ->whereNotNull('vend_transactions.operator_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$fromStr, $toStr])
            ->select(VendTransaction::salesRawTotalsSelect())
            ->first();

        $liveItems = VendTransaction::query()
            ->withoutGlobalScopes()
            ->where('is_multiple', true)
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            ->whereNotNull('vend_transactions.operator_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$fromStr, $toStr])
            ->leftJoin('vend_transaction_items', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
            ->select(VendTransaction::salesItemTotalsSelect())
            ->first();

        $liveUnreported = PaymentGatewayLog::query()
            // INNER JOIN operator_payment_gateways to mirror the rollup (which
            // attributes each log to an operator via this link) and the live
            // controller's whereHas('operatorPaymentGateway'). A log with no
            // gateway link is excluded by both, so it must be excluded here too.
            ->join('operator_payment_gateways', 'payment_gateway_logs.operator_payment_gateway_id', '=', 'operator_payment_gateways.id')
            ->where('payment_gateway_logs.status', PaymentGatewayLog::STATUS_APPROVE)
            ->where('payment_gateway_logs.is_dispensed', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('vend_transactions')
                    ->whereColumn('vend_transactions.payment_gateway_log_id', 'payment_gateway_logs.id');
            })
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('payment_gateway_logs.vend_id', $testingVendIds))
            ->where('payment_gateway_logs.approved_at', '>=', PaymentGatewayLog::UNREPORTED_GATEWAY_CUTOFF)
            ->whereBetween('payment_gateway_logs.approved_at', [$fromStr, $toStr])
            ->sum('payment_gateway_logs.amount');

        $live = [
            'total_count'                      => (int) $liveMain->total_count,
            'success_count'                    => (int) $liveMain->success_count,
            'cash_count'                       => (int) $liveMain->cash_count,
            'cashless_terminal_count'          => (int) $liveMain->cashless_terminal_count,
            'qr_payment_count'                 => (int) $liveMain->qr_payment_count,
            'delivery_platform_success_count'  => (int) $liveMain->delivery_platform_success_count,
            'single_qty'                       => (int) $liveMain->single_qty,
            'success_single_qty'               => (int) $liveMain->success_single_qty,
            'multiple_count_delivery_platform' => (int) $liveMain->multiple_count_delivery_platform,
            'multiple_count_machine'           => (int) $liveMain->multiple_count_machine,
            'success_amount'                   => (int) round((float) $liveMain->success_amount),
            'cash_amount'                      => (int) round((float) $liveMain->cash_amount),
            'cashless_terminal_amount'         => (int) round((float) $liveMain->cashless_terminal_amount),
            'qr_payment_amount'                => (int) round((float) $liveMain->qr_payment_amount),
            'delivery_platform_success_amount' => (int) round((float) $liveMain->delivery_platform_success_amount),
            'total_items'                      => (int) $liveItems->total_items,
            'success_items'                    => (int) $liveItems->success_items,
            'unreported_gateway_amount_major'  => round((float) $liveUnreported, 4),
        ];

        // --- ROLLUP: sum stored summary rows over the range ---------------------
        $sumSelect = array_map(
            fn($c) => DB::raw("COALESCE(SUM({$c}), 0) as {$c}"),
            VendTransactionDailySummary::RAW_SUM_COLUMNS
        );
        $rollupRow = VendTransactionDailySummary::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select($sumSelect)
            ->first();

        $rollup = [];
        foreach (VendTransactionDailySummary::RAW_SUM_COLUMNS as $c) {
            $rollup[$c] = $c === 'unreported_gateway_amount_major'
                ? round((float) ($rollupRow->{$c} ?? 0), 4)
                : (int) ($rollupRow->{$c} ?? 0);
        }

        // --- Compare -------------------------------------------------------------
        $rows = [];
        $mismatches = 0;
        foreach ($live as $field => $liveVal) {
            $rollupVal = $rollup[$field];
            $ok = $field === 'unreported_gateway_amount_major'
                ? abs($liveVal - $rollupVal) < 0.005
                : $liveVal === $rollupVal;
            if (!$ok) {
                $mismatches++;
            }
            $rows[] = [$field, $liveVal, $rollupVal, $ok ? 'OK' : 'MISMATCH'];
        }

        $this->table(['field', 'live', 'rollup', 'status'], $rows);

        if ($mismatches > 0) {
            $this->error("{$mismatches} field(s) MISMATCH — do NOT enable the rollup read path.");
            return self::FAILURE;
        }

        $this->info('All fields match. Rollup is faithful for this range.');
        return self::SUCCESS;
    }
}
