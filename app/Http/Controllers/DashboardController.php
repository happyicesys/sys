<?php

namespace App\Http\Controllers;

use App\Http\Resources\MonthResource;
use App\Http\Resources\OperatorResource;
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

    public function __construct()
    {
        $this->middleware(['permission:read dashboard'])->only('index');
    }

    public function index(Request $request)
    {
        $this->setDefaultOperators($request);
        $dayGraph = $this->getDayGraph($request);
        $productGraph = $this->getProductGraph($request);
        $bestPerformer = $this->getBestPerformer($request);
        $vendCount = $this->getVendCount($request);
        $monthGraphData = $this->getMonthGraphData($request);
        $activeMachineGraphData = $this->getActiveMachineGraphData($request);
        $monthlyAnalytics = $this->getMonthlyAnalytics($request);

        return Inertia::render('Dashboard', [
            'activeMachineGraphData' => $activeMachineGraphData,
            'categories' => OptionResource::collection(
                Category::toBase()->select('id', 'name')->orderBy('name')->get()
            ),
            'categoryGroups' => OptionResource::collection(
                CategoryGroup::toBase()->select('id', 'name')->orderBy('name')->get()
            ),
            'dayGraphData' => VendTransactionGraphResource::collection($dayGraph),
            'locationTypeOptions' => OptionResource::collection(
                LocationType::toBase()->select('id', 'name')->orderBy('sequence')->get()
            ),
            'monthGraphData' => $monthGraphData,
            'months' => MonthResource::collection(Month::all()),
            'monthsByModel' => $monthlyAnalytics,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productGraphData' => VendTransactionGraphResource::collection($productGraph),
            'performerGraphData' => VendTransactionGraphResource::collection($bestPerformer),
            'vendCount' => $vendCount,
        ]);
    }

    private function setDefaultOperators(Request $request)
    {
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $operatorHIMD = Operator::where('code', 'HIMD')->first();
                $request->merge(['operators' => [auth()->user()->operator_id, $operatorHIMD ? $operatorHIMD->id : null]]);
            }else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
    }

    private function getDayGraph(Request $request)
    {
        $day_date_from = $request->day_date_from ? Carbon::parse($request->day_date_from)->setTimezone($this->getUserTimezone()) : Carbon::today()->startOfMonth()->setTimezone($this->getUserTimezone());
        $day_date_to = $request->day_date_to ? Carbon::parse($request->day_date_to)->setTimezone($this->getUserTimezone()) : Carbon::today()->endOfMonth()->setTimezone($this->getUserTimezone());

        $dayGraph = VendRecord::query()
            ->whereBetween('date', [$day_date_from->copy()->subMonth()->startOfDay(), $day_date_to->copy()->endOfDay()])
            ->filterIndex($request)
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->groupBy('date')
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('MONTHNAME(date) as month_name'),
                DB::raw('DATE(date) as date'),
                DB::raw('DAY(date) as day'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count')
            );

        $todayGraph = VendTransaction::query()
            ->filterTransactionIndex($request)
            ->where(function ($query) {
                $query->where('error_code_normalized', 0)
                    ->orWhere('error_code_normalized', 6)
                    ->orWhereNull('error_code_normalized')
                    ->orWhere('is_multiple', true);
            })
            ->whereBetween('transaction_datetime', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->select(
                DB::raw('MONTH(transaction_datetime) as month'),
                DB::raw('MONTHNAME(transaction_datetime) as month_name'),
                DB::raw('DATE(transaction_datetime) as date'),
                DB::raw('DAY(transaction_datetime) as day'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(id) as count')
            );

        $dayGraph = $dayGraph->union($todayGraph)
            ->orderBy('date', 'asc')
            ->get();

        return $this->fillEmptyDates($dayGraph, Carbon::today()->subMonth()->startOfMonth(), Carbon::today());
    }

    private function fillEmptyDates($dayGraph, $startDate, $endDate)
    {
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $found = false;
            foreach ($dayGraph as $graphDayValue) {
                if ($graphDayValue->day === $currentDate->day && $graphDayValue->month === $currentDate->month) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $newModel = new VendRecord();
                $newModel->amount = 0;
                $newModel->count = 0;
                $newModel->date = $currentDate->copy()->startOfDay();
                $newModel->day = $currentDate->copy()->day;
                $newModel->month = $currentDate->copy()->month;
                $newModel->month_name = $currentDate->copy()->format('F');
                $dayGraph->push($newModel);
            }

            $currentDate->addDay();
        }

        return $dayGraph->sortBy('date');
    }

    private function getProductGraph(Request $request)
    {
        $seven_days_date_from = Carbon::today()->subDays(6)->setTimezone($this->getUserTimezone());
        $seven_days_date_to = Carbon::today()->setTimezone($this->getUserTimezone());

        return VendTransaction::query()
            ->with(['customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'product:id,code,name'])
            ->filterTransactionIndex($request)
            ->whereBetween('transaction_datetime', [$seven_days_date_from->startOfDay(), $seven_days_date_to->endOfDay()])
            ->whereIn('error_code_normalized', [0, 6])
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->groupBy('product_id')
            ->select(
                'id',
                DB::raw('product_id'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('COUNT(id) as count')
            )
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
    }

    private function getBestPerformer(Request $request)
    {
        return VendRecord::query()
            ->with(['customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend:id,code,name'])
            ->filterIndex($request)
            ->whereBetween('date', [Carbon::today()->copy()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()])
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->groupBy('vend_id')
            ->select(
                'id',
                'customer_id',
                'vend_id',
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count')
            )
            ->orderBy('amount', 'desc')
            ->limit(10)
            ->get();
    }

    private function getVendCount(Request $request)
    {
        return VendRecord::query()
            ->filterIndex($request)
            ->whereDate('date', Carbon::yesterday())
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->count();
    }

    private function getMonthGraphData(Request $request)
    {
        $lastYear = Carbon::today()->subYear()->startOfYear();
        $thisYear = Carbon::today()->endOfYear();

        $monthsArrInit = [];
        foreach ([$lastYear->year, $thisYear->year] as $year) {
            for ($i = 1; $i <= 12; $i++) {
                if ($year == Carbon::today()->year && $i > Carbon::today()->month) {
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
            ->whereBetween('date', [$lastYear->startOfDay(), $thisYear->endOfDay()])
            ->filterIndex($request)
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->groupBy('year', 'month')
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('MONTHNAME(date) as month_name'),
                DB::raw('YEAR(date) as year'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count')
            )
            ->orderBy('month', 'asc')
            ->get();

        foreach ($monthGraph as $month) {
            $monthsArrInit[$month->year][$month->month]['amount'] = $month->amount / 100;
            $monthsArrInit[$month->year][$month->month]['count'] = $month->count;
        }

        return collect($monthsArrInit);
    }

    private function getActiveMachineGraphData(Request $request)
    {
        $lastYear = Carbon::today()->subYear()->startOfYear();
        $thisYear = Carbon::today()->endOfYear();

        $activeMonths = [];
        foreach ([$lastYear->year, $thisYear->year] as $year) {
            for ($i = 1; $i <= 12; $i++) {
                if ($year == Carbon::today()->year && $i > Carbon::today()->month) {
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

        $activeMachineGraph = VendRecord::query()
            ->whereBetween('date', [$lastYear->startOfDay(), $thisYear->endOfDay()])
            ->whereIn('date', function ($query) {
                $query->select(DB::raw('MAX(date)'))->from('vend_records')->groupBy('year', 'month');
            })
            ->filterIndex($request)
            ->whereNotIn('vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->select(
                'date',
                DB::raw('MONTH(date) as month'),
                DB::raw('MONTHNAME(date) as monthname'),
                DB::raw('YEAR(date) as year'),
                DB::raw('COUNT(vend_id) AS count')
            )
            ->groupBy('year', 'month')
            ->orderBy('month', 'asc')
            ->get();

        foreach ($activeMachineGraph as $activeMachine) {
            $activeMonths[$activeMachine->year][$activeMachine->month]['count'] = $activeMachine->count;
        }

        return $activeMonths;
    }

    private function getMonthlyAnalytics(Request $request)
    {
        $request->merge([
            'monthlyDateFrom' => Carbon::today()->startOfYear()->startOfDay(),
            'monthlyDateTo' => Carbon::today()->endOfYear()->endOfDay(),
            'monthlyTypeName' => $request->monthlyTypeName ?? 'location-type'
        ]);

        $modelName = $this->getModelName($request->monthlyTypeName);
        $items = $this->getMonthlySalesQuery($request, $modelName)->get();

        $monthsByModel = [];
        $months = Month::all();
        $currentMonthNumber = Carbon::today()->month;

        foreach ($items as $item) {
            foreach ($months as $month) {
                if ($item->id && $item->month == $month->number) {
                    $monthsByModel[$item->name][$month->number] = [
                        'current' => $currentMonthNumber == $month->number,
                        'month_short_name' => $month->short_name,
                        'amount' => $item->amount ? $item->amount / 100 : 0,
                        'vend_count' => $item->count ? $item->count : 0,
                        'average' => $item->average / 100 ? $item->average / 100 : 0,
                    ];
                }
                if (!$item->id && $item->month == $month->number) {
                    $monthsByModel['Undefined'][$month->number] = [
                        'current' => $currentMonthNumber == $month->number,
                        'month_short_name' => $month->short_name,
                        'amount' => $item->amount ? $item->amount / 100 : 0,
                        'vend_count' => $item->count ? $item->count : 0,
                        'average' => $item->average / 100 ? $item->average / 100 : 0,
                    ];
                }
            }
        }

        return collect($monthsByModel)->sortKeys();
    }

    private function getModelName($monthlyTypeName)
    {
        switch ($monthlyTypeName) {
            case 'category':
                return 'categories';
            case 'location-type':
                return 'location_types';
            case 'operator':
                return 'operators';
            default:
                return 'location_types';
        }
    }

    private function getMonthlySalesQuery($request, $className)
    {
        $vendRecords = VendRecord::query()
            ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
            ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
            ->whereBetween('vend_records.date', [Carbon::parse($request->monthlyDateFrom), Carbon::parse($request->monthlyDateTo)])
            ->filterIndex($request)
            ->whereNotIn('vend_records.vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            })
            ->select('vend_records.date', DB::raw('COUNT(DISTINCT(vend_records.vend_id)) as count'));

        switch ($className) {
            case 'categories':
                $vendRecords->selectRaw('categories.id as id');
                break;
            case 'location_types':
                $vendRecords->selectRaw('location_types.id as id');
                break;
            case 'operators':
                $vendRecords->selectRaw('operators.id as id');
                break;
        }

        $vendRecords = $vendRecords->groupBy('id', 'vend_records.date');

        $query = VendRecord::query()
            ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
            ->leftJoin('location_types', 'customers.location_type_id', '=', 'location_types.id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
            ->leftJoinSub($vendRecords, 'x', function ($join) use ($className) {
                switch ($className) {
                    case 'categories':
                        $join->on('categories.id', '=', 'x.id');
                        break;
                    case 'location_types':
                        $join->on('location_types.id', '=', 'x.id');
                        break;
                    case 'operators':
                        $join->on('operators.id', '=', 'x.id');
                        break;
                }
                $join->on('vend_records.date', '=', 'x.date');
            })
            ->whereBetween('vend_records.date', [Carbon::parse($request->monthlyDateFrom), Carbon::parse($request->monthlyDateTo)])
            ->filterIndex($request)
            ->whereNotIn('vend_records.vend_id', function ($query) {
                $query->select('id')->from('vends')->where('is_testing', true);
            });

        switch ($className) {
            case 'categories':
                $query->selectRaw('categories.id as id')->selectRaw('categories.name as name');
                break;
            case 'location_types':
                $query->selectRaw('location_types.id as id')->selectRaw('location_types.name as name');
                break;
            case 'operators':
                $query->selectRaw('operators.id as id')->selectRaw('operators.name as name');
                break;
        }

        $query->selectRaw('SUM(vend_records.total_count) AS count')
            ->selectRaw('SUM(vend_records.total_amount) AS amount')
            ->selectRaw('COUNT(DISTINCT(vend_records.vend_id)) AS vend_count')
            ->selectRaw('AVG(vend_records.total_amount) AS average')
            ->selectRaw('vend_records.month')
            ->selectRaw('ROUND(AVG(x.count), 2) AS count')
            ->groupBy('id', 'vend_records.month')
            ->orderBy('name', 'asc');

        return $query;
    }
}
