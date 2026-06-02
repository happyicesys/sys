<?php

namespace App\Jobs\Concerns;

use App\Models\PaymentGatewayLog;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait AppendsUnreportedGatewayCsvRows
{
    /**
     * Append dispensed-but-unreported gateway revenue as one CSV row per payment.
     *
     * These PayNow/QR payments were approved and the item dispensed, but the
     * machine never reported the transaction back, so there is no vend_transaction
     * row to export. The dashboard "Total Sales" headline adds their amount; we
     * add matching rows here so the exported CSV total tallies with the dashboard
     * (and with accounting) from PaymentGatewayLog::UNREPORTED_GATEWAY_CUTOFF
     * onward.
     *
     * Amount is written in major units (e.g. 3.50) to match the `/100` convention
     * used for the regular transaction rows. Column order mirrors the CSV header
     * exactly (28 columns).
     */
    protected function appendUnreportedGatewayRows($stream, Request $request, ?User $user = null): void
    {
        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn() =>
            DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn($v) => (int) $v)->all()
        );

        // Keep the gateway rows scoped to the same machines as the CSV's
        // transaction rows when the user is restricted to specific vends.
        $userVendIds = ($user && $user->vends()->exists())
            ? $user->vends->pluck('id')
            : null;

        PaymentGatewayLog::query()
            ->with(['vend:id,code', 'operatorPaymentGateway.operator:id,code'])
            ->unreportedDispensed($request, $testingVendIds)
            ->when($userVendIds !== null, fn($q) => $q->whereIn('payment_gateway_logs.vend_id', $userVendIds))
            ->orderBy('payment_gateway_logs.approved_at')
            ->chunk(500, function ($logs) use ($stream) {
                foreach ($logs as $log) {
                    $orderIdCell = $log->order_id !== null && $log->order_id !== ''
                        ? '="' . $log->order_id . '"'
                        : '';

                    $amount = (float) $log->amount;

                    fputcsv($stream, [
                        $orderIdCell,                                                    // Order ID
                        $log->approved_at ? $log->approved_at->toDateTimeString() : '',  // Transaction Datetime
                        $log->vend->code ?? $log->vend_code ?? '',                       // Machine ID
                        '',                                                              // Machine Prefix
                        '',                                                              // Customer ID
                        '',                                                              // Customer Code
                        '',                                                              // Customer Name
                        '',                                                              // Channel
                        '',                                                              // Product Code
                        'Unreported Gateway Revenue',                                    // Product Name
                        '',                                                              // Price Type
                        $amount,                                                         // Amount (major units)
                        $amount,                                                         // Amount Breakdown
                        '',                                                              // Unit Cost
                        $log->method ?: 'Payment Gateway',                               // Payment Method
                        '',                                                              // Cashless Mfg
                        '',                                                              // Error Code
                        '',                                                              // Location Type
                        $log->operatorPaymentGateway->operator->code ?? '',              // Operator
                        'Unreported Gateway',                                            // Payment Status
                        '',                                                              // Is Refunded
                        'No',                                                            // Is Multiple
                        1,                                                               // Multiple Qty
                        $log->txn_src ?? '',                                             // TXN Source
                        '',                                                              // Member ID
                        '',                                                              // HID Card ID
                        '',                                                              // Voucher
                        '',                                                              // Campaign Labels
                    ]);
                }
            });
    }
}
