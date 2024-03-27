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

    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function handle(): void
    {

        $vends = VendTransaction::query()
            ->with([
                'vend:id,code,name',
                'customer',
            ])
            ->leftJoin('delivery_platform_orders', 'vend_transactions.id', '=', 'delivery_platform_orders.vend_transaction_id')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vend_transactions.customer_id', '=', 'customers.id')
            ->where('vend_transactions.created_at', '>=', Carbon::parse($this->from)->startOfDay())
            ->where('vend_transactions.created_at', '<=', Carbon::parse($this->to)->endOfDay())
            ->groupBy('date', 'vends.id')
            ->select(
                'vends.id AS vend_id',
                'vends.code',
                'vend_transactions.id',
                'customers.id AS customer_id',
                DB::raw('DATE(vend_transactions.created_at) as date'),
                DB::raw('DAY(vend_transactions.created_at) as day'),
                DB::raw('MONTH(vend_transactions.created_at) as month'),
                DB::raw('MONTHNAME(vend_transactions.created_at) AS month_name'),
                'customers.operator_id',
                'vend_id',
                DB::raw('YEAR(vend_transactions.created_at) as year'),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN error_code_normalized IS NULL THEN amount
                            WHEN error_code_normalized = 0 THEN amount
                            WHEN error_code_normalized = 6 THEN amount
                            WHEN is_multiple = 1 THEN amount
                            ELSE 0
                        END
                    ),0) as total_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN error_code_normalized IS NULL THEN vend_transactions.id
                            WHEN error_code_normalized = 0 THEN vend_transactions.id
                            WHEN error_code_normalized = 6 THEN vend_transactions.id
                            WHEN is_multiple = 1 THEN amount
                            ELSE NULL
                        END
                    ) as total_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN error_code_normalized IS NULL THEN revenue
                            WHEN error_code_normalized = 0 THEN revenue
                            WHEN error_code_normalized = 6 THEN revenue
                            WHEN is_multiple = 1 THEN revenue
                            ELSE 0
                        END
                    ),0) as revenue'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN error_code_normalized IS NULL THEN gross_profit
                            WHEN error_code_normalized = 0 THEN gross_profit
                            WHEN error_code_normalized = 6 THEN gross_profit
                            WHEN is_multiple = 1 THEN gross_profit
                            ELSE 0
                        END
                    ),0) as gross_profit'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN error_code_normalized IS NULL THEN 0
                            WHEN error_code_normalized = 0 THEN 0
                            WHEN error_code_normalized = 6 THEN 0
                            ELSE amount
                        END
                    ),0) as failure_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN error_code_normalized IS NULL THEN NULL
                            WHEN error_code_normalized = 0 THEN NULL
                            WHEN error_code_normalized = 6 THEN NULL
                            ELSE 1
                        END
                    ) as failure_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized IS NULL THEN amount
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN amount
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN amount
                            WHEN delivery_platform_orders.id IS NULL THEN 0
                            ELSE 0
                        END
                    ),0) as online_success_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized IS NULL THEN vend_transactions.id
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN vend_transactions.id
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN vend_transactions.id
                            WHEN delivery_platform_orders.id IS NULL THEN NULL
                            ELSE NULL
                        END
                    ) as online_success_count'
                ),
                DB::raw(
                    'COALESCE(SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized IS NULL THEN 0
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN 0
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN 0
                            WHEN delivery_platform_orders.id IS NULL THEN NULL
                            ELSE amount
                        END
                    ),0) as online_failure_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized IS NULL THEN NULL
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN NULL
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN NULL
                            WHEN delivery_platform_orders.id IS NULL THEN NULL
                            ELSE 1
                        END
                    ) as online_failure_count'
                ),
            )
            ->get();

        $vendWithTransactions = [];
        foreach($vends as $vend) {
            $vendWithTransactions[$vend->date][] = $vend->id;
            VendRecord::updateOrCreate([
                'vend_id' => $vend->vend_id,
                'date' => $vend->date,
            ], [
                'customer_id' => isset($vend->customer_id) ? $vend->customer_id : null,
                'customer_json' => isset($vend->customer_id) ? $vend->customer : ['name' => $vend->name],
                'day' => $vend->day,
                'failure_amount' => $vend->failure_amount,
                'failure_count' => $vend->failure_count,
                'gross_profit' => $vend->gross_profit,
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
                'year' => $vend->year,
            ]);
        }

        // also init the vends without transactions
        if($vendWithTransactions) {
            foreach($vendWithTransactions as $date => $vendIDs) {

                $vendWithoutTransactions = Vend::query()
                ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
                ->select(
                    'vends.id as id',
                    'vends.code as code',
                    'customers.operator_id',
                    'customers.id as customer_id'
                )
                ->where('customers.is_active', true)
                ->whereNotIn('vends.id', $vendIDs)
                ->get();

                foreach($vendWithoutTransactions as $vend) {
                    VendRecord::updateOrCreate([
                        'vend_id' => $vend->id,
                        'date' => Carbon::parse($date)->toDateString(),
                    ], [
                        'customer_id' => $vend->customer_id,
                        'customer_json' => isset($vend->customer_id) ? $vend->customer : ['name' => $vend->name],
                        'day' => Carbon::parse($date)->day,
                        'month' => Carbon::parse($date)->month,
                        'monthname' => Carbon::parse($date)->format('F'),
                        'operator_id' => $vend->operator_id,
                        'year' => Carbon::parse($date)->year,
                        'vend_code' => $vend->code,
                    ]);
                }
            }
        }

    }
}
