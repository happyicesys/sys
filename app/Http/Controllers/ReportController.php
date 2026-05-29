<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryDBResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\LocationTypeDBResource;
use App\Http\Resources\LocationTypeResource;
use App\Http\Resources\OptionResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductDBResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductStockCountResource;
use App\Http\Resources\SalesReportResource;
use App\Http\Resources\SalesPerformanceProductResource;
use App\Http\Resources\StockCountResource;
use App\Http\Resources\StockCountItemResource;
use App\Http\Resources\StockCountDayGraphResource;
use App\Http\Resources\VendContractResource;
use App\Http\Resources\VendDBResource;
use App\Http\Resources\VendModelResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendSnapshotDBResource;
use App\Http\Resources\VendTransactionGraphResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\ProductMapping;
use App\Models\Customer;
use App\Models\LocationType;
use App\Models\Operator;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\UnitCost;
use App\Models\Vend;
use App\Models\VendContract;
use App\Models\VendModel;
use App\Models\VendChannelStockEvent;
use App\Models\VendPrefix;
use App\Models\VendTransaction;
use App\Services\GpMetricsAggregator;
use App\Services\MachineHealthDashboardService;
use App\Traits\GetUserTimezone;
use App\Traits\HasFilter;
use App\Traits\HasMonthOption;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportController extends Controller
{
    use HasFilter, HasMonthOption, GetUserTimezone;

    /**
     * Column order used when selecting from the gp_metrics dataset.
     *
     * @var array<int, string>
     */
    protected array $gpMetricSelectColumns = [
        'txn_date',
        'operator_id',
        'vend_id',
        'customer_id',
        'category_id',
        'category_group_id',
        'customer_location_type_id',
        'transaction_location_type_id',
        'vend_prefix_id',
        'vend_contract_id',
        'vend_model_id',
        'product_id',
        'is_multiple',
        'is_binded_customer',
        'sale_count',
        'transaction_count',
        'success_count',
        'error_count',
        'error_count_no_4_5',
        'error_count_4_5',
        'amount_cents',
        'revenue_cents',
        'gross_profit_cents',
        'unit_cost_cents',
    ];

    public function __construct(
        private MachineHealthDashboardService $machineHealthDashboardService
    ) {
        $this->middleware(['permission:read reports'])->except(['indexMachineHealth', 'historyMachineHealth']);
        $this->middleware(['permission:read dashboard-machine-health'])->only(['indexMachineHealth', 'historyMachineHealth']);
    }

    public function indexSales(Request $request, $type)
    {

        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }

        $request->merge([
            // 'operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id,
            'visited' => isset($request->visited) ? $request->visited : true,
            'is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true'),
        ]);

        if ($request->currentFilterDate) {
            if ($request->currentFilterDate != '-1') {
                $request->merge(['date_from' => explode(',', $request->currentFilterDate)[0]]);
                $request->merge(['date_to' => explode(',', $request->currentFilterDate)[1]]);
            }
        }
        // if(!$request->date_from) {
        //     $request->merge(['date_from' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
        // }
        // if(!$request->date_to) {
        //     $request->merge(['date_to' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
        // }

        $shouldAutoload = $request->boolean('autoload', false);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'amount';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $categoryClassName = get_class(new Customer());
        $modelName = 'vends';

        switch ($type) {
            case 'category':
                $modelName = 'categories';
                break;
            case 'location-type':
                $modelName = 'location_types';
                break;
            case 'product':
                $modelName = 'products';
                break;
            case 'operator':
                $modelName = 'operators';
                break;
            case 'vend':
                $modelName = 'vends';
                break;
            case 'customer':
                $modelName = 'customers';
                break;
        }

        if ($shouldAutoload) {
            $t0 = microtime(true);

            $items = $this->getSalesQuery($request, $modelName);

            $t1 = microtime(true);
            Log::channel('single')->info('[SalesReport] query built', [
                'type'       => $type,
                'date_from'  => $request->date_from,
                'date_to'    => $request->date_to,
                'build_ms'   => round(($t1 - $t0) * 1000),
                'sql'        => $items->toSql(),
                'bindings'   => $items->getBindings(),
            ]);

            $totals = $this->getSalesReportTotals($items);

            $t2 = microtime(true);
            Log::channel('single')->info('[SalesReport] totals fetched', [
                'totals_ms' => round(($t2 - $t1) * 1000),
            ]);

            $items = $items->when($request->sortKey, function ($query, $search) use ($request) {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            });
            $items = $items->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                ->withQueryString();

            $t3 = microtime(true);
            Log::channel('single')->info('[SalesReport] paginate done', [
                'paginate_ms' => round(($t3 - $t2) * 1000),
                'total_ms'    => round(($t3 - $t0) * 1000),
                'rows'        => $items->total(),
            ]);
        } else {
            $items = new LengthAwarePaginator([], 0, $numberPerPage, 1, [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
            ]);
            $totals = ['total_count' => 0, 'total_amount' => 0.0, 'total_error_count' => 0, 'total_error_count_no_4_5' => 0, 'total_error_count_4_5' => 0, 'total_channel_availability' => 0, 'total_machine_count' => 0];
        }

        return Inertia::render('Report/Sales/Index', [
            'autoLoad' => $shouldAutoload,
            'categories' => CategoryResource::collection(
                Category::where('classname', $categoryClassName)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $categoryClassName)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'productMappingOptions' => ProductMappingResource::collection(
                ProductMapping::orderBy('name')->get()
            ),
            'reportDateOptions' => $this->getReportDateOptions(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendContractOptions' => VendContractResource::collection(
                VendContract::orderBy('name')->get()
            ),
            'vendModelOptions' => VendModelResource::collection(
                VendModel::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'items' => SalesReportResource::collection($items),
            'totals' => $totals,
        ]);
    }

    public function indexMachineHealth(Request $request)
    {
        $operatorOptions = Operator::select('id', 'code', 'name')->orderBy('name')->get();

        // Default is "All" operators — no pre-selection applied

        $dashboardData = $this->machineHealthDashboardService->getDashboardData($request);

        return Inertia::render('Report/MachineHealth/Index', [
            'machineHealth' => $dashboardData,
            'operatorOptions' => OptionResource::collection($operatorOptions),
            'vendPrefixOptions' => OptionResource::collection(
                VendPrefix::select('id', 'name')->orderBy('name')->get()
            ),
            'customerOptions' => OptionResource::collection(
                Customer::select('id', 'code', 'name')->orderBy('name')->limit(200)->get()
            ),
            'locationTypeOptions' => OptionResource::collection(
                LocationType::select('id', 'name')->orderBy('sequence')->get()
            ),
        ]);
    }

    public function activeMachineHealthAlerts(Request $request)
    {
        $request->validate([
            'vend_ids' => 'required|array',
            'vend_ids.*' => 'integer',
        ]);

        $vendIds = $request->vend_ids;

        // Fetch codes to filter the dashboard data
        $vends = Vend::whereIn('id', $vendIds)->orWhereIn('customer_id', $vendIds)->get();
        $machineCodes = $vends->pluck('code')->toArray();
        $resolvedVendIds = $vends->pluck('id')->toArray();

        // Create a mapping of found vends for double-keying the response
        $idMap = [];
        foreach ($vends as $v) {
            $idMap[$v->id] = $v->customer_id;
        }

        // Create a new request to fetch specific vends from the dashboard service
        $dashboardRequest = new Request();
        $dashboardRequest->merge([
            'machine_limit' => 10000,
            'channel_limit' => 100,
            'machine_codes' => $machineCodes,
            'show_all_errors' => false,
        ]);

        $dashboardData = $this->machineHealthDashboardService->getDashboardData($dashboardRequest);
        $alertsByVend = [];

        // Helper to add alert with double-keying
        $addAlert = function ($vid, $alert) use (&$alertsByVend, $idMap) {
            if (!isset($alertsByVend[$vid]))
                $alertsByVend[$vid] = [];
            $alertsByVend[$vid][] = $alert;

            // Also key by customer_id if different and provided in the request
            $cid = $idMap[$vid] ?? null;
            if ($cid && (string) $cid !== (string) $vid) {
                if (!isset($alertsByVend[$cid]))
                    $alertsByVend[$cid] = [];
                // Check for duplicates before adding to cid
                $isDup = false;
                foreach ($alertsByVend[$cid] as $existing) {
                    if ($existing['group'] === $alert['group'] && $existing['type'] === $alert['type']) {
                        $isDup = true;
                        break;
                    }
                }
                if (!$isDup) {
                    $alertsByVend[$cid][] = $alert;
                }
            }
        };

        // 1. Connectivity Alerts
        if (isset($dashboardData['connectivity']['buckets'])) {
            foreach ($dashboardData['connectivity']['buckets'] as $bucket) {
                foreach ($bucket['rows'] as $row) {
                    $vid = $row['vend_id'];
                    $addAlert($vid, [
                        'group' => 'connectivity',
                        'type' => 'connectivity',
                        'label' => 'Offline',
                        'duration' => $row['hours_offline'] . ' hours',
                        'occurred_at' => $row['last_contact_at'],
                    ]);
                }
            }
        }

        // Fallback Connectivity: Sync with official is_online status for requested vends
        $allRequestedVends = Vend::whereIn('id', array_unique(array_merge($vendIds, $resolvedVendIds)))->get();
        foreach ($allRequestedVends as $v) {
            if (!$v->is_online) {
                $hasConnectivityAlert = false;
                $currentAlerts = $alertsByVend[$v->id] ?? [];
                foreach ($currentAlerts as $alert) {
                    if ($alert['group'] === 'connectivity') {
                        $hasConnectivityAlert = true;
                        break;
                    }
                }

                if (!$hasConnectivityAlert) {
                    $lastContact = $v->last_updated_at ?: $v->mqtt_last_updated_at;
                    $duration = $lastContact ? round(now()->diffInMinutes($lastContact) / 60, 2) . ' hours' : 'Unknown';
                    $addAlert($v->id, [
                        'group' => 'connectivity',
                        'type' => 'connectivity',
                        'label' => 'Offline',
                        'duration' => $duration,
                        'occurred_at' => $lastContact ? $lastContact->toIso8601String() : null,
                    ]);
                }
            }
        }

        // 2 & 3. Temperature Smart Alerts
        $tempGroupKeys = [
            'rising_lowest_t1_smart',
            'rising_lowest_t2_smart',
            't2_frozen_smart',
            'operation_errors_smart',
            'preventive_maintenance_smart'
        ];

        $detailedLabelMap = [
            'comp_fan_off' => '2A) Cooling Fan, in OFF condition',
            'temps_above_0' => '2B) T1 or T2, above 0°C',
            'temps_above_minus_8' => '2C) T1 or T2, above -8°C',
            'not_reach_minus_18' => '2D) T1 or T2, did not reach -18°C',
            'temps_above_minus_17_upward' => '2E) Above -17°C & Rising',
            'lowest_24h_above' => '3A) Lowest (last 24h) above -21°C',
            'lowest_72h_above' => '3B) Lowest (last 72h) above -21°C',
            'rising_t1_trend' => '3C) Rising T1 Trend (24h vs Average)',
            'rising_t2_trend' => '3C) Rising T2 Trend (24h vs Average)',
            'rising_lowest_t1_smart' => '3C) Rising lowest T1 (24h vs 48h)',
            'rising_lowest_t2_smart' => '3C) Rising lowest T2 (24h vs 48h)',
            't2_frozen' => '3D) T2, never above 2°C',
            't2_frozen_smart' => '3D) T2, never above 2°C',
            't1_higher_than_t2_smart' => '3F) T1 higher than T2',
        ];

        if (isset($dashboardData['temperature'])) {
            foreach ($tempGroupKeys as $groupKey) {
                if (isset($dashboardData['temperature'][$groupKey]['rows'])) {
                    foreach ($dashboardData['temperature'][$groupKey]['rows'] as $row) {
                        $vid = $row['vend_id'];
                        $type = $row['alert_type'] ?? '';
                        $addAlert($vid, [
                            'group' => 'temperature',
                            'type' => $type,
                            'label' => $detailedLabelMap[$type] ?? 'Temperature Alert',
                            'duration' => ($row['duration_hours'] ?? null) ? $row['duration_hours'] . ' hours' : ($row['duration'] ?? null),
                            'occurred_at' => $row['started_at'] ?? $row['triggered_at'] ?? $row['occurred_at'] ?? null,
                        ]);
                    }
                }
            }
        }


        // 4. No Transactions
        $salesBuckets = [
            'any_sales' => 'No any Sales',
            'cash_sales' => 'No Cash Sales',
            'card_sales' => 'No Sales via Card Terminal',
            'qr_sales' => 'No Sales via QR',
            'digitalscreen_sales' => 'No Digital Screen Activity',
        ];

        if (isset($dashboardData['no_transactions'])) {
            $thresholds = $dashboardData['no_transactions']['thresholds'] ?? [];
            foreach ($salesBuckets as $key => $title) {
                if (isset($dashboardData['no_transactions'][$key])) {
                    foreach ($dashboardData['no_transactions'][$key] as $row) {
                        $vid = $row['vend_id'];

                        $label = $title;
                        if (isset($thresholds[$key])) {
                            $label .= " ({$thresholds[$key]}hr)";
                        }

                        $addAlert($vid, [
                            'group' => 'no_transactions',
                            'type' => $key,
                            'label' => $label,
                            'duration' => $row['hours_since'] . ' hours',
                            'occurred_at' => $row['last_transaction_at'],
                        ]);
                    }
                }
            }
        }

        // 5. Channel Errors
        if (isset($dashboardData['error_codes'])) {
            foreach ($dashboardData['error_codes'] as $groupKey => $group) {
                if (isset($group['rows'])) {
                    foreach ($group['rows'] as $row) {
                        $vid = $row['vend_id'];
                        if (isset($row['events']) && count($row['events']) > 0) {
                            foreach ($row['events'] as $ev) {
                                $addAlert($vid, [
                                    'group' => 'error_code',
                                    'type' => 'error_code_' . $ev['error_code'],
                                    'label' => 'Error ' . $ev['error_code'] . ' (Ch: ' . $ev['channel_code'] . ')',
                                    'duration' => null,
                                    'occurred_at' => $ev['created_at'],
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return response()->json($alertsByVend);
    }

    public function historyMachineHealth(Request $request)
    {
        $request->validate([
            'bucket' => 'required|string',
            'type' => 'required|string',
            'cursor' => 'nullable|string',
            'operator_ids' => 'nullable|array',
            'vend_prefix_ids' => 'nullable|array',
            'customer_ids' => 'nullable|array',
            'machine_codes' => 'nullable|array',
        ]);

        $bucket = $request->input('bucket');
        $type = $request->input('type');
        $cursor = $request->input('cursor');

        $query = \App\Models\MachineHealthHistory::query()
            ->with(['vend', 'vend.customer', 'vend.operator', 'vend.vendPrefix'])
            ->where('event', 'machine_health_alert')
            ->where('bucket', $bucket);

        // Filter by Vend properties
        $query->whereHas('vend', function ($q) use ($request) {
            $q->where('is_testing', false);

            if ($request->filled('operator_ids')) {
                $q->whereIn('operator_id', $request->input('operator_ids'));
            }
            if ($request->filled('vend_prefix_ids')) {
                $q->whereIn('vend_prefix_id', $request->input('vend_prefix_ids'));
            }
            if ($request->filled('customer_ids')) {
                $q->whereIn('customer_id', $request->input('customer_ids'));
            }
            if ($request->filled('machine_codes')) {
                $q->whereIn('code', $request->input('machine_codes'));
            }
        });

        if ($type === 'connectivity') {
            $query->where('alert_type', 'connectivity');
        } else {
            $query->where('alert_type', $type);
        }

        if ($cursor) {
            $query->where('occurred_at', '<', Carbon::parse($cursor));
        }

        $limit = 10;
        $logs = $query->orderByDesc('occurred_at')
            ->limit($limit + 1)
            ->get();

        $hasMore = $logs->count() > $limit;
        $logs = $logs->take($limit);

        // Exclude vends whose LATEST alert for this alert_type is already in a different (higher) bucket.
        // Applies to ALL sections (1 = connectivity, 2 = operation errors, 3 = preventive maintenance).
        // e.g. connectivity: < 12hr → > 12hr; comp_fan_off: > 45 mins → > 60 mins
        if ($logs->isNotEmpty()) {
            // alert_type stored in MachineHealthHistory matches the main query logic above
            $alertTypeForLookup = $type === 'connectivity' ? 'connectivity' : $type;
            $allVendIdsInLog = $logs->pluck('vend_id')->unique()->all();
            $latestBucketPerVend = \App\Models\MachineHealthHistory::where('event', 'machine_health_alert')
                ->where('alert_type', $alertTypeForLookup)
                ->whereIn('vend_id', $allVendIdsInLog)
                ->selectRaw('vend_id, bucket')
                ->orderByDesc('occurred_at')
                ->get()
                ->unique('vend_id')
                ->pluck('bucket', 'vend_id');

            $excludeVendIds = $latestBucketPerVend
                ->filter(fn($b) => $b !== $bucket)
                ->keys()
                ->all();

            if (!empty($excludeVendIds)) {
                $logs = $logs->reject(fn($log) => in_array($log->vend_id, $excludeVendIds))->values();
                $hasMore = false; // conservative: reset pagination after in-memory filter
            }
        }

        // Optimization: Pre-fetch all dismissal logs for these vends in one query
        $vendIds = $logs->pluck('vend_id')->unique()->all();
        $dismissals = collect();
        $legacySmartAlerts = collect();
        if (!empty($vendIds)) {
            $dismissals = \App\Models\MachineHealthHistory::whereIn('vend_id', $vendIds)
                ->where('event', 'machine_health_alert_dismissed')
                ->where('occurred_at', '>=', $logs->min('occurred_at'))
                ->orderBy('occurred_at', 'asc')
                ->get()
                ->groupBy('vend_id');

            // Pre-fetch legacy inactive smart alerts as fallback (excluding real-time connectivity which uses temp_monitoring_state)
            if ($type !== 'connectivity') {
                $legacySmartAlerts = \App\Models\VendSmartAlert::whereIn('vend_id', $vendIds)
                    ->where('alert_type', $type)
                    ->where('is_active', false)
                    ->where('updated_at', '>=', $logs->min('occurred_at'))
                    ->orderBy('updated_at', 'asc')
                    ->get()
                    ->groupBy('vend_id');
            }
        }

        return response()->json([
            'data' => $logs->map(function ($log) use ($dismissals, $legacySmartAlerts, $bucket, $type) {
                $res = [
                    'id' => $log->id,
                    'occurred_at' => $log->context['triggered_at'] ?? $log->occurred_at->toIso8601String(),
                    'vend_code' => $log->vend->code,
                    'vend_name' => $log->vend->name,
                    'vend_prefix_name' => $log->vend->vendPrefix ? $log->vend->vendPrefix->name : '',
                    'customer_name' => $log->vend->customer ? $log->vend->customer->name : '',
                    'operator_name' => $log->vend->operator ? $log->vend->operator->name : '',
                    'event' => $log->event,
                ];

                // Find the first dismissal for this vend that happened AFTER this alert
                $dismissLog = ($dismissals->get($log->vend_id) ?? collect())
                    ->filter(function ($d) use ($log, $bucket) {
                    // Match same bucket
                    if (($d->context['bucket'] ?? null) !== $bucket)
                        return false;
                    // Match same type
                    if (($log->context['type'] ?? null) !== ($d->context['type'] ?? null))
                        return false;
                    // Match same alert_type AND must have occurred after the trigger
                    if (($log->context['alert_type'] ?? null) !== ($d->context['alert_type'] ?? null))
                        return false;
                    return $d->occurred_at >= $log->occurred_at;
                })
                    ->first();

                // Primary: use the explicit dismissed log if found
                if ($dismissLog) {
                    $res['dismissed_at'] = $dismissLog->occurred_at->toIso8601String();
                } elseif ($log->context['dismissed_at'] ?? null) {
                    // Secondary: use dismissed_at stored in the alert's own context
                    $res['dismissed_at'] = $log->context['dismissed_at'];
                } elseif ($type !== 'connectivity') {
                    // Backward Compatibility: fall back to legacy inactive VendSmartAlerts
                    $smartAlert = ($legacySmartAlerts->get($log->vend_id) ?? collect())
                        ->filter(function ($a) use ($log) {
                        return $a->updated_at >= $log->occurred_at;
                    })
                        ->first();
                    $res['dismissed_at'] = $smartAlert ? $smartAlert->updated_at->toIso8601String() : null;
                } else {
                    $res['dismissed_at'] = null;
                }

                $dates = array_filter([
                    $log->vend->mqtt_last_updated_at,
                    $log->vend->last_updated_at,
                    $log->vend->last_vend_transaction_at,
                    $log->vend->offline_restart_count_datetime,
                ]);

                $last = empty($dates) ? null : max($dates);
                $res['last_contact_at'] = $last ? \Carbon\Carbon::parse($last)->toIso8601String() : null;

                // hours_offline: final lapse time shown next to the machine in the history popup.
                // Priority chain:
                //   1. Dismissal log's lapse_hours (computed & persisted at dismiss time — most accurate)
                //   2. Connectivity trigger's stored hours_offline (snapshot at trigger time)
                //   3. Smart alert meta duration in minutes (from trigger log context)
                //   4. null (hide the field in the frontend)
                if ($dismissLog && isset($dismissLog->context['lapse_hours'])) {
                    $res['hours_offline'] = max(0, round((float) $dismissLog->context['lapse_hours'], 2));
                } elseif ($type === 'connectivity') {
                    if (isset($log->context['hours_offline'])) {
                        $res['hours_offline'] = max(0, round((float) $log->context['hours_offline'], 2));
                    } else {
                        $res['hours_offline'] = $last ? max(0, round(\Carbon\Carbon::parse($last)->diffInMinutes(\Carbon\Carbon::now()) / 60, 2)) : null;
                    }
                } else {
                    // Sections 2 & 3: use the alert duration stored in meta (in minutes)
                    // If it's an active alert (no dismissal yet), calculate dynamically if possible
                    $meta = $log->context['meta'] ?? [];
                    $startTime = $meta['started_at'] ?? $meta['min_timestamp'] ?? $meta['triggered_at'] ?? null;

                    if (!$dismissLog && $startTime) {
                        try {
                            $start = Carbon::parse($startTime);
                            $res['hours_offline'] = max(0, round($start->diffInMinutes(now()) / 60, 2));
                        } catch (\Exception $e) {
                            $durationMinutes = $meta['duration'] ?? null;
                            $res['hours_offline'] = $durationMinutes !== null ? max(0, round((float) $durationMinutes / 60, 2)) : null;
                        }
                    } else {
                        $durationMinutes = $meta['duration'] ?? null;
                        $res['hours_offline'] = $durationMinutes !== null ? max(0, round((float) $durationMinutes / 60, 2)) : null;
                    }
                }

                return $res;
            }),
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? $logs->last()->occurred_at->toIso8601String() : null,
        ]);
    }

    private function resolveDefaultOperatorIds(?Collection $operatorOptions = null): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        $operatorId = $user->operator_id;
        $operatorCode = $user->operator?->code;

        if (!$operatorCode && $operatorId && $operatorOptions) {
            $operatorCode = optional($operatorOptions->firstWhere('id', $operatorId))->code;
        }

        if ($operatorCode === 'HIPL') {
            $codes = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST'];
            $ids = $operatorOptions
                ? $operatorOptions->whereIn('code', $codes)->pluck('id')->all()
                : Operator::whereIn('code', $codes)->pluck('id')->all();

            return array_values(array_unique(array_filter($ids)));
        }

        return $operatorId ? [$operatorId] : [];
    }


    public function indexGpVm(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;

        $className = get_class(new Customer());

        $vendQuery = $this->getUnitCostByVendQuery($request);
        $totals = $this->getSalesSubTotal($vendQuery);

        $perPage = $numberPerPage === 'All' ? 10000 : $numberPerPage;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $rows = (clone $vendQuery)
            ->forPage($currentPage, $perPage)
            ->get();

        $totalRows = $this->countUnitCostByVend($request);

        $vends = new LengthAwarePaginator(
            $rows,
            $totalRows,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Report/Gp/IndexVm', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'totals' => $totals,
            'vends' => VendDBResource::collection($vends),
        ]);
    }

    public function indexGpProduct(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $products = $this->getUnitCostByProductQuery($request);
        $totals = $this->getSalesSubTotal($products);
        $products = $products->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Gp/IndexProduct', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'totals' => $totals,
            'products' => ProductDBResource::collection($products),
        ]);
    }

    public function indexSalesPerformanceProduct(Request $request)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);

        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 30;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;

        $currentDate = $request->currentMonth
            ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
            : Carbon::today()->setTimezone($this->getUserTimezone());

        $productsQuery = $this->getUnitCostByProductQuery($request);
        $allProductIds = (clone $productsQuery)->pluck('id')->filter()->values()->all();

        $metrics = $this->buildSalesPerformanceMetrics($allProductIds, $request, $currentDate);

        $products = $productsQuery->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        $currentMonthDate = $currentDate->copy();
        $lastMonthDate = $currentDate->copy()->subMonth();
        $twoMonthsAgoDate = $currentDate->copy()->subMonths(2);

        $periodDays = [
            'this_month' => $currentMonthDate->daysInMonth,
            'last_month' => $lastMonthDate->daysInMonth,
            'two_months_ago' => $twoMonthsAgoDate->daysInMonth,
        ];

        $products->getCollection()->transform(function ($item) use ($metrics, $periodDays) {
            $perProduct = $metrics['per_product'][$item->id] ?? [
                'this_month' => ['channel_count' => 0, 'availability' => null],
                'last_month' => ['channel_count' => 0, 'availability' => null],
                'two_months_ago' => ['channel_count' => 0, 'availability' => null],
            ];

            $item->this_month_channel_count = $perProduct['this_month']['channel_count'] ?? 0;
            $item->this_month_availability = $perProduct['this_month']['availability'];
            $item->this_month_qty_per_day = $periodDays['this_month'] > 0
                ? round(($item->this_month_count ?? 0) / $periodDays['this_month'], 2)
                : 0;

            $item->last_month_channel_count = $perProduct['last_month']['channel_count'] ?? 0;
            $item->last_month_availability = $perProduct['last_month']['availability'];
            $item->last_month_qty_per_day = $periodDays['last_month'] > 0
                ? round(($item->last_month_count ?? 0) / $periodDays['last_month'], 2)
                : 0;

            $item->last_two_month_channel_count = $perProduct['two_months_ago']['channel_count'] ?? 0;
            $item->last_two_month_availability = $perProduct['two_months_ago']['availability'];
            $item->last_two_month_qty_per_day = $periodDays['two_months_ago'] > 0
                ? round(($item->last_two_month_count ?? 0) / $periodDays['two_months_ago'], 2)
                : 0;

            return $item;
        });

        $totalsBase = $this->getSalesSubTotal($productsQuery);

        $totals = array_merge($totalsBase, [
            'this_month_channel_total' => $metrics['aggregates']['this_month']['channel_count_sum'] ?? 0,
            'this_month_availability_avg' => $metrics['aggregates']['this_month']['availability'],
            'this_month_qty_per_day_total' => $periodDays['this_month'] > 0 ? round(($totalsBase['this_month_count_total'] ?? 0) / $periodDays['this_month'], 2) : 0,
            'last_month_channel_total' => $metrics['aggregates']['last_month']['channel_count_sum'] ?? 0,
            'last_month_availability_avg' => $metrics['aggregates']['last_month']['availability'],
            'last_month_qty_per_day_total' => $periodDays['last_month'] > 0 ? round(($totalsBase['last_month_count_total'] ?? 0) / $periodDays['last_month'], 2) : 0,
            'last_two_month_channel_total' => $metrics['aggregates']['two_months_ago']['channel_count_sum'] ?? 0,
            'last_two_month_availability_avg' => $metrics['aggregates']['two_months_ago']['availability'],
            'last_two_month_qty_per_day_total' => $periodDays['two_months_ago'] > 0 ? round(($totalsBase['last_two_month_count_total'] ?? 0) / $periodDays['two_months_ago'], 2) : 0,
        ]);

        $customerClass = get_class(new Customer());

        return Inertia::render('Report/SalesPerformance/IndexProduct', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $customerClass)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $customerClass)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'totals' => $totals,
            'products' => SalesPerformanceProductResource::collection($products),
        ]);
    }

    public function indexGpCategory(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $categories = $this->getUnitCostByCategoryQuery($request);
        $totals = $this->getSalesSubTotal($categories);
        $categories = $categories->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Gp/IndexCategory', [
            'categoryOptions' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'totals' => $totals,
            'categories' => CategoryDBResource::collection($categories),
        ]);
    }

    public function indexGpLocationType(Request $request)
    {
        // $request->merge(['operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id]);
        // if(!$request->operators) {
        //     if(auth()->user()->operator->code == 'HIPL') {
        //         $request->merge(['operators' => [
        //             auth()->user()->operator_id, Operator::where('code', 'HIMD')->first()?->id,
        //             auth()->user()->operator_id, Operator::where('code', 'LEA')->first()?->id,
        //         ]]);
        //     }else {
        //         $request->merge(['operators' => [auth()->user()->operator_id]]);
        //     }
        // }
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $request->is_binded_customer = auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false);
        $className = get_class(new Customer());

        $locationTypes = $this->getUnitCostByLocationTypeQuery($request);
        $totals = $this->getSalesSubTotal($locationTypes);
        $locationTypes = $locationTypes->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/Gp/IndexLocationType', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'totals' => $totals,
            'locationTypes' => LocationTypeDBResource::collection($locationTypes),
        ]);
    }

    public function indexSnapshot(Request $request)
    {
        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $request->merge([
                    'operators' => array_filter([
                        auth()->user()->operator_id,
                        Operator::where('code', 'HIMD')->first()?->id,
                        Operator::where('code', 'LEA')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'UL-ST')->first()?->id,
                    ])
                ]);
            } else {
                $request->merge(['operators' => [auth()->user()->operator_id]]);
            }
        }
        $request->merge(['currentMonth' => isset($request->currentMonth) ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone())]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : false]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false)]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'month_number';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $className = get_class(new Customer());

        $vendSnapshots = $this->getSnapshotQuery($request);
        // dd($vendSnapshots->get()->toArray());
        $vendSnapshots = $vendSnapshots->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
            ->withQueryString();

        return Inertia::render('Report/IndexSnapshot', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)->orderBy('name')->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)->orderBy('name')->get()
            ),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'monthOptions' => $this->getMonthOption(),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
            'vendSnapshots' => VendSnapshotDBResource::collection($vendSnapshots),
        ]);
    }

    public function indexStockCount(Request $request)
    {
        // ---- Operators default
        if (!$request->operators) {
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

        // ---- Date range from preset/custom
        $cfd = $request->input('currentFilterDate');

        // Frontend might send an object {id: "..."}; normalize to id string
        if (is_array($cfd) && isset($cfd['id'])) {
            $cfd = $cfd['id'];
            $request->merge(['currentFilterDate' => $cfd]);
        }

        if ($cfd) {
            if ($cfd !== '-1') {
                // Expect "YYYY-MM-DD,YYYY-MM-DD"
                [$df, $dt] = explode(',', (string) $cfd);
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            } else {
                $tz = $this->getUserTimezone();
                $df = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $dt = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            }
        } else {
            $today = Carbon::today($this->getUserTimezone())->toDateString();
            $request->merge(['date_from' => $today, 'date_to' => $today]);
        }

        // Swap if user sent from > to
        if (strtotime($request->date_from) > strtotime($request->date_to)) {
            $tmp = $request->date_from;
            $request->merge(['date_from' => $request->date_to, 'date_to' => $tmp]);
        }

        // ---- Misc defaults
        $request->merge(['visited' => $request->boolean('visited')]); // default false
        // keep sortKey/sortBy as they’ll be used by the pivot query for allowed keys
        $request->merge([
            'sortKey' => $request->input('sortKey', 'product_code'),
            'sortBy' => $request->input('sortBy', false),
        ]);
        // numberPerPage is read inside the pivot query
        if (!$request->filled('numberPerPage')) {
            $request->merge(['numberPerPage' => 100]);
        }

        // ---- Use the server-side pivot (D0/D1/D2)
        [$paginator, $pivotDates, $totals] = $this->getStockCountPivot3dQuery($request);

        $stockCounts = [
            'data' => ProductStockCountResource::collection(
                collect($paginator->items())
            )->resolve(), // plain array of transformed rows

            // links array (works across Laravel versions)
            'links' => method_exists($paginator, 'linkCollection')
                ? $paginator->linkCollection()->toArray()
                : ($paginator->toArray()['links'] ?? []),

            // meta block your Paginator component expects
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];

        // ---- Render
        return Inertia::render('Report/IndexStockCount', [
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::where('is_inventory', true)->orderBy('name')->orderBy('code')->get()
            ),
            'reportDateOptions' => $this->getReportStockCountDateOptions(),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),

            // Pivot payload (note: this is a DB paginator, not Eloquent models)
            'stockCounts' => $stockCounts,   // rows have *_d0/_d1/_d2 fields
            'pivotDates' => $pivotDates,    // { d0, d1, d2 } for table headers
            'totals' => $totals,
        ]);
    }

    public function indexStockCountDashboard(Request $request)
    {
        // ---- Default operators
        if (!$request->operators) {
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

        // ---- Build graphs (they already honor operators, vendPrefixes, codes, etc)
        $dayGraph = $this->getStockCountDayGraph($request);
        $qtyGraph = $this->getStockCountQtyDayGraph($request);

        return Inertia::render('Report/IndexStockCountDashboard', [
            'dayGraphData' => StockCountDayGraphResource::collection($dayGraph),
            'qtyGraphData' => StockCountDayGraphResource::collection($qtyGraph),
            'locationTypeOptions' => LocationTypeResource::collection(
                LocationType::orderBy('sequence')->get()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'productOptions' => ProductResource::collection(
                Product::where('is_inventory', true)->orderBy('name')->orderBy('code')->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::hasActiveVends()->orderBy('name')->get()
            ),
        ]);
    }



    public function exportUnitCostVendExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $vends = $this->getUnitCostByVendQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($vends)))->download('UnitCostByVend_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($vend) {
            return [
                'ID' => $vend->code,
                'Customer Name' => $vend->customer_code &&
                    $vend->customer_name ?
                    $vend->customer_code . '' . $vend->customer_name :
                    $vend->name,
                'Sales# (thisMth)' => $vend->this_month_count,
                'Sales$ (thisMth)' => $vend->this_month_revenue / 100,
                'GP (thisMth)' => $vend->this_month_gross_profit / 100,
                'GM (thisMth)' => $vend->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $vend->last_month_count,
                'Sales$ (lastMth)' => $vend->last_month_revenue / 100,
                'GP (lastMth)' => $vend->last_month_gross_profit / 100,
                'GM (lastMth)' => $vend->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $vend->last_two_month_count,
                'Sales$ (last2Mth)' => $vend->last_two_month_revenue / 100,
                'GP (last2Mth)' => $vend->last_two_month_gross_profit / 100,
                'GM (last2Mth)' => $vend->last_two_month_gross_profit_margin,
            ];
        });
    }

    public function exportStockCountExcel(Request $request)
    {
        // ------- mirror the defaults/normalization from indexStockCount -------

        // Default operators
        if (!$request->operators) {
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

        // Date range from preset/custom
        $cfd = $request->input('currentFilterDate');
        if (is_array($cfd) && isset($cfd['id'])) {
            $cfd = $cfd['id'];
            $request->merge(['currentFilterDate' => $cfd]);
        }

        if ($cfd) {
            if ($cfd !== '-1') {
                [$df, $dt] = explode(',', (string) $cfd);
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            } else {
                $tz = $this->getUserTimezone();
                $df = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $dt = Carbon::parse($request->date)->setTimezone($tz)->toDateString();
                $request->merge(['date_from' => $df, 'date_to' => $dt]);
            }
        } else {
            $today = Carbon::today($this->getUserTimezone())->toDateString();
            $request->merge(['date_from' => $today, 'date_to' => $today]);
        }

        // Swap if needed
        if (strtotime($request->date_from) > strtotime($request->date_to)) {
            $tmp = $request->date_from;
            $request->merge(['date_from' => $request->date_to, 'date_to' => $tmp]);
        }

        // Sorting & page size (export = all)
        $request->merge([
            'visited' => $request->boolean('visited'),
            'sortKey' => $request->input('sortKey', 'product_code'),
            'sortBy' => $request->input('sortBy', false),
            'numberPerPage' => 'All',
        ]);

        // ------- same pivot as page -------
        [$paginator, $pivotDates, $totals] = $this->getStockCountPivot3dQuery($request);
        $rows = collect($paginator->items());

        // d0 = yesterday of end date, d1 = -2 days, d2 = -3 days
        $d0 = Carbon::parse($pivotDates['d0'])->toDateString();
        // $d1 = Carbon::parse($pivotDates['d1'])->toDateString();
        // $d2 = Carbon::parse($pivotDates['d2'])->toDateString();

        // ----- Build a single canonical header list (order matters) -----
        $headers = [
            'Date',
            'Product ID',
            'Product Name',

            "Unit Cost",
            "Stock Value",
            "Qty in Machine",
            "Qty in Warehouse",
            "Stock Cost",

            // "{$d1} Unit Cost",
            // "{$d1} Stock Value",
            // "{$d1} Qty in Machine",
            // "{$d1} Qty in Warehouse",
            // "{$d1} Stock Cost",
            // "{$d1} Dollar Value",

            // "{$d2} Unit Cost",
            // "{$d2} Stock Value",
            // "{$d2} Qty in Machine",
            // "{$d2} Qty in Warehouse",
            // "{$d2} Stock Cost",
            // "{$d2} Dollar Value",
        ];

        $template = array_fill_keys($headers, null);

        // Helper to build a product row with all keys present
        $makeProductRow = function ($r) use ($template, $d0) {
            $row = $template;

            $row['Date'] = $d0;
            $row['Product ID'] = $r->product_code;
            $row['Product Name'] = $r->product_name;

            // d0
            $row["Unit Cost"] = (float) ($r->unit_cost_d0 ?? 0);
            $row["Stock Value"] = (float) ($r->stock_value_d0 ?? 0);
            $row["Qty in Machine"] = (int) ($r->qty_vend_d0 ?? 0);
            $row["Qty in Warehouse"] = (int) ($r->qty_warehouse_d0 ?? 0);
            $row["Stock Cost"] = (float) ($r->stock_cost_d0 ?? 0);
            // Dollar Value left null for product rows

            // // d1
            // $row["{$d1} Unit Cost"] = (float) ($r->unit_cost_d1 ?? 0);
            // $row["{$d1} Stock Value"] = (float) ($r->stock_value_d1 ?? 0);
            // $row["{$d1} Qty in Machine"] = (int) ($r->qty_vend_d1 ?? 0);
            // $row["{$d1} Qty in Warehouse"] = (int) ($r->qty_warehouse_d1 ?? 0);
            // $row["{$d1} Stock Cost"] = (float) ($r->stock_cost_d1 ?? 0);

            // // d2
            // $row["{$d2} Unit Cost"] = (float) ($r->unit_cost_d2 ?? 0);
            // $row["{$d2} Stock Value"] = (float) ($r->stock_value_d2 ?? 0);
            // $row["{$d2} Qty in Machine"] = (int) ($r->qty_vend_d2 ?? 0);
            // $row["{$d2} Qty in Warehouse"] = (int) ($r->qty_warehouse_d2 ?? 0);
            // $row["{$d2} Stock Cost"] = (float) ($r->stock_cost_d2 ?? 0);

            return $row;
        };

        // Build product rows
        $exportRows = $rows->map($makeProductRow);

        // Helper to build KPI rows with only "Dollar Value" filled
        $makeKpiRow = function (string $label, $d0Val, $d1Val, $d2Val) use ($template, $d0) {
            $row = $template;
            $row['Date'] = $d0;
            $row['Product ID'] = null;
            $row['Product Name'] = $label;

            // $row["{$d0} Dollar Value"] = (float) ($d0Val ?? 0);
            // $row["{$d1} Dollar Value"] = (float) ($d1Val ?? 0);
            // $row["{$d2} Dollar Value"] = (float) ($d2Val ?? 0);
            return $row;
        };

        // Append the 3 KPI rows (values already RM from getStockCountPivot3dQuery)
        // $exportRows->push($makeKpiRow(
        //     'Receivable - Daily cash sales',
        //     $totals->cash_sales_amount_d0 ?? 0,
        //     $totals->cash_sales_amount_d1 ?? 0,
        //     $totals->cash_sales_amount_d2 ?? 0,
        // ));

        // $exportRows->push($makeKpiRow(
        //     'Receivable - Daily cashless sales',
        //     $totals->cashless_sales_amount_d0 ?? 0,
        //     $totals->cashless_sales_amount_d1 ?? 0,
        //     $totals->cashless_sales_amount_d2 ?? 0,
        // ));

        // $exportRows->push($makeKpiRow(
        //     'Coin Float in machines',
        //     $totals->coin_float_amount_d0 ?? 0,
        //     $totals->coin_float_amount_d1 ?? 0,
        //     $totals->coin_float_amount_d2 ?? 0,
        // ));

        // --- Stream the file ---
        return (new FastExcel($this->yieldOneByOne($exportRows)))
            ->download('Stock_Count_' . Carbon::now()->format('Ymd_His') . '.xlsx', fn($row) => $row);
    }

    public function exportUnitCostProductExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $products = $this->getUnitCostByProductQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($products)))->download('UnitCostByProduct_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($product) {
            return [
                'ID' => $product->code,
                'Name' => $product->name,
                'Sales# (thisMth)' => $product->this_month_count,
                'Sales$ (thisMth)' => $product->this_month_revenue / 100,
                'GP (thisMth)' => $product->this_month_gross_profit / 100,
                'GM (thisMth)' => $product->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $product->last_month_count,
                'Sales$ (lastMth)' => $product->last_month_revenue / 100,
                'GP (lastMth)' => $product->last_month_gross_profit / 100,
                'GM (lastMth)' => $product->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $product->last_two_month_count,
                'Sales$ (last2Mth)' => $product->last_two_month_revenue / 100,
                'GP (last2Mth)' => $product->last_two_month_gross_profit / 100,
                'GM (last2Mth)' => $product->last_two_month_gross_profit_margin,
            ];
        });
    }

    public function exportUnitCostCategoryExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $categories = $this->getUnitCostByCategoryQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($categories)))->download('UnitCostByCategory_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($category) {
            return [
                'Name' => $category->name,
                'Sales# (thisMth)' => $category->this_month_count,
                'Sales$ (thisMth)' => $category->this_month_revenue / 100,
                'GP (thisMth)' => $category->this_month_gross_profit / 100,
                'GM (thisMth)' => $category->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $category->last_month_count,
                'Sales$ (lastMth)' => $category->last_month_revenue / 100,
                'GP (lastMth)' => $category->last_month_gross_profit / 100,
                'GM (lastMth)' => $category->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $category->last_two_month_count,
                'Sales$ (last2Mth)' => $category->last_two_month_revenue / 100,
                'GP (last2Mth)' => $category->last_two_month_gross_profit / 100,
                'GM (last2Mth)' => $category->last_two_month_gross_profit_margin,
            ];
        });
    }


    public function exportUnitCostLocationTypeExcel(Request $request)
    {
        $request->sortKey = $request->sortKey ? $request->sortKey : 'this_month_revenue';
        $request->sortBy = isset($request->sortBy) ? $request->sortBy : false;

        $locationTypes = $this->getUnitCostByLocationTypeQuery($request)->get();

        return (new FastExcel($this->yieldOneByOne($locationTypes)))->download('UnitCostByLocationType_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($locationType) {
            return [
                'Name' => $locationType->name,
                'Sales# (thisMth)' => $locationType->this_month_count,
                'Sales$ (thisMth)' => $locationType->this_month_revenue / 100,
                'GP (thisMth)' => $locationType->this_month_gross_profit / 100,
                'GM (thisMth)' => $locationType->this_month_gross_profit_margin,
                'Sales# (lastMth)' => $locationType->last_month_count,
                'Sales$ (lastMth)' => $locationType->last_month_revenue / 100,
                'GP (lastMth)' => $locationType->last_month_gross_profit / 100,
                'GM (lastMth)' => $locationType->last_month_gross_profit_margin,
                'Sales# (last2Mth)' => $locationType->last_two_month_count,
                'Sales$ (last2Mth)' => $locationType->last_two_month_revenue / 100,
                'GP (last2Mth)' => $locationType->last_two_month_gross_profit / 100,
                'GM (last2Mth)' => $locationType->last_two_month_gross_profit_margin,
            ];
        });
    }

    public function exportSnapshotChannelExcel(Request $request)
    {
        $request->merge(['currentMonth' => isset($request->currentMonth) ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone()) : Carbon::today()->setTimezone($this->getUserTimezone())]);
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : false)]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 50;
        $request->sortKey = $request->sortKey ? $request->sortKey : 'month_number';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;

        $vendSnapshots = $this->getSnapshotQuery($request);
        $vendSnapshots = $vendSnapshots->get();
        $vendChannelsArr = [];
        foreach ($vendSnapshots as $vendSnapshot) {
            if ($vendSnapshot->vend_channels_json) {
                foreach (json_decode($vendSnapshot->vend_channels_json) as $channel) {
                    if ($channel->is_active == 1) {
                        array_push($vendChannelsArr, [
                            'vend_code' => $vendSnapshot->vend_code,
                            'full_name' => $vendSnapshot->customer_code ?
                                $vendSnapshot->customer_code . ' ' . $vendSnapshot->customer_name :
                                $vendSnapshot->vend_name,
                            'channel_code' => $channel->code,
                            'product_code' => $channel->product ? $channel->product->code : '',
                            'product_name' => $channel->product ? $channel->product->name : '',
                            'qty' => $channel->qty,
                            'capacity' => $channel->capacity,
                            'price' => $channel->amount / 100,
                            'unit_cost' => $channel->product ? (UnitCost::where('product_id', $channel->product->id)->first() ? UnitCost::where('product_id', $channel->product->id)->first()->cost : 0) : '',
                            'balance_percent' => $channel->capacity ? round($channel->qty / $channel->capacity * 100) : '',
                        ]);
                    }
                }
            }
        }

        return (new FastExcel($this->yieldOneByOne($vendChannelsArr)))->download('Vend_channels_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($vendChannel) {
            return [
                'Machine ID' => $vendChannel['vend_code'],
                'Customer Name' => $vendChannel['full_name'],
                'Channel' => $vendChannel['channel_code'],
                'Product Code' => $vendChannel['product_code'],
                'Product Name' => $vendChannel['product_name'],
                'Qty' => $vendChannel['qty'],
                'Capacity' => $vendChannel['capacity'],
                'Price' => $vendChannel['price'],
                'Unit Cost' => $vendChannel['unit_cost'],
                'Balance Percent(%)' => $vendChannel['balance_percent'],
            ];
        });
    }

    public function exportSalesExcel(Request $request, $type)
    {
        $request->merge(['visited' => isset($request->visited) ? $request->visited : true]);
        $request->merge(['is_binded_customer' => auth()->user()->hasRole('operator') ? 'all' : ($request->is_binded_customer ? $request->is_binded_customer : 'true')]);

        if ($request->currentFilterDate) {
            if ($request->currentFilterDate != '-1') {
                $request->merge(['date_from' => explode(',', $request->currentFilterDate)[0]]);
                $request->merge(['date_to' => explode(',', $request->currentFilterDate)[1]]);
            }
            if ($request->currentFilterDate == '-1') {
                $request->merge(['date_from' => Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->toDateString()]);
                $request->merge(['date_to' => Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->toDateString()]);
            }
        } else {
            $request->merge(['date_from' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
            $request->merge(['date_to' => Carbon::today()->setTimezone($this->getUserTimezone())->toDateString()]);
        }

        $request->sortKey = $request->sortKey ? $request->sortKey : 'amount';
        $request->sortBy = $request->sortBy ? $request->sortBy : false;
        $categoryClassName = get_class(new Customer());
        $modelName = 'vends';

        switch ($type) {
            case 'category':
                $modelName = 'categories';
                break;
            case 'location-type':
                $modelName = 'location_types';
                break;
            case 'product':
                $modelName = 'products';
                break;
            case 'operator':
                $modelName = 'operators';
                break;
            case 'vend':
                $modelName = 'vends';
                break;
            case 'customer':
                $modelName = 'customers';
                break;
        }

        $items = $this->getSalesQuery($request, $modelName);
        $items = $items->when($request->sortKey, function ($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
        })
            ->get();

        return (new FastExcel($this->yieldOneByOne($items)))->download('SalesReport_' . $type . '_' . Carbon::now()->toDateTimeString() . '.xlsx', function ($item) use ($type) {
            $data = [
                'ID' => isset($item->code) ? $item->code : null,
                'Name' => $item->name,
            ];

            if ($type === 'vend' || $type === 'customer') {
                $data['Machine Prefix'] = $item->vend_prefix_name;
                $data['Machine Model'] = $item->vend_model_name;
                $data['Location Type'] = $item->location_type_name;
            }

            $data['Count (Success Only)'] = $item->count;

            if ($type === 'product') {
                $errorNo45 = isset($item->error_count_no_4_5) ? (int) $item->error_count_no_4_5 : 0;
                $successCount = (int) ($item->count ?? 0);
                $errorRateDen = $successCount + $errorNo45;
                $channelAvailability = isset($item->channel_availability) ? (int) $item->channel_availability : null;

                $data['Count (Error Exclude #4 and #5)'] = $errorNo45;
                $data['Error Rate (%)'] = $errorRateDen > 0 ? round($errorNo45 / $errorRateDen * 100, 2) : 0;
                $data['Count (Error #4 and #5)'] = isset($item->error_count_4_5) ? (int) $item->error_count_4_5 : 0;
                $data['Count of Machine'] = isset($item->machine_count) ? (int) $item->machine_count : null;
                $data['Channel Availability'] = $channelAvailability;
                $data['Average Daily Count per Channel'] = $channelAvailability > 0 ? round($successCount / $channelAvailability, 2) : null;
            }

            $data['Amount'] = $item->amount / 100;

            return $data;
        });
    }

    private function getSalesQuery($request, $className)
    {
        $start = Carbon::parse($request->date_from)->startOfDay();
        $end = Carbon::parse($request->date_to)->endOfDay();

        $useProductMetrics = $className === 'products' || $request->filled('product_code') || $request->filled('product_name');

        if ($useProductMetrics) {
            $transactionsQuery = $this->baseVendTransactionMetricsQuery($request, $start, $end)
                ->leftJoin('vend_models', 'vend_models.id', '=', 'gm.vend_model_id')
                ->leftJoin('vend_models as current_vend_models', 'vends.vend_model_id', '=', 'current_vend_models.id')
                ->leftJoin('location_types', 'location_types.id', '=', 'gm.transaction_location_type_id')
                ->leftJoin('categories as customer_categories', 'customers.category_id', '=', 'customer_categories.id');

            $countColumn = 'gm.sale_count';
            // Products tab: use ROUND(amount_cents) — prorated per item, whole cents, correct grand total.
            // Other tabs with product filter: use txn_amount_cents — full basket amount per transaction,
            // so each row exactly matches what the transaction page shows when filtered by that product.
            $amountColumn = ($className === 'products')
                ? 'ROUND(gm.amount_cents)'
                : 'gm.txn_amount_cents';

            switch ($className) {
                case 'products':
                    // Channel Availability for the Product tab.
                    //
                    // Previously this read from the pre-aggregated `product_vend_channels`
                    // snapshot table, which only carries (product_id, date, channel_count).
                    // That table has NO dimension to filter on, so the column ignored every
                    // sidebar filter (Customer / Operator / Location Type / Machine Prefix
                    // / Machine Model / Machine Contract / Product Mapping / Machine ID),
                    // making the value mismatch the other columns whenever a filter was
                    // applied — see user bug report 2026-05-07.
                    //
                    // Fix: compute live from `vend_channels` joined to `vends`, applying
                    // the same vend-level filters that the metrics query uses.  We then
                    // multiply the matched channel count by the number of days in the
                    // selected range to preserve the "channel-days of availability"
                    // semantic (e.g. 30 channels over a 30-day range → 900 channel-days),
                    // which is what the snapshot SUM was approximating in the unfiltered
                    // case.
                    //
                    // Note: this trades a small amount of day-by-day historical accuracy
                    // (channel counts that fluctuated mid-range) for filter correctness,
                    // which the user explicitly needs.  The snapshot table and its sync
                    // job are left untouched.
                    $numDays = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);

                    $pvcSub = $this->buildChannelAvailabilitySubQuery($request, $numDays);

                    $transactionsQuery
                        ->leftJoinSub($pvcSub, 'pvc', 'pvc.product_id', '=', 'gm.product_id')
                        ->selectRaw('gm.product_id as id')
                        ->selectRaw('MAX(products.code) as code')
                        ->selectRaw('MAX(products.name) as name')
                        ->selectRaw('SUM(gm.error_count) AS error_count')
                        ->selectRaw('SUM(gm.error_count_no_4_5) AS error_count_no_4_5')
                        ->selectRaw('SUM(gm.error_count_4_5) AS error_count_4_5')
                        ->selectRaw('MAX(pvc.channel_availability) AS channel_availability')
                        ->selectRaw('MAX(pvc.machine_count) AS machine_count');
                    // Count (Success Only) = sale_count minus ALL errors (both #3,#6,#7,#9 and #4,#5).
                    // Previously this only subtracted error_count_4_5, which gave correct numbers in
                    // databases where every error happened to be code 4 or 5, but inflated the success
                    // count whenever errors of code 3/7/9 (and others) existed.
                    $countColumn = 'gm.sale_count - gm.error_count';
                    $groupByExpr = 'gm.product_id';
                    break;
                case 'categories':
                    $transactionsQuery
                        ->selectRaw('customers.category_id as id')
                        ->selectRaw('MAX(customer_categories.name) as name');
                    $groupByExpr = 'customers.category_id';
                    break;
                case 'location_types':
                    $transactionsQuery
                        ->selectRaw('gm.transaction_location_type_id as id')
                        ->selectRaw('MAX(location_types.name) as name');
                    $groupByExpr = 'gm.transaction_location_type_id';
                    break;
                case 'operators':
                    $transactionsQuery
                        ->selectRaw('gm.operator_id as id')
                        ->selectRaw('MAX(operators.code) as code')
                        ->selectRaw('MAX(operators.name) as name');
                    $groupByExpr = 'gm.operator_id';
                    break;
                case 'vends':
                    $transactionsQuery
                        ->selectRaw('gm.vend_id as id')
                        ->selectRaw('MAX(vends.code) as code')
                        ->selectRaw('MAX(CASE WHEN customers.id IS NOT NULL THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\')," (", IFNULL(current_vend_prefixes.name, \'\'),") - ", IFNULL(customers.name, \'\')) ELSE vends.name END) as name')
                        ->selectRaw('COALESCE(MAX(vend_models.name), MAX(current_vend_models.name)) as vend_model_name')
                        ->selectRaw('MAX(current_vend_prefixes.name) as vend_prefix_name')
                        ->selectRaw('MAX(product_mappings.name) as product_mapping_name')
                        ->selectRaw('MAX(location_types.name) as location_type_name');
                    $groupByExpr = 'gm.vend_id';
                    break;
                case 'customers':
                    $transactionsQuery
                        ->selectRaw('gm.customer_id as id')
                        ->selectRaw('MAX(gm.customer_id + 20000) as code')
                        ->selectRaw('MAX(CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\'), " - ", IFNULL(customers.name, \'\')) ELSE customers.name END) as name')
                        ->selectRaw('COALESCE(MAX(vend_models.name), MAX(current_vend_models.name)) as vend_model_name')
                        ->selectRaw('MAX(current_vend_prefixes.name) as vend_prefix_name')
                        ->selectRaw('MAX(product_mappings.name) as product_mapping_name')
                        ->selectRaw('MAX(location_types.name) as location_type_name');
                    $groupByExpr = 'gm.customer_id';
                    break;
                default:
                    $groupByExpr = 'gm.product_id';
            }
        } else {
            $transactionsQuery = $this->baseVendRecordsQuery($request, $start, $end);

            $countColumn = 'vr.total_count';
            $amountColumn = 'vr.total_amount';

            switch ($className) {
                case 'categories':
                    $transactionsQuery
                        ->selectRaw('customers.category_id as id')
                        ->selectRaw('MAX(categories.name) as name');
                    $groupByExpr = 'customers.category_id';
                    break;
                case 'location_types':
                    $transactionsQuery
                        ->selectRaw('vr.location_type_id as id')
                        ->selectRaw('MAX(location_types.name) as name');
                    $groupByExpr = 'vr.location_type_id';
                    break;
                case 'operators':
                    $transactionsQuery
                        ->selectRaw('vr.operator_id as id')
                        ->selectRaw('MAX(operators.code) as code')
                        ->selectRaw('MAX(operators.name) as name');
                    $groupByExpr = 'vr.operator_id';
                    break;
                case 'vends':
                    $transactionsQuery
                        ->leftJoin('vend_models as current_vend_models', 'vends.vend_model_id', '=', 'current_vend_models.id')
                        ->selectRaw('vr.vend_id as id')
                        ->selectRaw('MAX(vends.code) as code')
                        ->selectRaw('MAX(CASE WHEN customers.id IS NOT NULL THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\')," (", IFNULL(current_vend_prefixes.name, \'\'),") - ", IFNULL(customers.name, \'\')) ELSE vends.name END) as name')
                        ->selectRaw('COALESCE(MAX(vend_models.name), MAX(current_vend_models.name)) as vend_model_name')
                        ->selectRaw('MAX(current_vend_prefixes.name) as vend_prefix_name')
                        ->selectRaw('MAX(product_mappings.name) as product_mapping_name')
                        ->selectRaw('MAX(location_types.name) as location_type_name');
                    $groupByExpr = 'vr.vend_id';
                    break;
                case 'customers':
                    $transactionsQuery
                        ->leftJoin('vend_models as current_vend_models', 'vends.vend_model_id', '=', 'current_vend_models.id')
                        ->selectRaw('vr.customer_id as id')
                        ->selectRaw('MAX(vr.customer_id + 20000) as code')
                        ->selectRaw('MAX(CASE WHEN customers.person_id IS NOT NULL THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\'), " - ", IFNULL(customers.name, \'\')) ELSE customers.name END) as name')
                        ->selectRaw('COALESCE(MAX(vend_models.name), MAX(current_vend_models.name)) as vend_model_name')
                        ->selectRaw('MAX(current_vend_prefixes.name) as vend_prefix_name')
                        ->selectRaw('MAX(product_mappings.name) as product_mapping_name')
                        ->selectRaw('MAX(location_types.name) as location_type_name');
                    $groupByExpr = 'vr.customer_id';
                    break;
                default:
                    $groupByExpr = 'vr.vend_id';
            }
        }

        $transactionsQuery
            ->selectRaw('SUM(' . $countColumn . ') AS count')
            ->selectRaw('SUM(' . $amountColumn . ') AS amount')
            ->groupBy(DB::raw($groupByExpr));

        return $transactionsQuery;
    }

    /**
     * Build the Channel Availability subquery for the Sales Report > Product tab.
     *
     * Returns a subquery selecting (product_id, channel_availability) where
     * channel_availability = COUNT(matching active vend_channels) * $numDays.
     *
     * Applies the same vend-level filters (Customer, Machine ID, Operator,
     * Location Type, Machine Prefix, Machine Model, Machine Contract,
     * Product Mapping, Customer Binded?) that filterGpMetricsReport() applies
     * to the main metrics query, so the column reacts consistently with the
     * other columns when filters are set.
     *
     * Operator-scoped users (non Happy Ice) are also restricted to their own
     * operator_id and assigned vends, mirroring filterOperatorVendTransactionDB.
     */
    private function buildChannelAvailabilitySubQuery($request, int $numDays)
    {
        $sub = DB::table('vend_channels as vc')
            ->join('vends', 'vc.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
            ->where('vc.is_active', true)
            ->whereNotNull('vc.product_id')
            ->where('vends.is_active', true)
            ->where('vends.is_disposed', false);

        // ----- Machine ID (codes) -----
        if ($request->filled('codes')) {
            $codes = $request->codes;
            if (strpos($codes, ',') !== false) {
                $codeList = array_filter(array_map('trim', explode(',', $codes)));
                if (!empty($codeList)) {
                    $sub->whereIn('vends.code', $codeList);
                }
            } else {
                $sub->where('vends.code', 'LIKE', "{$codes}%");
            }
        }

        // ----- Customer (free-text) -----
        if ($request->filled('customer')) {
            $search = $request->customer;
            $sub->where(function ($q) use ($search) {
                $q->where('customers.virtual_customer_code', 'LIKE', "{$search}%")
                    ->orWhere('vend_prefixes.name', 'LIKE', "{$search}%")
                    ->orWhere('customers.name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('customer_code')) {
            $sub->where('customers.code', 'LIKE', '%' . $request->customer_code . '%');
        }

        if ($request->filled('customer_name')) {
            $name = $request->customer_name;
            $sub->where(function ($q) use ($name) {
                $q->where('customers.name', 'LIKE', "%{$name}%")
                    ->orWhere('vends.name', 'LIKE', "%{$name}%");
            });
        }

        // ----- Customer Binded? -----
        if ($request->filled('is_binded_customer') && $request->is_binded_customer !== 'all') {
            if ($request->is_binded_customer === 'true' || $request->is_binded_customer === true) {
                $sub->whereNotNull('vends.customer_id');
            } elseif ($request->is_binded_customer === 'false' || $request->is_binded_customer === false) {
                $sub->whereNull('vends.customer_id');
            }
        }

        // ----- Location Type -----
        if ($request->filled('location_type_id') && $request->location_type_id !== 'all') {
            $sub->where('vends.location_type_id', $request->location_type_id);
        }

        // ----- Operator -----
        if ($request->filled('operators')) {
            $ops = $request->operators;
            if (is_array($ops) && !in_array('all', $ops, true)) {
                $sub->whereIn('vends.operator_id', $ops);
            }
        }

        // ----- Machine Contract -----
        if ($request->filled('vendContracts')) {
            $vc = $request->vendContracts;
            if (is_array($vc) && !in_array('all', $vc, true)) {
                $sub->whereIn('vends.vend_contract_id', $vc);
            }
        }

        // ----- Machine Model -----
        if ($request->filled('vendModels')) {
            $vm = $request->vendModels;
            if (is_array($vm) && !in_array('all', $vm, true)) {
                $sub->whereIn('vends.vend_model_id', $vm);
            }
        }

        // ----- Machine Prefix (with Single-UD expansion, matching filterGpMetricsReport) -----
        if ($request->filled('vendPrefixes')) {
            $vp = $request->vendPrefixes;
            if (is_array($vp) && !in_array('all', $vp, true)) {
                if (in_array('single-ud', $vp, true)) {
                    $vp = array_unique(array_merge($vp, [56, 57, 58, 60, 63, 64, 76, 83]));
                    $idx = array_search('single-ud', $vp, true);
                    if ($idx !== false) {
                        unset($vp[$idx]);
                    }
                }
                $sub->whereIn('vends.vend_prefix_id', $vp);
            }
        }

        // ----- Product Mapping -----
        if ($request->filled('productMappings')) {
            $pm = $request->productMappings;
            $ids = is_array($pm) ? $pm : [$pm];
            $ids = array_filter($ids, fn($v) => $v !== null && $v !== '');
            if (!in_array('all', $ids, true) && !empty($ids)) {
                $sub->whereIn('vends.product_mapping_id', $ids);
            }
        }

        // ----- Operator-scoped user restriction (mirrors filterOperatorVendTransactionDB) -----
        if (auth()->check()) {
            $user = auth()->user();
            $operatorId = $user->operator_id;
            $isHappyIce = $operatorId == 1;
            if (!$isHappyIce && $operatorId) {
                $sub->where('vends.operator_id', $operatorId);
            }
            if ($user->vends()->exists()) {
                $vendIds = $user->vends->pluck('id')->toArray();
                if (!empty($vendIds)) {
                    $sub->whereIn('vends.id', $vendIds);
                }
            }
        }

        // channel_availability: only count channels that STILL HAVE STOCK
        //   (vc.qty > 0) — a sold-out channel cannot generate sales, so counting
        //   it would unfairly inflate the denominator of "Average Daily Count per
        //   Channel".  Requested by user 2026-05-27.
        // machine_count: number of distinct machines carrying this SKU (on an
        //   active channel) as of the latest snapshot — NOT restricted by stock,
        //   so it reflects how many machines the product is loaded into.
        return $sub
            ->selectRaw('vc.product_id, SUM(CASE WHEN vc.qty > 0 THEN 1 ELSE 0 END) * ' . (int) $numDays . ' AS channel_availability, COUNT(DISTINCT vc.vend_id) AS machine_count')
            ->groupBy('vc.product_id');
    }

    private function baseVendRecordsQuery($request, Carbon $start, Carbon $end): Builder
    {
        $today = Carbon::today()->setTimezone($this->getUserTimezone());

        // If the end date is before today, we only need historical data from vend_records
        if ($end->lt($today)) {
            $query = DB::table('vend_records as vr')
                ->whereBetween('vr.date', [$start, $end]);
        } else {
            // We need to handle today's data or mix of history and today
            $historical = DB::table('vend_records as vr')
                ->select(
                    'vr.vend_id',
                    'vr.customer_id',
                    'vr.operator_id',
                    'vr.location_type_id',
                    'vr.vend_prefix_id',
                    'vr.vend_model_id',
                    'vr.date',
                    'vr.total_count',
                    'vr.total_amount',
                    'vr.revenue'
                )
                ->whereBetween('vr.date', [$start, $end])
                ->where('vr.date', '<', $today->toDateString());

            $live = $this->getLiveVendRecordsQuery(
                $start->lt($today) ? $today : $start,
                $end
            );

            // If start is today or future, historical is empty (filtered by date < today)
            if ($start->gte($today)) {
                $query = DB::query()->fromSub($live, 'vr');
            } else {
                $query = DB::query()->fromSub($historical->unionAll($live), 'vr');
            }
        }

        $query
            ->leftJoin('vends', 'vr.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vr.customer_id', '=', 'customers.id')
            ->leftJoin('operators', 'vr.operator_id', '=', 'operators.id')
            ->leftJoin('location_types', 'vr.location_type_id', '=', 'location_types.id')
            ->leftJoin('vend_prefixes', 'vr.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('vend_prefixes as current_vend_prefixes', 'vends.vend_prefix_id', '=', 'current_vend_prefixes.id')
            ->leftJoin('product_mappings', 'vends.product_mapping_id', '=', 'product_mappings.id')
            ->leftJoin('vend_models', 'vr.vend_model_id', '=', 'vend_models.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id');

        $query = $this->filterVendRecordsReport($query, $request);

        return $this->filterOperatorVendTransactionDB($query);
    }

    private function getLiveVendRecordsQuery(Carbon $from, Carbon $to)
    {
        $rawQuery = GpMetricsAggregator::buildRawQuery($from, $to);

        return DB::query()->fromSub($rawQuery, 'gm')
            ->groupBy('date', 'vend_id', 'customer_id')
            ->select(
                'vend_id',
                'customer_id',
                'operator_id',
                'transaction_location_type_id AS location_type_id',
                'vend_prefix_id',
                'vend_model_id',
                DB::raw('txn_date as date'),
                DB::raw('SUM(sale_count) as total_count'),
                DB::raw('SUM(amount_cents) as total_amount'),
                DB::raw('SUM(amount_cents) as revenue')
            );
    }

    private function baseVendTransactionMetricsQuery($request, Carbon $start, Carbon $end, ?string $locationTypeColumn = null): Builder
    {
        $today = Carbon::today()->setTimezone($this->getUserTimezone());

        // ── diagnostic: log routing decision ──────────────────────────────────
        $gpMetricsCount = DB::table('gp_metrics')
            ->whereBetween('txn_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        if ($end->lt($today)) {
            $path = 'historical (gp_metrics)';
            $rawQuery = GpMetricsAggregator::buildHistoricalQuery($start, $end);
        } elseif ($start->gte($today)) {
            $path = 'live (buildRawQuery)';
            $rawQuery = GpMetricsAggregator::buildRawQuery($start, $end);
        } else {
            $path = 'mixed (gp_metrics UNION buildRawQuery)';
            $historical = GpMetricsAggregator::buildHistoricalQuery($start, $today->copy()->subDay());
            $live = GpMetricsAggregator::buildRawQuery($today, $end);
            $rawQuery = $historical->unionAll($live);
        }

        Log::channel('single')->info('[SalesReport] baseVendTransactionMetricsQuery', [
            'path'             => $path,
            'start'            => $start->toIso8601String(),
            'end'              => $end->toIso8601String(),
            'today'            => $today->toIso8601String(),
            'user_timezone'    => $this->getUserTimezone(),
            'gp_metrics_rows'  => $gpMetricsCount,
        ]);
        // ─────────────────────────────────────────────────────────────────────

        $dataset = DB::query()->fromSub($rawQuery, 'gm');

        $query = $dataset
            ->leftJoin('vends', 'gm.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'gm.customer_id', '=', 'customers.id')
            ->leftJoin('products', 'gm.product_id', '=', 'products.id')
            ->leftJoin('categories', 'gm.category_id', '=', 'categories.id')
            ->leftJoin('vend_prefixes', 'gm.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('vend_prefixes as current_vend_prefixes', 'vends.vend_prefix_id', '=', 'current_vend_prefixes.id')
            ->leftJoin('product_mappings', 'vends.product_mapping_id', '=', 'product_mappings.id')
            ->leftJoin('operators', 'operators.id', '=', 'gm.operator_id');

        $query = $this->filterGpMetricsReport(
            $query,
            $request,
            $locationTypeColumn ?? 'gm.transaction_location_type_id'
        );

        return $this->filterOperatorVendTransactionDB($query);
    }

    private function getStockCountDayGraph(Request $request)
    {
        $tz = $this->getUserTimezone();
        $from = now($tz)->startOfMonth()->subMonth()->startOfDay();
        $to = now($tz)->subDay()->endOfDay();

        if ($request->filled('day_date_from'))
            $from = Carbon::parse($request->day_date_from, $tz)->startOfDay();
        if ($request->filled('day_date_to'))
            $to = Carbon::parse($request->day_date_to, $tz)->endOfDay();

        $dateSql = "DATE(CONCAT(sc.year,'-',LPAD(sc.month,2,'0'),'-',LPAD(sc.day,2,'0')))";

        // ----- coin float (simple sum per day) -----
        $coinQuery = DB::table('stock_counts as sc')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true))
                    $q->whereIn('sc.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    if (in_array('single-ud', $ids, true)) {
                        $ids = array_unique(array_merge($ids, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $ids = array_values(array_diff($ids, ['single-ud']));
                    }
                    $q->whereIn('sc.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all')
                    $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array) $codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1)
                        $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1)
                        $sq->where('v.code', 'LIKE', '%' . $codes[0] . '%');
                });
            })
            ->when($request->customer, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereExists(function ($sq) use ($search) {
                        $sq->from('customers as c')
                            ->whereColumn('c.id', 'sc.customer_id')
                            ->where(function ($sub) use ($search) {
                                $sub->where('c.virtual_customer_code', 'LIKE', "{$search}%")
                                    ->orWhere('c.name', 'LIKE', "%{$search}%");
                            });
                    })
                        ->orWhereExists(function ($sq) use ($search) {
                            $sq->from('vend_prefixes as vp')
                                ->whereColumn('vp.id', 'sc.vend_prefix_id')
                                ->where('vp.name', 'LIKE', "{$search}%");
                        });
                });
            })
            ->when($request->products, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereExists(function ($sq) use ($ids) {
                        $sq->from('stock_count_items as sci_filter')
                            ->whereColumn('sci_filter.stock_count_id', 'sc.id')
                            ->whereIn('sci_filter.product_id', $ids);
                    });
                }
            });

        $coinQuery = $this->applyStockCountDateRange($coinQuery, $from, $to);

        $coin = $coinQuery
            ->groupBy(DB::raw($dateSql))
            ->selectRaw("$dateSql as d")
            ->selectRaw('SUM(sc.coin_float_amount) / 100.0 as coin_float_rm')
            ->pluck('coin_float_rm', 'd');

        // ----- STOCK VALUE & (pivot-style) STOCK COST per day -----
        // Build a per-product-per-day subquery that mimics getStockCountPivot3dQuery:
        //   machine_cost_cents = SUM(unit_cost * qty_vend)
        //   wh_qty = MAX(qty_warehouse), wh_uc = MAX(unit_cost)
        //   stock_cost_rm_per_product = (machine_cost_cents + wh_qty * wh_uc) / 100
        // Also carry stock_value_amount to sum later.
        $perProductPerDay = DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true))
                    $q->whereIn('sc.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true))
                    $q->whereIn('sc.vend_prefix_id', $ids);
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all')
                    $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array) $codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1)
                        $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1)
                        $sq->where('v.code', 'LIKE', '%' . $codes[0] . '%');
                });
            });

        $perProductPerDay = $this->applyStockCountDateRange($perProductPerDay, $from, $to)
            ->groupBy(DB::raw($dateSql), 'sci.product_id')
            ->groupBy(DB::raw($dateSql), 'sci.product_id')
            ->selectRaw("$dateSql as d")
            ->selectRaw('SUM(sci.unit_cost_amount * sci.qty_vend)               as machine_cost_cents')
            ->selectRaw('MAX(sci.qty_warehouse)                                 as wh_qty')
            ->selectRaw('MAX(sci.unit_cost_amount)                              as wh_uc_cents')
            ->selectRaw('SUM(sci.stock_value_amount)                            as stock_value_cents');

        $rows = DB::query()
            ->fromSub($perProductPerDay, 'ppd')
            ->groupBy('d')
            ->selectRaw('d')
            // pivot-style stock cost (RM)
            ->selectRaw('SUM( (machine_cost_cents + wh_qty * wh_uc_cents) / 100.0 ) as stock_cost_rm')
            // stock value in machines (RM)
            ->selectRaw('SUM( stock_value_cents / 100.0 ) as stock_value_rm')
            ->get()
            ->keyBy('d');

        // ----- assemble continuous daily series -----
        $series = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $y = (int) $cursor->year;
            $m = (int) $cursor->month;
            $day = (int) $cursor->day;

            $row = $rows->get($d);
            $series[] = (object) [
                'amount' => $row?->stock_value_rm ?? 0.0, // y (left): Stock Value in Machines (RM)
                'count' => $row?->stock_cost_rm ?? 0.0, // y1 (right): Total Stock Cost - before GST (RM)
                'coin_float' => $coin[$d] ?? 0.0,

                'date' => $d,
                'day' => $day,
                'month' => $m,
                'month_name' => Carbon::createFromDate($y, $m, 1)->format('F'),
                'year' => $y,
            ];

            $cursor->addDay();
        }

        usort($series, fn($a, $b) => strcmp($a->date, $b->date));
        return collect($series);
    }

    private function getStockCountQtyDayGraph(Request $request)
    {
        $tz = $this->getUserTimezone();
        $from = now($tz)->startOfMonth()->subMonth()->startOfDay();
        $to = now($tz)->subDay()->endOfDay();

        if ($request->filled('day_date_from'))
            $from = Carbon::parse($request->day_date_from, $tz)->startOfDay();
        if ($request->filled('day_date_to'))
            $to = Carbon::parse($request->day_date_to, $tz)->endOfDay();

        $dateSql = "DATE(CONCAT(sc.year,'-',LPAD(sc.month,2,'0'),'-',LPAD(sc.day,2,'0')))";

        // Per product, per day:
        // - machine_qty  = SUM(qty_vend)
        // - warehouse_qty = MAX(qty_warehouse) (to avoid multiplying counts across rows)
        $perProductPerDay = DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true))
                    $q->whereIn('sc.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    if (in_array('single-ud', $ids, true)) {
                        $ids = array_unique(array_merge($ids, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $ids = array_values(array_diff($ids, ['single-ud']));
                    }
                    $q->whereIn('sc.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all')
                    $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array) $codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1)
                        $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1)
                        $sq->where('v.code', 'LIKE', '%' . $codes[0] . '%');
                });
            })
            ->when($request->customer, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereExists(function ($sq) use ($search) {
                        $sq->from('customers as c')
                            ->whereColumn('c.id', 'sc.customer_id')
                            ->where(function ($sub) use ($search) {
                                $sub->where('c.virtual_customer_code', 'LIKE', "{$search}%")
                                    ->orWhere('c.name', 'LIKE', "%{$search}%");
                            });
                    })
                        ->orWhereExists(function ($sq) use ($search) {
                            $sq->from('vend_prefixes as vp')
                                ->whereColumn('vp.id', 'sc.vend_prefix_id')
                                ->where('vp.name', 'LIKE', "{$search}%");
                        });
                });
            })
            ->when($request->products, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sci.product_id', $ids);
                }
            });

        $perProductPerDay = $this->applyStockCountDateRange($perProductPerDay, $from, $to)
            ->groupBy(DB::raw($dateSql), 'sci.product_id')
            ->selectRaw("$dateSql as d")
            ->selectRaw('SUM(sci.qty_vend)     as machine_qty')
            ->selectRaw('MAX(sci.qty_warehouse) as warehouse_qty');

        // Aggregate to a single row per day
        $rows = DB::query()
            ->fromSub($perProductPerDay, 'ppd')
            ->groupBy('d')
            ->selectRaw('d')
            ->selectRaw('SUM(machine_qty)   as machine_qty')
            ->selectRaw('SUM(warehouse_qty) as warehouse_qty')
            ->get()
            ->keyBy('d');

        // ---- Balance % per day (average across all vends) ----
        // Per vend per day: SUM(qty_vend) / vend_capacity * 100
        // Vend capacity from current active vend_channels (capacity > 0)
        $vendCapacitySub = DB::table('vend_channels')
            ->where('is_active', true)
            ->where('capacity', '>', 0)
            ->groupBy('vend_id')
            ->selectRaw('vend_id, SUM(capacity) as total_capacity');

        // Rebuild filters for per-vend-per-day balance % subquery
        $perVendPerDay = DB::table('stock_count_items as sci2')
            ->join('stock_counts as sc2', 'sc2.id', '=', 'sci2.stock_count_id')
            ->joinSub($vendCapacitySub, 'vc', 'vc.vend_id', '=', 'sc2.vend_id')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true))
                    $q->whereIn('sc2.operator_id', $ids);
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    if (in_array('single-ud', $ids, true)) {
                        $ids = array_unique(array_merge($ids, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $ids = array_values(array_diff($ids, ['single-ud']));
                    }
                    $q->whereIn('sc2.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all')
                    $q->whereIn('sc2.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes) ? array_values(array_filter(array_map('trim', explode(',', $codes)))) : (array) $codes;
                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v2')->whereColumn('v2.id', 'sc2.vend_id');
                    if (count($codes) > 1)
                        $sq->whereIn('v2.code', $codes);
                    elseif (count($codes) === 1)
                        $sq->where('v2.code', 'LIKE', '%' . $codes[0] . '%');
                });
            })
            ->when($request->customer, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereExists(function ($sq) use ($search) {
                        $sq->from('customers as c2')
                            ->whereColumn('c2.id', 'sc2.customer_id')
                            ->where(function ($sub) use ($search) {
                                $sub->where('c2.virtual_customer_code', 'LIKE', "{$search}%")
                                    ->orWhere('c2.name', 'LIKE', "%{$search}%");
                            });
                    })
                        ->orWhereExists(function ($sq) use ($search) {
                            $sq->from('vend_prefixes as vp2')
                                ->whereColumn('vp2.id', 'sc2.vend_prefix_id')
                                ->where('vp2.name', 'LIKE', "{$search}%");
                        });
                });
            });

        $dateSql2 = "DATE(CONCAT(sc2.year,'-',LPAD(sc2.month,2,'0'),'-',LPAD(sc2.day,2,'0')))";
        // Inline date range filter (applyStockCountDateRange hardcodes sc. alias; here we need sc2.)
        $fromYear2 = (int) $from->year;
        $fromMonth2 = (int) $from->month;
        $fromDay2 = (int) $from->day;
        $toYear2 = (int) $to->year;
        $toMonth2 = (int) $to->month;
        $toDay2 = (int) $to->day;
        $perVendPerDay = $perVendPerDay->where(function ($between) use ($fromYear2, $fromMonth2, $fromDay2, $toYear2, $toMonth2, $toDay2) {
            $between
                ->where(function ($lower) use ($fromYear2, $fromMonth2, $fromDay2) {
                    $lower->where('sc2.year', '>', $fromYear2)
                        ->orWhere(function ($e) use ($fromYear2, $fromMonth2) {
                            $e->where('sc2.year', $fromYear2)->where('sc2.month', '>', $fromMonth2);
                        })
                        ->orWhere(function ($e) use ($fromYear2, $fromMonth2, $fromDay2) {
                            $e->where('sc2.year', $fromYear2)->where('sc2.month', $fromMonth2)->where('sc2.day', '>=', $fromDay2);
                        });
                })
                ->where(function ($upper) use ($toYear2, $toMonth2, $toDay2) {
                    $upper->where('sc2.year', '<', $toYear2)
                        ->orWhere(function ($e) use ($toYear2, $toMonth2) {
                            $e->where('sc2.year', $toYear2)->where('sc2.month', '<', $toMonth2);
                        })
                        ->orWhere(function ($e) use ($toYear2, $toMonth2, $toDay2) {
                            $e->where('sc2.year', $toYear2)->where('sc2.month', $toMonth2)->where('sc2.day', '<=', $toDay2);
                        });
                });
        })
            ->groupBy(DB::raw($dateSql2), 'sc2.vend_id')
            ->selectRaw("$dateSql2 as d")
            ->selectRaw('sc2.vend_id')
            ->selectRaw('SUM(sci2.qty_vend) as vend_qty')
            ->selectRaw('MAX(vc.total_capacity) as vend_capacity')
            ->selectRaw('CASE WHEN MAX(vc.total_capacity) > 0 THEN ROUND(SUM(sci2.qty_vend) / MAX(vc.total_capacity) * 100, 2) ELSE NULL END as vend_balance_pct');

        $balanceRows = DB::query()
            ->fromSub($perVendPerDay, 'pvd')
            ->whereNotNull('pvd.vend_balance_pct')
            ->groupBy('d')
            ->selectRaw('d')
            ->selectRaw('ROUND(AVG(vend_balance_pct), 2) as avg_balance_pct')
            ->get()
            ->keyBy('d');

        // Build continuous daily series
        $series = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $d = $cursor->toDateString();
            $y = (int) $cursor->year;
            $m = (int) $cursor->month;
            $day = (int) $cursor->day;

            $row = $rows->get($d);
            $balanceRow = $balanceRows->get($d);
            $series[] = (object) [
                'date' => $d,
                'day' => $day,
                'month' => $m,
                'month_name' => Carbon::createFromDate($y, $m, 1)->format('F'),
                'year' => $y,
                'machine_qty' => $row?->machine_qty ?? 0,
                'warehouse_qty' => $row?->warehouse_qty ?? 0,
                'balance_percent' => $balanceRow?->avg_balance_pct ?? null,
            ];

            $cursor->addDay();
        }

        usort($series, fn($a, $b) => strcmp($a->date, $b->date));
        return collect($series);
    }


    private function metricsDataset(Carbon $start, Carbon $end): Builder
    {
        $columns = $this->gpMetricSelectColumns;
        $today = Carbon::today();
        $queries = [];

        // Split at the end of the previous month.
        // This ensures that the current month is always calculated live, avoiding missing data issues
        // if the background job is delayed.
        // Split at the end of the month before last.
        // This includes "last month" in the live calculation to ensure data is present
        // even if the monthly aggregation job for the last month hasn't completed yet.
        $endOfLastMonth = $today->copy()->startOfMonth()->subMonth()->subDay();

        $factStart = $start->copy();
        $factEndCandidate = $end->copy();

        if ($factStart->lte($endOfLastMonth)) {
            $effectiveFactEnd = $factEndCandidate->min($endOfLastMonth);
            if ($effectiveFactEnd->gte($factStart)) {
                // Insert NULL placeholder for txn_amount_cents (a live-only column) immediately
                // after amount_cents to match the column order produced by buildRawQuery.
                $amountIdx = array_search('amount_cents', $columns);
                $gpMetricsColumns = $amountIdx !== false
                    ? array_merge(
                        array_slice($columns, 0, $amountIdx + 1),
                        [DB::raw('NULL as txn_amount_cents')],
                        array_slice($columns, $amountIdx + 1)
                    )
                    : array_merge($columns, [DB::raw('NULL as txn_amount_cents')]);

                $queries[] = DB::table('gp_metrics')
                    ->select($gpMetricsColumns)
                    ->whereBetween('txn_date', [$factStart->toDateString(), $effectiveFactEnd->toDateString()]);
            }
        }

        // If the requested range extends beyond the end of last month, use live aggregation for that part
        if ($end->gt($endOfLastMonth)) {
            $startOfCurrentMonth = $endOfLastMonth->copy()->addDay();
            $liveStart = $start->copy()->max($startOfCurrentMonth);
            $queries[] = GpMetricsAggregator::buildRawQuery($liveStart, $end);
        }

        if (empty($queries)) {
            $queries[] = GpMetricsAggregator::buildRawQuery($start, $end);
        }

        $base = array_shift($queries);
        foreach ($queries as $query) {
            $base = $base->unionAll($query);
        }

        return DB::query()->fromSub($base, 'gm');
    }

    private function baseGpMetricsQuery($request, Carbon $start, Carbon $end, ?string $locationTypeColumn = null): Builder
    {
        $dataset = $this->metricsDataset($start, $end);

        $query = $dataset
            ->leftJoin('vends', 'gm.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'gm.customer_id', '=', 'customers.id')
            ->leftJoin('products', 'gm.product_id', '=', 'products.id')
            ->leftJoin('categories', 'gm.category_id', '=', 'categories.id')
            ->leftJoin('category_groups', 'gm.category_group_id', '=', 'category_groups.id')
            ->leftJoin('vend_prefixes', 'gm.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('operators', 'operators.id', '=', 'gm.operator_id');

        $query = $this->filterGpMetricsReport(
            $query,
            $request,
            $locationTypeColumn ?? 'gm.transaction_location_type_id'
        );

        return $this->filterOperatorVendTransactionDB($query);
    }

    private function buildUnitCostByVendBaseComponents($request): array
    {
        $currentDate = $request->currentMonth
            ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
            : Carbon::today()->setTimezone($this->getUserTimezone());

        $rangeStart = $currentDate->copy()->subMonths(2)->startOfMonth();
        $rangeEnd = $currentDate->copy()->endOfMonth();
        $monthDiffExpression = 'PERIOD_DIFF(DATE_FORMAT("' . $currentDate->format('Y-m') . '-01", "%Y%m"), DATE_FORMAT(gm.txn_date, "%Y%m"))';

        $baseQuery = $this->baseGpMetricsQuery($request, $rangeStart, $rangeEnd)
            ->whereNotNull('gm.vend_id');

        return [$baseQuery, $monthDiffExpression];
    }

    private function getUnitCostByVendQuery($request)
    {
        [$baseQuery, $monthDiffExpression] = $this->buildUnitCostByVendBaseComponents($request);

        $query = $baseQuery
            ->selectRaw('gm.vend_id as id')
            ->selectRaw('MAX(vends.name) as name')
            ->selectRaw('MAX(vends.code) as code')
            ->selectRaw('MAX(CASE WHEN customers.person_id THEN CONCAT(IFNULL(customers.virtual_customer_code, \'\')," (", IFNULL(customers.virtual_customer_prefix, \'\'),")") ELSE vends.code END) as customer_code')
            ->selectRaw('MAX(customers.name) as customer_name')
            ->selectRaw($monthDiffExpression . ' as month_diff')
            ->selectRaw('SUM(gm.sale_count) as count')
            ->selectRaw('SUM(gm.amount_cents) as revenue')
            ->selectRaw('SUM(gm.gross_profit_cents) as gross_profit')
            ->selectRaw('ROUND(SUM(gm.gross_profit_cents) * 100.0 / NULLIF(SUM(gm.amount_cents), 0), 1) as gross_profit_margin')
            ->groupBy('gm.vend_id', DB::raw($monthDiffExpression));

        $vends = DB::query()
            ->fromSub($query, 'transac')
            ->select(
                DB::raw('MAX(customer_code) as customer_code'),
                DB::raw('MAX(customer_name) as customer_name'),
                'id',
                'name',
                'code',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin')
            )
            ->groupBy('id', 'name', 'code');

        $vends = $vends->when($request->sortKey, function ($query, $search) use ($request) {
            if (strpos($search, '->')) {
                $inputSearch = explode('->', $search);
                $query->orderByRaw(
                    'LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc')
                )->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            } else {
                $dir = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
                if (
                    in_array($search, [
                        'this_month_count',
                        'this_month_revenue',
                        'this_month_gross_profit',
                        'this_month_gross_profit_margin',
                        'last_month_count',
                        'last_month_revenue',
                        'last_month_gross_profit',
                        'last_month_gross_profit_margin',
                        'last_two_month_count',
                        'last_two_month_revenue',
                        'last_two_month_gross_profit',
                        'last_two_month_gross_profit_margin'
                    ])
                ) {
                    $query->orderByRaw("CAST($search AS DECIMAL(15,2)) $dir");
                } else {
                    $query->orderBy($search, $dir);
                }
            }
        });

        return $vends;
    }

    private function countUnitCostByVend(Request $request): int
    {
        [$baseQuery] = $this->buildUnitCostByVendBaseComponents($request);

        return (clone $baseQuery)
            ->select('gm.vend_id')
            ->distinct()
            ->count('gm.vend_id');
    }

    /**
     * Build per-product availability and channel metrics for the sales performance report.
     *
     * @param  array<int>  $productIds
     * @return array{
     *     per_product: array<int, array<string, array<string, mixed>>>,
     *     aggregates: array<string, array<string, mixed>>
     * }
     */
    private function buildSalesPerformanceMetrics(array $productIds, Request $request, Carbon $currentDate): array
    {
        $result = [
            'per_product' => [],
            'aggregates' => [
                'this_month' => ['available_seconds' => 0, 'total_seconds' => 0, 'channel_count_sum' => 0, 'availability' => null],
                'last_month' => ['available_seconds' => 0, 'total_seconds' => 0, 'channel_count_sum' => 0, 'availability' => null],
                'two_months_ago' => ['available_seconds' => 0, 'total_seconds' => 0, 'channel_count_sum' => 0, 'availability' => null],
            ],
        ];

        if (empty($productIds)) {
            return $result;
        }

        $rangeStart = $currentDate->copy()->subMonths(2)->startOfMonth();
        $rangeEnd = $currentDate->copy()->endOfMonth();
        $historyStart = $rangeStart->copy()->subMonth()->startOfMonth();

        $currentStart = $currentDate->copy()->startOfMonth();
        $currentEnd = $currentDate->copy()->endOfMonth();

        $lastStart = $currentStart->copy()->subMonth();
        $lastEnd = $lastStart->copy()->endOfMonth();

        $twoStart = $currentStart->copy()->subMonthsNoOverflow(2);
        $twoEnd = $twoStart->copy()->endOfMonth();

        $periods = [
            'this_month' => [
                'key' => $currentStart->format('Y-m'),
                'start' => $currentStart,
                'end' => $currentEnd,
            ],
            'last_month' => [
                'key' => $lastStart->format('Y-m'),
                'start' => $lastStart,
                'end' => $lastEnd,
            ],
            'two_months_ago' => [
                'key' => $twoStart->format('Y-m'),
                'start' => $twoStart,
                'end' => $twoEnd,
            ],
        ];

        $channelUsageRows = $this->getChannelUsageRows($productIds, $request, $rangeStart, $rangeEnd);

        $channelsByProduct = [];
        $channelIds = [];

        foreach ($channelUsageRows as $row) {
            $channelsByProduct[$row->product_id][$row->month_key][$row->channel_id] = $row->vend_id;
            $channelIds[] = $row->channel_id;
        }

        $eventsQuery = VendChannelStockEvent::query()
            ->join('vends', 'vend_channel_stock_events.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vends.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->leftJoin('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('operators', 'vends.operator_id', '=', 'operators.id')
            ->whereIn('vend_channel_stock_events.product_id', $productIds)
            ->whereBetween('vend_channel_stock_events.occurred_at', [$historyStart, $rangeEnd])
            ->select('vend_channel_stock_events.*');

        $eventsQuery = $this->filterStockEventAvailabilityQuery($eventsQuery, $request);
        $eventsQuery = $this->filterOperatorVendTransactionDB($eventsQuery);

        if (!empty($channelIds)) {
            $eventsQuery->whereIn('vend_channel_stock_events.vend_channel_id', $channelIds);
        }

        $eventRows = $eventsQuery
            ->orderBy('vend_channel_stock_events.product_id')
            ->orderBy('vend_channel_stock_events.vend_channel_id')
            ->orderBy('vend_channel_stock_events.occurred_at')
            ->get();

        foreach ($eventRows as $eventRow) {
            if ($eventRow->occurred_at->lt($rangeStart) || $eventRow->occurred_at->gt($rangeEnd)) {
                continue;
            }

            $monthKey = $eventRow->occurred_at->format('Y-m');
            $channelsByProduct[$eventRow->product_id][$monthKey][$eventRow->vend_channel_id] = $eventRow->vend_id;
            $channelIds[] = $eventRow->vend_channel_id;
        }

        $channelIds = array_values(array_unique($channelIds));
        $events = $eventRows->groupBy(['product_id', 'vend_channel_id']);

        foreach ($productIds as $productId) {
            $result['per_product'][$productId] = [
                'this_month' => ['channel_count' => 0, 'availability' => null, 'available_seconds' => 0, 'total_seconds' => 0],
                'last_month' => ['channel_count' => 0, 'availability' => null, 'available_seconds' => 0, 'total_seconds' => 0],
                'two_months_ago' => ['channel_count' => 0, 'availability' => null, 'available_seconds' => 0, 'total_seconds' => 0],
            ];

            foreach ($periods as $periodKey => $period) {
                $channelSet = array_keys($channelsByProduct[$productId][$period['key']] ?? []);
                $channelCount = count($channelSet);
                $availableSeconds = 0;
                $totalSeconds = 0;

                foreach ($channelSet as $channelId) {
                    /** @var \Illuminate\Support\Collection<int, VendChannelStockEvent> $channelEvents */
                    $channelEvents = $events->get($productId)?->get($channelId) ?? collect();
                    [$available, $total] = $this->calculateChannelAvailability($channelEvents, $period['start'], $period['end']);
                    $availableSeconds += $available;
                    $totalSeconds += $total;
                }

                if ($channelCount === 0 || $totalSeconds === 0) {
                    $availability = null;
                } else {
                    $availability = round(($availableSeconds / $totalSeconds) * 100, 1);
                }

                $result['per_product'][$productId][$periodKey] = [
                    'channel_count' => $channelCount,
                    'availability' => $availability,
                    'available_seconds' => $availableSeconds,
                    'total_seconds' => $totalSeconds,
                ];

                $result['aggregates'][$periodKey]['channel_count_sum'] += $channelCount;
                $result['aggregates'][$periodKey]['available_seconds'] += $availableSeconds;
                $result['aggregates'][$periodKey]['total_seconds'] += $totalSeconds;
            }
        }

        foreach ($result['aggregates'] as $key => $data) {
            if (($data['total_seconds'] ?? 0) > 0) {
                $result['aggregates'][$key]['availability'] = round(($data['available_seconds'] / $data['total_seconds']) * 100, 1);
            } else {
                $result['aggregates'][$key]['availability'] = null;
            }
        }

        return $result;
    }

    /**
     * Retrieve distinct channel usage by product and month within a date range.
     *
     * @param  array<int>  $productIds
     */
    private function getChannelUsageRows(array $productIds, Request $request, Carbon $rangeStart, Carbon $rangeEnd)
    {
        if (empty($productIds)) {
            return collect();
        }

        $transactionDatetimeSql = 'COALESCE(vend_transactions.transaction_datetime, vend_transactions.created_at)';
        $transactionDatetime = DB::raw($transactionDatetimeSql);
        $monthExpressionSql = "DATE_FORMAT({$transactionDatetimeSql}, '%Y-%m')";
        $applyTransactionDateRange = function ($query) use ($rangeStart, $rangeEnd) {
            $query->whereBetween('vend_transactions.transaction_datetime', [$rangeStart, $rangeEnd])
                ->orWhere(function ($sub) use ($rangeStart, $rangeEnd) {
                    $sub->whereNull('vend_transactions.transaction_datetime')
                        ->whereBetween('vend_transactions.created_at', [$rangeStart, $rangeEnd]);
                });
        };
        $monthExpression = DB::raw($monthExpressionSql);

        $single = DB::table('vend_transactions')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vend_transactions.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->leftJoin('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('operators', 'vend_transactions.operator_id', '=', 'operators.id')
            ->leftJoin('vend_channels', 'vend_transactions.vend_channel_id', '=', 'vend_channels.id')
            ->where(function ($query) use ($applyTransactionDateRange) {
                $applyTransactionDateRange($query);
            })
            ->where(function ($query) {
                $query->where('vend_transactions.is_multiple', false)
                    ->orWhereNull('vend_transactions.is_multiple');
            })
            ->where(function ($query) {
                $query->whereIn('vend_transactions.vend_channel_error_id', [1, 5])
                    ->orWhereNull('vend_transactions.vend_channel_error_id');
            })
            ->whereNotNull('vend_transactions.vend_channel_id')
            ->whereIn(DB::raw('COALESCE(vend_transactions.product_id, vend_channels.product_id)'), $productIds)
            ->selectRaw('COALESCE(vend_transactions.product_id, vend_channels.product_id) as product_id')
            ->selectRaw('vend_transactions.vend_channel_id as channel_id')
            ->selectRaw($monthExpressionSql . ' as month_key')
            ->selectRaw('MAX(vend_transactions.vend_id) as vend_id')
            ->groupBy('product_id', 'channel_id', DB::raw($monthExpressionSql));

        $single = $this->filterVendTransactionReport($single, $request);
        $single = $this->filterOperatorVendTransactionDB($single);

        $multi = DB::table('vend_transaction_items')
            ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
            ->leftJoin('vends', 'vend_transactions.vend_id', '=', 'vends.id')
            ->leftJoin('customers', 'vend_transactions.customer_id', '=', 'customers.id')
            ->leftJoin('categories', 'customers.category_id', '=', 'categories.id')
            ->leftJoin('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
            ->leftJoin('vend_prefixes', 'vends.vend_prefix_id', '=', 'vend_prefixes.id')
            ->leftJoin('operators', 'vend_transactions.operator_id', '=', 'operators.id')
            ->leftJoin('vend_channels', 'vend_transaction_items.vend_channel_id', '=', 'vend_channels.id')
            ->where(function ($query) use ($applyTransactionDateRange) {
                $applyTransactionDateRange($query);
            })
            ->where('vend_transactions.is_multiple', true)
            ->where(function ($query) {
                $query->whereIn('vend_transaction_items.vend_channel_error_code', ['0', '6'])
                    ->orWhereNull('vend_transaction_items.vend_channel_error_code');
            })
            ->whereNotNull('vend_transaction_items.vend_channel_id')
            ->whereIn(DB::raw('COALESCE(vend_transaction_items.product_id, vend_channels.product_id)'), $productIds)
            ->selectRaw('COALESCE(vend_transaction_items.product_id, vend_channels.product_id) as product_id')
            ->selectRaw('vend_transaction_items.vend_channel_id as channel_id')
            ->selectRaw($monthExpressionSql . ' as month_key')
            ->selectRaw('MAX(vend_transactions.vend_id) as vend_id')
            ->groupBy('product_id', 'channel_id', DB::raw($monthExpressionSql));

        $multi = $this->filterVendTransactionReport($multi, $request);
        $multi = $this->filterOperatorVendTransactionDB($multi);

        return $single->get()->concat($multi->get());
    }

    private function filterStockEventAvailabilityQuery($query, Request $request)
    {
        return $query
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('categories.id', $search);
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('category_groups.id', $search);
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $codes = array_filter(array_map('trim', explode(',', $search)));
                    if (!empty($codes)) {
                        $query->whereIn('vends.code', $codes);
                    }
                } else {
                    $query->where('vends.code', 'LIKE', "{$search}%");
                }
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('customers.name', 'LIKE', "%{$search}%")
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer, function ($query, $search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('vend_prefixes.name', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('customers.id');
                    } else {
                        $query->whereNull('customers.id');
                    }
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.location_type_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    $query->whereIn('vends.operator_id', $search);
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    if (in_array('single-ud', $search, true)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $search = array_values(array_diff($search, ['single-ud']));
                    }
                    $query->whereIn('vends.vend_prefix_id', $search);
                }
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    $query->whereIn('vends.vend_contract_id', $search);
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    $query->whereIn('vends.vend_model_id', $search);
                }
            });
    }

    /**
     * Calculate available time for a channel within a period based on stock events.
     *
     * @param  \Illuminate\Support\Collection<int, VendChannelStockEvent>  $events
     * @return array{0:int,1:int}
     */
    private function calculateChannelAvailability($events, Carbon $periodStart, Carbon $periodEnd): array
    {
        $periodStart = $periodStart->copy();
        $periodEndExclusive = $periodEnd->copy()->addSecond();
        $totalSeconds = $periodEndExclusive->diffInSeconds($periodStart, true);

        if ($totalSeconds <= 0) {
            return [0, 0];
        }

        if ($events->isEmpty()) {
            return [$totalSeconds, $totalSeconds];
        }

        $state = VendChannelStockEvent::TYPE_RESTOCKED;

        foreach ($events as $event) {
            if ($event->occurred_at->lt($periodStart)) {
                $state = $event->event_type;
            } else {
                break;
            }
        }

        $cursor = $periodStart->copy();
        $availableSeconds = 0;

        foreach ($events as $event) {
            $eventTime = $event->occurred_at->copy();
            if ($eventTime->lte($periodStart)) {
                $state = $event->event_type;
                continue;
            }

            if ($eventTime->gte($periodEndExclusive)) {
                break;
            }

            if ($eventTime->gt($cursor)) {
                $duration = $eventTime->diffInSeconds($cursor, true);
                if ($state !== VendChannelStockEvent::TYPE_SOLD_OUT) {
                    $availableSeconds += $duration;
                }
                $cursor = $eventTime->copy();
            }

            $state = $event->event_type;
        }

        if ($cursor->lt($periodEndExclusive)) {
            $duration = $periodEndExclusive->diffInSeconds($cursor, true);
            if ($state !== VendChannelStockEvent::TYPE_SOLD_OUT) {
                $availableSeconds += $duration;
            }
        }

        return [$availableSeconds, $totalSeconds];
    }

    private function getUnitCostByProductQuery($request)
    {
        $currentDate = $request->currentMonth
            ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
            : Carbon::today()->setTimezone($this->getUserTimezone());

        $rangeStart = $currentDate->copy()->subMonths(2)->startOfMonth();
        $rangeEnd = $currentDate->copy()->endOfMonth();
        $monthDiffExpression = 'PERIOD_DIFF(DATE_FORMAT("' . $currentDate->format('Y-m') . '-01", "%Y%m"), DATE_FORMAT(gm.txn_date, "%Y%m"))';

        $baseQuery = $this->baseGpMetricsQuery($request, $rangeStart, $rangeEnd)
            ->whereNotNull('gm.product_id');

        $query = $baseQuery
            ->selectRaw('gm.product_id as id')
            ->selectRaw('MAX(products.name) as name')
            ->selectRaw('MAX(products.code) as code')
            ->selectRaw($monthDiffExpression . ' as month_diff')
            ->selectRaw('SUM(gm.sale_count) as count')
            ->selectRaw('SUM(gm.amount_cents) as revenue')
            ->selectRaw('SUM(gm.gross_profit_cents) as gross_profit')
            ->selectRaw('ROUND(SUM(gm.gross_profit_cents) * 100.0 / NULLIF(SUM(gm.amount_cents), 0), 1) as gross_profit_margin')
            ->groupBy('gm.product_id', DB::raw($monthDiffExpression));

        $products = DB::query()
            ->fromSub($query, 'transac')
            ->select(
                'id',
                'name',
                'code',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin')
            )
            ->groupBy('id', 'name', 'code');

        $products = $products->when($request->sortKey, function ($query, $search) use ($request) {
            if (strpos($search, '->')) {
                $inputSearch = explode('->', $search);
                $query->orderByRaw(
                    'LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc')
                )->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            } else {
                $dir = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
                if (
                    in_array($search, [
                        'this_month_count',
                        'this_month_revenue',
                        'this_month_gross_profit',
                        'this_month_gross_profit_margin',
                        'last_month_count',
                        'last_month_revenue',
                        'last_month_gross_profit',
                        'last_month_gross_profit_margin',
                        'last_two_month_count',
                        'last_two_month_revenue',
                        'last_two_month_gross_profit',
                        'last_two_month_gross_profit_margin'
                    ])
                ) {
                    $query->orderByRaw("CAST($search AS DECIMAL(15,2)) $dir");
                } else {
                    $query->orderBy($search, $dir);
                }
            }
        });

        return $products;
    }

    private function getUnitCostByCategoryQuery($request)
    {
        $currentDate = $request->currentMonth
            ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
            : Carbon::today()->setTimezone($this->getUserTimezone());

        $className = get_class(new Customer());
        $rangeStart = $currentDate->copy()->subMonths(2)->startOfMonth();
        $rangeEnd = $currentDate->copy()->endOfMonth();
        $monthDiffExpression = 'PERIOD_DIFF(DATE_FORMAT("' . $currentDate->format('Y-m') . '-01", "%Y%m"), DATE_FORMAT(gm.txn_date, "%Y%m"))';

        $baseQuery = $this->baseGpMetricsQuery($request, $rangeStart, $rangeEnd)
            ->whereNotNull('gm.category_id');

        $query = $baseQuery
            ->selectRaw('gm.category_id as id')
            ->selectRaw('MAX(categories.name) as name')
            ->selectRaw('MAX(categories.classname) as classname')
            ->selectRaw($monthDiffExpression . ' as month_diff')
            ->selectRaw('SUM(gm.sale_count) as count')
            ->selectRaw('SUM(gm.amount_cents) as revenue')
            ->selectRaw('SUM(gm.gross_profit_cents) as gross_profit')
            ->selectRaw('ROUND(SUM(gm.gross_profit_cents) * 100.0 / NULLIF(SUM(gm.amount_cents), 0), 1) as gross_profit_margin')
            ->groupBy('gm.category_id', DB::raw($monthDiffExpression));

        $categories = DB::query()
            ->fromSub($query, 'transac')
            ->select(
                'id',
                'name',
                'classname',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin')
            )
            ->where('classname', $className)
            ->groupBy('id', 'name', 'classname');

        $categories = $categories->when($request->sortKey, function ($query, $search) use ($request) {
            if (strpos($search, '->')) {
                $inputSearch = explode('->', $search);
                $query->orderByRaw(
                    'LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc')
                )->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            } else {
                $dir = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
                if (
                    in_array($search, [
                        'this_month_count',
                        'this_month_revenue',
                        'this_month_gross_profit',
                        'this_month_gross_profit_margin',
                        'last_month_count',
                        'last_month_revenue',
                        'last_month_gross_profit',
                        'last_month_gross_profit_margin',
                        'last_two_month_count',
                        'last_two_month_revenue',
                        'last_two_month_gross_profit',
                        'last_two_month_gross_profit_margin'
                    ])
                ) {
                    $query->orderByRaw("CAST($search AS DECIMAL(15,2)) $dir");
                } else {
                    $query->orderBy($search, $dir);
                }
            }
        });

        return $categories;
    }

    private function getUnitCostByLocationTypeQuery($request)
    {
        $currentDate = $request->currentMonth
            ? Carbon::createFromFormat('Y-m', $request->currentMonth)->setTimezone($this->getUserTimezone())
            : Carbon::today()->setTimezone($this->getUserTimezone());

        $rangeStart = $currentDate->copy()->subMonths(2)->startOfMonth();
        $rangeEnd = $currentDate->copy()->endOfMonth();
        $monthDiffExpression = 'PERIOD_DIFF(DATE_FORMAT("' . $currentDate->format('Y-m') . '-01", "%Y%m"), DATE_FORMAT(gm.txn_date, "%Y%m"))';

        $baseQuery = $this->baseGpMetricsQuery($request, $rangeStart, $rangeEnd)
            ->leftJoin('location_types', 'location_types.id', '=', 'gm.transaction_location_type_id')
            ->whereNotNull('gm.transaction_location_type_id');

        $query = $baseQuery
            ->selectRaw('gm.transaction_location_type_id as id')
            ->selectRaw('MAX(location_types.name) as name')
            ->selectRaw($monthDiffExpression . ' as month_diff')
            ->selectRaw('SUM(gm.sale_count) as count')
            ->selectRaw('SUM(gm.amount_cents) as revenue')
            ->selectRaw('SUM(gm.gross_profit_cents) as gross_profit')
            ->selectRaw('ROUND(SUM(gm.gross_profit_cents) * 100.0 / NULLIF(SUM(gm.amount_cents), 0), 1) as gross_profit_margin')
            ->groupBy('gm.transaction_location_type_id', DB::raw($monthDiffExpression));

        $locationTypes = DB::query()
            ->fromSub($query, 'transac')
            ->select(
                'id',
                'name',
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN count ELSE 0 END) AS this_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN revenue ELSE 0 END) AS this_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit ELSE 0 END) AS this_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 0 THEN gross_profit_margin ELSE 0 END) AS this_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN count ELSE 0 END) AS last_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN revenue ELSE 0 END) AS last_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit ELSE 0 END) AS last_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 1 THEN gross_profit_margin ELSE 0 END) AS last_month_gross_profit_margin'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN count ELSE 0 END) AS last_two_month_count'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN revenue ELSE 0 END) AS last_two_month_revenue'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit ELSE 0 END) AS last_two_month_gross_profit'),
                DB::raw('SUM(CASE WHEN month_diff = 2 THEN gross_profit_margin ELSE 0 END) AS last_two_month_gross_profit_margin')
            )
            ->groupBy('id', 'name');

        $locationTypes = $locationTypes->when($request->sortKey, function ($query, $search) use ($request) {
            if (strpos($search, '->')) {
                $inputSearch = explode('->', $search);
                $query->orderByRaw(
                    'LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc')
                )->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            } else {
                $dir = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';
                if (
                    in_array($search, [
                        'this_month_count',
                        'this_month_revenue',
                        'this_month_gross_profit',
                        'this_month_gross_profit_margin',
                        'last_month_count',
                        'last_month_revenue',
                        'last_month_gross_profit',
                        'last_month_gross_profit_margin',
                        'last_two_month_count',
                        'last_two_month_revenue',
                        'last_two_month_gross_profit',
                        'last_two_month_gross_profit_margin'
                    ])
                ) {
                    $query->orderByRaw("CAST($search AS DECIMAL(15,2)) $dir");
                } else {
                    $query->orderBy($search, $dir);
                }
            }
        });

        return $locationTypes;
    }

    private function getSnapshotQuery($request)
    {
        $vendSnapshots = DB::table('vend_snapshots')
            ->leftJoin('vends', 'vends.id', '=', 'vend_snapshots.vend_id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_snapshots.customer_id')
            ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
            ->leftJoin('product_mappings', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->leftJoin('categories', 'categories.id', '=', 'customers.category_id')
            ->leftJoin('category_groups', 'category_groups.id', '=', 'categories.category_group_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_snapshots.operator_id')
            ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
            ->select(
                'vend_snapshots.id AS id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.person_id',
                'customers.virtual_customer_code',
                'customers.virtual_customer_prefix',
                DB::raw('MONTH(vend_snapshots.created_at) - 1 AS month_number'),
                DB::raw('YEAR(vend_snapshots.created_at) AS year_number'),
                'product_mappings.name AS product_mapping_name',
                'vends.code AS vend_code',
                'vends.name AS vend_name',
                'vend_snapshots.created_at AS created_at',
                'vend_snapshots.parameter_json',
                'vend_snapshots.vend_channels_json',
            );

        $vendSnapshots = $this->filterOperatorVendTransactionDB($vendSnapshots);
        $vendSnapshots = $this->filterVendsDB($vendSnapshots, $request);


        $vendSnapshots = $vendSnapshots
            ->when($request->currentMonth, function ($query, $search) {
                $query
                    ->where('vend_snapshots.created_at', '>=', $search->copy()->startOfMonth()->addDay()->startOfDay())
                    ->where('vend_snapshots.created_at', '<=', $search->copy()->endOfMonth()->addDay()->endOfDay());
            });
        $vendSnapshots = $vendSnapshots->groupBy('vends.id', 'year_number', 'month_number');

        return $vendSnapshots;
    }

    private function getStockCountQuery($request)
    {
        // read sort inputs (sortBy=true => DESC, false => ASC)
        $sortKey = $request->input('sortKey');                // 'sequence' | 'channel_code' | null
        $sortDesc = filter_var($request->input('sortBy'), FILTER_VALIDATE_BOOLEAN); // bool
        $dir = $sortDesc ? 'DESC' : 'ASC';

        if (!$sortKey) {
            // default to sequence if not specified
            $sortKey = 'product_code';
        }

        $stockCounts = StockCount::query()
            ->with([
                'stockCountItems' => function ($q) use ($sortKey, $dir) {
                    if ($sortKey === 'stock_value_amount') {
                        $q->orderBy('stock_value_amount', $dir);
                    } else if ($sortKey === 'qty_vend') {
                        $q->orderBy('qty_vend', $dir);
                    } else if ($sortKey === 'qty_warehouse') {
                        $q->orderBy('qty_warehouse', $dir);
                    } else if ($sortKey === 'stock_cost_amount') {
                        $q->orderBy('stock_cost_amount', $dir);
                    }
                },
                'stockCountItems.product' => function ($q) use ($sortKey, $dir) {
                    if ($sortKey === 'product_code') {
                        // nulls last, then sequence asc/desc, then channel_code as tiebreaker
                        $q->orderByRaw("CAST(code AS UNSIGNED) $dir")
                            ->orderBy('code', $dir);

                    }
                },
                'stockCountItems.product.thumbnail'
            ])
            ->filterIndex($request)
            ->groupBy();

        return $stockCounts;
    }

    private function getStockCountPivot3dQuery(Request $request)
    {
        $tz = $this->getUserTimezone();
        $end = Carbon::parse($request->date_to ?? Carbon::today($tz), $tz)->toDateString();
        $d0 = Carbon::parse($end)->subDay()->toDateString();
        $d1 = Carbon::parse($end)->subDays(2)->toDateString();
        $d2 = Carbon::parse($end)->subDays(3)->toDateString();

        $periods = [];
        foreach (['d0' => $d0, 'd1' => $d1, 'd2' => $d2] as $label => $date) {
            $carbon = Carbon::parse($date);
            $periods[$label] = [
                'date' => $date,
                'year' => (int) $carbon->year,
                'month' => (int) $carbon->month,
                'day' => (int) $carbon->day,
            ];
        }
        $caseExpr = fn(string $label, string $value) => $this->stockCountDateCase($periods[$label], $value);

        // ---------- rows (per product, 3 days) ----------
        $q = DB::table('stock_count_items as sci')
            ->join('stock_counts as sc', 'sc.id', '=', 'sci.stock_count_id')
            ->join('products as p', 'p.id', '=', 'sci.product_id')

            // filters
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sc.operator_id', $ids);
                }
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    if (in_array('single-ud', $ids, true)) {
                        $ids = array_unique(array_merge($ids, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $ids = array_values(array_diff($ids, ['single-ud']));
                    }
                    $q->whereIn('sc.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all')
                    $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes)
                    ? array_values(array_filter(array_map('trim', explode(',', $codes))))
                    : (array) $codes;

                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')
                        ->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1)
                        $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1)
                        $sq->where('v.code', 'LIKE', '%' . $codes[0] . '%');
                });
            })
            ->when($request->customer, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereExists(function ($sq) use ($search) {
                        $sq->from('customers as c')
                            ->whereColumn('c.id', 'sc.customer_id')
                            ->where(function ($sub) use ($search) {
                                $sub->where('c.virtual_customer_code', 'LIKE', "{$search}%")
                                    ->orWhere('c.name', 'LIKE', "%{$search}%");
                            });
                    })
                        ->orWhereExists(function ($sq) use ($search) {
                            $sq->from('vend_prefixes as vp')
                                ->whereColumn('vp.id', 'sc.vend_prefix_id')
                                ->where('vp.name', 'LIKE', "{$search}%");
                        });
                });
            })
            ->when($request->products, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sci.product_id', $ids);
                }
            })

            ->select([
                'p.id   as product_id',
                'p.code as product_code',
                'p.name as product_name',

                // qty in machine (sum) + qty in warehouse (once)
                DB::raw('SUM(' . $caseExpr('d0', 'sci.qty_vend') . ') AS qty_vend_d0'),
                DB::raw('SUM(' . $caseExpr('d1', 'sci.qty_vend') . ') AS qty_vend_d1'),
                DB::raw('SUM(' . $caseExpr('d2', 'sci.qty_vend') . ') AS qty_vend_d2'),

                DB::raw('MAX(' . $caseExpr('d0', 'sci.qty_warehouse') . ') AS qty_warehouse_d0'),
                DB::raw('MAX(' . $caseExpr('d1', 'sci.qty_warehouse') . ') AS qty_warehouse_d1'),
                DB::raw('MAX(' . $caseExpr('d2', 'sci.qty_warehouse') . ') AS qty_warehouse_d2'),

                // unit cost (RM) per day, directly from sci
                DB::raw('ROUND(MAX(' . $caseExpr('d0', 'sci.unit_cost_amount') . ') / 100, 2) AS unit_cost_d0'),
                DB::raw('ROUND(MAX(' . $caseExpr('d1', 'sci.unit_cost_amount') . ') / 100, 2) AS unit_cost_d1'),
                DB::raw('ROUND(MAX(' . $caseExpr('d2', 'sci.unit_cost_amount') . ') / 100, 2) AS unit_cost_d2'),

                // stock value in machine (RM)
                DB::raw('ROUND(SUM(' . $caseExpr('d0', 'sci.stock_value_amount') . ') / 100, 2) AS stock_value_d0'),
                DB::raw('ROUND(SUM(' . $caseExpr('d1', 'sci.stock_value_amount') . ') / 100, 2) AS stock_value_d1'),
                DB::raw('ROUND(SUM(' . $caseExpr('d2', 'sci.stock_value_amount') . ') / 100, 2) AS stock_value_d2'),

                // stock cost (RM) = machine cost + ONE warehouse cost
                DB::raw("
                    ROUND((
                        SUM(" . $caseExpr('d0', 'sci.unit_cost_amount * sci.qty_vend') . ")
                        + (MAX(" . $caseExpr('d0', 'sci.qty_warehouse') . ")
                           * MAX(" . $caseExpr('d0', 'sci.unit_cost_amount') . "))
                    ) / 100, 2) AS stock_cost_d0
                "),
                DB::raw("
                    ROUND((
                        SUM(" . $caseExpr('d1', 'sci.unit_cost_amount * sci.qty_vend') . ")
                        + (MAX(" . $caseExpr('d1', 'sci.qty_warehouse') . ")
                           * MAX(" . $caseExpr('d1', 'sci.unit_cost_amount') . "))
                    ) / 100, 2) AS stock_cost_d1
                "),
                DB::raw("
                    ROUND((
                        SUM(" . $caseExpr('d2', 'sci.unit_cost_amount * sci.qty_vend') . ")
                        + (MAX(" . $caseExpr('d2', 'sci.qty_warehouse') . ")
                           * MAX(" . $caseExpr('d2', 'sci.unit_cost_amount') . "))
                    ) / 100, 2) AS stock_cost_d2
                "),
            ])
            ->groupBy('p.id', 'p.code', 'p.name');

        $q = $this->applyStockCountDates($q, $periods);

        // sorting
        $sortKey = $request->input('sortKey', 'product_code');
        $desc = filter_var($request->input('sortBy', false), FILTER_VALIDATE_BOOLEAN);
        $dir = $desc ? 'desc' : 'asc';
        $allowed = [
            'product_code',
            'unit_cost_d0',
            'unit_cost_d1',
            'unit_cost_d2',
            'qty_vend_d0',
            'qty_vend_d1',
            'qty_vend_d2',
            'qty_warehouse_d0',
            'qty_warehouse_d1',
            'qty_warehouse_d2',
            'stock_value_d0',
            'stock_value_d1',
            'stock_value_d2',
            'stock_cost_d0',
            'stock_cost_d1',
            'stock_cost_d2'
        ];
        if (!in_array($sortKey, $allowed, true))
            $sortKey = 'product_code';
        if ($sortKey === 'product_code') {
            $q->orderByRaw('CAST(product_code AS UNSIGNED) ' . $dir)->orderBy('product_code', $dir);
        } else {
            $q->orderBy($sortKey, $dir);
        }

        // ---------- totals from the row aliases ----------
        $totals = DB::query()
            ->fromSub((clone $q)->reorder(), 'rows')
            ->selectRaw('
                /* sums shown in footer */
                SUM(stock_value_d0)   AS stock_value_d0,
                SUM(qty_vend_d0)      AS qty_vend_d0,
                SUM(qty_warehouse_d0) AS qty_warehouse_d0,
                SUM(stock_cost_d0)    AS stock_cost_d0,

                SUM(stock_value_d1)   AS stock_value_d1,
                SUM(qty_vend_d1)      AS qty_vend_d1,
                SUM(qty_warehouse_d1) AS qty_warehouse_d1,
                SUM(stock_cost_d1)    AS stock_cost_d1,

                SUM(stock_value_d2)   AS stock_value_d2,
                SUM(qty_vend_d2)      AS qty_vend_d2,
                SUM(qty_warehouse_d2) AS qty_warehouse_d2,
                SUM(stock_cost_d2)    AS stock_cost_d2,

                /* weighted average unit cost per day = total_cost / total_qty */
                SUM(unit_cost_d0) AS unit_cost_d0,
                SUM(unit_cost_d1) AS unit_cost_d1,
                SUM(unit_cost_d2) AS unit_cost_d2
            ')
            ->first();

        // ---------- KPIs (cash/cashless/coin) ----------
        $kpisQuery = DB::table('stock_counts as sc')
            ->when($request->operators, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereIn('sc.operator_id', $ids);
                }
            })
            ->when($request->vendPrefixes, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    if (in_array('single-ud', $ids, true)) {
                        $ids = array_unique(array_merge($ids, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $ids = array_values(array_diff($ids, ['single-ud']));
                    }
                    $q->whereIn('sc.vend_prefix_id', $ids);
                }
            })
            ->when($request->location_type_id ?? $request->locationType, function ($q, $val) {
                if ($val !== 'all')
                    $q->whereIn('sc.location_type_id', (array) $val);
            })
            ->when($request->codes, function ($q, $codes) {
                $codes = is_string($codes)
                    ? array_values(array_filter(array_map('trim', explode(',', $codes))))
                    : (array) $codes;

                $q->whereExists(function ($sq) use ($codes) {
                    $sq->from('vends as v')
                        ->whereColumn('v.id', 'sc.vend_id');
                    if (count($codes) > 1)
                        $sq->whereIn('v.code', $codes);
                    elseif (count($codes) === 1)
                        $sq->where('v.code', 'LIKE', '%' . $codes[0] . '%');
                });
            })
            ->when($request->customer, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereExists(function ($sq) use ($search) {
                        $sq->from('customers as c')
                            ->whereColumn('c.id', 'sc.customer_id')
                            ->where(function ($sub) use ($search) {
                                $sub->where('c.virtual_customer_code', 'LIKE', "{$search}%")
                                    ->orWhere('c.name', 'LIKE', "%{$search}%");
                            });
                    })
                        ->orWhereExists(function ($sq) use ($search) {
                            $sq->from('vend_prefixes as vp')
                                ->whereColumn('vp.id', 'sc.vend_prefix_id')
                                ->where('vp.name', 'LIKE', "{$search}%");
                        });
                });
            })
            ->when($request->products, function ($q, $ids) {
                if (is_array($ids) && !in_array('all', $ids, true)) {
                    $q->whereExists(function ($sq) use ($ids) {
                        $sq->from('stock_count_items as sci_filter')
                            ->whereColumn('sci_filter.stock_count_id', 'sc.id')
                            ->whereIn('sci_filter.product_id', $ids);
                    });
                }
            });

        $kpisQuery = $this->applyStockCountDates($kpisQuery, $periods);

        $kpis = $kpisQuery
            ->selectRaw("
                ROUND(SUM(" . $caseExpr('d0', 'sc.cash_sales_amount') . ") / 100, 2) AS cash_sales_amount_d0,
                ROUND(SUM(" . $caseExpr('d1', 'sc.cash_sales_amount') . ") / 100, 2) AS cash_sales_amount_d1,
                ROUND(SUM(" . $caseExpr('d2', 'sc.cash_sales_amount') . ") / 100, 2) AS cash_sales_amount_d2,

                ROUND(SUM(" . $caseExpr('d0', 'sc.cashless_sales_amount') . ") / 100, 2) AS cashless_sales_amount_d0,
                ROUND(SUM(" . $caseExpr('d1', 'sc.cashless_sales_amount') . ") / 100, 2) AS cashless_sales_amount_d1,
                ROUND(SUM(" . $caseExpr('d2', 'sc.cashless_sales_amount') . ") / 100, 2) AS cashless_sales_amount_d2,

                ROUND(SUM(" . $caseExpr('d0', 'sc.coin_float_amount') . ") / 100, 2) AS coin_float_amount_d0,
                ROUND(SUM(" . $caseExpr('d1', 'sc.coin_float_amount') . ") / 100, 2) AS coin_float_amount_d1,
                ROUND(SUM(" . $caseExpr('d2', 'sc.coin_float_amount') . ") / 100, 2) AS coin_float_amount_d2
            ")
            ->first();

        // merge KPI fields + pre-compute "Dollar Value" (cash+cashless+coin) for the footer
        foreach ((array) $kpis as $k => $v) {
            $totals->{$k} = $v ?? 0;
        }
        $totals->dollar_value_d0 = ($totals->cash_sales_amount_d0 ?? 0)
            + ($totals->cashless_sales_amount_d0 ?? 0)
            + ($totals->coin_float_amount_d0 ?? 0);
        $totals->dollar_value_d1 = ($totals->cash_sales_amount_d1 ?? 0)
            + ($totals->cashless_sales_amount_d1 ?? 0)
            + ($totals->coin_float_amount_d1 ?? 0);
        $totals->dollar_value_d2 = ($totals->cash_sales_amount_d2 ?? 0)
            + ($totals->cashless_sales_amount_d2 ?? 0)
            + ($totals->coin_float_amount_d2 ?? 0);

        // pagination
        $perPage = ($request->numberPerPage === 'All') ? 10000 : (int) ($request->numberPerPage ?? 100);
        $paginator = $q->paginate($perPage)->appends($request->query());

        return [$paginator, ['d0' => $d0, 'd1' => $d1, 'd2' => $d2], $totals];
    }

    private function stockCountDateCase(array $periodMeta, string $valueExpression): string
    {
        $year = $periodMeta['year'];
        $month = $periodMeta['month'];
        $day = $periodMeta['day'];

        return "CASE WHEN sc.year = {$year} AND sc.month = {$month} AND sc.day = {$day} THEN {$valueExpression} ELSE 0 END";
    }

    private function applyStockCountDates($query, array $periods)
    {
        $query->where(function ($outer) use ($periods) {
            foreach ($periods as $period) {
                $outer->orWhere(function ($inner) use ($period) {
                    $inner->where('sc.year', $period['year'])
                        ->where('sc.month', $period['month'])
                        ->where('sc.day', $period['day']);
                });
            }
        });

        return $query;
    }

    private function applyStockCountDateRange($query, Carbon $from, Carbon $to)
    {
        $fromYear = (int) $from->year;
        $fromMonth = (int) $from->month;
        $fromDay = (int) $from->day;

        $toYear = (int) $to->year;
        $toMonth = (int) $to->month;
        $toDay = (int) $to->day;

        return $query->where(function ($between) use ($fromYear, $fromMonth, $fromDay, $toYear, $toMonth, $toDay) {
            $between
                ->where(function ($lower) use ($fromYear, $fromMonth, $fromDay) {
                    $lower->where('sc.year', '>', $fromYear)
                        ->orWhere(function ($eqYear) use ($fromYear, $fromMonth) {
                            $eqYear->where('sc.year', $fromYear)
                                ->where('sc.month', '>', $fromMonth);
                        })
                        ->orWhere(function ($eqYearMonth) use ($fromYear, $fromMonth, $fromDay) {
                            $eqYearMonth->where('sc.year', $fromYear)
                                ->where('sc.month', $fromMonth)
                                ->where('sc.day', '>=', $fromDay);
                        });
                })
                ->where(function ($upper) use ($toYear, $toMonth, $toDay) {
                    $upper->where('sc.year', '<', $toYear)
                        ->orWhere(function ($eqYear) use ($toYear, $toMonth) {
                            $eqYear->where('sc.year', $toYear)
                                ->where('sc.month', '<', $toMonth);
                        })
                        ->orWhere(function ($eqYearMonth) use ($toYear, $toMonth, $toDay) {
                            $eqYearMonth->where('sc.year', $toYear)
                                ->where('sc.month', $toMonth)
                                ->where('sc.day', '<=', $toDay);
                        });
                });
        });
    }


    private function getSalesSubTotal($dataCols)
    {
        return collect((clone $dataCols)->get())->pipe(function ($data) {
            $thisMonthTotal = $data->sum(function ($data) {
                return $data->this_month_revenue / 100;
            });
            $thisMonthGrossProfitTotal = $data->sum(function ($data) {
                return $data->this_month_gross_profit / 100;
            });
            $lastMonthTotal = $data->sum(function ($data) {
                return $data->last_month_revenue / 100;
            });
            $lastMonthGrossProfitTotal = $data->sum(function ($data) {
                return $data->last_month_gross_profit / 100;
            });
            $lastTwoMonthTotal = $data->sum(function ($data) {
                return $data->last_two_month_revenue / 100;
            });
            $lastTwoMonthGrossProfitTotal = $data->sum(function ($data) {
                return $data->last_two_month_gross_profit / 100;
            });
            return [
                'this_month_count_total' => $data->sum('this_month_count'),
                'this_month_revenue_total' => $thisMonthTotal,
                'this_month_gross_profit_total' => $thisMonthGrossProfitTotal,
                'this_month_gross_margin_total' => round($thisMonthGrossProfitTotal / ($thisMonthTotal ? $thisMonthTotal : 1) * 100, 1),
                'last_month_count_total' => $data->sum('last_month_count'),
                'last_month_revenue_total' => $lastMonthTotal,
                'last_month_gross_profit_total' => $lastMonthGrossProfitTotal,
                'last_month_gross_margin_total' => round($lastMonthGrossProfitTotal / ($lastMonthTotal ? $lastMonthTotal : 1) * 100, 1),
                'last_two_month_count_total' => $data->sum('last_two_month_count'),
                'last_two_month_revenue_total' => $lastTwoMonthTotal,
                'last_two_month_gross_profit_total' => $lastTwoMonthGrossProfitTotal,
                'last_two_month_gross_margin_total' => round($lastTwoMonthGrossProfitTotal / ($lastTwoMonthTotal ? $lastTwoMonthTotal : 1) * 100, 1),
            ];
        });
    }

    private function getSalesReportTotals($items)
    {
        return collect((clone $items)->get())->pipe(function ($item) {
            $total_count = $item->sum(function ($item) {
                return $item->count;
            });
            $total_amount = $item->sum(function ($item) {
                return $item->amount / 100;
            });
            $total_error_count = $item->sum(function ($item) {
                return isset($item->error_count) ? $item->error_count : 0;
            });
            $total_error_count_no_4_5 = $item->sum(function ($item) {
                return isset($item->error_count_no_4_5) ? $item->error_count_no_4_5 : 0;
            });
            $total_error_count_4_5 = $item->sum(function ($item) {
                return isset($item->error_count_4_5) ? $item->error_count_4_5 : 0;
            });
            $total_channel_availability = $item->sum(function ($item) {
                return isset($item->channel_availability) ? (int)$item->channel_availability : 0;
            });
            $total_machine_count = $item->sum(function ($item) {
                return isset($item->machine_count) ? (int)$item->machine_count : 0;
            });
            return [
                'total_count' => $total_count,
                'total_amount' => $total_amount,
                'total_error_count' => $total_error_count,
                'total_error_count_no_4_5' => $total_error_count_no_4_5,
                'total_error_count_4_5' => $total_error_count_4_5,
                'total_channel_availability' => $total_channel_availability,
                'total_machine_count' => $total_machine_count,
            ];
        });
    }

    private function yieldOneByOne($items)
    {
        foreach ($items as $item) {
            yield $item;
        }
    }
}
