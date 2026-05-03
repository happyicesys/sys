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

        $itemsSub = DB::table('vend_transaction_items as vti')
            ->select(
                'vend_transaction_id',
                DB::raw('SUM(COALESCE(vti.unit_cost, 0)) as total_cost'),
                DB::raw('COUNT(CASE WHEN NOT (vti.vend_channel_error_code IN (0, 6) OR vti.vend_channel_error_code IS NULL) THEN 1 ELSE NULL END) as item_error_count'),
                DB::raw('COUNT(CASE WHEN vti.vend_channel_error_code IN (0, 6) OR vti.vend_channel_error_code IS NULL THEN 1 ELSE NULL END) as success_item_count'),
                DB::raw('COUNT(*) as total_item_count')
            )
            ->groupBy('vend_transaction_id');

        $query = DB::table('vend_transactions as vt')
            ->join('vends as v', 'vt.vend_id', '=', 'v.id')
            ->leftJoin('vend_channel_errors as vce', 'vt.vend_channel_error_id', '=', 'vce.id')
            ->leftJoinSub($itemsSub, 'vti', 'vt.id', '=', 'vti.vend_transaction_id')
            ->leftJoin('delivery_platform_orders as dpo', 'vt.id', '=', 'dpo.vend_transaction_id')
            ->leftJoin('customers as c', 'vt.customer_id', '=', 'c.id')
            ->leftJoin('location_types as lt', 'c.location_type_id', '=', 'lt.id')
            ->whereBetween('vt.transaction_datetime', [
                Carbon::parse($this->from)->setTimezone($timezone)->startOfDay(),
                Carbon::parse($this->to)->setTimezone($timezone)->endOfDay()
            ])
            ->where('vt.amount', '>', 0)
            ->select(
                'v.id AS vend_id',
                'v.code as vend_code',
                'v.vend_model_id',
                'v.vend_prefix_id',
                'c.id AS customer_id',
                'lt.id AS location_type_id',
                'vt.operator_id',
                DB::raw("DATE(vt.transaction_datetime) as date"),
                DB::raw("DAY(vt.transaction_datetime) as day"),
                DB::raw("MONTH(vt.transaction_datetime) as month"),
                DB::raw("MONTHNAME(vt.transaction_datetime) AS monthname"),
                DB::raw("YEAR(vt.transaction_datetime) as year"),

                // Maps exactly to "Total Revenue" on Transactions page
                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = true THEN vt.amount
                    WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6) THEN vt.amount
                    ELSE 0 END) as total_amount'),

                // Maps to "Total Qty Purchased"
                DB::raw('SUM(vt.qty) as all_total_count'),

                // Maps to "Total Qty Dispensed"
                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = true THEN COALESCE(vti.success_item_count, 0)
                    WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6) THEN COALESCE(vt.qty, 1)
                    ELSE 0 END) as total_count'),

                // Proper error count mapping
                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = true THEN COALESCE(vti.item_error_count, 0)
                    WHEN vt.vend_channel_error_id IS NOT NULL AND vce.code NOT IN (0, 6) THEN COALESCE(vt.qty, 1)
                    ELSE 0 END) as error_count'),

                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = true THEN vt.revenue
                    WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6) THEN vt.revenue
                    ELSE 0 END) as revenue'),

                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = true THEN vt.revenue - COALESCE(vti.total_cost, 0)
                    WHEN vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6) THEN vt.gross_profit
                    ELSE 0 END) as gross_profit'),

                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = false AND vt.vend_channel_error_id IS NOT NULL AND vce.code NOT IN (0, 6) THEN vt.amount
                    ELSE 0 END) as failure_amount'),

                DB::raw('SUM(CASE
                    WHEN vt.is_multiple = false AND vt.vend_channel_error_id IS NOT NULL AND vce.code NOT IN (0, 6) THEN COALESCE(vt.qty, 1)
                    ELSE 0 END) as failure_count'),

                DB::raw('SUM(CASE
                    WHEN dpo.id IS NOT NULL AND (vt.is_multiple = true OR vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6)) THEN vt.amount
                    ELSE 0 END) as online_success_amount'),

                DB::raw('SUM(CASE
                    WHEN dpo.id IS NOT NULL AND (vt.is_multiple = true OR vt.vend_channel_error_id IS NULL OR vce.code IN (0, 6)) THEN COALESCE(vt.qty, 1)
                    ELSE 0 END) as online_success_count'),

                DB::raw('SUM(CASE
                    WHEN dpo.id IS NOT NULL AND vt.is_multiple = false AND vt.vend_channel_error_id IS NOT NULL AND vce.code NOT IN (0, 6) THEN vt.amount
                    ELSE 0 END) as online_failure_amount'),

                DB::raw('SUM(CASE
                    WHEN dpo.id IS NOT NULL AND vt.is_multiple = false AND vt.vend_channel_error_id IS NOT NULL AND vce.code NOT IN (0, 6) THEN COALESCE(vt.qty, 1)
                    ELSE 0 END) as online_failure_count')
            )
            ->groupBy('date', 'v.id', 'c.id');

        $results = $query->get();
        $vendWithTransactions = [];

        foreach ($results as $row) {
            $vendWithTransactions[$row->date][] = $row->vend_id;

            VendRecord::updateOrCreate(
                [
                    'vend_id' => $row->vend_id,
                    'date' => $row->date,
                    'customer_id' => $row->customer_id,
                ],
                get_object_vars($row)
            );
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
                        'month' => Carbon::parse($date)->month,
                        'monthname' => Carbon::parse($date)->monthName,
                        'year' => Carbon::parse($date)->year,
                        'operator_id' => $vend->operator_id,
                        'vend_code' => $vend->code,
                        'vend_prefix_id' => $vend->vend_prefix_id,
                        'location_type_id' => $vend->location_type_id,
                        'total_amount' => 0,
                        'total_count' => 0,
                        'all_total_count' => 0,
                        'error_count' => 0,
                        'revenue' => 0,
                        'gross_profit' => 0,
                        'failure_amount' => 0,
                        'failure_count' => 0,
                        'online_success_amount' => 0,
                        'online_success_count' => 0,
                        'online_failure_amount' => 0,
                        'online_failure_count' => 0,
                    ]);
                }
            }
        }
    }
}
