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
use Illuminate\Support\Facades\DB;

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
        if ($this->model instanceof Vend) {
            $vend = $this->model;
            $customer = $vend->customer;
        } elseif ($this->model instanceof Customer) {
            $customer = $this->model;
            $vend = $customer->vend;
        } else {
            return;
        }

        if ($vend) {
            $todayTxns = $vend->daysVendTransactions(0, 0)->where('amount', '>', 0);
            $todayAmount = (int) $todayTxns->clone()->isSuccessful()->sum('amount');
            $todayCount = $this->calculateSuccessfulItemCount($todayTxns);
            $todayAllCount = (int) $todayTxns->clone()->sum(DB::raw("
                CASE
                    WHEN vend_transactions.is_multiple = 1 THEN COALESCE(vend_transactions.qty, 0)
                    ELSE COALESCE(NULLIF(vend_transactions.qty, 0), 1)
                END
            "));
            $todayErrorCount = $todayTxns->clone()->isError()->count();
            $todayRevenue = (int) $todayTxns->clone()->isSuccessful()->sum('revenue');
            $todayGrossProfit = (int) $todayTxns->clone()->isSuccessful()->sum('gross_profit');

            $records1 = $vend->daysVendRecords(1, 1)->get();
            $records2 = $vend->daysVendRecords(2, 0)->get();
            $records6 = $vend->daysVendRecords(6, 0)->get();
            $records29 = $vend->daysVendRecords(29, 0)->get();
            $lifetime = $vend->lifetimeVendRecords;

            $daysSinceStart = max((int) Carbon::parse($vend->begin_date ?: now())->diffInDays(Carbon::parse($vend->termination_date ?: now())), 1);
            $daysFor30 = $vend->begin_date && Carbon::parse($vend->begin_date)->diffInDays(now()) < 30
                ? max(Carbon::parse($vend->begin_date)->diffInDays(now()), 1)
                : 30;

            $recordsFor30 = $vend->daysVendRecords($daysFor30 - 1, 0)->get();

            $vend->update([
                'vend_transaction_totals_json' => [
                    'today_amount' => $todayAmount,
                    'today_count' => $todayCount,
                    'yesterday_amount' => (int) $records1->sum('total_amount'),
                    'yesterday_count' => (int) $records1->sum('total_count'),
                    'three_days_amount' => (int) $records2->sum('total_amount') + $todayAmount,
                    'three_days_count' => (int) $records2->sum('total_count') + $todayCount,
                    'three_days_all_count' => (int) $records2->sum('all_total_count') + $todayAllCount,
                    'three_days_error_count' => (int) $records2->sum('error_count') + $todayErrorCount,
                    'three_days_error_rate' => ($records2->sum('error_count') + $todayErrorCount) > 0
                        ? (($records2->sum('error_count') + $todayErrorCount) / ($records2->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'seven_days_amount' => (int) $records6->sum('total_amount') + $todayAmount,
                    'seven_days_count' => (int) $records6->sum('total_count') + $todayCount,
                    'seven_days_all_count' => (int) $records6->sum('all_total_count') + $todayAllCount,
                    'seven_days_error_count' => (int) $records6->sum('error_count') + $todayErrorCount,
                    'seven_days_error_rate' => ($records6->sum('error_count') + $todayErrorCount) > 0
                        ? (($records6->sum('error_count') + $todayErrorCount) / ($records6->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'thirty_days_count' => (int) $records29->sum('total_count') + $todayCount,
                    'thirty_days_revenue' => (int) $records29->sum('revenue') + $todayRevenue,
                    'thirty_days_gross_profit' => (int) $records29->sum('gross_profit') + $todayGrossProfit,
                    'vend_records_amount_latest' => (int) $lifetime->sum('total_amount') + $todayAmount,
                    'vend_records_amount_average_day' => ((int) $lifetime->sum('total_amount') + $todayAmount) / $daysSinceStart,
                    'vend_records_thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'vend_records_thirty_days_amount_average' => ((int) $recordsFor30->sum('total_amount') + $todayAmount) / $daysFor30,
                ]
            ]);
        }

        if ($customer) {
            $todayTxns = $customer->daysVendTransactions(0, 0)->where('amount', '>', 0);
            $todayAmount = (int) $todayTxns->clone()->isSuccessful()->sum('amount');
            $todayCount = $this->calculateSuccessfulItemCount($todayTxns);
            $todayAllCount = (int) $todayTxns->clone()->sum(DB::raw("
                CASE
                    WHEN vend_transactions.is_multiple = 1 THEN COALESCE(vend_transactions.qty, 0)
                    ELSE COALESCE(NULLIF(vend_transactions.qty, 0), 1)
                END
            "));
            $todayErrorCount = $todayTxns->clone()->isError()->count();
            $todayRevenue = (int) $todayTxns->clone()->isSuccessful()->sum('revenue');
            $todayGrossProfit = (int) $todayTxns->clone()->isSuccessful()->sum('gross_profit');

            $records1 = $customer->daysVendRecords(1, 1)->get();
            $records2 = $customer->daysVendRecords(2, 0)->get();
            $records6 = $customer->daysVendRecords(6, 0)->get();
            $records29 = $customer->daysVendRecords(29, 0)->get();
            $lifetime = $customer->lifetimeVendRecords;

            $daysSinceStart = max((int) Carbon::parse($customer->begin_date ?: now())->diffInDays(Carbon::parse($customer->termination_date ?: now())), 1);
            $daysFor30 = $customer->begin_date && Carbon::parse($customer->begin_date)->diffInDays(now()) < 30
                ? max(Carbon::parse($customer->begin_date)->diffInDays(now()), 1)
                : 30;

            $recordsFor30 = $customer->daysVendRecords($daysFor30 - 1, 0)->get();

            $customer->update([
                'totals_json' => [
                    'today_amount' => $todayAmount,
                    'today_count' => $todayCount,
                    'yesterday_amount' => (int) $records1->sum('total_amount'),
                    'yesterday_count' => (int) $records1->sum('total_count'),
                    'three_days_amount' => (int) $records2->sum('total_amount') + $todayAmount,
                    'three_days_count' => (int) $records2->sum('total_count') + $todayCount,
                    'three_days_all_count' => (int) $records2->sum('all_total_count') + $todayAllCount,
                    'three_days_error_count' => (int) $records2->sum('error_count') + $todayErrorCount,
                    'three_days_error_rate' => ($records2->sum('error_count') + $todayErrorCount) > 0
                        ? (($records2->sum('error_count') + $todayErrorCount) / ($records2->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'seven_days_amount' => (int) $records6->sum('total_amount') + $todayAmount,
                    'seven_days_count' => (int) $records6->sum('total_count') + $todayCount,
                    'seven_days_all_count' => (int) $records6->sum('all_total_count') + $todayAllCount,
                    'seven_days_error_count' => (int) $records6->sum('error_count') + $todayErrorCount,
                    'seven_days_error_rate' => ($records6->sum('error_count') + $todayErrorCount) > 0
                        ? (($records6->sum('error_count') + $todayErrorCount) / ($records6->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'thirty_days_count' => (int) $records29->sum('total_count') + $todayCount,
                    'thirty_days_revenue' => (int) $records29->sum('revenue') + $todayRevenue,
                    'thirty_days_gross_profit' => (int) $records29->sum('gross_profit') + $todayGrossProfit,
                    'vend_records_amount_latest' => (int) $lifetime->sum('total_amount') + $todayAmount,
                    'vend_records_amount_average_day' => ((int) $lifetime->sum('total_amount') + $todayAmount) / $daysSinceStart,
                    'vend_records_thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'vend_records_thirty_days_amount_average' => ((int) $recordsFor30->sum('total_amount') + $todayAmount) / $daysFor30,
                ]
            ]);
        }
    }

    private function calculateSuccessfulItemCount($transactionQuery): int
    {
        return (int) $transactionQuery
            ->clone()
            ->with('vendChannelError:id,code')
            ->get([
                'id',
                'qty',
                'success_qty',
                'is_multiple',
                'vend_channel_error_id',
            ])
            ->sum(function ($transaction) {
                if ($transaction->success_qty !== null && (int) $transaction->success_qty > 0) {
                    return (int) $transaction->success_qty;
                }

                $errorCode = optional($transaction->vendChannelError)->code;

                if (
                    is_null($transaction->vend_channel_error_id) ||
                    in_array((int) $errorCode, [0, 6], true) ||
                    (bool) $transaction->is_multiple
                ) {
                    return (int) ($transaction->qty ?? 0);
                }

                return 0;
            });
    }
}
