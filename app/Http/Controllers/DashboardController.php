<?php

namespace App\Http\Controllers;

use App\Http\Resources\MonthResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendTransactionGraphResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Month;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
        // $this->middleware(['permission:read dashboard'])->only('index');
    }

    public function index(Request $request)
    {
        $this->setDefaultOperators($request);
        $bestPerformerLimit = (int) $request->input('best_performer_limit', $request->input('performer_limit', 20));
        $bestPerformerLimit = max(1, min(50, $bestPerformerLimit));
        $worstPerformerLimit = (int) $request->input('worst_performer_limit', 20);
        $worstPerformerLimit = max(1, min(50, $worstPerformerLimit));
        $dayGraph = $this->getDayGraph($request);
        $productGraph = $this->getProductGraph($request);
        $bestPerformer = $this->getBestPerformer($request, $bestPerformerLimit);
        $worstPerformer = $this->getWorstPerformer($request, $worstPerformerLimit);
        $vendCount = $this->getVendCount($request);
        $monthGraphData = $this->getMonthGraphData($request);
        // $monthGraphData = Cache::remember(
        //     'month_graph_data_' . auth()->id(),
        //     300, // cache duration in seconds (5 minutes)
        //     fn () => $this->getMonthGraphData($request)
        // );

        $activeMachineGraphData = $this->getActiveMachineGraphData($request);
        $monthlyAnalytics = $this->getMonthlyAnalytics($request);

        return Inertia::render('Dashboard', [
            'activeMachineGraphData' => $activeMachineGraphData,
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
            'performerLimit' => $bestPerformerLimit,
            'worstPerformerGraphData' => VendTransactionGraphResource::collection($worstPerformer),
            'worstPerformerLimit' => $worstPerformerLimit,
            'vendCount' => $vendCount,
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
            ),
        ]);
    }

    private function setDefaultOperators(Request $request)
    {
        if (!$request->operators) {
            if(auth()->user()->operator->code == 'HIPL') {
                $request->merge(['operators' => [
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ]]);
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
            });

            // dd($dayGraph->get()->toArray());
            $dayGraph->groupBy('date')
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
                DB::raw('SUM(success_qty) as count')
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

    private function getBestPerformer(Request $request, int $limit)
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
            ->limit($limit)
            ->get();
    }

    private function getWorstPerformer(Request $request, int $limit)
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
            ->orderBy('amount', 'asc')
            ->limit($limit)
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

        // Subquery: latest date per (year, month)
        $latestSub = DB::table('vend_records')
            ->selectRaw('MAX(date) as latest_date, year, month')
            ->groupBy('year', 'month');

        $activeMachineGraph = DB::table('vend_records')
            ->joinSub($latestSub, 'latest', function ($join) {
                $join->on('vend_records.date', '=', 'latest.latest_date')
                     ->on('vend_records.year', '=', 'latest.year')
                     ->on('vend_records.month', '=', 'latest.month');
            })
            ->whereBetween('vend_records.date', [$lastYear->startOfDay(), $thisYear->endOfDay()])
            ->whereNotIn('vend_records.vend_id', function ($query) {
                $query->select('id')
                    ->from('vends')
                    ->where(function ($query) {
                        $query->where('is_testing', true)
                            ->orWhereNull('customer_id');
                    });
            })
            ->when(method_exists(new \App\Models\VendRecord, 'scopeFilterIndex'), function ($query) use ($request) {
                // Apply filterIndex() scope only if it exists
                \App\Models\VendRecord::applyScope($query, 'filterIndex', $request);
            })
            ->when($request->operators, function ($query) use ($request) {
                $query->whereIn('vend_records.operator_id', $request->operators);
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                    $query->whereIn('vend_records.vend_id', function ($subQuery) use ($search) {
                        $subQuery->select('id')
                            ->from('vends')
                            ->whereIn('code', $search);
                    });
                } else {
                    $query->whereIn('vend_records.vend_id', function ($subQuery) use ($search) {
                        $subQuery->select('id')
                            ->from('vends')
                            ->where('code', 'like', '%' . $search . '%');
                    });
                }
            })
            ->when($request->customer, function ($query, $search) {
                if (strpos($search, '-') !== false) {
                    $searchArray = explode('-', $search);
                    $query->whereIn('vend_records.customer_id', function ($subQuery) use ($searchArray) {
                        $subQuery->select('id')
                            ->from('customers')
                            ->where('virtual_customer_prefix', $searchArray[0])
                            ->where('virtual_customer_code', 'like', "{$searchArray[1]}%");
                    });
                } else {
                    $query->whereIn('vend_records.customer_id', function ($subQuery) use ($search) {
                        $subQuery->select('id')
                            ->from('customers')
                            ->where('virtual_customer_prefix', 'like', "{$search}%")
                            ->orWhere('virtual_customer_code', 'like', "{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                }
            })
            ->when($request->vendModels, function($query, $search) {
                if(!in_array('all', $search)){
                    $query->whereIn('vend_records.vend_model_id', $search);
                }
            })
            ->when($request->vendPrefixes, function($query, $search) {
                if(!in_array('all', $search)){
                    if(in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_records.vend_prefix_id', $search);
                }
            })
            ->when($request->locationType, function($query, $search) use ($request) {
                // dd($request->all(), $search);
                if($search != 'all') {
                    $query->where('vend_records.location_type_id', $search);
                }
            })
            ->select(
                'vend_records.date',
                DB::raw('MONTH(vend_records.date) as month'),
                DB::raw('MONTHNAME(vend_records.date) as monthname'),
                DB::raw('YEAR(vend_records.date) as year'),
                DB::raw('COUNT(vend_records.vend_id) as count')
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

        // Generate a unique cache key based on request filters
        // $cacheKey = 'monthly_analytics_' . auth()->id() . '_' . md5(json_encode([
        //     $request->monthlyDateFrom,
        //     $request->monthlyDateTo,
        //     $request->monthlyTypeName,
        //     $request->operators,
        //     $request->locationType,
        //     $request->vendPrefixes,
        //     $request->customer,
        // ]));

        // Use Cache::remember to store for 5 minutes (adjustable)
        // $items = Cache::remember($cacheKey, 300, function () use ($request) {
        //     $modelName = $this->getModelName($request->monthlyTypeName);
        //     return $this->getMonthlySalesQuery($request, $modelName)->get();
        // });

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
                        'vend_count' => $item->count ?? 0,
                        'average' => $item->average ? $item->average / 100 : 0,
                    ];
                }
                if (!$item->id && $item->month == $month->number) {
                    $monthsByModel['Undefined'][$month->number] = [
                        'current' => $currentMonthNumber == $month->number,
                        'month_short_name' => $month->short_name,
                        'amount' => $item->amount ? $item->amount / 100 : 0,
                        'vend_count' => $item->count ?? 0,
                        'average' => $item->average ? $item->average / 100 : 0,
                    ];
                }
            }
        }

        return collect($monthsByModel)->sortKeys();
    }


    private function getModelName($monthlyTypeName)
    {
        switch ($monthlyTypeName) {
            // case 'category':
            //     return 'categories';
            case 'location-type':
                return 'location_types';
            case 'operator':
                return 'operators';
            default:
                return 'location_types';
        }
    }

    // private function getMonthlySalesQuery($request, $className)
    // {
    //     $vendRecords = VendRecord::query()
    //         ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
    //         ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
    //         ->leftJoin('location_types', 'vend_records.location_type_id', '=', 'location_types.id')
    //         ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
    //         ->whereBetween('vend_records.date', [Carbon::parse($request->monthlyDateFrom), Carbon::parse($request->monthlyDateTo)])
    //         ->filterIndex($request)
    //         ->whereNotIn('vend_records.vend_id', function ($query) {
    //             $query->select('id')->from('vends')->where('is_testing', true);
    //         })
    //         ->select('vend_records.date', DB::raw('COUNT(DISTINCT(vend_records.vend_id)) as count'));

    //     switch ($className) {
    //         case 'location_types':
    //             $vendRecords->selectRaw('location_types.id as id');
    //             break;
    //         case 'operators':
    //             $vendRecords->selectRaw('operators.id as id');
    //             break;
    //     }

    //     $vendRecords = $vendRecords->groupBy('id', 'vend_records.date');

    //     $query = VendRecord::query()
    //         ->leftJoin('vends', 'vend_records.vend_id', '=', 'vends.id')
    //         ->leftJoin('customers', 'customers.id', '=', 'vend_records.customer_id')
    //         ->leftJoin('location_types', 'vend_records.location_type_id', '=', 'location_types.id')
    //         // ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
    //         // ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
    //         ->leftJoin('operators', 'operators.id', '=', 'vend_records.operator_id')
    //         ->leftJoinSub($vendRecords, 'x', function ($join) use ($className) {
    //             switch ($className) {
    //                 case 'location_types':
    //                     $join->on('location_types.id', '=', 'x.id');
    //                     break;
    //                 case 'operators':
    //                     $join->on('operators.id', '=', 'x.id');
    //                     break;
    //             }
    //             $join->on('vend_records.date', '=', 'x.date');
    //         })
    //         ->whereBetween('vend_records.date', [Carbon::parse($request->monthlyDateFrom), Carbon::parse($request->monthlyDateTo)])
    //         ->filterIndex($request)
    //         ->whereNotIn('vend_records.vend_id', function ($query) {
    //             $query->select('id')->from('vends')->where('is_testing', true);
    //         });

    //     switch ($className) {
    //         case 'location_types':
    //             $query->selectRaw('location_types.id as id')->selectRaw('location_types.name as name');
    //             break;
    //         case 'operators':
    //             $query->selectRaw('operators.id as id')->selectRaw('operators.name as name');
    //             break;
    //     }

    //     $query
    //         // ->selectRaw('SUM(vend_records.total_count) AS count')
    //         ->selectRaw('SUM(vend_records.total_amount) AS amount')
    //         ->selectRaw('COUNT(DISTINCT(vend_records.vend_id)) AS vend_count')
    //         ->selectRaw('AVG(vend_records.total_amount) AS average')
    //         ->selectRaw('vend_records.month')
    //         ->selectRaw('ROUND(AVG(x.count), 2) AS count')
    //         ->groupBy('id', 'vend_records.month')
    //         ->orderBy('name', 'asc');

    //     return $query;
    // }

    private function getMonthlySalesQuery($request, $className)
{
    $dateFrom = Carbon::parse($request->monthlyDateFrom);
    $dateTo = Carbon::parse($request->monthlyDateTo);

    // Subquery: daily active vend count per id (location_type_id/operator) & date
    $dailyActive = VendRecord::query()
        ->selectRaw('vend_records.location_type_id as location_type_id')
        ->selectRaw('vend_records.operator_id as operator_id')
        ->selectRaw('vend_records.date as date')
        ->selectRaw('COUNT(DISTINCT vend_records.vend_id) as daily_active_count')
        ->leftJoin('vends as v2', function($join) {
            $join->on('vend_records.vend_id', '=', 'v2.id')
                ->where('v2.is_testing', true);
        })
        ->whereBetween('vend_records.date', [$dateFrom, $dateTo])
        ->whereNull('v2.id') // replaces NOT IN for efficiency
        ->when($request->operators, fn($q) => $q->whereIn('vend_records.operator_id', $request->operators))
        ->groupBy('vend_records.date');

    if ($className === 'location_types') {
        $dailyActive->groupBy('vend_records.location_type_id');
    } elseif ($className === 'operators') {
        $dailyActive->groupBy('vend_records.operator_id');
    }

    $query = VendRecord::query()
        ->selectRaw('vend_records.month')
        ->selectRaw('SUM(vend_records.total_amount) as amount')
        ->selectRaw('COUNT(DISTINCT vend_records.vend_id) as vend_count')
        ->selectRaw('AVG(vend_records.total_amount) as average')
        ->leftJoin('vends as v2', function($join) {
            $join->on('vend_records.vend_id', '=', 'v2.id')
                ->where('v2.is_testing', true);
        })
        ->leftJoinSub($dailyActive, 'daily_active', function($join) use ($className) {
            $join->on('vend_records.date', '=', 'daily_active.date');
            if ($className === 'location_types') {
                $join->on('vend_records.location_type_id', '=', 'daily_active.location_type_id');
            } elseif ($className === 'operators') {
                $join->on('vend_records.operator_id', '=', 'daily_active.operator_id');
            }
        })
        ->whereBetween('vend_records.date', [$dateFrom, $dateTo])
        ->whereNull('v2.id') // replaces NOT IN
        ->when($request->operators, fn($q) => $q->whereIn('vend_records.operator_id', $request->operators));

    if ($className === 'location_types') {
        $query->leftJoin('location_types', 'vend_records.location_type_id', '=', 'location_types.id')
              ->selectRaw('location_types.id as id')
              ->selectRaw('location_types.name as name')
              ->groupBy('location_types.id', 'vend_records.month')
              ->orderBy('location_types.name', 'asc');
    } elseif ($className === 'operators') {
        $query->leftJoin('operators', 'vend_records.operator_id', '=', 'operators.id')
              ->selectRaw('operators.id as id')
              ->selectRaw('operators.name as name')
              ->groupBy('operators.id', 'vend_records.month')
              ->orderBy('operators.name', 'asc');
    }

    $query->selectRaw('ROUND(AVG(daily_active.daily_active_count), 2) as average_active_count');

    return $query;
}

}
