<?php

namespace App\Jobs\Vend;

use App\Models\Customer;
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

    public $tries = 1;
    public $timeout = 5;

    protected $model;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->model instanceof Vend) {
            $vend = $this->model;
        } else if($this->model instanceof Customer){
            $vend = $this->model->vend;
        } else {
            return;
        }

        if($vend) {
            // $totalsJson = [
            //     'today_amount' => $customer->daysVendTransactions(0,0)->sum('amount'),
            //     'today_count' => $customer->daysVendTransactions(0,0)->count(),
            //     'yesterday_amount' => $customer->daysVendTransactions(1,1)->sum('amount'),
            //     'yesterday_count' => $customer->daysVendTransactions(1,1)->count(),
            //     'seven_days_amount' => $customer->daysVendTransactions(6,0)->sum('amount'),
            //     'seven_days_count' => $customer->daysVendTransactions(6,0)->count(),
            //     'thirty_days_amount' => $customer->daysVendTransactions(29,0)->sum('amount'),
            //     'thirty_days_count' => $customer->daysVendTransactions(29,0)->count(),
            //     'thirty_days_revenue' => $customer->daysVendTransactions(29,0)->sum('revenue'),
            //     'thirty_days_gross_profit' => $customer->daysVendTransactions(29,0)->sum('gross_profit'),
            //     'vend_records_amount_latest' => $customer->lifetimeVendRecords->sum('total_amount'),
            //     'vend_records_amount_average_day' => $customer->lifetimeVendRecords->sum('total_amount')/ (Carbon::parse($customer->begin_date)->diffInDays(Carbon::parse($customer->termination_date ?? Carbon::now())) ?: 1),
            //     'vend_records_thirty_days_amount' => $customer->daysVendRecords(29,0)->sum('total_amount'),
            //     'vend_records_thirty_days_amount_average' =>
            //         $customer->daysVendRecords(29,0)->sum('total_amount')/
            //         (
            //             Carbon::parse($customer->begin_date)->diffInDays(Carbon::now()) < 30 ?
            //             (Carbon::parse($customer->begin_date)->diffInDays(Carbon::now()) == 0 ? 1 : Carbon::parse($customer->begin_date)->diffInDays(Carbon::now())) :
            //             30
            //         ),
            //     ];

                $vend->customer->update([
                    'totals_json' => [
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
                        'vend_records_thirty_days_amount' => $vend->vendRecordsThirtyDays->sum('total_amount'),
                        'vend_records_thirty_days_amount_average' =>
                            $vend->vendRecordsThirtyDays->sum('total_amount')/
                            (
                                Carbon::parse($vend->begin_date)->diffInDays(Carbon::now()) < 30 ?
                                (Carbon::parse($vend->begin_date)->diffInDays(Carbon::now()) == 0 ? 1 : Carbon::parse($vend->begin_date)->diffInDays(Carbon::now())) :
                                30
                            ),
                    ]
                ]);

            // $customer->update(['totals_json' => $totalsJson]);
        }


    }
}
