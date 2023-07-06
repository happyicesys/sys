<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendTransactionGraphResource;
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

        $dayGraph = VendTransaction::query()
            ->whereIn('id', function($query) use ($day_date_from, $day_date_to) {
                $query->select('id')
                ->from('vend_transactions')
                ->where('created_at', '>=', $day_date_from->copy()->subMonth()->startOfMonth()->startOfDay())
                ->where('created_at', '<=', $day_date_to->copy()->endOfDay());
            })
            ->filterTransactionIndex($request)
            ->whereIn('error_code_normalized', [0, 6])
            ->groupBy('date')
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('MONTHNAME(created_at) AS month_name'),
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAY(created_at) as day'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(id) as count'),
            )
            ->get();

        // $dayGraphDataArr = [];
        // $dayGraph->each(function($item) use (&$dayGraphDataArr) {
        //     $dayGraphDataArr[$item->month_name][$item->day] = [
        //         'day' => $item->day,
        //         'amount' => $item->amount/ 100,
        //         'count' => $item->count,
        //     ];
        // });

        return Inertia::render('Dashboard', [
            'dayGraphData' => VendTransactionGraphResource::collection($dayGraph),
            // 'dayGraphDataArr' => $dayGraphDataArr,
        ]);
    }
}
