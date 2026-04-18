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
use Illuminate\Support\Facades\Cache;
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

        // Pre-resolve vend IDs from machine codes once here so every downstream
        // query can use a direct whereIn instead of firing its own subquery against
        // the vends table repeatedly (would otherwise run ~7 identical lookups).
        if ($request->codes) {
            $codesArr = strpos($request->codes, ',') !== false
                ? array_map('trim', explode(',', $request->codes))
                : [$request->codes];
            $resolvedVendIds = \DB::table('vends')
                ->whereIn('code', $codesArr)
                ->pluck('id')
                ->toArray();
            $request->merge(['_resolved_vend_ids' => $resolvedVendIds]);
        }

        $shouldAutoload = $request->boolean('autoload', false);

        $bestPerformerLimit = (int) $request->input('best_performer_limit', $request->input('performer_limit', 20));
        $bestPerformerLimit = max(1, min(50, $bestPerformerLimit));
        $worstPerformerLimit = (int) $request->input('worst_performer_limit', 20);
        $worstPerformerLimit = max(1, min(50, $worstPerformerLimit));

        // Fetch months once — reused by getMonthlyAnalytics() and the Inertia render
        // to avoid firing two identical "select * from months" queries per request.
        $allMonths = Month::all();

        if ($shouldAutoload) {
            $t = microtime(true);
            \Log::info('[Dashboard] start');

            // Cache testing vend IDs for 5 min. VendController::update() busts this
            // key whenever is_testing changes, so staleness is bounded.
            $testingVendIds = Cache::remember('testing_vend_ids', 300, function () {
                return \DB::table('vends')
                    ->where('is_testing', true)
                    ->pluck('id')
                    ->toArray();
            });
            \Log::info('[Dashboard] testingVendIds: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $dayGraph = $this->getDayGraph($request, $testingVendIds);
            \Log::info('[Dashboard] getDayGraph: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $productGraph = $this->getProductGraph($request);
            \Log::info('[Dashboard] getProductGraph: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $bestPerformer = $this->getBestPerformer($request, $bestPerformerLimit, $testingVendIds);
            \Log::info('[Dashboard] getBestPerformer: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $worstPerformer = $this->getWorstPerformer($request, $worstPerformerLimit, $testingVendIds);
            \Log::info('[Dashboard] getWorstPerformer: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $vendCount = $this->getVendCount($request, $testingVendIds);
            \Log::info('[Dashboard] getVendCount: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $monthGraphData = $this->getMonthGraphData($request, $testingVendIds);
            \Log::info('[Dashboard] getMonthGraphData: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $activeMachineGraphData = $this->getActiveMachineGraphData($request, $testingVendIds);
            \Log::info('[Dashboard] getActiveMachineGraphData: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $monthlyAnalytics = $this->getMonthlyAnalytics($request, $allMonths);
            \Log::info('[Dashboard] getMonthlyAnalytics: ' . round((microtime(true) - $t) * 1000) . 'ms'); $t = microtime(true);

            $salesComparisonGraphData = $this->getSalesComparisonGraph($request, $testingVendIds);
            \Log::info('[Dashboard] getSalesComparisonGraph: ' . round((microtime(true) - $t) * 1000) . 'ms');
        } else {
            $emptyCollection = collect([]);
            $dayGraph = $emptyCollection;
            $productGraph = $emptyCollection;
            $bestPerformer = $emptyCollection;
            $worstPerformer = $emptyCollection;
            $vendCount = 0;
            $monthGraphData = [];
            $activeMachineGraphData = [];
            $monthlyAnalytics = [];
            $salesComparisonGraphData = [];
        }

        return Inertia::render('Dashboard', [
            'activeMachineGraphData' => $activeMachineGraphData,
            'autoLoad' => $shouldAutoload,
            'dayGraphData' => VendTransactionGraphResource::collection($dayGraph),
            'locationTypeOptions' => OptionResource::collection(
                LocationType::toBase()->select('id', 'name')->orderBy('sequence')->get()
            ),
            'monthGraphData' => $monthGraphData,
            'months' => MonthResource::collection($allMonths),
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
            'salesComparisonGraphData' => $salesComparisonGraphData,
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

        $cacheKey = $this->makeCacheKey('sales_comparison_graph', $request);
        $results = Cache::remember($cacheKey, 300, function () use ($request, $testingVendIds, $periods) {
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

            return $query->get();
        });

        // Initialize structure
        // Weather service disabled — was causing 5-10 s of latency (6 calls × ~1-2 s each).
        // Re-enable by restoring getDailyWeatherForRange() calls per period.
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
                $data[$key]['weather_icons'][$day] = null; // weather disabled
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
                // Single query instead of 4 separate first() calls.
                $operatorMap = Operator::whereIn('code', ['HIMD', 'LEA', 'HIESG', 'UL-ST'])
                    ->pluck('id', 'code');
                $request->merge([
                    'operators' => [
                        auth()->user()->operator_id,
                        $operatorMap->get('HIMD'),
                        $operatorMap->get('LEA'),
                        $operatorMap->get('HIESG'),
                        $operatorMap->get('UL-ST'),
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

        // Weather service disabled — was causing 5-10 s delay per call.
        // Re-enable by restoring the getDailyWeatherForRange() call below.
        foreach ($dayGraph as $day) {
            $day->weather_icon = null;
        }

        return $dayGraph;
    }

    private function fillEmptyDates($dayGraph, $startDate, $endDate)
    {
        // Build an O(1) lookup keyed by "month-day" so we don't scan the entire
        // collection for every date in the range (was O(n²), now O(n)).
        $existingKeys = [];
        foreach ($dayGraph as $graphDayValue) {
            $existingKeys[$graphDayValue->month . '-' . $graphDayValue->day] = true;
        }

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $key = $currentDate->month . '-' . $currentDate->day;
            if (!isset($existingKeys[$key])) {
                $newModel = new VendRecord();
                $newModel->amount = 0;
                $newModel->count = 0;
                $newModel->date = $currentDate->copy()->startOfDay();
                $newModel->day = $currentDate->day;
                $newModel->month = $currentDate->month;
                $newModel->month_name = $currentDate->format('F Y');
                $dayGraph->push($newModel);
                $existingKeys[$key] = true; // prevent duplicate inserts
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
        $cacheKey = $this->makeCacheKey('vend_count', $request);
        return Cache::remember($cacheKey, 300, function () use ($request, $testingVendIds) {
            return VendRecord::query()
                ->filterIndex($request)
                ->whereDate('date', Carbon::yesterday())
                ->whereNotIn('vend_id', $testingVendIds)
                ->count();
        });
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

        $cacheKey = $this->makeCacheKey('month_graph', $request);
        $monthGraph = Cache::remember($cacheKey, 300, function () use ($request, $testingVendIds, $lastYear, $thisYear) {
            return VendRecord::query()
                ->whereBetween('date', [$lastYear->copy()->startOfDay(), $thisYear->copy()->endOfDay()])
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
        });

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

        // Subquery: latest date per (year, month).
        // Scoping by the same date range the outer query uses prevents a full table scan —
        // the MAX(date) per month is the same whether we scan all history or just these 2 years.
        $latestSub = DB::table('vend_records')
            ->selectRaw('MAX(date) as latest_date, year, month')
            ->whereBetween('date', [$lastYear->copy()->startOfDay(), $thisYear->copy()->endOfDay()])
            ->groupBy('year', 'month');

        // Cache for 5 min. VendController::update() busts both keys on save.
        $excludeVendIds = Cache::remember('exclude_vend_ids_for_active_machine', 300, function () {
            return \DB::table('vends')
                ->where(function ($q) {
                    $q->where('is_testing', true)
                        ->orWhereNull('customer_id');
                })
                ->pluck('id')
                ->toArray();
        });

        $cacheKey = $this->makeCacheKey('active_machine_graph', $request);
        $activeMachineGraph = Cache::remember($cacheKey, 300, function () use ($request, $excludeVendIds, $latestSub, $lastYear, $thisYear) {
        return DB::table('vend_records')
            ->joinSub($latestSub, 'latest', function ($join) {
                $join->on('vend_records.date', '=', 'latest.latest_date')
                    ->on('vend_records.year', '=', 'latest.year')
                    ->on('vend_records.month', '=', 'latest.month');
            })
            ->whereBetween('vend_records.date', [$lastYear->copy()->startOfDay(), $thisYear->copy()->endOfDay()])
            ->whereNotIn('vend_records.vend_id', $excludeVendIds)
            ->when(method_exists(new \App\Models\VendRecord, 'scopeFilterIndex'), function ($query) use ($request) {
                // Apply filterIndex() scope only if it exists
                \App\Models\VendRecord::applyScope($query, 'filterIndex', $request);
            })
            ->when($request->operators, function ($query) use ($request) {
                $query->whereIn('vend_records.operator_id', $request->operators);
            })
            ->when($request->codes, function ($query, $search) use ($request) {
                // Use pre-resolved IDs when available to avoid a repeated subquery.
                if ($request->has('_resolved_vend_ids')) {
                    $query->whereIn('vend_records.vend_id', $request->input('_resolved_vend_ids', []));
                } elseif (strpos($search, ',') !== false) {
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
                            ->where('code', 'LIKE', "%{$search}%");
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
        }); // end Cache::remember for active_machine_graph

        foreach ($activeMachineGraph as $activeMachine) {
            $activeMonths[$activeMachine->year][$activeMachine->month]['count'] = $activeMachine->count;
        }

        return $activeMonths;
    }


    private function getMonthlyAnalytics(Request $request, $allMonths = null)
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

        // Cache the expensive full-year double-join query for 5 minutes.
        // Use ->format() on the Carbon dates so microseconds don't break the key.
        $cacheKey = 'monthly_analytics_' . auth()->id() . '_' . md5(json_encode([
            $monthlyDateFrom->format('Y-m-d'),
            $monthlyDateTo->format('Y-m-d'),
            $request->monthlyTypeName,
            $request->operators,
            $request->locationType,
            $request->vendPrefixes,
            $request->customer,
        ]));

        $modelName = $this->getModelName($request->monthlyTypeName);
        $items = Cache::remember($cacheKey, 300, function () use ($request, $modelName) {
            return $this->getMonthlySalesQuery($request, $modelName)->get();
        });

        $monthsByModel = [];
        // keyBy() gives O(1) lookup — replaces the O(items × 12) nested foreach.
        // Use the pre-fetched $allMonths if available (avoids a duplicate DB query).
        $months = ($allMonths ?? Month::all())->keyBy('number');

        foreach ($items as $item) {
            $month = $months->get($item->month);
            if (!$month) {
                continue;
            }
            $entry = [
                'current' => $currentMonthNumber == $item->month,
                'month_short_name' => $month->short_name,
                'amount' => $item->amount ? $item->amount / 100 : 0,
                'vend_count' => $item->count ?? 0,
                'average' => $item->average ? $item->average / 100 : 0,
            ];
            if ($item->id) {
                $monthsByModel[$item->name][$item->month] = $entry;
            } else {
                $monthsByModel['Undefined'][$item->month] = $entry;
            }
        }

        return collect($monthsByModel)->sortKeys();
    }


    /**
     * Build a stable, user-scoped cache key from the active request filters.
     * Extra scalar values (e.g. a date range) can be passed in $extra.
     */
    private function makeCacheKey(string $name, Request $request, array $extra = []): string
    {
        return $name . '_' . auth()->id() . '_' . md5(json_encode(array_merge([
            $request->operators,
            $request->customer,
            $request->codes,
            $request->vendModels,
            $request->vendPrefixes,
            $request->locationType,
            $request->month_year,
        ], $extra)));
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
