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

    protected $dateFrom;
    protected $dateTo;
    /**
     * Create a new job instance.
     */
    public function __construct($dateFrom, $dateTo)
    {
        $this->dateFrom = $dateFrom ?? Carbon::today()->toDateString();
        $this->dateTo = $dateTo ?? Carbon::today()->toDateString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vends = VendTransaction::query()
            ->with('vend:id,code,name')
            ->leftJoin('delivery_platform_orders', 'vend_transactions.id', '=', 'delivery_platform_orders.vend_transaction_id')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->where('vend_transactions.created_at', '>=', Carbon::parse($this->dateFrom)->startOfDay())
            ->where('vend_transactions.created_at', '<=', Carbon::parse($this->dateTo)->endOfDay())
            // ->where('vends.is_active', true)
            // ->whereIn('vend_id', function($query) {
            //     $query->select('vend_id')
            //         ->from('vend_bindings')
            //         ->where('is_active', true);
            // })
            ->groupBy('date', 'vends.id')
            ->select(
                'vends.id AS vend_id',
                'vend_transactions.id',
                'customer_id',
                DB::raw('DATE(vend_transactions.created_at) as date'),
                DB::raw('DAY(vend_transactions.created_at) as day'),
                DB::raw('MONTH(vend_transactions.created_at) as month'),
                DB::raw('MONTHNAME(vend_transactions.created_at) AS month_name'),
                'operator_id',
                'vend_id',
                DB::raw('YEAR(vend_transactions.created_at) as year'),
                DB::raw(
                    'SUM(
                        CASE
                            WHEN error_code_normalized = 0 THEN amount
                            WHEN error_code_normalized = 6 THEN amount
                            ELSE 0
                        END
                    ) as total_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN error_code_normalized = 0 THEN vend_transactions.id
                            WHEN error_code_normalized = 6 THEN vend_transactions.id
                            ELSE NULL
                        END
                    ) as total_count'
                ),
                DB::raw(
                    'SUM(
                        CASE
                            WHEN error_code_normalized = 0 THEN 0
                            WHEN error_code_normalized = 6 THEN 0
                            ELSE amount
                        END
                    ) as failure_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN error_code_normalized = 0 THEN NULL
                            WHEN error_code_normalized = 6 THEN NULL
                            ELSE 1
                        END
                    ) as failure_count'
                ),
                DB::raw(
                    'SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN amount
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN amount
                            ELSE 0
                        END
                    ) as online_success_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN vend_transactions.id
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN vend_transactions.id
                            ELSE NULL
                        END
                    ) as online_success_count'
                ),
                DB::raw(
                    'SUM(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN 0
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN 0
                            ELSE amount
                        END
                    ) as online_failure_amount'
                ),
                DB::raw(
                    'COUNT(
                        CASE
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 0 THEN NULL
                            WHEN delivery_platform_orders.id IS NOT NULL AND error_code_normalized = 6 THEN NULL
                            ELSE 1
                        END
                    ) as online_failure_count'
                ),
            )
            ->get();


        foreach($vends as $vend) {
            VendRecord::updateOrCreate([
                'vend_id' => $vend->vend_id,
                'date' => $vend->date,
            ], [
                'customer_id' => isset($vend->customer_id) ? $vend->customer_id : null,
                'day' => $vend->day,
                'failure_amount' => $vend->failure_amount,
                'failure_count' => $vend->failure_count,
                'month' => $vend->month,
                'monthname' => $vend->month_name,
                'online_failure_amount' => $vend->online_failure_amount,
                'online_failure_count' => $vend->online_failure_count,
                'online_success_amount' => $vend->online_success_amount,
                'online_success_count' => $vend->online_success_count,
                'operator_id' => $vend->operator_id,
                'total_amount' => $vend->total_amount,
                'total_count' => $vend->total_count,
                'vend_code' => $vend->vend->code,
                'year' => $vend->year,
            ]);
        }
    }
}
