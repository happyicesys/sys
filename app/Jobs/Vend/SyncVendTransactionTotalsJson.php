<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use Carbon\Carbon;
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
                'thirty_days_revenue' => $vend->vendThirtyDaysTransactions->sum(function($vendTransaction) {
                    return $vendTransaction->getRevenue();
                }),
                'thirty_days_gross_profit' => $vend->vendThirtyDaysTransactions->sum(function($vendTransaction) {
                    return $vendTransaction->getGrossProfit();
                }),
                'vend_records_amount_latest' => $vend->vendRecordsLatest->sum('total_amount'),
                'vend_records_amount_average_day' => $vend->vendRecordsLatest->sum('total_amount')/ (Carbon::parse($vend->begin_date)->diffInDays(Carbon::parse($vend->termination_date ?? Carbon::now())) ?: 1),
                'vend_records_thirty_days_amount' =>
                    $vend->vendRecordsThirtyDays->sum('total_amount')/
                    (
                        Carbon::parse($vend->begin_date)->diffInDays(Carbon::now()) < 30 ?
                        Carbon::parse($vend->begin_date)->diffInDays(Carbon::now()) :
                        30
                    ),
            ]
        ]);
    }
}
