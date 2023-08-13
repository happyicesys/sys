<?php

namespace App\Http\Controllers;

use App\Http\Resources\MonthResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\VendTransactionGraphResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Month;
use App\Models\Operator;
use App\Models\Vend;
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
        $className = get_class(new Customer());
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

        $bestPerformer = VendRecord::query()
            ->with([
                'customer:id,code,name',
                'vend:id,code,name',
            ])
            ->filterIndex($request)
            ->where('date', '>=', $today->copy()->subDays(29)->startOfDay())
            ->where('date', '<=', $today->copy()->endOfDay())
            ->groupBy('vend_id')
            ->select(
                'id',
                'customer_id',
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count'),
            )
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();

        $vendCount = VendRecord::query()
            ->filterIndex($request)
            ->whereDate('date', '=', $today->copy()->subDay())
            ->count();

        // 2 years
        $lastYear = $today->copy()->subYear()->startOfYear();
        $thisYear = $today->copy()->endOfYear()->endOfDay();
        $monthsArrInit = [];
        $yearsArrInit = [
            $lastYear->copy()->year,
            $thisYear->copy()->year,
        ];
        foreach($yearsArrInit as $year) {
            for($i = 1; $i <= 12; $i++) {

                if($today->copy()->year == $year && $i > $today->copy()->month) {
                    continue;
                }

                $monthsArrInit[$year][$i] = [
                    'month' => $i,
                    'month_name' => Carbon::createFromDate($year, $i, 1)->format('F'),
                    'year' => $year,
                    'amount' => 0,
                    'count' => 0,
                ];
            }
        }

        $monthGraph = VendRecord::query()
            ->where('date' , '>=', $lastYear->copy()->startOfDay())
            ->where('date', '<=', $thisYear->copy()->endOfDay())
            ->filterIndex($request)
            ->groupBy('year', 'month')
            ->select(
                DB::raw('month'),
                DB::raw('monthname AS month_name'),
                DB::raw('year'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count'),
            )
            ->orderBy('month', 'asc')
            ->get();

        foreach($monthGraph as $month) {
            $monthsArrInit[$month->year][$month->month]['amount'] = $month->amount/ 100;
            $monthsArrInit[$month->year][$month->month]['count'] = $month->count;
        }

        // 2 years
        $activeYears = [
            $lastYear->copy()->year,
            $thisYear->copy()->year,
        ];
        $activeMonths = [];
        $activeMachineGraph = VendRecord::query()
            ->where('date' , '>=', $lastYear->copy()->startOfDay())
            ->where('date', '<=', $thisYear->copy()->endOfDay())
            ->whereIn('date', function($query) {
                $query->select(DB::raw('MAX(date)'))
                    ->from('vend_records')
                    ->groupBy('year', 'month');
            })
            ->filterIndex($request)
            ->select(
                'date',
                'month',
                'monthname',
                DB::raw('COUNT(vend_id) AS count'),
                'year'
            )
            ->groupBy('year', 'month')
            ->orderBy('month', 'asc')
            ->get();

        foreach($activeYears as $year) {
            for($i = 1; $i <= 12; $i++) {

                if($today->copy()->year == $year && $i > $today->copy()->month) {
                    continue;
                }

                $activeMonths[$year][$i] = [
                    'month' => $i,
                    'month_name' => Carbon::createFromDate($year, $i, 1)->format('F'),
                    'year' => $year,
                    'count' => 0,
                ];
            }
        }
        foreach($activeMachineGraph as $activeMachine) {
            $activeMonths[$activeMachine->year][$activeMachine->month]['count'] = $activeMachine->count;
        }


        // monthly within 1 year by different criteria
        $request->merge(['monthlyDateFrom' => Carbon::today()->setTimezone($this->getUserTimezone())->startOfYear()->startOfDay()]);
        $request->merge(['monthlyDateTo' => Carbon::today()->setTimezone($this->getUserTimezone())->endOfYear()->endOfDay()]);
        $request->merge(['monthlyTypeName' => $request->monthlyTypeName ?? 'location-type']);
        $modelName = '';
        switch($request->monthlyTypeName) {
            case 'category':
                $modelName = 'categories';
                break;
            case 'location-type':
                $modelName = 'location_types';
                break;
            case 'operator':
                $modelName = 'operators';
                break;
        }
        $items = $this->getMonthlySalesQuery($request, $modelName);
        $items = $items->get();

        $monthsByModel = [];
        $months = Month::all();
        $currentMonthNumber = Carbon::today()->month;

        foreach($items as $item) {
            foreach($months as $month) {
                if($item->id and $item->month == $month->number) {
                    $monthsByModel[$item->name][$month->number] =
                    [
                        'current' => $currentMonthNumber == $month->number ? true : false,
                        'month_short_name' => $month->short_name,
                        'amount' => $item->amount ? $item->amount/ 100 : 0,
                        'vend_count' => $item->vend_count ? $item->vend_count : 0,
                        'average' => $item->average/ 100 ? $item->average/ 100 : 0,
                    ];
                }
                if(!$item->id and $item->month == $month->number) {
                    $monthsByModel['Undefined'][$month->number] =
                    [
                        'current' => $currentMonthNumber == $month->number ? true : false,
                        'month_short_name' => $month->short_name,
                        'amount' => $item->amount ? $item->amount/ 100 : 0,
                        'vend_count' => $item->vend_count ? $item->vend_count : 0,
                        'average' => $item->average/ 100 ? $item->average/ 100 : 0,
                    ];
                }
            }
        }
        $monthsByModel = collect($monthsByModel)->sortKeys();

        return Inertia::render('Dashboard', [
            'activeMachineGraphData' => $activeMonths,
            'categories' => OptionResource::collection(
                Category::toBase()->where('classname', $className)->select('id', 'name')->orderBy('name')->get()
            ),
            'categoryGroups' => OptionResource::collection(
                CategoryGroup::toBase()->where('classname', $className)->select('id', 'name')->orderBy('name')->get()
            ),
            'dayGraphData' => VendTransactionGraphResource::collection($dayGraph),
            'locationTypeOptions' => OptionResource::collection(
                LocationType::toBase()->select('id', 'name')->orderBy('sequence')->get()
            ),
            'monthGraphData' => collect($monthsArrInit),
            'months' => MonthResource::collection($months),
            'monthsByModel' => $monthsByModel,
            'operatorOptions' => OptionResource::collection(
                Operator::toBase()->select('id', 'code', 'name')->orderBy('name')->get()
            ),
            'productGraphData' => VendTransactionGraphResource::collection($productGraph),
            'performerGraphData' => VendTransactionGraphResource::collection($bestPerformer),
            'vendCount' => $vendCount,
        ]);
    }


    private function getMonthlySalesQuery($request, $className)
    {
        $query = VendRecord::query()
            ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
            ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
            ->where('vend_records.date', '>=', Carbon::parse($request->monthlyDateFrom))
            ->where('vend_records.date', '<=', Carbon::parse($request->monthlyDateTo))
            ->filterIndex($request);

        switch($className) {
            case 'categories':
                $query
                    ->selectRaw('categories.id as id')
                    ->selectRaw('categories.name as name');
                break;
            case 'location_types':
                $query
                    ->selectRaw('location_types.id as id')
                    ->selectRaw('location_types.name as name');
                break;
            case 'operators':
                $query
                    ->selectRaw('operators.id as id')
                    ->selectRaw('operators.name as name');
                break;
        }

        $query = $query
            ->selectRaw('SUM(total_count) AS count')
            ->selectRaw('SUM(total_amount) AS amount')
            ->selectRaw('COUNT(DISTINCT(vend_id)) AS vend_count')
            ->selectRaw('AVG(total_amount) AS average')
            ->selectRaw('month');

        $query = $query->groupBy('id', 'month')
            ->orderBy('name', 'asc')
            ->orderBy('month', 'asc');

        return $query;
    }
}
