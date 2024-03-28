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
    public $timeout = 30;

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
            $customer = $this->model->customer;
        } else if($this->model instanceof Customer){
            $vend = $this->model->vend;
            $customer = $this->model;
        } else {
            return;
        }

        if($vend) {
            $vend->update([
                'vend_transaction_totals_json' => [
                    'today_amount' => $vend->daysVendTransactions(0,0)->sum('amount'),
                    'today_count' => $vend->daysVendTransactions(0,0)->count(),
                    'yesterday_amount' => (int)$vend->daysVendRecords(1,1)->sum('total_amount'),
                    'yesterday_count' => $vend->daysVendRecords(1,1)->count(),
                    'seven_days_amount' => (int)$vend->daysVendRecords(6,0)->sum('total_amount'),
                    'seven_days_count' => $vend->daysVendRecords(6,0)->count(),
                    'thirty_days_amount' => (int)$vend->daysVendRecords(29,0)->sum('total_amount'),
                    'thirty_days_count' => $vend->daysVendRecords(29,0)->count(),
                    'thirty_days_revenue' => (int)$vend->daysVendRecords(29,0)->sum('revenue'),
                    'thirty_days_gross_profit' => (int)$vend->daysVendRecords(29,0)->sum('gross_profit'),
                    'vend_records_amount_latest' => (int)$vend->lifetimeVendRecords->sum('total_amount'),
                    'vend_records_amount_average_day' => $vend->lifetimeVendRecords->sum('total_amount')/ (Carbon::parse($vend->begin_date)->diffInDays(Carbon::parse($vend->termination_date ?? Carbon::now())) ?: 1),
                    'vend_records_thirty_days_amount' => (int)$vend->daysVendRecords(29,0)->sum('total_amount'),
                    'vend_records_thirty_days_amount_average' =>
                        $vend->daysVendRecords(29,0)->sum('total_amount')/
                        (
                            Carbon::parse($vend->begin_date)->diffInDays(Carbon::now()) < 30 ?
                            (Carbon::parse($vend->begin_date)->diffInDays(Carbon::now()) == 0 ? 1 : Carbon::parse($vend->begin_date)->diffInDays(Carbon::now())) :
                            30
                        ),
                ]
            ]);
        }

        if($customer) {
            $customer->update([
                'totals_json' => [
                    'today_amount' => (int)$customer->daysVendTransactions(0,0)->sum('amount'),
                    'today_count' => $customer->daysVendTransactions(0,0)->count(),
                    'yesterday_amount' => (int)$customer->daysVendRecords(1,1)->sum('total_amount'),
                    'yesterday_count' => $customer->daysVendRecords(1,1)->count(),
                    'seven_days_amount' => (int)$customer->daysVendRecords(6,0)->sum('total_amount'),
                    'seven_days_count' => $customer->daysVendRecords(6,0)->count(),
                    'thirty_days_amount' => (int)$customer->daysVendRecords(29,0)->sum('total_amount'),
                    'thirty_days_count' => $customer->daysVendRecords(29,0)->count(),
                    'thirty_days_revenue' => (int)$customer->daysVendRecords(29,0)->sum('revenue'),
                    'thirty_days_gross_profit' => (int)$customer->daysVendRecords(29,0)->sum('gross_profit'),
                    'vend_records_amount_latest' => (int)$customer->lifetimeVendRecords->sum('total_amount'),
                    'vend_records_amount_average_day' => $customer->lifetimeVendRecords->sum('total_amount')/ (Carbon::parse($customer->begin_date)->diffInDays(Carbon::parse($customer->termination_date ?? Carbon::now())) ?: 1),
                    'vend_records_thirty_days_amount' => (int)$customer->daysVendRecords(29,0)->sum('total_amount'),
                    'vend_records_thirty_days_amount_average' =>
                        $customer->daysVendRecords(29,0)->sum('total_amount')/
                        (
                            Carbon::parse($customer->begin_date)->diffInDays(Carbon::now()) < 30 ?
                            (Carbon::parse($customer->begin_date)->diffInDays(Carbon::now()) == 0 ? 1 : Carbon::parse($customer->begin_date)->diffInDays(Carbon::now())) :
                            30
                        ),
                ]
            ]);
        }
    }
}
