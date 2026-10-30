<?php

namespace App\Console\Commands;

use App\Jobs\RefundOmiseJob;
use App\Models\PaymentGatewayLog;
use App\Models\Setting;
use Illuminate\Console\Command;

class RefundPaymentGatewayEveryTenMinutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refund:payment-gateway-every-ten-minutes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refund Payment Gateway Every Ten Minutes When No Confirm from Vend';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $setting = Setting::first();

        // Each run scans [last cutoff, now − REFUND_PENDING_MINUTES] and saves the new cutoff.
        $cutoff = now()->subMinutes(PaymentGatewayLog::REFUND_PENDING_MINUTES);

        // Never scanned (lsh had NULL from 2025 to 2026-10-30): a NULL bound made the query throw
        // every run, so lsh never auto-refunded. Start a day back instead.
        $scannedFrom = $setting->payment_gateway_log_refund_scanned_at ?? $cutoff->copy()->subDay();

        $paymentGatewayLogs = PaymentGatewayLog::query()
            ->with('paymentGateway')
            ->where('status', PaymentGatewayLog::STATUS_APPROVE)
            ->where('approved_at', '>=', $scannedFrom)
            ->where('approved_at', '<=', $cutoff)
            ->where('is_dispensed', false)
            ->get();

        $setting->payment_gateway_log_refund_scanned_at = $cutoff;
        $setting->save();

        foreach ($paymentGatewayLogs as $paymentGatewayLog) {
            // Shared refundability definition (see PaymentGateway::supportsApiRefund)
            // — Omise is the only API-refundable gateway today; add a dispatch
            // branch when Fiuu/Midtrans refunds land.
            if ($paymentGatewayLog->paymentGateway?->supportsApiRefund()) {
                RefundOmiseJob::dispatch($paymentGatewayLog->order_id, \App\Support\AutoRefundSource::OMISE_NO_DISPENSE)->onQueue('default');
            }
        }
    }
}
