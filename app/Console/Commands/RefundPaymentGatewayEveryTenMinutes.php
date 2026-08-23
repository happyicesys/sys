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

        $now = now();

        $paymentGatewayLogs = PaymentGatewayLog::query()
            ->with('paymentGateway')
            ->where('status', PaymentGatewayLog::STATUS_APPROVE)
            ->where('approved_at', '>=', $setting->payment_gateway_log_refund_scanned_at)
            ->where('approved_at', '<=', $now->subMinutes(PaymentGatewayLog::REFUND_PENDING_MINUTES))
            ->where('is_dispensed', false)
            ->get();

        $setting->payment_gateway_log_refund_scanned_at = $now;
        $setting->save();

        foreach ($paymentGatewayLogs as $paymentGatewayLog) {
            switch ($paymentGatewayLog->paymentGateway->name) {
                case 'omise':
                    RefundOmiseJob::dispatch($paymentGatewayLog->order_id, \App\Support\AutoRefundSource::OMISE_NO_DISPENSE)->onQueue('default');
                    break;
                default:
                    break;
            }
        }
    }
}
