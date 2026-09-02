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
     * used for the regular transaction rows. Rows are authored against the chunk
     * export's 31-column layout and trimmed/padded to the caller's own header
     * width via $columnCount (see putGatewayRow).
     *
     * "Access Product(s)": these rows have NO product - payment_gateway_logs has
     * no product_id, because the machine never told us what came out. There is
     * nothing to attribute, so a product-restricted export omits them entirely
     * rather than crediting a partner with revenue that provably is not theirs.
     * One note row is emitted in their place so the missing amount is visible
     * instead of being a silent reconciliation gap.
     *
     * @param  array<int, int>|null  $allowedProductIds  null = unrestricted
     * @param  string|null  $transactionAccessFrom  'Y-m-d' cut-off, null = unrestricted
     * @param  int  $columnCount  width of the CALLER's header. The rows below are
     *                            built for the chunk export's 32-column layout;
     *                            the single-file export's header is 29 wide (it
     *                            has no Dispense Attempted?/Refund Request/Refund
     *                            Status columns), and without this the appended
     *                            rows spilled three cells past its header.
     */
    protected function appendUnreportedGatewayRows($stream, Request $request, ?User $user = null, ?array $allowedProductIds = null, ?string $transactionAccessFrom = null, int $columnCount = 32): void
    {
        if ($allowedProductIds !== null) {
            $this->putGatewayRow($stream, array_merge(
                ['', '', '', '', '', '', '', '', ''],
                ['Unreported Gateway Revenue (omitted - product-restricted view)'],
                array_fill(0, 22, '')
            ), $columnCount);

            return;
        }

        $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn () => DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn ($v) => (int) $v)->all()
        );

        // Keep the gateway rows scoped to the same machines as the CSV's
        // transaction rows when the user is restricted to specific vends.
        $userVendIds = ($user && $user->vends()->exists())
            ? $user->vends->pluck('id')
            : null;

        PaymentGatewayLog::query()
            ->with(['vend:id,code', 'operatorPaymentGateway.operator:id,code'])
            ->unreportedDispensed($request, $testingVendIds)
            ->when($userVendIds !== null, fn ($q) => $q->whereIn('payment_gateway_logs.vend_id', $userVendIds))
            // "Transaction Access From". These rows are gateway money with no
            // vend_transaction behind them, so they carry their own timestamp -
            // approved_at, not transaction_datetime. Without this a restricted
            // viewer's CSV ends with a block of pre-cut-off revenue.
            ->when($transactionAccessFrom !== null, fn ($q) => $q->where('payment_gateway_logs.approved_at', '>=', $transactionAccessFrom))
            ->orderBy('payment_gateway_logs.approved_at')
            ->chunk(500, function ($logs) use ($stream, $columnCount) {
                foreach ($logs as $log) {
                    $orderIdCell = $log->order_id !== null && $log->order_id !== ''
                        ? '="'.$log->order_id.'"'
                        : '';

                    $amount = (float) $log->amount;

                    $this->putGatewayRow($stream, [
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
                        // Paid (gateway approved) but the machine never reported — the two
                        // facts the split Payment/Dispense columns carry (App\Support\SaleStatus).
                        // Product Name above stays the row's "Unreported Gateway Revenue" marker.
                        \App\Support\SaleStatus::PAID,                                   // Payment Status
                        \App\Support\SaleStatus::NO_REPORT,                              // Dispense Status
                        '',                                                              // Is Refunded
                        'No',                                                            // Is Multiple
                        1,                                                               // Multiple Qty
                        $log->txn_src ?? '',                                             // TXN Source
                        '',                                                              // Member ID
                        '',                                                              // HID Card ID
                        '',                                                              // Voucher
                        '',                                                              // Campaign Labels
                        'Yes',                                                           // Dispense Attempted? (unreportedDispensed => is_dispensed=true)
                        '',                                                              // Refund Request
                        '',                                                              // Refund Status
                    ], $columnCount);
                }
            });
    }

    /**
     * Write one appended row trimmed (or padded) to the caller's header width,
     * so an export whose header omits the trailing columns does not get rows
     * that spill past it.
     */
    private function putGatewayRow($stream, array $row, int $columnCount): void
    {
        if (count($row) > $columnCount) {
            $row = array_slice($row, 0, $columnCount);
        } elseif (count($row) < $columnCount) {
            $row = array_pad($row, $columnCount, '');
        }

        fputcsv($stream, $row);
    }
}
