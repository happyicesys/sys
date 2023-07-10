<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendTransactionGraphResource;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use GetUserTimezone;

    public function index(Request $request)
    {
        $request->merge(['is_binded_customer' => isset($request->is_binded_customer) ? $request->is_binded_customer : true]);
        $request->merge(['operator_id' => isset($request->operator_id) ? $request->operator_id : auth()->user()->operator->id]);

        $day_date_from = Carbon::today()->setTimezone($this->getUserTimezone())->startOfMonth();
        $day_date_to = Carbon::today()->setTimezone($this->getUserTimezone())->endOfMonth();
        if($request->day_date_from) {
            $day_date_from = Carbon::parse($request->day_date_from)->setTimezone($this->getUserTimezone());
        }
        if($request->day_date_to) {
            $day_date_to = Carbon::parse($request->day_date_to)->setTimezone($this->getUserTimezone());
        }
        $today = Carbon::today()->setTimezone($this->getUserTimezone());

        // 2 months
        $dayGraph = VendRecord::query()
            ->where('date' , '>=', $day_date_from->copy()->subMonth()->startOfMonth()->startOfDay())
            ->where('date', '<=', $day_date_to->copy()->endOfDay())
            ->filterIndex($request)
            ->groupBy('date')
            ->select(
                DB::raw('month'),
                DB::raw('monthname AS month_name'),
                DB::raw('date'),
                DB::raw('day'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count'),
            );
        $todayGraph = VendTransaction::query()
            ->filterTransactionIndex($request)
            ->whereIn('error_code_normalized', [0, 6])
            ->where('created_at', '>=', Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay())
            ->where('created_at', '<=', Carbon::today()->setTimezone($this->getUserTimezone())->endOfDay())
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('MONTHNAME(created_at) AS month_name'),
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAY(created_at) as day'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(id) as count'),
            );
        $dayGraph = $dayGraph->union($todayGraph)
            ->orderBy('date', 'asc')
            ->get();

        // 7 days
        // products
        $seven_days_date_from = Carbon::today()->subDays(6)->setTimezone($this->getUserTimezone());
        $seven_days_date_to = Carbon::today()->setTimezone($this->getUserTimezone());
        $productGraph = VendTransaction::query()
            ->with('product:id,code,name')
            ->filterTransactionIndex($request)
            ->where('created_at', '>=', $seven_days_date_from->copy()->startOfDay())
            ->where('created_at', '<=', $seven_days_date_to->copy()->endOfDay())
            ->whereIn('error_code_normalized', [0, 6])
            ->groupBy('product_id')
            ->select(
                'id',
                DB::raw('product_id'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(id) as count'),
            )
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        $bestPerformer = VendTransaction::query()
            ->with([
                'customer:id,code,name',
                'product:id,code,name',
                'vend:id,code,name',
            ])
            ->filterTransactionIndex($request)
            ->where('created_at', '>=', $seven_days_date_from->copy()->startOfDay())
            ->where('created_at', '<=', $seven_days_date_to->copy()->endOfDay())
            ->whereIn('error_code_normalized', [0, 6])
            ->groupBy('vend_id')
            ->select(
                'id',
                'customer_id',
                'vend_id',
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(id) as count'),
            )
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();

        $monthGraph = VendRecord::query()
            ->where('date' , '>=', $today->copy()->subYear()->startOfYear()->startOfDay())
            ->where('date', '<=', $today->copy()->endOfYear()->endOfDay())
            ->filterIndex($request)
            ->groupBy('year', 'month')
            ->select(
                DB::raw('month'),
                DB::raw('monthname AS month_name'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count'),
            )
            ->get();

        // dd($monthGraph->toArray());

        return Inertia::render('Dashboard', [
            'dayGraphData' => VendTransactionGraphResource::collection($dayGraph),
            'monthGraphData' => VendTransactionGraphResource::collection($monthGraph),
            'productGraphData' => VendTransactionGraphResource::collection($productGraph),
            'performerGraphData' => VendTransactionGraphResource::collection($bestPerformer),
        ]);
    }
}
