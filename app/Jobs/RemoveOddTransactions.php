<?php

namespace App\Jobs;

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
        VendTransaction::query()
            ->where(function($query) {
                $query->where('amount', '=', 20000)
                    ->orWhere('amount', '=', 0)
                    ->orWhere('amount', '=', 10);
            })
            ->where('vend_transactions.created_at', '>=', Carbon::parse($this->from)->startOfDay())
            ->where('vend_transactions.created_at', '<=', Carbon::parse($this->to)->endOfDay())
            ->delete();
    }
}
