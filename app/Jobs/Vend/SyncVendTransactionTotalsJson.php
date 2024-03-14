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
            $customer = $this->model->customer;
        } else if($this->model instanceof Customer){
            $customer = $this->model;
        } else {
            return;
        }

        if($customer) {
            $timezone = $customer->operator ? $customer->operator->timezone : 'Asia/Singapore';
            $today = Carbon::today()->setTimezone($timezone);

            // Calculate the begin and termination dates of the latest binding
            $latestBindingBeginDate = Carbon::parse($customer->begin_date)->setTimezone($timezone);
            $latestBindingTerminationDate = Carbon::parse($customer->termination_date)->setTimezone($timezone);

            // Calculate the date ranges for different periods
            $todayTransactions = $customer->daysVendTransactions(0, 0);
            $yesterdayTransactions = $customer->daysVendTransactions(1, 1);
            $sevenDaysTransactions = $customer->daysVendTransactions(6, 0);
            $thirtyDaysTransactions = $customer->daysVendTransactions(29, 0);
            $thirtyDaysVendRecords = $customer->daysVendRecords(29, 0);


            // Calculate the totals
            $totalsJson = [
                'today_amount' => $todayTransactions->sum('amount'),
                'today_count' => $todayTransactions->count(),
                'yesterday_amount' => $yesterdayTransactions->sum('amount'),
                'yesterday_count' => $yesterdayTransactions->count(),
                'seven_days_amount' => $sevenDaysTransactions->sum('amount'),
                'seven_days_count' => $sevenDaysTransactions->count(),
                'thirty_days_amount' => $thirtyDaysTransactions->sum('amount'),
                'thirty_days_count' => $thirtyDaysTransactions->count(),
                'thirty_days_revenue' => $thirtyDaysTransactions->sum('revenue'),
                'thirty_days_gross_profit' => $thirtyDaysTransactions->sum('gross_profit'),
                'vend_records_amount_latest' => $customer->lifetimeVendRecords->sum('total_amount'),
                'vend_records_amount_average_day' => $customer->lifetimeVendRecords->sum('total_amount') / max($latestBindingBeginDate->diffInDays($latestBindingTerminationDate) ?: 1, 1),
                'vend_records_thirty_days_amount' => $thirtyDaysVendRecords->sum('total_amount'),
                'vend_records_thirty_days_amount_average' => $thirtyDaysVendRecords->sum('total_amount') / max($latestBindingBeginDate->diffInDays($today) < 30 ? ($latestBindingBeginDate->diffInDays($today) == 0 ? 1 : $latestBindingBeginDate->diffInDays($today)) : 30, 1),
            ];

            $customer->update(['totals_json' => $totalsJson]);
        }


    }
}
