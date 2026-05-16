<?php

namespace App\Jobs\Vend;

use App\Models\Customer;
use App\Models\Vend;
use App\Models\VendChannelErrorLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncVendTransactionTotalsJson implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 90;

    // Prevent duplicate jobs for same model for 3 minutes
    public $uniqueFor = 180;

    public function uniqueId()
    {
        if ($this->model instanceof Vend) {
            return 'vend_' . $this->model->id;
        } elseif ($this->model instanceof Customer) {
            return 'customer_' . $this->model->id;
        }
        return 'unknown';
    }

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
            $todayTxns = $vend->daysVendTransactions(0, 0);
            $todayAmount = (int) $todayTxns->clone()->isSuccessful()->sum('amount');
            $todayCount = $this->calculateSuccessfulItemCount($todayTxns);
            $todayAllCount = (int) $todayTxns->clone()->sum(DB::raw("
                CASE
                    WHEN vend_transactions.is_multiple = 1 THEN COALESCE(vend_transactions.qty, 0)
                    ELSE COALESCE(NULLIF(vend_transactions.qty, 0), 1)
                END
            "));
            $todayErrorCount = $this->calculateErrorItemCount($todayTxns);
            $todayRevenue = (int) $todayTxns->clone()->isSuccessful()->sum('revenue');
            $todayGrossProfit = (int) $todayTxns->clone()->isSuccessful()->sum('gross_profit');

            // Calculate from vend_transactions only, ignoring heartbeat errors

            $records1 = $vend->daysVendRecords(1, 1)->get();
            // 2d range = yesterday's vend_records + today (added separately below).
            // vend_records does not contain today's row yet, so daysVendRecords(1, 1) returns
            // exactly yesterday — combined with today this yields a true 2-day window.
            $records2 = $records1;
            $records7 = $vend->daysVendRecords(7, 0)->get();
            $records29 = $vend->daysVendRecords(29, 0)->get();
            $lifetime = $vend->lifetimeVendRecords;

            $daysSinceStart = max((int) Carbon::parse($vend->begin_date ?: now())->diffInDays(Carbon::parse($vend->termination_date ?: now())), 1);
            $daysFor30 = $vend->begin_date && Carbon::parse($vend->begin_date)->diffInDays(now()) < 30
                ? max(Carbon::parse($vend->begin_date)->diffInDays(now()), 1)
                : 30;

            $recordsFor30 = $vend->daysVendRecords($daysFor30 - 1, 0)->get();

            // Calendar-month totals (current / last / last-2) sourced from
            // vend_records since vend_transactions is too slow at this scale.
            // vend_records does not contain today's row yet, so today's
            // vend_transactions amount is added on top of the current-month sum.
            $currentMthStart = Carbon::now()->startOfMonth()->startOfDay();
            $currentMthEnd = Carbon::now()->endOfMonth()->endOfDay();
            $lastMthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
            $lastMthEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();
            $last2MthStart = Carbon::now()->subMonthsNoOverflow(2)->startOfMonth()->startOfDay();
            $last2MthEnd = Carbon::now()->subMonthsNoOverflow(2)->endOfMonth()->endOfDay();

            $currentMthAmountRecords = (int) $vend->vendRecords()
                ->whereBetween('date', [$currentMthStart, $currentMthEnd])
                ->sum('total_amount');
            $lastMthAmount = (int) $vend->vendRecords()
                ->whereBetween('date', [$lastMthStart, $lastMthEnd])
                ->sum('total_amount');
            $last2MthAmount = (int) $vend->vendRecords()
                ->whereBetween('date', [$last2MthStart, $last2MthEnd])
                ->sum('total_amount');

            $vend->update([
                'vend_transaction_totals_json' => [
                    'today_amount' => $todayAmount,
                    'today_count' => $todayCount,
                    'yesterday_amount' => (int) $records1->sum('total_amount'),
                    'yesterday_count' => (int) $records1->sum('total_count'),
                    'one_day_error_count' => $todayErrorCount,
                    'one_day_all_count' => $todayAllCount,
                    'one_day_error_rate' => $todayAllCount > 0
                        ? ($todayErrorCount / $todayAllCount) * 100
                        : 0,
                    'two_days_amount' => (int) $records2->sum('total_amount') + $todayAmount,
                    'two_days_count' => (int) $records2->sum('total_count') + $todayCount,
                    'two_days_all_count' => (int) $records2->sum('all_total_count') + $todayAllCount,
                    'two_days_error_count' => (int) $records2->sum('error_count') + $todayErrorCount,
                    'two_days_error_rate' => ($records2->sum('error_count') + $todayErrorCount) > 0
                        ? (($records2->sum('error_count') + $todayErrorCount) / ($records2->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'seven_days_amount' => (int) $records7->sum('total_amount') + $todayAmount,
                    'seven_days_count' => (int) $records7->sum('total_count') + $todayCount,
                    'seven_days_all_count' => (int) $records7->sum('all_total_count') + $todayAllCount,
                    'seven_days_error_count' => (int) $records7->sum('error_count') + $todayErrorCount,
                    'seven_days_error_rate' => ($records7->sum('error_count') + $todayErrorCount) > 0
                        ? (($records7->sum('error_count') + $todayErrorCount) / ($records7->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'thirty_days_count' => (int) $records29->sum('total_count') + $todayCount,
                    'thirty_days_revenue' => (int) $records29->sum('revenue') + $todayRevenue,
                    'thirty_days_gross_profit' => (int) $records29->sum('gross_profit') + $todayGrossProfit,
                    'vend_records_amount_latest' => (int) $lifetime->sum('total_amount') + $todayAmount,
                    'vend_records_amount_average_day' => ((int) $lifetime->sum('total_amount') + $todayAmount) / $daysSinceStart,
                    'vend_records_thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'vend_records_thirty_days_amount_average' => ((int) $recordsFor30->sum('total_amount') + $todayAmount) / $daysFor30,
                    // Calendar-month sales totals — used by CustomerIndex.vue
                    // "Mthly Sales $" sub-column. Current month adds today's
                    // amount on top since vend_records doesn't carry today.
                    'current_mth_amount' => $currentMthAmountRecords + $todayAmount,
                    'last_mth_amount' => $lastMthAmount,
                    'last_2_mth_amount' => $last2MthAmount,
                ]
            ]);
        }

        if ($customer) {
            $todayTxns = $customer->daysVendTransactions(0, 0);
            $todayAmount = (int) $todayTxns->clone()->isSuccessful()->sum('amount');
            $todayCount = $this->calculateSuccessfulItemCount($todayTxns);
            $todayAllCount = (int) $todayTxns->clone()->sum(DB::raw("
                CASE
                    WHEN vend_transactions.is_multiple = 1 THEN COALESCE(vend_transactions.qty, 0)
                    ELSE COALESCE(NULLIF(vend_transactions.qty, 0), 1)
                END
            "));
            $todayErrorCount = $this->calculateErrorItemCount($todayTxns);
            $todayRevenue = (int) $todayTxns->clone()->isSuccessful()->sum('revenue');
            $todayGrossProfit = (int) $todayTxns->clone()->isSuccessful()->sum('gross_profit');

            // Calculate from vend_transactions only, ignoring heartbeat errors

            $records1 = $customer->daysVendRecords(1, 1)->get();
            // 2d range = yesterday's vend_records + today (added separately below).
            // vend_records does not contain today's row yet, so daysVendRecords(1, 1) returns
            // exactly yesterday — combined with today this yields a true 2-day window.
            $records2 = $records1;
            $records7 = $customer->daysVendRecords(7, 0)->get();
            $records29 = $customer->daysVendRecords(29, 0)->get();
            $lifetime = $customer->lifetimeVendRecords;

            $daysSinceStart = max((int) Carbon::parse($customer->begin_date ?: now())->diffInDays(Carbon::parse($customer->termination_date ?: now())), 1);
            $daysFor30 = $customer->begin_date && Carbon::parse($customer->begin_date)->diffInDays(now()) < 30
                ? max(Carbon::parse($customer->begin_date)->diffInDays(now()), 1)
                : 30;

            $recordsFor30 = $customer->daysVendRecords($daysFor30 - 1, 0)->get();

            // Calendar-month totals (current / last / last-2) sourced from
            // vend_records since vend_transactions is too slow at this scale.
            // vend_records does not contain today's row yet, so today's
            // vend_transactions amount is added on top of the current-month sum.
            $currentMthStart = Carbon::now()->startOfMonth()->startOfDay();
            $currentMthEnd = Carbon::now()->endOfMonth()->endOfDay();
            $lastMthStart = Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
            $lastMthEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay();
            $last2MthStart = Carbon::now()->subMonthsNoOverflow(2)->startOfMonth()->startOfDay();
            $last2MthEnd = Carbon::now()->subMonthsNoOverflow(2)->endOfMonth()->endOfDay();

            $currentMthAmountRecords = (int) $customer->vendRecords()
                ->whereBetween('date', [$currentMthStart, $currentMthEnd])
                ->sum('total_amount');
            $lastMthAmount = (int) $customer->vendRecords()
                ->whereBetween('date', [$lastMthStart, $lastMthEnd])
                ->sum('total_amount');
            $last2MthAmount = (int) $customer->vendRecords()
                ->whereBetween('date', [$last2MthStart, $last2MthEnd])
                ->sum('total_amount');

            $customer->update([
                'totals_json' => [
                    'today_amount' => $todayAmount,
                    'today_count' => $todayCount,
                    'yesterday_amount' => (int) $records1->sum('total_amount'),
                    'yesterday_count' => (int) $records1->sum('total_count'),
                    'one_day_error_count' => $todayErrorCount,
                    'one_day_all_count' => $todayAllCount,
                    'one_day_error_rate' => $todayAllCount > 0
                        ? ($todayErrorCount / $todayAllCount) * 100
                        : 0,
                    'two_days_amount' => (int) $records2->sum('total_amount') + $todayAmount,
                    'two_days_count' => (int) $records2->sum('total_count') + $todayCount,
                    'two_days_all_count' => (int) $records2->sum('all_total_count') + $todayAllCount,
                    'two_days_error_count' => (int) $records2->sum('error_count') + $todayErrorCount,
                    'two_days_error_rate' => ($records2->sum('error_count') + $todayErrorCount) > 0
                        ? (($records2->sum('error_count') + $todayErrorCount) / ($records2->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'seven_days_amount' => (int) $records7->sum('total_amount') + $todayAmount,
                    'seven_days_count' => (int) $records7->sum('total_count') + $todayCount,
                    'seven_days_all_count' => (int) $records7->sum('all_total_count') + $todayAllCount,
                    'seven_days_error_count' => (int) $records7->sum('error_count') + $todayErrorCount,
                    'seven_days_error_rate' => ($records7->sum('error_count') + $todayErrorCount) > 0
                        ? (($records7->sum('error_count') + $todayErrorCount) / ($records7->sum('all_total_count') + $todayAllCount)) * 100
                        : 0,
                    'thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'thirty_days_count' => (int) $records29->sum('total_count') + $todayCount,
                    'thirty_days_revenue' => (int) $records29->sum('revenue') + $todayRevenue,
                    'thirty_days_gross_profit' => (int) $records29->sum('gross_profit') + $todayGrossProfit,
                    'vend_records_amount_latest' => (int) $lifetime->sum('total_amount') + $todayAmount,
                    'vend_records_amount_average_day' => ((int) $lifetime->sum('total_amount') + $todayAmount) / $daysSinceStart,
                    'vend_records_thirty_days_amount' => (int) $records29->sum('total_amount') + $todayAmount,
                    'vend_records_thirty_days_amount_average' => ((int) $recordsFor30->sum('total_amount') + $todayAmount) / $daysFor30,
                    // Calendar-month sales totals — used by CustomerIndex.vue
                    // "Mthly Sales $" sub-column. Current month adds today's
                    // amount on top since vend_records doesn't carry today.
                    'current_mth_amount' => $currentMthAmountRecords + $todayAmount,
                    'last_mth_amount' => $lastMthAmount,
                    'last_2_mth_amount' => $last2MthAmount,
                ]
            ]);
        }
    }

    private function calculateSuccessfulItemCount($transactionQuery): int
    {
        // Use SQL aggregation instead of loading all records
        // This is much faster and uses less memory

        // Case 1: success_qty is not null and > 0 -> use success_qty
        // Case 2: is_multiple = 1 OR error_code in (0, 6) OR vend_channel_error_id is null -> use qty
        // Case 3: otherwise -> 0

        $result = $transactionQuery
            ->clone()
            ->leftJoin('vend_channel_errors', 'vend_transactions.vend_channel_error_id', '=', 'vend_channel_errors.id')
            ->selectRaw('
                SUM(
                    CASE
                        WHEN vend_transactions.success_qty IS NOT NULL AND vend_transactions.success_qty > 0
                            THEN vend_transactions.success_qty
                        WHEN vend_transactions.vend_channel_error_id IS NULL
                            OR vend_channel_errors.code IN (0, 6)
                            OR vend_transactions.is_multiple = 1
                            THEN COALESCE(vend_transactions.qty, 0)
                        ELSE 0
                    END
                ) as total_count
            ')
            ->value('total_count');

        return (int) ($result ?? 0);
    }

    private function calculateErrorItemCount($transactionQuery): int
    {
        $result = $transactionQuery
            ->clone()
            ->leftJoin('vend_channel_errors', 'vend_transactions.vend_channel_error_id', '=', 'vend_channel_errors.id')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN vend_transactions.is_multiple = 1 THEN
                            (SELECT COUNT(*) FROM vend_transaction_items
                             LEFT JOIN vend_channel_errors AS vce ON vend_transaction_items.vend_channel_error_id = vce.id
                             WHERE vend_transaction_items.vend_transaction_id = vend_transactions.id
                               AND vce.code != 0
                            )
                        ELSE
                            CASE
                                WHEN vend_channel_errors.code != 0 OR JSON_UNQUOTE(JSON_EXTRACT(vend_transactions.vend_transaction_json, '$.GET_TYPE')) != '1' THEN 1
                                ELSE 0
                            END
                    END
                ) as error_count
            ")
            ->value('error_count');

        return (int) ($result ?? 0);
    }
}
