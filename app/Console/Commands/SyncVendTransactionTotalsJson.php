<?php

namespace App\Console\Commands;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncVendTransactionTotalsJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:vend-transaction-totals-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transaction total json';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vends = Vend::all();

        foreach($vends as $vend) {
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
        }


    }
}
