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
        $timezone = config('app.timezone');


        $successfulItemsExpression = <<<SQL
CASE
    WHEN vt.success_qty IS NOT NULL AND vt.success_qty > 0 THEN vt.success_qty
    WHEN (vt.success_qty IS NULL OR vt.success_qty = 0)
         AND (
             vt.vend_channel_error_id IS NULL
             OR vt.vend_channel_error_id IN (1, 5)
         )
    THEN COALESCE(vt.qty, 0)
    ELSE 0
END
SQL;

        $single = DB::table('vend_transactions as vt')
            ->leftJoin('delivery_platform_orders as dpo', 'vt.id', '=', 'dpo.vend_transaction_id')
            ->leftJoin('vends as v', 'vt.vend_id', '=', 'v.id')
            ->leftJoin('customers as c', 'vt.customer_id', '=', 'c.id')
            ->leftJoin('location_types as lt', 'c.location_type_id', '=', 'lt.id')
            ->whereBetween('vt.transaction_datetime', [
                Carbon::parse($this->from)->setTimezone($timezone)->startOfDay(),
                Carbon::parse($this->to)->setTimezone($timezone)->endOfDay()
            ])
            ->where('vt.amount', '>', 0)
            ->where(function ($query) {
                $query->where('vt.is_multiple', false)
                    ->orWhereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('vend_transaction_items')
                            ->whereColumn('vend_transaction_id', 'vt.id');
                    });
            })
            ->select(
                'v.id AS vend_id',
                'v.code',
                'v.vend_model_id',
                'v.vend_prefix_id',
                'c.id AS customer_id',
                'lt.id AS location_type_id',
                'vt.operator_id',
                DB::raw("DATE(vt.transaction_datetime) as date"),
                DB::raw("DAY(vt.transaction_datetime) as day"),
                DB::raw("MONTH(vt.transaction_datetime) as month"),
                DB::raw("MONTHNAME(vt.transaction_datetime) AS month_name"),
                DB::raw("YEAR(vt.transaction_datetime) as year"),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vt.vend_channel_error_id IS NULL OR vt.vend_channel_error_id IN (1, 5) THEN vt.amount
                            ELSE 0
                        END
                    ),0) as total_amount'
                ),
                DB::raw('SUM(vt.qty) as all_total_count'),
                DB::raw("SUM({$successfulItemsExpression}) as total_count"),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN vt.vend_channel_error_id IS NOT NULL AND vt.vend_channel_error_id NOT IN (1) THEN 1
                            ELSE NULL
                        END
                    ) as error_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vt.vend_channel_error_id IS NULL OR vt.vend_channel_error_id IN (1, 5) THEN vt.revenue
                            ELSE 0
                        END
                    ),0) as revenue'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vt.vend_channel_error_id IS NULL OR vt.vend_channel_error_id IN (1, 5) THEN vt.gross_profit
                            ELSE 0
                        END
                    ),0) as gross_profit'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN vt.vend_channel_error_id IS NOT NULL AND vt.vend_channel_error_id NOT IN (1, 5) THEN vt.amount
                            ELSE 0
                        END
                    ),0) as failure_amount'
                ),
                DB::raw("SUM(COALESCE(vt.qty, 0)) - SUM({$successfulItemsExpression}) as failure_count"),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN dpo.id IS NOT NULL AND (vt.vend_channel_error_id IS NULL OR vt.vend_channel_error_id IN (1, 5)) THEN vt.amount
                            ELSE 0
                        END
                    ),0) as online_success_amount'
                ),
                DB::raw(
                    'SUM(
                        CASE
                            WHEN dpo.id IS NOT NULL THEN ( ' . $successfulItemsExpression . ' )
                            ELSE 0
                        END
                    ) as online_success_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN dpo.id IS NOT NULL AND vt.vend_channel_error_id NOT IN (1, 5) AND vt.vend_channel_error_id IS NOT NULL THEN vt.amount
                            ELSE 0
                        END
                    ),0) as online_failure_amount'
                ),
                DB::raw(
                    'SUM(
                        CASE
                        WHEN dpo.id IS NOT NULL THEN (COALESCE(vt.qty, 0) - ( ' . $successfulItemsExpression . ' ))
                        ELSE 0
                        END
                    ) as online_failure_count'
                )
            )
            ->groupBy('date', 'v.id', 'c.id');

        $multiSuccessExpression = "(vti.vend_channel_error_id IS NULL OR vti.vend_channel_error_id IN (1, 5) OR vti.vend_channel_error_code IN (0, 6))";

        $multi = DB::table('vend_transaction_items as vti')
            ->join('vend_transactions as vt', 'vti.vend_transaction_id', '=', 'vt.id')
            ->leftJoin('delivery_platform_orders as dpo', 'vt.id', '=', 'dpo.vend_transaction_id')
            ->leftJoin('vends as v', 'vt.vend_id', '=', 'v.id')
            ->leftJoin('customers as c', 'vt.customer_id', '=', 'c.id')
            ->leftJoin('location_types as lt', 'c.location_type_id', '=', 'lt.id')
            ->whereBetween('vt.transaction_datetime', [
                Carbon::parse($this->from)->setTimezone($timezone)->startOfDay(),
                Carbon::parse($this->to)->setTimezone($timezone)->endOfDay()
            ])
            ->where('vt.amount', '>', 0)
            ->where('vt.is_multiple', true)
            ->select(
                'v.id AS vend_id',
                'v.code',
                'v.vend_model_id',
                'v.vend_prefix_id',
                'c.id AS customer_id',
                'lt.id AS location_type_id',
                'vt.operator_id',
                DB::raw("DATE(vt.transaction_datetime) as date"),
                DB::raw("DAY(vt.transaction_datetime) as day"),
                DB::raw("MONTH(vt.transaction_datetime) as month"),
                DB::raw("MONTHNAME(vt.transaction_datetime) AS month_name"),
                DB::raw("YEAR(vt.transaction_datetime) as year"),
                DB::raw(
                    "COALESCE(SUM(
                        CASE WHEN {$multiSuccessExpression} THEN vti.unit_price_amount ELSE 0 END
                    ),0) as total_amount"
                ),
                DB::raw('COUNT(*) as all_total_count'),
                DB::raw(
                    "SUM(CASE WHEN {$multiSuccessExpression} THEN 1 ELSE 0 END) as total_count"
                ),
                DB::raw(
                    "COUNT(CASE WHEN NOT (vti.vend_channel_error_id IS NULL OR vti.vend_channel_error_id IN (1) OR vti.vend_channel_error_code IN (0)) THEN 1 ELSE NULL END) as error_count"
                ),
                DB::raw(
                    "COALESCE(SUM(
                        CASE WHEN {$multiSuccessExpression} THEN ROUND(vti.unit_price_amount / (1 + COALESCE(vt.gst_vat_rate, 0) / 100)) ELSE 0 END
                    ),0) as revenue"
                ),
                DB::raw(
                    "COALESCE(SUM(
                        CASE WHEN {$multiSuccessExpression} THEN ROUND(vti.unit_price_amount / (1 + COALESCE(vt.gst_vat_rate, 0) / 100)) - (COALESCE(vti.unit_cost, 0) * 100) ELSE 0 END
                    ),0) as gross_profit"
                ),
                DB::raw(
                    "COALESCE(SUM(CASE WHEN NOT {$multiSuccessExpression} THEN vti.unit_price_amount ELSE 0 END),0) as failure_amount"
                ),
                DB::raw(
                    "SUM(CASE WHEN NOT {$multiSuccessExpression} THEN 1 ELSE 0 END) as failure_count"
                ),
                DB::raw(
                    "COALESCE(SUM(
                        CASE WHEN dpo.id IS NOT NULL AND {$multiSuccessExpression} THEN vti.unit_price_amount ELSE 0 END
                    ),0) as online_success_amount"
                ),
                DB::raw(
                    "SUM(CASE WHEN dpo.id IS NOT NULL AND {$multiSuccessExpression} THEN 1 ELSE 0 END) as online_success_count"
                ),
                DB::raw(
                    "COALESCE(SUM(
                        CASE WHEN dpo.id IS NOT NULL AND NOT {$multiSuccessExpression} THEN vti.unit_price_amount ELSE 0 END
                    ),0) as online_failure_amount"
                ),
                DB::raw(
                    "SUM(CASE WHEN dpo.id IS NOT NULL AND NOT {$multiSuccessExpression} THEN 1 ELSE 0 END) as online_failure_count"
                )
            )
            ->groupBy('date', 'v.id', 'c.id');

        $union = $single->unionAll($multi);

        $vends = DB::query()->fromSub($union, 'u')
            ->groupBy('date', 'vend_id', 'customer_id')
            ->select(
                'date',
                'vend_id',
                'customer_id',
                'code',
                'vend_model_id',
                'vend_prefix_id',
                'location_type_id',
                'day',
                'month',
                'month_name',
                'year',
                'operator_id',
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(all_total_count) as all_total_count'),
                DB::raw('SUM(total_count) as total_count'),
                DB::raw('SUM(error_count) as error_count'),
                DB::raw('SUM(revenue) as revenue'),
                DB::raw('SUM(gross_profit) as gross_profit'),
                DB::raw('SUM(failure_amount) as failure_amount'),
                DB::raw('SUM(failure_count) as failure_count'),
                DB::raw('SUM(online_success_amount) as online_success_amount'),
                DB::raw('SUM(online_success_count) as online_success_count'),
                DB::raw('SUM(online_failure_amount) as online_failure_amount'),
                DB::raw('SUM(online_failure_count) as online_failure_count')
            )
            ->get();

        $vendWithTransactions = [];
        foreach ($vends as $vend) {
            $vendWithTransactions[$vend->date][] = $vend->vend_id;
            if ($vend->vend_id == 0) {
                continue;
            }
            VendRecord::updateOrCreate([
                'date' => $vend->date,
                'vend_id' => $vend->vend_id,
                'customer_id' => isset($vend->customer_id) ? $vend->customer_id : null,
            ], [
                'all_total_count' => $vend->all_total_count,
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
                    ->where('vends.is_active', true)
                    ->whereNotNull('vends.customer_id')
                    ->whereNotIn('vends.id', $vendIDs)
                    ->where('vends.id', '!=', 0)
                    ->where('vends.code', '!=', null)
                    ->get();

                foreach ($vendWithoutTransactions as $vend) {
                    VendRecord::updateOrCreate([
                        'vend_id' => $vend->id,
                        'date' => Carbon::parse($date)->toDateString(),
                        'customer_id' => $vend->customer_id,
                    ], [
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
