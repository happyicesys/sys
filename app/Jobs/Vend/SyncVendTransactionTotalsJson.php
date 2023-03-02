<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVendTransactionTotalsJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vend;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Vend $vend)
    {
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vend = $this->vend;

        $vend->update([
            'vend_transaction_totals_json' => [
                'today_amount' => $vend->vendTodayTransactions->sum('amount'),
                'today_count' => $vend->vendTodayTransactions->count(),
                'yesterday_amount' => $vend->vendYesterdayTransactions->sum('amount'),
                'yesterday_count' => $vend->vendYesterdayTransactions->count(),
                'seven_days_amount' => $vend->vendSevenDaysTransactions->sum('amount'),
                'seven_days_count' => $vend->vendSevenDaysTransactions->count(),
                'thirty_days_amount' => $vend->vendThirtyDaysTransactions->sum('amount'),
                'thirty_days_count' => $vend->vendThirtyDaysTransactions->count(),
            ]
        ]);
    }
}
