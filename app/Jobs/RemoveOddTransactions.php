<?php

namespace App\Jobs;

use App\Models\Operator;
use App\Models\PaymentMethod;
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
        $retainPaymentMethod = PaymentMethod::where(function ($query) {
            $query->where('code', 10)
                ->orWhere('code', 11);
        })->pluck('id')->toArray();

        $retainOperator = Operator::where('code', 'TEST')->pluck('id')->toArray();

        VendTransaction::query()
            ->where(function ($query) {
                $query->where('amount', '=', 20000)
                    ->orWhere('amount', '=', 0)
                    ->orWhere('amount', '=', 10);
            })
            ->whereNotIn('payment_method_id', $retainPaymentMethod)
            ->whereNotIn('operator_id', $retainOperator)
            ->where('vend_transactions.created_at', '>=', Carbon::parse($this->from)->startOfDay())
            ->where('vend_transactions.created_at', '<=', Carbon::parse($this->to)->endOfDay())
            ->chunkById(500, function ($transactions) {
                $transactionIds = $transactions->pluck('id')->toArray();

                if (!empty($transactionIds)) {
                    \App\Models\VendChannelErrorLog::whereIn('vend_transaction_id', $transactionIds)->delete();
                    \App\Models\VendTransactionItem::whereIn('vend_transaction_id', $transactionIds)->delete();
                    VendTransaction::whereIn('id', $transactionIds)->delete();
                }
            });
    }
}
