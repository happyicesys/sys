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
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendModel;
use App\Models\VendPrefix;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use App\Services\VendTransactionSalesAggregator;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use GetUserTimezone;

    protected $weatherService;

    public function __construct(\App\Services\WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
        // $this->middleware(['permission:read dashboard'])->only('index');
    }

    public function index(Request $request)
    {
        $this->setDefaultOperators($request);

        // Fetch testing vend IDs once and reuse across all queries
        $testingVendIds = \DB::table('vends')
            ->where('is_testing', true)
            ->pluck('id')
            ->toArray();

        $bestPerformerLimit = (int) $request->input('best_performer_limit', $request->input('performer_limit', 20));
        $bestPerformerLimit = max(1, min(50, $bestPerformerLimit));
        $worstPerformerLimit = (int) $request->input('worst_performer_limit', 20);
        $worstPerformerLimit = max(1, min(50, $worstPerformerLimit));
        $dayGraph = $this->getDayGraph($request, $testingVendIds);
        $productGraph = $this->getProductGraph($request);
        $bestPerformer = $this->getBestPerformer($request, $bestPerformerLimit, $testingVendIds);
        $worstPerformer = $this->getWorstPerformer($request, $worstPerformerLimit, $testingVendIds);
        $vendCount = $this->getVendCount($request, $testingVendIds);
        $monthGraphData = $this->getMonthGraphData($request, $testingVendIds);
        // $monthGraphData = Cache::remember(
        //     'month_graph_data_' . auth()->id(),
        //     300, // cache duration in seconds (5 minutes)
        //     fn () => $this->getMonthGraphData($request)
        // );

        $activeMachineGraphData = $this->getActiveMachineGraphData($request, $testingVendIds);
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
            'productGraphData' => $productGraph,
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
            'salesComparisonGraphData' => $this->getSalesComparisonGraph($request, $testingVendIds),
        ]);
    }

    private function getSalesComparisonGraph(Request $request, array $testingVendIds)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year)->setTimezone($this->getUserTimezone())->startOfMonth();
        } else {
            $baseDate = Carbon::today()->setTimezone($this->getUserTimezone())->startOfMonth();
        }

        $today = Carbon::today()->setTimezone($this->getUserTimezone());

        // Define the 6 periods
        $periods = [
            'current_month' => $baseDate->copy(),
            'prev_month' => $baseDate->copy()->subMonth(),
            'next_month' => $baseDate->copy()->addMonth(),
            'last_year_same_month' => $baseDate->copy()->subYear(),
            'last_year_prev_month' => $baseDate->copy()->subYear()->subMonth(),
            'last_year_next_month' => $baseDate->copy()->subYear()->addMonth(),
        ];

        // Filter out future "next month" - REMOVED to always show 3 months
        // $includeNextMonth = !$periods['next_month']->isFuture();
        // if (!$includeNextMonth) {
        //     unset($periods['next_month']);
        // }

        $query = VendRecord::query()
            ->filterIndex($request)
            ->whereNotIn('vend_id', $testingVendIds)
            ->select(
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('DAY(date) as day'),
                DB::raw('MONTH(date) as month'),
                DB::raw('YEAR(date) as year')
            )
            ->groupBy('year', 'month', 'day');

        // Build where clause for all periods
        $query->where(function ($q) use ($periods) {
            foreach ($periods as $key => $date) {
                $q->orWhere(function ($subQ) use ($date) {
                    $subQ->whereYear('date', $date->year)
                        ->whereMonth('date', $date->month);
                });
            }
        });


        $results = $query->get();

        $operator = auth()->user()->operator;
        $lat = $operator->address?->latitude ?? 1.3521;
        $lng = $operator->address?->longitude ?? 103.8198;


        // Initialize structure
        $data = [];
        foreach ($periods as $key => $date) {
            $daysInMonth = $date->daysInMonth;
            $data[$key] = [
                'label' => $date->format('M Y'),
                'data' => [],
                'year' => $date->year,
                'month' => $date->month,
                'weather_icons' => [],
            ];

            $periodStart = $date->copy()->startOfMonth();
            $periodEnd = $date->copy()->endOfMonth();
            $weatherData = $this->weatherService->getDailyWeatherForRange($periodStart, $periodEnd, $lat, $lng);


            // Initialize days based on actual days in month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                // Check if this date is in the future
                $checkDate = Carbon::create($date->year, $date->month, $day)->setTimezone($this->getUserTimezone());

                // Use null for future dates (Chart.js won't draw lines to null values)
                // Use 0 for past/today dates with no data
                if ($checkDate->isFuture()) {
                    $data[$key]['data'][$day] = null;
                } else {
                    $data[$key]['data'][$day] = 0;
                }
                $data[$key]['weather_icons'][$day] = $weatherData[$checkDate->format('Y-m-d')] ?? null;

            }
        }

        // Fill data from query results
        foreach ($results as $row) {
            foreach ($data as $key => &$periodData) {
                if ($row->year == $periodData['year'] && $row->month == $periodData['month']) {
                    // Only set data if the day exists in the initialized array (sanity check)
                    if (isset($periodData['data'][$row->day])) {
                        $periodData['data'][$row->day] = (float) $row->amount / 100;
                    }
                }
            }
        }

        // Re-index data to be 0-indexed arrays for Chart.js
        foreach ($data as &$periodData) {
            $periodData['data'] = array_values($periodData['data']);
            $periodData['weather_icons'] = array_values($periodData['weather_icons']);
        }

        return $data;
    }

    private function setDefaultOperators(Request $request)
    {
        if (!$request->operators || (is_array($request->operators) && in_array('all', $request->operators))) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ]
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
    }

    private function getDayGraph(Request $request, array $testingVendIds)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year)->setTimezone($this->getUserTimezone());
            $day_date_from = $baseDate->copy()->startOfMonth();
            $day_date_to = $baseDate->copy()->endOfMonth();
        } else {
            $day_date_from = $request->day_date_from ? Carbon::parse($request->day_date_from)->setTimezone($this->getUserTimezone()) : Carbon::today()->startOfMonth()->setTimezone($this->getUserTimezone());
            $day_date_to = $request->day_date_to ? Carbon::parse($request->day_date_to)->setTimezone($this->getUserTimezone()) : Carbon::today()->endOfMonth()->setTimezone($this->getUserTimezone());
        }

        $dayGraph = VendRecord::query()
            ->whereBetween('date', [$day_date_from->copy()->subMonth()->startOfDay(), $day_date_to->copy()->endOfDay()])
            ->filterIndex($request)
            ->whereNotIn('vend_id', $testingVendIds);

        // dd($dayGraph->get()->toArray());
        $dayGraph->groupBy('date')
            ->select(
                DB::raw('MONTH(date) as month'),
                DB::raw('DATE_FORMAT(date, "%M %Y") as month_name'),
                DB::raw('DATE(date) as date'),
                DB::raw('DAY(date) as day'),
                DB::raw('SUM(total_amount) as amount'),
                DB::raw('SUM(total_count) as count')
            );

        $dayGraph = $dayGraph->orderBy('date', 'asc')->get();

        $today = Carbon::today()->setTimezone($this->getUserTimezone());
        if ($today->between($day_date_from->copy()->subMonth()->startOfDay(), $day_date_to->copy()->endOfDay())) {
            // Ensure we use application timezone boundaries to match DB storage
            $startOfTodayUTC = $today->copy()->setTimezone(config('app.timezone'))->startOfDay();
            $endOfTodayUTC = $today->copy()->setTimezone(config('app.timezone'))->endOfDay();

            $todayTransactions = VendTransaction::query()
                ->filterTransactionIndex($request)
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->where(function ($query) {
                    $query->where('vend_channel_errors.code', 0)
                        ->orWhere('vend_channel_errors.code', 6)
                        ->orWhereNull('vend_channel_errors.code');
                })
                ->whereBetween('transaction_datetime', [$startOfTodayUTC, $endOfTodayUTC])
                ->where('amount', '>', 0)
                ->whereNotIn('vend_id', $testingVendIds)
                ->select(
                    DB::raw('ROUND(SUM(amount), 2) as amount'),
                    DB::raw('SUM(success_qty) as count')
                )
                ->first();

            if ($todayTransactions) {
                // Check if today already exists in dayGraph (unlikely from VendRecord but good to check)
                $existingTodayIndex = $dayGraph->search(function ($item) use ($today) {
                    return $item->day == $today->day && $item->month == $today->month;
                });

                if ($existingTodayIndex !== false) {
                    // If exists (maybe partial VendRecord?), replace or add? Usually VendsRecords are T-1.
                    // Let's assume real-time takes precedence or we sum?
                    // For safety, let's override with real-time if VendRecord is empty, or sum if partial.
                    // But simpler is to assume VendRecord doesn't have Today yet.
                    $dayGraph[$existingTodayIndex]->amount = $todayTransactions->amount ?? 0;
                    $dayGraph[$existingTodayIndex]->count = $todayTransactions->count ?? 0;
                } else {
                    $newEntry = new VendRecord();
                    $newEntry->month = $today->month;
                    $newEntry->month_name = $today->format('F Y'); // "January 2026"
                    $newEntry->date = $today->copy(); // Keep as Carbon object or string matching others
                    $newEntry->day = $today->day;
                    $newEntry->amount = $todayTransactions->amount ?? 0;
                    $newEntry->count = $todayTransactions->count ?? 0;
                    $dayGraph->push($newEntry);
                }
            }
        }

        $dayGraph = $this->fillEmptyDates($dayGraph, $day_date_from->copy()->subMonth(), $day_date_to);

        $operator = auth()->user()->operator;
        $lat = $operator->address?->latitude ?? 1.3521;
        $lng = $operator->address?->longitude ?? 103.8198;
        $weatherData = $this->weatherService->getDailyWeatherForRange($day_date_from->copy()->subMonth(), $day_date_to, $lat, $lng);

        foreach ($dayGraph as $day) {
            $d = $day->date instanceof \Carbon\Carbon ? $day->date->format('Y-m-d') : $day->date;
            $day->weather_icon = $weatherData[$d] ?? null;
        }

        return $dayGraph;
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
                $newModel->month_name = $currentDate->copy()->format('F Y');
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

        $salesQuery = VendTransactionSalesAggregator::productTotals(
            $seven_days_date_from,
            $seven_days_date_to,
            function ($query) use ($request) {
                $query->filterTransactionIndex($request)
                    ->whereNotIn('vend_transactions.vend_id', function ($subQuery) {
                        $subQuery->select('id')->from('vends')->where('is_testing', true);
                    });
            }
        );

        $topProducts = $salesQuery
            ->orderByDesc('total_count')
            ->limit(10)
            ->get();

        if ($topProducts->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->select('id', 'code', 'name')
            ->whereIn('id', $topProducts->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return $topProducts->map(function ($row) use ($products) {
            $product = $products->get($row->product_id);

            return [
                'amount' => (int) $row->total_amount / 100,
                'count' => (int) $row->total_count,
                'product' => $product ? [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                ] : null,
            ];
        });
    }

    private function getBestPerformer(Request $request, int $limit, array $testingVendIds)
    {
        return VendRecord::query()
            ->with(['customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend:id,code,name,customer_id,vend_prefix_id', 'vend.customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend.vendPrefix:id,name'])
            ->filterIndex($request)
            ->whereBetween('date', [Carbon::today()->copy()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()])
            ->whereNotIn('vend_id', $testingVendIds)
            ->groupBy('vend_records.vend_id')
            ->select(
                'vend_records.id',
                'vend_records.customer_id',
                'vend_records.vend_id',
                DB::raw('SUM(vend_records.total_amount) as amount'),
                DB::raw('SUM(vend_records.total_count) as count')
            )
            ->orderBy('amount', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getWorstPerformer(Request $request, int $limit, array $testingVendIds)
    {
        return VendRecord::query()
            ->with(['customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend:id,code,name,customer_id,vend_prefix_id', 'vend.customer:id,code,name,virtual_customer_prefix,virtual_customer_code', 'vend.vendPrefix:id,name'])
            ->filterIndex($request)
            ->whereBetween('date', [Carbon::today()->copy()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()])
            ->whereNotIn('vend_id', $testingVendIds)
            ->groupBy('vend_records.vend_id')
            ->select(
                'vend_records.id',
                'vend_records.customer_id',
                'vend_records.vend_id',
                DB::raw('SUM(vend_records.total_amount) as amount'),
                DB::raw('SUM(vend_records.total_count) as count')
            )
            ->orderBy('amount', 'asc')
            ->limit($limit)
            ->get();
    }

    private function getVendCount(Request $request, array $testingVendIds)
    {
        return VendRecord::query()
            ->filterIndex($request)
            ->whereDate('date', Carbon::yesterday())
            ->whereNotIn('vend_id', $testingVendIds)
            ->count();
    }

    private function getMonthGraphData(Request $request, array $testingVendIds)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year);
            $thisYear = $baseDate->copy()->endOfYear();
            $lastYear = $baseDate->copy()->subYear()->startOfYear();
            $compareYear = $baseDate->year;
            $compareMonth = $baseDate->month;
        } else {
            $thisYear = Carbon::today()->endOfYear();
            $lastYear = Carbon::today()->subYear()->startOfYear();
            $compareYear = Carbon::today()->year;
            $compareMonth = Carbon::today()->month;
        }

        $monthsArrInit = [];
        foreach ([$lastYear->year, $thisYear->year] as $year) {
            for ($i = 1; $i <= 12; $i++) {
                if ($year == $compareYear && $i > $compareMonth) {
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
            ->whereNotIn('vend_id', $testingVendIds)
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

    private function getActiveMachineGraphData(Request $request, array $testingVendIds)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year);
            $thisYear = $baseDate->copy()->endOfYear();
            $lastYear = $baseDate->copy()->subYear()->startOfYear();
            $compareYear = $baseDate->year;
            $compareMonth = $baseDate->month;
        } else {
            $thisYear = Carbon::today()->endOfYear();
            $lastYear = Carbon::today()->subYear()->startOfYear();
            $compareYear = Carbon::today()->year;
            $compareMonth = Carbon::today()->month;
        }

        $activeMonths = [];
        foreach ([$lastYear->year, $thisYear->year] as $year) {
            for ($i = 1; $i <= 12; $i++) {
                if ($year == $compareYear && $i > $compareMonth) {
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

        // Get testing vend IDs + vends with null customer_id
        $excludeVendIds = \DB::table('vends')
            ->where(function ($q) {
                $q->where('is_testing', true)
                    ->orWhereNull('customer_id');
            })
            ->pluck('id')
            ->toArray();

        $activeMachineGraph = DB::table('vend_records')
            ->joinSub($latestSub, 'latest', function ($join) {
                $join->on('vend_records.date', '=', 'latest.latest_date')
                    ->on('vend_records.year', '=', 'latest.year')
                    ->on('vend_records.month', '=', 'latest.month');
            })
            ->whereBetween('vend_records.date', [$lastYear->startOfDay(), $thisYear->endOfDay()])
            ->whereNotIn('vend_records.vend_id', $excludeVendIds)
            ->when(method_exists(new \App\Models\VendRecord, 'scopeFilterIndex'), function ($query) use ($request) {
                // Apply filterIndex() scope only if it exists
                \App\Models\VendRecord::applyScope($query, 'filterIndex', $request);
            })
            ->when($request->operators, function ($query) use ($request) {
                $query->whereIn('vend_records.operator_id', $request->operators);
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
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
            ->when($request->vendModels, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_records.vend_model_id', $search);
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    if (in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_records.vend_prefix_id', $search);
                }
            })
            ->when($request->locationType, function ($query, $search) use ($request) {
                // dd($request->all(), $search);
                if ($search != 'all') {
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
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        foreach ($activeMachineGraph as $activeMachine) {
            $activeMonths[$activeMachine->year][$activeMachine->month]['count'] = $activeMachine->count;
        }

        return $activeMonths;
    }


    private function getMonthlyAnalytics(Request $request)
    {
        if ($request->month_year) {
            $baseDate = Carbon::createFromFormat('Y-m', $request->month_year);
            $monthlyDateFrom = $baseDate->copy()->startOfYear()->startOfDay();
            $monthlyDateTo = $baseDate->copy()->endOfYear()->endOfDay();
            $currentMonthNumber = $baseDate->month;
        } else {
            $monthlyDateFrom = Carbon::today()->startOfYear()->startOfDay();
            $monthlyDateTo = Carbon::today()->endOfYear()->endOfDay();
            $currentMonthNumber = Carbon::today()->month;
        }

        $request->merge([
            'monthlyDateFrom' => $monthlyDateFrom,
            'monthlyDateTo' => $monthlyDateTo,
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
            ->leftJoin('vends as v2', function ($join) {
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
            ->leftJoin('vends as v2', function ($join) {
                $join->on('vend_records.vend_id', '=', 'v2.id')
                    ->where('v2.is_testing', true);
            })
            ->leftJoinSub($dailyActive, 'daily_active', function ($join) use ($className) {
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
