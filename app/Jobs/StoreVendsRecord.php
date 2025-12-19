<?php

namespace App\Jobs;

use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreVendsRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Execute the job.
     */
    protected $from;
    protected $to;
    protected $seedActive;

    public function __construct($from, $to, $seedActive = false)
    {
        $this->from = $from;
        $this->to = $to;
        $this->seedActive = $seedActive;
    }

    public function handle(): void
    {
        $successfulItemsExpression = <<<SQL
CASE
    WHEN vend_transactions.success_qty IS NOT NULL AND vend_transactions.success_qty > 0 THEN vend_transactions.success_qty
    WHEN (vend_transactions.success_qty IS NULL OR vend_transactions.success_qty = 0)
         AND (
             vend_transactions.vend_channel_error_id IS NULL
             OR vend_channel_errors.code IN (0, 6)
             OR vend_transactions.is_multiple = 1
         )
    THEN COALESCE(vend_transactions.qty, 0)
    ELSE 0
END
SQL;

        $vends = VendTransaction::query()
            ->join('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('delivery_platform_orders', 'vend_transactions.id', '=', 'delivery_platform_orders.vend_transaction_id')
            ->leftJoin('customers', 'vend_transactions.customer_id', '=', 'customers.id')
            ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
            ->leftJoin('vend_channel_errors', 'vend_transactions.vend_channel_error_id', '=', 'vend_channel_errors.id')
            ->where('vend_transactions.transaction_datetime', '>', Carbon::parse($this->from)->setTimezone('Asia/Singapore')->startOfDay())
            ->where('vend_transactions.transaction_datetime', '<', Carbon::parse($this->to)->setTimezone('Asia/Singapore')->endOfDay())
            ->where('vend_transactions.amount', '>', 0)
            ->groupBy('date', 'vends.id')
            ->select(
                'vends.id AS vend_id',
                'vends.code',
                'vends.vend_model_id',
                'vends.vend_prefix_id',
                'vend_transactions.id',
                'customers.id AS customer_id',
                'location_types.id AS location_type_id',
                DB::raw('DATE(vend_transactions.transaction_datetime) as date'),
                DB::raw('DAY(vend_transactions.transaction_datetime) as day'),
                DB::raw('MONTH(vend_transactions.transaction_datetime) as month'),
                DB::raw('MONTHNAME(vend_transactions.transaction_datetime) AS month_name'),
                'vend_transactions.operator_id',
                'vend_id',
                DB::raw('YEAR(vend_transactions.transaction_datetime) as year'),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vend_channel_error_id IS NULL THEN amount
                            WHEN vend_channel_errors.code = 0 THEN amount
                            WHEN vend_channel_errors.code = 6 THEN amount
                            WHEN is_multiple = 1 THEN amount
                            ELSE 0
                        END
                    ),0) as total_amount'
                ),
                DB::raw(
                    'SUM(vend_transactions.qty) as all_total_count'
                ),
                DB::raw(
                    "SUM({$successfulItemsExpression}) as total_count"
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN vend_channel_error_id IS NOT NULL THEN 1
                            ELSE NULL
                        END
                    ) as error_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vend_channel_error_id IS NULL THEN revenue
                            WHEN vend_channel_errors.code = 0 THEN revenue
                            WHEN vend_channel_errors.code = 6 THEN revenue
                            WHEN is_multiple = 1 THEN revenue
                            ELSE 0
                        END
                    ),0) as revenue'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vend_channel_error_id IS NULL THEN gross_profit
                            WHEN vend_channel_errors.code = 0 THEN gross_profit
                            WHEN vend_channel_errors.code = 6 THEN gross_profit
                            WHEN is_multiple = 1 THEN gross_profit
                            ELSE 0
                        END
                    ),0) as gross_profit'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vend_channel_error_id IS NULL THEN 0
                            WHEN vend_channel_errors.code = 0 THEN 0
                            WHEN vend_channel_errors.code = 6 THEN 0
                            ELSE amount
                        END
                    ),0) as failure_amount'
                ),
                DB::raw(
                    "SUM(COALESCE(vend_transactions.qty, 0)) - SUM({$successfulItemsExpression}) as failure_count"
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND vend_channel_error_id IS NULL THEN amount
                            WHEN delivery_platform_orders.id IS NOT NULL AND vend_channel_errors.code = 0 THEN amount
                            WHEN delivery_platform_orders.id IS NOT NULL AND vend_channel_errors.code = 6 THEN amount
                            WHEN delivery_platform_orders.id IS NULL THEN 0
                            ELSE 0
                        END
                    ),0) as online_success_amount'
                ),
                DB::raw(
                    'SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL THEN ' . $successfulItemsExpression . '
                            ELSE 0
                        END
                    ) as online_success_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND vend_channel_error_id IS NULL THEN 0
                            WHEN delivery_platform_orders.id IS NOT NULL AND vend_channel_errors.code = 0 THEN 0
                            WHEN delivery_platform_orders.id IS NOT NULL AND vend_channel_errors.code = 6 THEN 0
                            WHEN delivery_platform_orders.id IS NULL THEN NULL
                            ELSE amount
                        END
                    ),0) as online_failure_amount'
                ),
                DB::raw(
                    'SUM(
                        CASE
                        WHEN delivery_platform_orders.id IS NOT NULL THEN (COALESCE(vend_transactions.qty, 0) - (' . $successfulItemsExpression . '))
                        ELSE 0
                        END
                    ) as online_failure_count'
                ),
            )
            ->where('vend_transactions.vend_id', '!=', 0)
            ->get();

        $vendWithTransactions = [];
        foreach ($vends as $vend) {
            $vendWithTransactions[$vend->date][] = $vend->id;
            if ($vend->id == 0) {
                continue;
            }
            VendRecord::updateOrCreate([
                'date' => $vend->date,
                'vend_id' => $vend->vend_id,
            ], [
                'all_total_count' => $vend->all_total_count,
                'customer_id' => isset($vend->customer_id) ? $vend->customer_id : null,
                'day' => $vend->day,
                'error_count' => $vend->error_count,
                'failure_amount' => $vend->failure_amount,
                'failure_count' => $vend->failure_count,
                'gross_profit' => $vend->gross_profit,
                'location_type_id' => $vend->location_type_id ?? null,
                'month' => $vend->month,
                'monthname' => $vend->month_name,
                'online_failure_amount' => $vend->online_failure_amount,
                'online_failure_count' => $vend->online_failure_count,
                'online_success_amount' => $vend->online_success_amount,
                'online_success_count' => $vend->online_success_count,
                'operator_id' => $vend->operator_id,
                'revenue' => $vend->revenue,
                'total_amount' => $vend->total_amount,
                'total_count' => $vend->total_count,
                'vend_code' => $vend->code,
                'vend_model_id' => $vend->vend_model_id,
                'vend_prefix_id' => $vend->vend_prefix_id,
                'year' => $vend->year,
            ]);
        }

        // also init the vends without transactions
        if ($vendWithTransactions and $this->seedActive) {
            foreach ($vendWithTransactions as $date => $vendIDs) {
                $vendWithoutTransactions = Vend::query()
                    ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
                    ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
                    ->select(
                        'vends.id as id',
                        'vends.code as code',
                        'vends.operator_id',
                        'vends.vend_prefix_id',
                        'customers.id as customer_id',
                        'location_types.id as location_type_id'
                    )
                    ->where('customers.is_active', true)
                    ->whereNotIn('vends.id', $vendIDs)
                    ->where('vends.id', '!=', 0)
                    ->where('vends.code', '!=', null)
                    ->get();

                foreach ($vendWithoutTransactions as $vend) {
                    VendRecord::updateOrCreate([
                        'vend_id' => $vend->id,
                        'date' => Carbon::parse($date)->toDateString(),
                    ], [
                        'customer_id' => $vend->customer_id,
                        'day' => Carbon::parse($date)->day,
                        'location_type_id' => $vend->location_type_id ?? null,
                        'month' => Carbon::parse($date)->month,
                        'monthname' => Carbon::parse($date)->format('F'),
                        'operator_id' => $vend->operator_id,
                        'year' => Carbon::parse($date)->year,
                        'vend_code' => $vend->code,
                        'vend_model_id' => $vend->vend_model_id ?? null,
                        'vend_prefix_id' => $vend->vend_prefix_id,
                    ]);
                }
            }
        }

    }
}
