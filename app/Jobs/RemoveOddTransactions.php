<?php

namespace App\Jobs;

use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveOddTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $from;

    protected $to;

    /**
     * Create a new job instance.
     */
    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! filter_var(env('DELETE_ODD_TRANSACTIONS', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        // Amounts / payment-method codes / operator code live on VendTransaction so
        // PaymentGatewayLog::scopeUnreportedDispensed can exclude exactly the rows
        // this job deletes (a swept transaction otherwise leaves its gateway log
        // looking "never reported" and its amount gets re-added to Total Sales).
        $retainPaymentMethod = PaymentMethod::whereIn('code', VendTransaction::ODD_TRANSACTION_RETAIN_PAYMENT_METHOD_CODES)
            ->pluck('id')
            ->toArray();

        $testOperator = Operator::where('code', VendTransaction::ODD_TRANSACTION_OPERATOR_CODE)->pluck('id')->toArray();

        $retainVendIds = Vend::whereIn('code', VendTransaction::ODD_TRANSACTION_RETAIN_VEND_CODES)
            ->pluck('id')
            ->toArray();

        VendTransaction::query()
            ->whereNotIn('payment_method_id', $retainPaymentMethod)
            ->when(! empty($retainVendIds), fn ($q) => $q->whereNotIn('vend_transactions.vend_id', $retainVendIds))
            ->where(function ($query) use ($testOperator) {
                $query->whereIn('amount', VendTransaction::ODD_TRANSACTION_AMOUNTS)
                    ->orWhereIn('operator_id', $testOperator);
            })
            ->where('vend_transactions.created_at', '>=', Carbon::parse($this->from)->startOfDay())
            ->where('vend_transactions.created_at', '<=', Carbon::parse($this->to)->endOfDay())
            ->chunkById(500, function ($transactions) {
                $transactionIds = $transactions->pluck('id')->toArray();

                if (! empty($transactionIds)) {
                    \App\Models\VendChannelErrorLog::whereIn('vend_transaction_id', $transactionIds)->delete();
                    \App\Models\VendTransactionItem::whereIn('vend_transaction_id', $transactionIds)->delete();
                    VendTransaction::whereIn('id', $transactionIds)->delete();
                }
            });

        if (! empty($testOperator)) {
            \App\Models\VendRecord::whereIn('operator_id', $testOperator)
                ->when(! empty($retainVendIds), fn ($q) => $q->whereNotIn('vend_id', $retainVendIds))
                ->whereBetween('date', [
                    Carbon::parse($this->from)->startOfDay(),
                    Carbon::parse($this->to)->endOfDay(),
                ])
                ->delete();
        }
    }
}
