<?php

namespace App\Console\Commands;

use App\Models\PaymentGatewayLog;
use App\Jobs\RefundOmiseJob;
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
        $paymentGatewayLogs = PaymentGatewayLog::query()
            ->with('paymentGateway')
            ->where('status', PaymentGatewayLog::STATUS_APPROVE)
            ->where('created_at', '>=', '2025-04-01 00:00:00')
            ->where('created_at', '<=', now()->subMinutes(PaymentGatewayLog::REFUND_PENDING_MINUTES))
            ->where('is_dispensed', false)
            ->get();

        foreach($paymentGatewayLogs as $paymentGatewayLog) {
            switch($paymentGatewayLog->paymentGateway->name) {
                case 'Omise':
                    RefundOmiseJob::dispatch($paymentGatewayLog->order_id)->onQueue('default');
                    break;
                default:
                    break;
            }
        }
    }
}
