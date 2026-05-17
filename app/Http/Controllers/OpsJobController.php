<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\OpsJobResource;
use App\Http\Resources\OpsJobItemResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Jobs\PublishMqtt;
use App\Jobs\SyncOpsJobItemTransactionItemCMS;
use App\Jobs\SyncOpsJobTransactionCMS;
use App\Models\Address;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\OpsJobTask;
use App\Models\ProductMovement;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelRecord;
use App\Models\VendData;
use App\Models\VendTransaction;
use App\Traits\GetUserTimezone;
use App\Services\MapService;
use App\Services\OpsJobService;
use App\Services\ProductMappingService;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class OpsJobController extends Controller
{
    use GetUserTimezone;

    protected $mapService;
    protected $opsJobService;
    protected $productMappingService;
    protected $runningNumberService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->mapService = new MapService();
        $this->opsJobService = new OpsJobService();
        $this->productMappingService = new ProductMappingService();
        $this->runningNumberService = new RunningNumberService();
    }

    public function index(Request $request)
    {
        $isDriver = auth()->user()->hasRole('driver');

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

        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'date',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->subDays(3)->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->addWeek()->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);

        // If the current user is a driver, always restrict to their own jobs
        if ($isDriver) {
            $request->merge([
                'delivered_by' => auth()->id(),
            ]);
        }

        $query = OpsJob::query()
            ->with(['createdBy', 'deliveredBy', 'operator', 'pickedBy', 'updatedBy']);

        // Apply filters
        $query->when($request->code, function ($query, $search) {
            $query->where('code', 'LIKE', "%{$search}%");
        })
            ->when($request->date_from, function ($query, $search) {
                $query->where('date', '>=', $search);
            })
            ->when($request->date_to, function ($query, $search) {
                $query->where('date', '<=', $search);
            })
            ->when($request->delivered_by, function ($query, $search) {
                $query->where('delivered_by', $search);
            })
            ->when($request->created_by, function ($query, $search) {
                $query->where('created_by', $search);
            })
            ->when($request->ops_job_item_ref_id, function ($query, $search) {
                $query->whereHas('opsJobItems', function ($query) use ($search) {
                    $query->where('id', $search - 25000);
                });
            })
            ->when($request->vend_code, function ($query, $search) {
                $query->whereHas('opsJobItems.vend', function ($query) use ($search) {
                    $query->where('code', $search);
                });
            })
            ->whereHas('deliveredBy', function ($query) use ($request) {
                $query->whereIn('operator_id', $request->operators);
            });

        // Sorting (standard columns)
        if (
            !in_array($request->sortKey, [
                'ops_job_items_count',
                'ops_job_items_delivered_count',
                'ops_job_items_verified_count',
                'ops_job_items_delivered_count_percentage',
                'ops_job_items_verified_count_percentage',
                'total_cash_amount_from_vmc',
                'delta_cash_amount',
            ])
        ) {
            $query->orderBy($request->sortKey, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
        }

        // Paginate first to get the target IDs
        $opsJobs = $query->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)->withQueryString();
        $opsJobIds = $opsJobs->pluck('id')->toArray();

        if (!empty($opsJobIds)) {
            // 1. Item Stats Aggregation (Filtered by IDs)
            $itemStats = DB::table('ops_job_items')
                ->whereIn('ops_job_id', $opsJobIds)
                ->selectRaw('
                    ops_job_id,
                    COUNT(*) as ops_job_items_count,
                    SUM(CASE WHEN status >= ? AND status <> ? THEN 1 ELSE 0 END) as ops_job_items_delivered_count,
                    SUM(CASE WHEN picked_at IS NOT NULL THEN 1 ELSE 0 END) as ops_job_items_picked_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ops_job_items_verified_count,
                    SUM(cash_amount) as total_cash_amount,
                    SUM(temp_cash_amount_from_vmc) as total_cash_amount_from_vmc,
                    SUM(cash_amount) - SUM(temp_cash_amount_from_vmc) as delta_cash_amount,
                    SUM(acc_total_amount) as acc_vend_transactions_amount,
                    SUM(acc_total_cash_amount) as acc_vend_transactions_cash_amount,
                    SUM(acc_total_cashless_amount) as acc_vend_transactions_cashless_amount,
                    SUM(acc_total_promo_amount) as acc_vend_transactions_promo_amount,
                    SUM(acc_total_count) as acc_vend_transactions_count,
                    SUM(CASE WHEN cms_transaction_id IS NOT NULL THEN 1 ELSE 0 END) as cms_transaction_count,
                    SUM(refillable_amount) as refillable_amount_frozen,
                    SUM(refillable_count) as refillable_count_frozen
                ', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED, OpsJob::STATUS_VERIFIED])
                ->groupBy('ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 2. Channel Stats Aggregation (Filtered by IDs)
            $channelStats = DB::table('ops_job_item_channels as ojic')
                ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
                ->join('ops_jobs as oj', 'oji.ops_job_id', '=', 'oj.id')
                ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                ->leftJoin('unit_costs as uc', function ($join) {
                    $join->on('p.id', '=', 'uc.product_id')
                        ->where('uc.is_current', '=', true);
                })
                ->leftJoin(DB::raw('(
                    SELECT id, product_id, qty, date
                    FROM (
                        SELECT id, product_id, qty, date,
                            ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
                        FROM product_limits
                    ) pl_inner
                    WHERE rn = 1
                ) AS pl'), function ($join) {
                    $join->on('p.id', '=', 'pl.product_id')
                        ->on('pl.date', '=', 'oj.date');
                })
                ->whereIn('oji.ops_job_id', $opsJobIds)
                ->selectRaw('
                    oji.ops_job_id,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * vc.amount ELSE 0 END) as picked_amount,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty ELSE 0 END) as picked_count,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * COALESCE(uc.cost, 0) ELSE 0 END) as picked_cost,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * vc.amount ELSE 0 END) as stock_in_amount,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty ELSE 0 END) as stock_in_count,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * COALESCE(uc.cost, 0) ELSE 0 END) as stock_in_cost,
                    SUM(CASE WHEN (oji.status = 1 OR oji.refillable_amount IS NULL) AND p.is_available = 1 THEN GREATEST(
                        CASE
                            WHEN pl.id IS NOT NULL AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                            ELSE (vc.capacity - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                        END, 0
                    ) ELSE 0 END * vc.amount) as live_refillable_amount,
                    SUM(CASE WHEN (oji.status = 1 OR oji.refillable_amount IS NULL) AND p.is_available = 1 THEN GREATEST(
                        CASE
                            WHEN pl.id IS NOT NULL AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                            ELSE (vc.capacity - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                        END, 0
                    ) ELSE 0 END) as live_refillable_count
                ', [
                    OpsJob::STATUS_PICKED,
                    OpsJob::STATUS_PICKED,
                    OpsJob::STATUS_PICKED,
                    OpsJob::STATUS_DELIVERED,
                    OpsJob::STATUS_CANCELLED,
                    OpsJob::STATUS_DELIVERED,
                    OpsJob::STATUS_CANCELLED,
                    OpsJob::STATUS_DELIVERED,
                    OpsJob::STATUS_CANCELLED
                ])
                ->where('vc.is_active', 1)
                ->where('vc.capacity', '>', 0)
                ->groupBy('oji.ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 3. Task Stats Aggregation (count, value sum in cents, qty sum, status counts + picked/completed amounts)
            $taskStats = DB::table('ops_job_tasks')
                ->whereIn('ops_job_id', $opsJobIds)
                ->selectRaw('
                    ops_job_id,
                    COUNT(*) as ops_job_tasks_count,
                    SUM(value) as tasks_value_sum,
                    SUM(COALESCE(qty, 0)) as tasks_qty_sum,
                    SUM(CASE WHEN status >= 2 THEN 1 ELSE 0 END) as tasks_picked_count,
                    SUM(CASE WHEN status >= 3 THEN 1 ELSE 0 END) as tasks_completed_count,
                    SUM(CASE WHEN status >= 2 THEN value ELSE 0 END) as tasks_picked_value,
                    SUM(CASE WHEN status >= 2 THEN COALESCE(qty, 0) ELSE 0 END) as tasks_picked_qty,
                    SUM(CASE WHEN status >= 3 THEN value ELSE 0 END) as tasks_completed_value,
                    SUM(CASE WHEN status >= 3 THEN COALESCE(qty, 0) ELSE 0 END) as tasks_completed_qty
                ')
                ->groupBy('ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 4. Merge Data
            foreach ($opsJobs as $job) {
                $iStat = $itemStats->get($job->id);
                $cStat = $channelStats->get($job->id);
                $tStat = $taskStats->get($job->id);

                $job->ops_job_tasks_count = (int) ($tStat?->ops_job_tasks_count ?? 0);
                $job->ops_job_items_count = ($iStat?->ops_job_items_count ?? 0) + $job->ops_job_tasks_count;
                $job->ops_job_items_delivered_count = ($iStat?->ops_job_items_delivered_count ?? 0) + (int) ($tStat?->tasks_completed_count ?? 0);
                $job->ops_job_items_picked_count = ($iStat?->ops_job_items_picked_count ?? 0) + (int) ($tStat?->tasks_picked_count ?? 0);
                $job->ops_job_items_verified_count = $iStat?->ops_job_items_verified_count ?? 0;
                $job->total_cash_amount = $iStat?->total_cash_amount ?? 0;
                $job->total_cash_amount_from_vmc = $iStat?->total_cash_amount_from_vmc ?? 0;
                $job->delta_cash_amount = $iStat?->delta_cash_amount ?? 0;
                $job->acc_vend_transactions_amount = $iStat?->acc_vend_transactions_amount ?? 0;
                $job->acc_vend_transactions_cash_amount = $iStat?->acc_vend_transactions_cash_amount ?? 0;
                $job->acc_vend_transactions_cashless_amount = $iStat?->acc_vend_transactions_cashless_amount ?? 0;
                $job->acc_vend_transactions_promo_amount = $iStat?->acc_vend_transactions_promo_amount ?? 0;
                $job->acc_vend_transactions_count = $iStat?->acc_vend_transactions_count ?? 0;
                $job->cms_transaction_count = $iStat?->cms_transaction_count ?? 0;

                // refillable_amount is in cents; tasks.value is also stored in cents
                $job->refillable_amount = ($iStat?->refillable_amount_frozen ?? 0) + ($cStat?->live_refillable_amount ?? 0) + ($tStat?->tasks_value_sum ?? 0);
                $job->refillable_count = ($iStat?->refillable_count_frozen ?? 0) + ($cStat?->live_refillable_count ?? 0) + ($tStat?->tasks_qty_sum ?? 0);

                // Picked: add task picked value+qty (status >= 2)
                $job->picked_amount = ($cStat?->picked_amount ?? 0) + (int) ($tStat?->tasks_picked_value ?? 0);
                $job->picked_count = ($cStat?->picked_count ?? 0) + (int) ($tStat?->tasks_picked_qty ?? 0);
                $job->picked_cost = $cStat?->picked_cost ?? 0;

                // Stock-in: add task completed value+qty (status >= 3)
                $job->stock_in_amount = ($cStat?->stock_in_amount ?? 0) + (int) ($tStat?->tasks_completed_value ?? 0);
                $job->stock_in_count = ($cStat?->stock_in_count ?? 0) + (int) ($tStat?->tasks_completed_qty ?? 0);
                $job->stock_in_cost = $cStat?->stock_in_cost ?? 0;

                // Percentages
                $job->ops_job_items_picked_count_percentage = $job->ops_job_items_count > 0
                    ? ($job->ops_job_items_picked_count / $job->ops_job_items_count) * 100
                    : 0;
                $job->ops_job_items_delivered_count_percentage = $job->ops_job_items_count > 0
                    ? ($job->ops_job_items_delivered_count / $job->ops_job_items_count) * 100
                    : 0;
                $job->ops_job_items_verified_count_percentage = $job->ops_job_items_count > 0
                    ? ($job->ops_job_items_verified_count / $job->ops_job_items_count) * 100
                    : 0;
                // API Invoice % — tasks cannot have API invoices, so use items-only delivered count as denominator
                $itemsOnlyDeliveredCount = $iStat?->ops_job_items_delivered_count ?? 0;
                $job->cms_transaction_percentage = $itemsOnlyDeliveredCount > 0
                    ? ($job->cms_transaction_count / $itemsOnlyDeliveredCount) * 100
                    : 0;
            }

            // Sorting for aggregated columns (after data is merged)
            if (
                in_array($request->sortKey, [
                    'ops_job_items_count',
                    'ops_job_items_delivered_count',
                    'ops_job_items_verified_count',
                    'ops_job_items_delivered_count_percentage',
                    'ops_job_items_verified_count_percentage',
                    'total_cash_amount_from_vmc',
                    'delta_cash_amount',
                ])
            ) {
                $opsJobs->getCollection()->sortBy(
                    $request->sortKey,
                    SORT_REGULAR,
                    filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? false : true // true for descending
                );
            }
        }


        return Inertia::render('OpsJob/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'opsJobs' => OpsJobResource::collection(
                $opsJobs
            ),
            'userOptions' => UserResource::collection(
                User::when($isDriver, function ($q) {
                    $q->where('id', auth()->id());
                })
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function summary(Request $request)
    {
        $isDriver = auth()->user()->hasRole('driver');

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

        $request->merge([
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->subDays(7)->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->subDays(1)->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);

        if ($isDriver) {
            $request->merge([
                'delivered_by' => auth()->id(),
            ]);
        }

        // Base query for filtering
        $query = OpsJob::query();

        // Apply filters
        $query->when($request->date_from, function ($query, $search) {
            $query->where('date', '>=', $search);
        })
            ->when($request->date_to, function ($query, $search) {
                $query->where('date', '<=', $search);
            })
            ->when($request->delivered_by, function ($query, $search) {
                if (is_array($search)) {
                    $query->whereIn('delivered_by', $search);
                } else {
                    $query->where('delivered_by', $search);
                }
            })
            ->whereHas('deliveredBy', function ($query) use ($request) {
                $query->whereIn('operator_id', $request->operators);
            });

        $opsJobIds = $query->pluck('id')->toArray();

        // Retrieve raw ops jobs to group by delivered_by
        $opsJobsAll = $query->with('deliveredBy')->get();
        $groupedOpsJobs = $opsJobsAll->groupBy('delivered_by');

        $summaries = collect();

        if (!empty($opsJobIds)) {
            // 1. Item Stats Aggregation
            $itemStats = DB::table('ops_job_items')
                ->whereIn('ops_job_id', $opsJobIds)
                ->selectRaw('
                    ops_job_id,
                    COUNT(*) as ops_job_items_count,
                    SUM(CASE WHEN status >= ? AND status <> ? THEN 1 ELSE 0 END) as ops_job_items_delivered_count,
                    SUM(CASE WHEN picked_at IS NOT NULL THEN 1 ELSE 0 END) as ops_job_items_picked_count,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ops_job_items_verified_count,
                    SUM(cash_amount) as total_cash_amount,
                    SUM(temp_cash_amount_from_vmc) as total_cash_amount_from_vmc,
                    SUM(cash_amount) - SUM(temp_cash_amount_from_vmc) as delta_cash_amount,
                    SUM(acc_total_amount) as acc_vend_transactions_amount,
                    SUM(acc_total_cash_amount) as acc_vend_transactions_cash_amount,
                    SUM(acc_total_cashless_amount) as acc_vend_transactions_cashless_amount,
                    SUM(acc_total_promo_amount) as acc_vend_transactions_promo_amount,
                    SUM(acc_total_count) as acc_vend_transactions_count,
                    SUM(CASE WHEN cms_transaction_id IS NOT NULL THEN 1 ELSE 0 END) as cms_transaction_count,
                    SUM(refillable_amount) as refillable_amount_frozen,
                    SUM(refillable_count) as refillable_count_frozen
                ', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED, OpsJob::STATUS_VERIFIED])
                ->groupBy('ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 2. Channel Stats Aggregation
            $channelStats = DB::table('ops_job_item_channels as ojic')
                ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
                ->join('ops_jobs as oj', 'oji.ops_job_id', '=', 'oj.id')
                ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                ->leftJoin('unit_costs as uc', function ($join) {
                    $join->on('p.id', '=', 'uc.product_id')
                        ->where('uc.is_current', '=', true);
                })
                ->leftJoin(DB::raw('(
                    SELECT id, product_id, qty, date
                    FROM (
                        SELECT id, product_id, qty, date,
                            ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
                        FROM product_limits
                    ) pl_inner
                    WHERE rn = 1
                ) AS pl'), function ($join) {
                    $join->on('p.id', '=', 'pl.product_id')
                        ->on('pl.date', '=', 'oj.date');
                })
                ->whereIn('oji.ops_job_id', $opsJobIds)
                ->selectRaw('
                    oji.ops_job_id,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * vc.amount ELSE 0 END) as picked_amount,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty ELSE 0 END) as picked_count,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * COALESCE(uc.cost, 0) ELSE 0 END) as picked_cost,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * vc.amount ELSE 0 END) as stock_in_amount,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty ELSE 0 END) as stock_in_count,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * COALESCE(uc.cost, 0) ELSE 0 END) as stock_in_cost,
                    SUM(CASE WHEN (oji.status = 1 OR oji.refillable_amount IS NULL) AND p.is_available = 1 THEN GREATEST(
                        CASE
                            WHEN pl.id IS NOT NULL AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                            ELSE (vc.capacity - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                        END, 0
                    ) ELSE 0 END * vc.amount) as live_refillable_amount,
                    SUM(CASE WHEN (oji.status = 1 OR oji.refillable_amount IS NULL) AND p.is_available = 1 THEN GREATEST(
                        CASE
                            WHEN pl.id IS NOT NULL AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                            ELSE (vc.capacity - COALESCE(CASE WHEN oji.status >= 2 THEN COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty) ELSE vc.qty END, 0))
                        END, 0
                    ) ELSE 0 END) as live_refillable_count
                ', [
                    OpsJob::STATUS_PICKED,
                    OpsJob::STATUS_PICKED,
                    OpsJob::STATUS_PICKED,
                    OpsJob::STATUS_DELIVERED,
                    OpsJob::STATUS_CANCELLED,
                    OpsJob::STATUS_DELIVERED,
                    OpsJob::STATUS_CANCELLED,
                    OpsJob::STATUS_DELIVERED,
                    OpsJob::STATUS_CANCELLED
                ])
                ->where('vc.is_active', 1)
                ->where('vc.capacity', '>', 0)
                ->groupBy('oji.ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            foreach ($groupedOpsJobs as $deliveryById => $jobs) {
                $summary = [
                    'id' => $deliveryById, // Use delivery_by ID as referencing key
                    'delivered_by' => $jobs->first()->deliveredBy,
                    'job_count' => $jobs->count(),
                    'ops_job_items_count' => 0,
                    'ops_job_items_delivered_count' => 0,
                    'ops_job_items_picked_count' => 0,
                    'ops_job_items_verified_count' => 0,
                    'total_cash_amount' => 0,
                    'total_cash_amount_from_vmc' => 0,
                    'delta_cash_amount' => 0,
                    'acc_vend_transactions_amount' => 0,
                    'acc_vend_transactions_cash_amount' => 0,
                    'acc_vend_transactions_count' => 0,
                    'cms_transaction_count' => 0,
                    'refillable_amount' => 0,
                    'refillable_count' => 0,
                    'picked_amount' => 0,
                    'picked_count' => 0,
                    'picked_cost' => 0,
                    'stock_in_amount' => 0,
                    'stock_in_count' => 0,
                    'stock_in_cost' => 0,
                ];

                foreach ($jobs as $job) {
                    $iStat = $itemStats->get($job->id);
                    $cStat = $channelStats->get($job->id);

                    // Accumulate stats
                    $summary['ops_job_items_count'] += $iStat?->ops_job_items_count ?? 0;
                    $summary['ops_job_items_delivered_count'] += $iStat?->ops_job_items_delivered_count ?? 0;
                    $summary['ops_job_items_picked_count'] += $iStat?->ops_job_items_picked_count ?? 0;
                    $summary['ops_job_items_verified_count'] += $iStat?->ops_job_items_verified_count ?? 0;
                    $summary['total_cash_amount'] += $iStat?->total_cash_amount ?? 0;
                    $summary['total_cash_amount_from_vmc'] += $iStat?->total_cash_amount_from_vmc ?? 0;
                    $summary['delta_cash_amount'] += $iStat?->delta_cash_amount ?? 0;
                    $summary['acc_vend_transactions_amount'] += $iStat?->acc_vend_transactions_amount ?? 0;
                    $summary['acc_vend_transactions_cash_amount'] += $iStat?->acc_vend_transactions_cash_amount ?? 0;
                    $summary['acc_vend_transactions_count'] += $iStat?->acc_vend_transactions_count ?? 0;
                    $summary['cms_transaction_count'] += $iStat?->cms_transaction_count ?? 0;

                    $summary['refillable_amount'] += ($iStat?->refillable_amount_frozen ?? 0) + ($cStat?->live_refillable_amount ?? 0);
                    $summary['refillable_count'] += ($iStat?->refillable_count_frozen ?? 0) + ($cStat?->live_refillable_count ?? 0);
                    $summary['picked_amount'] += $cStat?->picked_amount ?? 0;
                    $summary['picked_count'] += $cStat?->picked_count ?? 0;
                    $summary['picked_cost'] += $cStat?->picked_cost ?? 0;
                    $summary['stock_in_amount'] += $cStat?->stock_in_amount ?? 0;
                    $summary['stock_in_count'] += $cStat?->stock_in_count ?? 0;
                    $summary['stock_in_cost'] += $cStat?->stock_in_cost ?? 0;
                }

                // Calculate percentages
                $summary['ops_job_items_picked_count_percentage'] = $summary['ops_job_items_count'] > 0
                    ? ($summary['ops_job_items_picked_count'] / $summary['ops_job_items_count']) * 100
                    : 0;
                $summary['ops_job_items_delivered_count_percentage'] = $summary['ops_job_items_count'] > 0
                    ? ($summary['ops_job_items_delivered_count'] / $summary['ops_job_items_count']) * 100
                    : 0;
                $summary['ops_job_items_verified_count_percentage'] = $summary['ops_job_items_count'] > 0
                    ? ($summary['ops_job_items_verified_count'] / $summary['ops_job_items_count']) * 100
                    : 0;
                $summary['cms_transaction_percentage'] = $summary['ops_job_items_delivered_count'] > 0
                    ? ($summary['cms_transaction_count'] / $summary['ops_job_items_delivered_count']) * 100
                    : 0;

                // Conversion from cents to dollars (or base currency)
                $summary['total_cash_amount'] /= 100;
                $summary['total_cash_amount_from_vmc'] /= 100;
                $summary['delta_cash_amount'] /= 100;
                $summary['acc_vend_transactions_amount'] /= 100;
                $summary['acc_vend_transactions_cash_amount'] /= 100;
                $summary['refillable_amount'] /= 100;
                $summary['picked_amount'] /= 100;
                $summary['picked_cost'] /= 100;
                $summary['stock_in_amount'] /= 100;
                $summary['stock_in_cost'] /= 100;

                $summaries->push($summary);
            }
        }

        $summaries = $summaries->sortBy(function ($summary) {
            return $summary['delivered_by'] ? $summary['delivered_by']->name : 'Unassigned';
        });

        return Inertia::render('OpsJob/Summary', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'summaries' => $summaries->values(),
            'userOptions' => UserResource::collection(
                User::when($isDriver, function ($q) {
                    $q->where('id', auth()->id());
                })
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function confirmItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        switch ($opsJobItem->status) {
            case 1:
                $stats = DB::table('ops_job_item_channels as ojic')
                    ->join('ops_jobs as oj', 'oj.id', '=', DB::raw($opsJobItem->ops_job_id))
                    ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                    ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                    ->leftJoin(DB::raw('(
                        SELECT id, product_id, qty, date
                        FROM (
                            SELECT id, product_id, qty, date,
                                ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
                            FROM product_limits
                        ) pl_inner
                        WHERE rn = 1
                    ) AS pl'), function ($join) {
                        $join->on('p.id', '=', 'pl.product_id')
                            ->on('pl.date', '=', 'oj.date');
                    })
                    ->where('ojic.ops_job_item_id', $opsJobItem->id)
                    ->selectRaw('
                        SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(vc.qty, 0)) ELSE (vc.capacity - COALESCE(vc.qty, 0)) END, 0) ELSE 0 END * vc.amount) as refillable_amount,
                        SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(vc.qty, 0)) ELSE (vc.capacity - COALESCE(vc.qty, 0)) END, 0) ELSE 0 END) as refillable_count
                    ')->first();

                $opsJobItem->update([
                    'status' => OpsJob::STATUS_PICKED,
                    'picked_by' => auth()->id(),
                    'picked_at' => Carbon::now(),
                    'undo_picked_at' => null,
                    'undo_picked_by' => null,
                    'refillable_amount' => $stats ? $stats->refillable_amount : 0,
                    'refillable_count' => $stats ? $stats->refillable_count : 0,
                ]);

                $stockActionType = $opsJobItem->stock_action_type;
                $isAutoPickAction = $stockActionType === 'return_stock' || $stockActionType === 'onsite_adjustment';

                if ($isAutoPickAction) {
                    // For return_stock and onsite_adjustment, no warehouse picking is needed.
                    // Set all channel picked_qty to 0 and move straight to Picked.
                    $opsJobItem->opsJobItemChannels()->update([
                        'picked_before_qty' => DB::raw('qty'),
                        'picked_qty' => 0,
                        'saved_picked_qty' => 0,
                    ]);
                } elseif ($request->channels) {
                    foreach ($request->channels as $channel) {
                        $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                        $opsJobItemChannel->update([
                            'picked_before_qty' => $channel['qty'],
                            'picked_qty' => $channel['picked'],
                            'qty' => $channel['qty'],
                            'saved_picked_qty' => $channel['picked'],
                        ]);

                        if ($channel['picked'] != 0) {
                            ProductMovement::create([
                                'product_id' => $opsJobItemChannel->product_id,
                                'type' => ProductMovement::TYPE_PICKED,
                                'qty' => -1 * $channel['picked'],
                                'operator_id' => $opsJobItem->opsJob->operator_id,
                                'user_id' => auth()->id(),
                                // 'remarks' => $opsJobItem->id + 25000,
                                'batch_number' => $opsJobItem->id + 25000,
                                'created_at' => Carbon::now(),
                                'updated_at' => Carbon::now(),
                            ]);
                        }
                    }
                }
                break;
            case 2:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_DELIVERED,
                    'completed_by' => auth()->id(),
                    'completed_at' => Carbon::now(),
                    'cash_amount' => $request->cash_amount,
                    'cashless_amount' => $request->cashless_amount,
                    'temp_cash_amount_from_vmc' => $request->temp_cash_amount_from_vmc,
                    'undo_completed_at' => null,
                    'undo_completed_by' => null,
                ]);

                $hasMappingChange = $opsJobItem->opsJobItemChannels()->where('is_upcoming_product', true)->exists();

                if ($request->channels) {
                    foreach ($request->channels as $channel) {
                        $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                        $opsJobItemChannel->update([
                            'actual_before_qty' => $channel['qty'],
                            'actual_qty' => $channel['refill'],
                            'capacity' => $channel['capacity'],
                            'qty' => $channel['qty'],
                        ]);
                    }
                }

                if ($hasMappingChange) {
                    $vend = $opsJobItem->vend;
                    if ($vend) {
                        $targetMappingId = $vend->upcoming_product_mapping_id ?: ($vend->productMapping ? $vend->productMapping->upcoming_product_mapping_id : null);
                        if ($targetMappingId) {
                            $vend->update([
                                'product_mapping_id' => $targetMappingId,
                                'upcoming_product_mapping_id' => null,
                                'binded_at' => Carbon::now(),
                            ]);
                            $vend->refresh();
                        }
                    }
                }

                // Auto push product info to machine if implement_new_mapping
                if($opsJobItem->stock_action_type === 'implement_new_mapping') {
                    $vend = $opsJobItem->vend;
                    if ($vend) {
                        $this->productMappingService->syncChannelsByVend($vend);
                        \App\Jobs\Vend\SaveVendChannelsJson::dispatchSync($vend->id);
                        
                        $fid = 1;
                        $content = base64_encode(json_encode([
                            'Type' => 'TYPESYNCAPICHANNELSLOTLIST',
                            'time' => Carbon::now()->timestamp,
                            'action' => '',
                            'mid' => $vend->code,
                        ]));
                        $contentLength = strlen($content);
                        $key = $vend && $vend->private_key ? $vend->private_key : '123456789110138A';
                        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

                        PublishMqtt::dispatch('CM' . $vend->code, $fid . ',' . $contentLength . ',' . $content . ',' . $md5)->onQueue('high');
                    }
                }

                if ($opsJobItem->refillable_amount === null || $opsJobItem->refillable_count === null) {
                    $stats = DB::table('ops_job_item_channels as ojic')
                        ->join('ops_jobs as oj', 'oj.id', '=', DB::raw($opsJobItem->ops_job_id))
                        ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                        ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                        ->leftJoin(DB::raw('(
                            SELECT id, product_id, qty, date
                            FROM (
                                SELECT id, product_id, qty, date,
                                    ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
                                FROM product_limits
                            ) pl_inner
                            WHERE rn = 1
                        ) AS pl'), function ($join) {
                            $join->on('p.id', '=', 'pl.product_id')
                                ->on('pl.date', '=', 'oj.date');
                        })
                        ->where('ojic.ops_job_item_id', $opsJobItem->id)
                        ->selectRaw('
                            SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty, 0)) ELSE (vc.capacity - COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty, 0)) END, 0) ELSE 0 END * vc.amount) as refillable_amount,
                            SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty, 0)) ELSE (vc.capacity - COALESCE(ojic.picked_before_qty, ojic.qty, vc.qty, 0)) END, 0) ELSE 0 END) as refillable_count
                        ')->first();

                    $opsJobItem->update([
                        'refillable_amount' => $stats ? $stats->refillable_amount : 0,
                        'refillable_count' => $stats ? $stats->refillable_count : 0,
                    ]);
                }

                // get previous opsJobItem of same vend, then return the completed_at
                $previousOpsJobItem = OpsJobItem::where('vend_id', $opsJobItem->vend_id)
                    ->where('id', '<', $opsJobItem->id)
                    ->where('status', '>=', OpsJob::STATUS_DELIVERED)
                    ->where('status', '<>', OpsJob::STATUS_CANCELLED)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($previousOpsJobItem) {
                    $vendTransactions = VendTransaction::query()
                        ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
                        ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
                        ->where('vend_transactions.created_at', '>=', $previousOpsJobItem->completed_at)
                        ->where('vend_transactions.created_at', '<=', $opsJobItem->completed_at)
                        ->where('vend_transactions.vend_id', $opsJobItem->vend_id)
                        ->isSuccessful()
                        ->selectRaw('SUM(vend_transactions.amount) as total_amount')
                        ->selectRaw('COUNT(*) as total_count')
                        ->selectRaw('SUM(CASE WHEN payment_methods.code = 0 THEN vend_transactions.amount ELSE 0 END) as total_cash_amount')
                        ->selectRaw('SUM(CASE WHEN payment_methods.code <> 0 THEN vend_transactions.amount ELSE 0 END) as total_cashless_amount')
                        ->selectRaw('SUM(vend_channels.amount - vend_transactions.amount) as total_promo_amount')
                        ->first();

                    $opsJobItem->update([
                        'previous_ops_job_item_id' => $previousOpsJobItem->id,
                        'acc_total_amount' => $vendTransactions->total_amount,
                        'acc_total_cash_amount' => $vendTransactions->total_cash_amount,
                        'acc_total_cashless_amount' => $vendTransactions->total_cashless_amount,
                        'acc_total_promo_amount' => $vendTransactions->total_promo_amount,
                        'acc_total_count' => $vendTransactions->total_count,
                    ]);
                }

                // search for B and A vend channel records within 30 mins
                $vendChannelRecord = VendChannelRecord::query()
                    ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, before_data_created_at, ?))', [$opsJobItem->completed_at])
                    ->where('customer_id', $opsJobItem->customer_id)
                    ->doesntHave('opsJobItem')
                    ->whereBetween('before_data_created_at', [
                        Carbon::parse($opsJobItem->completed_at)->subMinutes(30),
                        Carbon::parse($opsJobItem->completed_at)->addMinutes(30)
                    ])
                    ->first();

                if ($vendChannelRecord) {
                    $opsJobItem->update([
                        'vend_channel_record_id' => $vendChannelRecord->id,
                    ]);

                    if ($vendChannelRecord->before_data_json || $vendChannelRecord->after_data_json) {
                        $opsJobItem->opsJobItemChannels->each(function ($opsJobItemChannel) use ($vendChannelRecord) {
                            if ($vendChannelRecord->before_data_json) {
                                $channels = $vendChannelRecord->before_data_json['channels'] ?? [];

                                foreach ($channels as $channel) {
                                    if (isset($channel['channel_code']) && $channel['channel_code'] == $opsJobItemChannel->vend_channel_code) {
                                        $opsJobItemChannel->update([
                                            'vmc_before_qty' => $channel['qty'], // Update with the 'qty' value from the matched channel
                                        ]);
                                        break; // Exit the loop once the matching channel is found
                                    }
                                }
                            }

                            if ($vendChannelRecord->after_data_json) {
                                if ($vendChannelRecord->after_data_json) {
                                    $channels = $vendChannelRecord->after_data_json['channels'] ?? [];

                                    foreach ($channels as $channel) {
                                        if (isset($channel['channel_code']) && $channel['channel_code'] == $opsJobItemChannel->vend_channel_code) {
                                            $opsJobItemChannel->update([
                                                'vmc_after_qty' => $channel['qty'], // Update with the 'qty' value from the matched channel
                                            ]);
                                            break; // Exit the loop once the matching channel is found
                                        }
                                    }
                                }
                            }
                        });
                    }
                }

                break;
        }

        return redirect()->back();
    }

    public function addChannel(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        // Guard: only allowed in pending status with no stock action
        if ($opsJobItem->status != OpsJob::STATUS_PENDING || $opsJobItem->stock_action_type) {
            return redirect()->back()->with('error', 'Cannot add channel in current state.');
        }

        $channelCode = $request->channel_code;
        $pickedQty = (int) $request->picked_qty;
        $replaceChannelId = $request->replace_channel_id ? (int) $request->replace_channel_id : null;

        // Find the VendChannel belonging to this vend
        $vendChannel = VendChannel::where('vend_id', $opsJobItem->vend_id)
            ->where('code', $channelCode)
            ->first();

        if (!$vendChannel) {
            return redirect()->back()->with('error', 'Vend channel not found.');
        }

        // Prevent duplicates
        $exists = $opsJobItem->opsJobItemChannels()
            ->where('vend_channel_id', $vendChannel->id)
            ->where('is_upcoming_product', false)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Channel already added to this job.');
        }

        // Validate the channel to be replaced (if provided)
        $replacedChannel = null;
        if ($replaceChannelId) {
            $replacedChannel = $opsJobItem->opsJobItemChannels()
                ->where('id', $replaceChannelId)
                ->first();
            if (!$replacedChannel) {
                return redirect()->back()->with('error', 'Channel to be replaced not found in this job.');
            }
        }

        // Resolve product_id: VendChannel may have no product assigned yet if the slot
        // was just physically activated. Fall back to the product mapping item so the
        // thumbnail and product name always show correctly.
        $productId = $vendChannel->product_id ?? 0;
        if (!$productId) {
            $vend = $opsJobItem->vend()->with('productMapping.productMappingItems')->first();
            $mappingItem = $vend?->productMapping?->productMappingItems
                ->firstWhere('channel_code', $channelCode);
            $productId = $mappingItem?->product_id ?? 0;
        }

        // If replacing an existing channel, mark it as manually replaced and reset its pick qty to 0
        if ($replacedChannel) {
            $replacedChannel->update([
                'is_manually_replaced' => true,
                'saved_picked_qty'     => 0,
            ]);
        }

        // Create OpsJobItemChannel snapshot (same pattern as createOpsJobItem)
        $opsJobItem->opsJobItemChannels()->create([
            'amount'                              => $vendChannel->amount,
            'ops_job_id'                          => $opsJobItem->ops_job_id,
            'ops_job_item_id'                     => $opsJobItem->id,
            'product_id'                          => $productId,
            'vend_channel_code'                   => $vendChannel->code,
            'vend_channel_id'                     => $vendChannel->id,
            'vend_code'                           => $opsJobItem->vend->code,
            'actual_qty'                          => 0,
            'capacity'                            => $vendChannel->capacity,
            'qty'                                 => $vendChannel->qty,
            'picked_qty'                          => 0,
            'saved_picked_qty'                    => $pickedQty,
            'is_upcoming_product'                 => false,
            'replaces_ops_job_item_channel_id'    => $replacedChannel?->id,
        ]);

        return redirect()->back()->with('success', 'Channel added successfully.');
    }

    public function deleteChannel(Request $request, $itemChannelId)
    {
        $opsJobItemChannel = \App\Models\OpsJobItemChannel::findOrFail($itemChannelId);
        $opsJobItem = $opsJobItemChannel->opsJobItem;

        // Guard: only allowed in pending status with no stock action
        if ($opsJobItem->status != OpsJob::STATUS_PENDING || $opsJobItem->stock_action_type) {
            return redirect()->back()->with('error', 'Cannot delete channel in current state.');
        }

        // If this channel was replacing another, clear the is_manually_replaced flag on the original channel
        if ($opsJobItemChannel->replaces_ops_job_item_channel_id) {
            $originalChannel = \App\Models\OpsJobItemChannel::find($opsJobItemChannel->replaces_ops_job_item_channel_id);
            if ($originalChannel) {
                $originalChannel->update([
                    'is_manually_replaced' => false,
                    'saved_picked_qty'     => null,
                ]);
            }
        }

        $opsJobItemChannel->delete();

        return redirect()->back()->with('success', 'Channel removed successfully.');
    }

    public function saveItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        // Per-SKU freeze semantics:
        //   - Front-end only sends channels the operator manually touched
        //     (is_user_modified or is_user_unfreeze).
        //   - Untouched channels are NOT sent and we leave their
        //     saved_picked_qty alone (so previously-frozen rows stay frozen,
        //     and previously-live rows stay live and keep tracking the
        //     Needed Qty as sales come in).
        //   - For sent channels:
        //       * unfreeze=true  -> saved_picked_qty = null (back to live)
        //       * unfreeze=false -> saved_picked_qty = picked (freeze at value)
        if ($request->channels) {
            foreach ($request->channels as $channel) {

                $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                if ($opsJobItemChannel) {
                    $unfreeze = !empty($channel['unfreeze']);
                    $opsJobItemChannel->update([
                        'saved_picked_qty' => $unfreeze ? null : $channel['picked'],
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Quantities saved successfully.');
    }

    public function qtyList(Request $request, $status = 3)
    {
        $dataArr = [];
        $input = collect($request->all());
        $items = VendChannel::query()
            ->with([
                'product:id,code,name,desc,is_available',
                'product.thumbnail:id,full_url,attachments.modelable_id,attachments.modelable_type',
            ])
            ->leftJoin('products', 'products.id', '=', 'vend_channels.product_id')
            ->leftJoin('ops_job_item_channels', 'ops_job_item_channels.vend_channel_id', '=', 'vend_channels.id')
            ->leftJoin('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->whereIn('ops_job_items.id', $input->pluck('id')->toArray());

        switch ($status) {
            case OpsJob::STATUS_PICKED:
                $items = $items->where('ops_job_items.status', OpsJob::STATUS_PICKED);
                break;
            case OpsJob::STATUS_DELIVERED:
                $items = $items->where('ops_job_items.status', '>=', OpsJob::STATUS_DELIVERED);
                break;
        }

        $items = $items->where('ops_job_items.status', '<>', OpsJob::STATUS_CANCELLED)
            ->select(
                'vend_channels.product_id',
                DB::raw("
                    SUM(
                        CASE
                            WHEN ops_job_items.status = " . OpsJob::STATUS_PICKED . " THEN ops_job_item_channels.picked_qty
                            WHEN ops_job_items.status >= " . OpsJob::STATUS_DELIVERED . " THEN ops_job_item_channels.actual_qty
                            ELSE 0
                        END
                    ) as topup_qty
                ")
            )
            ->groupBy('vend_channels.product_id')
            ->orderBy('products.code')
            ->having('topup_qty', '<>', 0)
            ->get();


        $dataArr = [
            'items' => $items->toArray(),
            'vends' => $input->map(function ($item) {
                return [
                    'code' => $item['code'] ?? null,
                    'customer_id' => $item['customer_id'] ?? null,
                    'customer_code' => $item['customer_code'] ?? null,
                    'customer_name' => $item['customer_name'] ?? null,
                    'person_id' => $item['person_id'] ?? null,
                ];
            }),
        ];

        return $dataArr;
    }

    public function syncCmsInvoices($id)
    {
        $opsJob = OpsJob::findOrFail($id);

        $opsJob = OpsJob::query()
            ->with([
                'createdBy',
                'deliveredBy',
                'opsJobItems.customer',
                'opsJobItems.opsJobItemChannels.product',
                'opsJobItems.opsJobItemChannels.vendChannel.product'
            ])
            ->find($id);

        $data = [
            'date' => Carbon::parse($opsJob->date)->format('Y-m-d'),
            'driver' => $opsJob->deliveredBy->username,
            'created_by' => auth()->user()->username,
            'status' => 'Delivered',
            'customers' => [],
        ];

        if ($opsJob->opsJobItems) {
            foreach ($opsJob->opsJobItems as $opsJobItem) {
                if (($opsJobItem->status < OpsJob::STATUS_DELIVERED) or ($opsJobItem->status == OpsJob::STATUS_CANCELLED) or ($opsJobItem->cms_transaction_id)) {
                    continue;
                }
                if ($opsJobItem->customer && $opsJobItem->customer->person_id) {
                    SyncOpsJobTransactionCMS::dispatch($opsJobItem, $data, auth()->user()->id);
                    $opsJobItem->update(['is_inventory_adjusted' => true]);
                }
            }
        }



        return redirect()->back();
    }

    public function syncInventory($id)
    {
        $opsJob = OpsJob::with('opsJobItems')->findOrFail($id);

        $opsJob->opsJobItems->each(function ($opsJobItem) {
            if ($opsJobItem->status >= OpsJob::STATUS_DELIVERED && $opsJobItem->status != OpsJob::STATUS_CANCELLED && !$opsJobItem->is_inventory_adjusted) {
                $opsJobItem->update(['is_inventory_adjusted' => true]);
            }
        });

        return redirect()->back()->with('success', 'Inventory Synced Successfully');
    }

    public function edit(Request $request, $id)
    {
        $opsJob = OpsJob::query()
            ->with([
                'createdBy:id,name', // Select only necessary columns
                'deliveredBy:id,name',
                'operator:id,name',
                'opsJobItems' => function ($query) use ($request) {
                    $query->when($request->vend_code, function ($query, $search) {
                        $query->whereHas('vend', function ($query) use ($search) {
                            $query->where('code', 'LIKE', "{$search}%");
                        });
                    });
                    $query->when($request->customer, function ($query, $search) {
                        $query->where(function ($query) use ($search) {
                            $query->whereHas('vend.customer', function ($query) use ($search) {
                                $query->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('virtual_customer_code', 'LIKE', "{$search}%");
                            });
                        });
                    });

                    // Select necessary columns
                    $query->select([
                        'id',
                        'cash_amount',
                        'cashless_amount',
                        'customer_id',
                        'notes',
                        'ops_job_id',
                        'vend_id',
                        'cms_transaction_at',
                        'cms_transaction_by',
                        'cms_transaction_id',
                        'is_cash_collected',
                        'is_inventory_adjusted',
                        'sequence',
                        'status',
                        'picked_at',
                        'picked_by',
                        'previous_ops_job_item_id',
                        'refillable_amount',
                        'refillable_count',
                        'completed_at',
                        'completed_by',
                        'remarks',
                        'remarks_updated_at',
                        'remarks_updated_by',
                        'temp_cash_amount_from_vmc',
                        'vend_channel_record_id',
                        'verified_at',
                        'verified_by',
                        'stock_action_type',
                        // Stored columns aliased to the names the resource/frontend expect.
                        // Previously these were computed via 6 correlated self-join subqueries of the form
                        // SELECT SUM(col) FROM ops_job_items WHERE id = X — which always returned exactly
                        // one row and were therefore equivalent to reading the column directly.
                        'acc_total_amount as acc_vend_transactions_amount',
                        'acc_total_count as acc_vend_transactions_count',
                        'acc_total_cash_amount as acc_vend_transactions_cash_amount',
                        'acc_total_cashless_amount as acc_vend_transactions_cashless_amount',
                        'acc_total_promo_amount as acc_vend_transactions_promo_amount',
                    ]);

                    $query->selectRaw('
                    (SELECT
                        CASE
                            WHEN ops_job_items.status = 1 THEN created_at
                            WHEN ops_job_items.status = 2 THEN picked_at
                            WHEN ops_job_items.status = 3 THEN completed_at
                            WHEN ops_job_items.status = 4 THEN verified_at
                            WHEN ops_job_items.status = 98 THEN flagged_at
                            WHEN ops_job_items.status = 99 THEN cancelled_at
                    ELSE NULL END) as status_at');

                    $query->selectRaw('
                    (SELECT
                        CASE
                            WHEN ops_job_items.status = 1 THEN created_by
                            WHEN ops_job_items.status = 2 THEN picked_by
                            WHEN ops_job_items.status = 3 THEN completed_by
                            WHEN ops_job_items.status = 4 THEN verified_by
                            WHEN ops_job_items.status = 98 THEN flagged_by
                            WHEN ops_job_items.status = 99 THEN cancelled_by
                    ELSE NULL END) as status_by');

                    // Adjust the selectRaw queries to correctly reference the opsJobItems relationship
                    // For pending items (status=1), use live vend_channels data (qty, capacity) to match CustomerIndex's actual_stock_in_value logic
                    $query->selectRaw('
                    COALESCE(ops_job_items.refillable_amount, (SELECT SUM(
                        CASE WHEN products.is_available = 1 THEN GREATEST(
                            CASE
                                WHEN pl.id AND pl.qty < vend_channels.capacity THEN (pl.qty - COALESCE(CASE WHEN ops_job_items.status >= 2 THEN COALESCE(ops_job_item_channels.picked_before_qty, ops_job_item_channels.qty, vend_channels.qty) ELSE vend_channels.qty END, 0))
                                ELSE (vend_channels.capacity - COALESCE(CASE WHEN ops_job_items.status >= 2 THEN COALESCE(ops_job_item_channels.picked_before_qty, ops_job_item_channels.qty, vend_channels.qty) ELSE vend_channels.qty END, 0))
                            END, 0
                        ) ELSE 0 END * vend_channels.amount
                    )
                     FROM ops_job_item_channels
                     JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                     LEFT JOIN products ON vend_channels.product_id = products.id
                     LEFT JOIN (
                        SELECT pl.id, pl.product_id, pl.qty
                        FROM product_limits AS pl
                        INNER JOIN (
                            SELECT product_id, MAX(id) AS max_id
                            FROM product_limits
                            WHERE date = (SELECT date FROM ops_jobs WHERE ops_jobs.id = ops_job_items.ops_job_id)
                            GROUP BY product_id
                        ) AS latest_pl ON pl.id = latest_pl.max_id
                     ) AS pl ON products.id = pl.product_id
                     WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                     AND vend_channels.is_active = 1
                     AND vend_channels.capacity > 0
                    )) as refillable_amount');

                    $query->selectRaw('
                    COALESCE(ops_job_items.refillable_count, (SELECT SUM(
                        CASE WHEN products.is_available = 1 THEN GREATEST(
                            CASE
                                WHEN pl.id AND pl.qty < vend_channels.capacity THEN (pl.qty - COALESCE(CASE WHEN ops_job_items.status >= 2 THEN COALESCE(ops_job_item_channels.picked_before_qty, ops_job_item_channels.qty, vend_channels.qty) ELSE vend_channels.qty END, 0))
                                ELSE (vend_channels.capacity - COALESCE(CASE WHEN ops_job_items.status >= 2 THEN COALESCE(ops_job_item_channels.picked_before_qty, ops_job_item_channels.qty, vend_channels.qty) ELSE vend_channels.qty END, 0))
                            END, 0
                        ) ELSE 0 END
                    )
                     FROM ops_job_item_channels
                     JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                     LEFT JOIN products ON vend_channels.product_id = products.id
                     LEFT JOIN (
                        SELECT pl.id, pl.product_id, pl.qty
                        FROM product_limits AS pl
                        INNER JOIN (
                            SELECT product_id, MAX(id) AS max_id
                            FROM product_limits
                            WHERE date = (SELECT date FROM ops_jobs WHERE ops_jobs.id = ops_job_items.ops_job_id)
                            GROUP BY product_id
                        ) AS latest_pl ON pl.id = latest_pl.max_id
                     ) AS pl ON products.id = pl.product_id
                     WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                     AND vend_channels.is_active = 1
                     AND vend_channels.capacity > 0
                    )) as refillable_count');

                    $query->selectRaw('
                    (SELECT SUM(ops_job_item_channels.picked_qty * vend_channels.amount)
                    FROM ops_job_item_channels
                    JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                    WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                    AND ops_job_items.status >= ?
                    ) as picked_amount', [OpsJob::STATUS_PICKED]);

                    $query->selectRaw('
                    (SELECT SUM(ops_job_item_channels.picked_qty)
                    FROM ops_job_item_channels
                    JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                    WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                    AND ops_job_items.status >= ?
                    ) as picked_count', [OpsJob::STATUS_PICKED]);

                    $query->selectRaw('
                    (SELECT SUM(ops_job_item_channels.actual_qty * vend_channels.amount)
                     FROM ops_job_item_channels
                     JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                    WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                     AND ops_job_items.status >= ?
                     AND ops_job_items.status <> ?
                    ) as stock_in_amount', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED]);

                    $query->selectRaw('
                    (SELECT SUM(ops_job_item_channels.actual_qty)
                     FROM ops_job_item_channels
                     JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                     WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                     AND ops_job_items.status >= ?
                     AND ops_job_items.status <> ?
                    ) as stock_in_count', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED]);

                    // Previously 6 correlated self-join subqueries of the form:
                    //   SELECT SUM(col) FROM ops_job_items WHERE id = X
                    // which always return exactly the column value of the current row.
                    // Replaced with direct column reads / simple expressions — zero extra queries.
                    $query->selectRaw('cash_amount as total_cash_amount');
                    $query->selectRaw('temp_cash_amount_from_vmc as total_cash_amount_from_vmc');
                    $query->selectRaw('(cash_amount - temp_cash_amount_from_vmc) as delta_cash_amount');

                    $query->selectRaw('
                    (SELECT delivery_address.postcode
                    FROM addresses AS delivery_address
                    WHERE delivery_address.modelable_id = ops_job_items.customer_id
                    AND delivery_address.modelable_type = "App\\\Models\\\Customer"
                    AND delivery_address.type = 2
                    LIMIT 1) as delivery_postcode'
                    );

                    $query->when($request->sortKey, function ($query, $search) use ($request) {
                        if (
                            in_array($search, [
                                'picked_amount',
                                'stock_in_amount',
                                'acc_vend_transactions_amount',
                                'total_cash_amount',
                                'total_cash_amount_from_vmc',
                                'delta_cash_amount',
                            ])
                        ) {
                            $query->orderByRaw("{$search} " . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                        } else {
                            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                        }
                    }, function ($query) {
                        $query->orderByRaw('ISNULL(sequence), sequence ASC')->orderBy('created_at');
                    });
                },
                'opsJobItems.attachments',
                'opsJobItems.vend:id,customer_id,code,vend_prefix_id,product_mapping_id,upcoming_product_mapping_id,parameter_json,vend_channel_error_logs_json',
                'opsJobItems.vend.productMapping.productMappingItemsNormalSequence.product',
                'opsJobItems.vend.productMapping.upcomingProductMapping.productMappingItemsNormalSequence.product',
                'opsJobItems.vend.upcomingProductMapping.productMappingItemsNormalSequence.product',
                // Delivery-platform bindings on the vend — used to render the
                // green "Grab" pill next to the customer name in the Customer
                // column. Mirrors the chain eager-loaded in
                // CustomerController::summary() (customer.vends.deliveryProductMappingVends...).
                // The deliveryProductMappingVends() relation is already scoped
                // to is_active=true + end_date IS NULL on the Vend model.
                'opsJobItems.vend.deliveryProductMappingVends:id,vend_id,delivery_product_mapping_id',
                'opsJobItems.vend.deliveryProductMappingVends.deliveryProductMapping:id,delivery_platform_operator_id',
                'opsJobItems.vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator:id,delivery_platform_id',
                'opsJobItems.vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name',
                'opsJobItems.cmsTransactionBy',
                'opsJobItems.customer.deliveryAddress',
                'opsJobItems.remarksUpdatedBy:id,name',
                'opsJobItems.vend.vendPrefix',
                'opsJobItems.pickedBy:id,name',
                'opsJobItems.previousOpsJobItem',
                'opsJobItems.statusBy',
                'opsJobItems.completedBy:id,name',
                'opsJobItems.vendChannelRecord',
                'opsJobItems.verifiedBy',
                'updatedBy:id,name',
                // Tasks: simple indexed query, ordered by sequence
                'opsJobTasks' => fn($q) => $q->with('createdBy:id,name', 'pickedBy:id,name', 'completedBy:id,name')->orderByRaw('ISNULL(sequence), sequence ASC'),
            ])
            ->findOrFail($id);

        // Load opsJobItemChannels in a single query keyed on ops_job_id rather than
        // letting Laravel eager-load via ops_job_item_id IN (...).
        //
        // The two-level eager load 'opsJobItems.opsJobItemChannels' produces:
        //   SELECT * FROM ops_job_item_channels WHERE ops_job_item_id IN (id1, id2, ..., idN)
        // That's N index seeks (one per item) + N heap-access rounds → 524ms.
        //
        // ops_job_item_channels stores ops_job_id directly with a single-column index
        // (added at table creation). A single WHERE ops_job_id = ? is one range scan
        // that hits contiguous leaf pages → near-instant for any realistic job size.
        //
        // After loading, we group by ops_job_item_id and set the relation on each item
        // so the rest of the code (resources, Vue) sees the same nested structure.
        $channelsByItemId = OpsJobItemChannel::where('ops_job_id', $opsJob->id)
            ->with(['vendChannel.product.thumbnail'])
            ->get()
            ->groupBy('ops_job_item_id');

        $opsJob->opsJobItems->each(function ($item) use ($channelsByItemId) {
            $item->setRelation(
                'opsJobItemChannels',
                $channelsByItemId->get($item->id, collect())
            );
        });

        // Two-step approach instead of a correlated NOT EXISTS subquery.
        //
        // Old approach: NOT EXISTS (SELECT * FROM ops_job_items WHERE vend_id = vends.id AND ops_job_id = ?)
        // MySQL evaluated this per-vend with no index on vend_id — effectively a full table scan
        // of ops_job_items for every vend row → 1700ms.
        //
        // New approach:
        //   Step 1 — fetch the small set of vend_ids already in this job (one indexed query).
        //   Step 2 — exclude those ids from the vend list using NOT IN (resolved via PK).
        //
        // Also replaced has('customer') EXISTS check with whereNotNull: since customer_id is a
        // FK with referential integrity, a non-null customer_id guarantees the customer exists,
        // making the correlated EXISTS on customers unnecessary.
        $vendIdsInJob = OpsJobItem::where('ops_job_id', $opsJob->id)
            ->pluck('vend_id');

        $unbindedVendOptions = Vend::query()
            ->select(['id', 'customer_id', 'operator_id', 'code'])
            ->with(['customer:id,name'])
            ->whereNotNull('customer_id')
            ->when($vendIdsInJob->isNotEmpty(), fn($q) => $q->whereNotIn('id', $vendIdsInJob))
            ->get();

        return Inertia::render('OpsJob/Edit', [
            'cmsBaseUrl' => env('CMS_URL'),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'opsJob' => new OpsJobResource($opsJob),
            'unbindedVendOptions' => VendResource::collection($unbindedVendOptions),
            'userOptions' => UserResource::collection(
                User::with('roles')->when(auth()->user()->hasRole('driver'), function ($q) {
                    $q->where('id', auth()->id());
                })
                    ->when(!auth()->user()->hasRole('superadmin') && auth()->user()->operator_id != 1, function ($q) {
                        $q->where('operator_id', auth()->user()->operator_id);
                    })
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function editItem(Request $request, $id)
    {
        $opsJob = OpsJobItem::findOrFail($id)->opsJob;
        $opsJobItem = OpsJobItem::query()
            ->with([
                'vend:id,customer_id,code,vend_prefix_id,product_mapping_id,upcoming_product_mapping_id',
                'vend.productMapping.productMappingItemsNormalSequence.product',
                'vend.productMapping.productMappingItemsNormalSequence.product.thumbnail',
                'vend.productMapping.upcomingProductMapping.productMappingItemsNormalSequence.product',
                'vend.upcomingProductMapping.productMappingItemsNormalSequence.product',
                'cmsTransactionBy',
                'createdBy',
                'customer.deliveryAddress',
                'vend.vendPrefix',
                'opsJob',
                'opsJobItemChannels' => function ($query) {
                    $query->orderBy('vend_channel_code', 'asc')->orderBy('is_upcoming_product', 'asc');
                },
                'opsJobItemChannels.product' => function ($query) use ($opsJob) {
                    $query->select('*')
                        ->selectRaw('(
                        SELECT qty FROM product_limits
                        WHERE product_limits.product_id = products.id
                        AND product_limits.date = ?
                        LIMIT 1
                    ) AS max_ops_job_pick_limit', [$opsJob->date->toDateString()]);
                },
                'opsJobItemChannels.product.thumbnail',
                'opsJobItemChannels.vendChannel.product' => function ($query) use ($opsJob) {
                    $query->select('*')
                        ->selectRaw('(
                        SELECT qty FROM product_limits
                        WHERE product_limits.product_id = products.id
                        AND product_limits.date = ?
                        LIMIT 1
                    ) AS max_ops_job_pick_limit', [$opsJob->date->toDateString()]);
                },
                'opsJobItemChannels.vendChannel.product.thumbnail',
                'attachments',
                'remarksUpdatedBy:id,name',
                'pickedBy:id,name',
                'previousOpsJobItem',
                'statusBy',
                'completedBy:id,name',
                'undoCompletedBy:id,name',
                'undoFlaggedBy:id,name',
                'undoPickedBy:id,name',
                'undoVerifiedBy:id,name',
                'vendChannelRecord',
                'verifiedBy',
            ])
            ->select(
                '*'
            )
            ->selectRaw('(
                SELECT SUM(oj_items.acc_total_amount)
                FROM ops_job_items oj_items
                WHERE oj_items.id = ops_job_items.id
            ) as acc_vend_transactions_amount')
            ->selectRaw('(
                SELECT SUM(oj_items.acc_total_cash_amount)
                FROM ops_job_items oj_items
                WHERE oj_items.id = ops_job_items.id
            ) as acc_vend_transactions_cash_amount')
            ->selectRaw('(
                SELECT SUM(oj_items.acc_total_cashless_amount)
                FROM ops_job_items oj_items
                WHERE oj_items.id = ops_job_items.id
            ) as acc_vend_transactions_cashless_amount')
            ->selectRaw('(
                SELECT SUM(oj_items.acc_total_promo_amount)
                FROM ops_job_items oj_items
                WHERE oj_items.id = ops_job_items.id
            ) as acc_vend_transactions_promo_amount')
            ->selectRaw('(
                SELECT SUM(oj_items.acc_total_count)
                FROM ops_job_items oj_items
                WHERE oj_items.id = ops_job_items.id
            ) as acc_vend_transactions_count')
            ->selectRaw('
                (SELECT SUM(ops_job_item_channels.actual_qty * vend_channels.amount)
                FROM ops_job_item_channels
                JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                JOIN ops_jobs ON ops_jobs.id = ops_job_item_channels.ops_job_id
                WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                AND ops_job_items.status >= ?
                AND ops_job_items.status <> ?
                AND ops_jobs.id = ops_job_item_channels.ops_job_id
                ) as stock_in_amount', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED])
            ->selectRaw('
                (SELECT SUM(ops_job_item_channels.picked_qty * vend_channels.amount)
                FROM ops_job_item_channels
                JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                ) as picked_amount')
            ->findOrFail($id);



        return Inertia::render('OpsJob/EditItem', [
            'opsJobItem' => OpsJobItemResource::make($opsJobItem),
        ]);
    }

    public function assign(Request $request)
    {
        $vendsID = $request->vends_id;
        $driverID = $request->driver_id;
        $date = $request->date;

        $opsJob = OpsJob::where('date', $date)
            ->where('delivered_by', $driverID)
            ->where('operator_id', auth()->user()->operator_id)
            ->first();

        if (!$opsJob) {
            $code = $this->generateUniqueOpsJobCode('driver_id');

            $opsJob = OpsJob::create([
                'code' => $code,
                'created_by' => auth()->id(),
                'date' => $date,
                'delivered_by' => $driverID,
                'operator_id' => auth()->user()->operator_id,
                'updated_by' => null,
                'updated_at' => null,
            ]);
        }

        foreach ($vendsID as $vendID) {
            $this->createOpsJobItem($opsJob->id, $vendID);
        }
    }

    public function createItem(Request $request, $id)
    {
        $this->createOpsJobItem($id, $request->vend_id);
    }

    public function changeItemStatus(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        if ($request->nextStatus) {
            switch ($request->nextStatus) {
                case 99:
                    $opsJobItem->status = OpsJob::STATUS_CANCELLED;
                    $opsJobItem->cancelled_at = Carbon::now();
                    $opsJobItem->cancelled_by = auth()->id();
                    $opsJobItem->save();
                    break;
                case -1:
                    $opsJobItem->opsJobItemChannels()->delete();
                    $opsJobItem->attachments()->delete();
                    $opsJobItem->delete();
                    return redirect()->route('ops-jobs');
            }
        }

        return redirect()->back();
    }

    public function itemCashCollected(Request $request, $opsJobItemID)
    {
        $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

        $opsJobItem->update([
            'is_cash_collected' => true,
            'cash_amount' => $request->cash_amount ? $request->cash_amount : 0,
            'cashless_amount' => $request->cashless_amount ? $request->cashless_amount : 0,
            'temp_cash_amount_from_vmc' => $request->temp_cash_amount_from_vmc ? $request->temp_cash_amount_from_vmc : 0,
        ]);

        return redirect()->back();
    }

    public function renumberItems(Request $request, $id)
    {
        $opsJob = OpsJob::findOrFail($id);

        // ---------------------------------------------------------------
        // Build a merged ordered list from the request.
        // Each entry has: { type: 'item'|'task', id: int }
        // The frontend sends them in the desired final sequence order.
        //
        // Legacy format (opsJobItems only) is still supported so existing
        // callers (Route.vue) don't break before they are updated.
        // ---------------------------------------------------------------

        $mergedOrder = collect($request->mergedOrder ?? []);

        if ($mergedOrder->isNotEmpty()) {
            // New unified format
            $sequence = 1;
            foreach ($mergedOrder as $entry) {
                if (($entry['type'] ?? '') === 'task') {
                    OpsJobTask::where('id', $entry['id'])
                        ->where('ops_job_id', $opsJob->id) // safety: only own tasks
                        ->update(['sequence' => $sequence]);
                } else {
                    OpsJobItem::where('id', $entry['id'])
                        ->where('ops_job_id', $opsJob->id) // safety: only own items
                        ->update(['sequence' => $sequence]);
                }
                $sequence++;
            }
        } else {
            // Legacy: only opsJobItems provided
            $opsJobItems = collect($request->opsJobItems ?? []);
            $ids = $opsJobItems->pluck('id')->filter()->toArray();

            if (!empty($ids)) {
                $opsJob->opsJobItems()
                    ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
                    ->get()
                    ->each(function ($opsJobItem, $index) {
                        $opsJobItem->update(['sequence' => $index + 1]);
                    });
            }

            // Also renumber tasks after items when using legacy format
            $opsJobTasks = collect($request->opsJobTasks ?? []);
            $taskIds = $opsJobTasks->pluck('id')->filter()->toArray();

            if (!empty($taskIds)) {
                $startSeq = count($ids) + 1;
                $opsJob->opsJobTasks()
                    ->orderByRaw('FIELD(id, ' . implode(',', $taskIds) . ')')
                    ->get()
                    ->each(function ($task, $index) use ($startSeq) {
                        $task->update(['sequence' => $startSeq + $index]);
                    });
            }
        }

        return redirect()->back();
    }

    public function route(Request $request, $id)
    {
        $opsJob = OpsJob::query()
            ->with([
                'deliveredBy',
                'opsJobItems' => function ($query) use ($request) {
                    $query
                        ->leftJoin('customers', 'customers.id', '=', 'ops_job_items.customer_id')
                        ->leftJoin('addresses', function ($query) {
                            $query->on('addresses.modelable_id', '=', 'customers.id')
                                ->where('addresses.modelable_type', '=', 'App\Models\Customer')
                                ->where('addresses.type', '=', 2)
                                ->limit(1);
                        })
                        ->select('ops_job_items.*', 'postcode AS delivery_postcode');

                    $query->when($request->sortKey, function ($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    }, function ($query) {
                        $query->orderByRaw('ISNULL(ops_job_items.sequence), ops_job_items.sequence ASC')->orderBy('postcode');
                    });
                },
                'opsJobItems.customer.deliveryAddress',
                'opsJobItems.opsJobItemChannels',
                'opsJobItems.statusBy',
                'opsJobItems.vend.vendPrefix',
                // Tasks are loaded separately; Route.vue merges them into the items array
                'opsJobTasks' => fn($q) => $q->orderByRaw('ISNULL(sequence), sequence ASC'),
            ])
            ->find($id);

        $opsJobAddresses = $opsJob->opsJobItems->pluck('customer.deliveryAddress')->filter()->unique('id');

        return Inertia::render('OpsJob/Route', [
            'destinationAddresses' => AddressResource::collection(
                Address::where('type', '100')
                    ->latest()
                    ->get()
            ),
            'originAddresses' => AddressResource::collection(
                Address::where('type', '90')
                    ->latest()
                    ->get()
            ),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'opsJob' => OpsJobResource::make($opsJob),
        ]);
    }

    public function saveSequence(Request $request, $id)
    {
        $opsJob = OpsJob::findOrFail($id);

        // New unified path: mergedOrder with type markers
        if ($request->has('mergedOrder') && is_array($request->mergedOrder)) {
            foreach ($request->mergedOrder as $entry) {
                if (($entry['type'] ?? '') === 'task') {
                    OpsJobTask::where('id', $entry['id'])
                        ->where('ops_job_id', $opsJob->id)
                        ->update(['sequence' => $entry['generated_sequence']]);
                } else {
                    OpsJobItem::where('id', $entry['id'])
                        ->where('ops_job_id', $opsJob->id)
                        ->update(['sequence' => $entry['generated_sequence']]);
                }
            }
            return redirect()->back();
        }

        // Legacy path: opsJobItems array only
        foreach ($request->opsJobItems as $opsJobItemRequest) {
            if (isset($opsJobItemRequest['isOpsJobItem']) and $opsJobItemRequest['isOpsJobItem'] == false) {
                continue;
            }
            // Skip synthetic task entries injected by Route.vue
            if (isset($opsJobItemRequest['_isTask']) and $opsJobItemRequest['_isTask'] == true) {
                continue;
            }
            $opsJobItem = OpsJobItem::findOrFail($opsJobItemRequest['id']);
            $opsJobItem->update([
                'sequence' => $opsJobItemRequest['generated_sequence'],
            ]);
        }

        return redirect()->back();
    }

    public function settleItemChannelError($opsJobItemChannelID)
    {
        $opsJobItemChannel = OpsJobItemChannel::findOrFail($opsJobItemChannelID);
        $opsJobItemChannel->update([
            'error_settled_at' => Carbon::now(),
            'is_error_settle' => true,
        ]);

        return redirect()->back();
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required',
            'delivered_by' => 'required',
        ]);

        $code = $this->generateUniqueOpsJobCode('delivered_by');

        try {
            $opsJob = OpsJob::create([
                'code' => $code,
                'created_by' => auth()->id(),
                'date' => $request->date,
                'delivered_by' => $request->delivered_by,
                'operator_id' => auth()->user()->operator_id,
                'updated_by' => null,
                'updated_at' => null,
            ]);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'delivered_by' => 'Unable to create the job because a duplicate code was generated. Please try again.',
                ]);
            }

            throw $exception;
        }

        return redirect()->route('ops-jobs');
    }

    // public function syncCmsInvoices($id)
    // {
    //     $opsJob = OpsJob::findOrFail($id);

    //     foreach($opsJob->opsJobItems as $opsJobItem) {
    //         if($opsJobItem->cms_transaction_id) {
    //             continue;
    //         }

    //         $dataArr[] = [
    //             'ops_job_item_id' => $opsJobItem->id,
    //             'customer_id' => $opsJobItem->customer_id,
    //             'person_id' => $opsJobItem->customer?->person_id,
    //         ];
    //     }

    //     $this->opsJobService->createCMSEmptyInvoicesByOpsJobItem($dataArr, $opsJob->date, $opsJob->deliveredBy);

    //     SyncOpsJobItemTransactionItemCMS::dispatch($opsJobItem->id);

    //     return redirect()->back();
    // }

    public function delete($id)
    {
        $opsJob = OpsJob::findOrFail($id);

        if ($opsJob->opsJobItems) {
            foreach ($opsJob->opsJobItems as $opsJobItem) {
                if ($opsJobItem->opsJobItemChannels) {
                    $opsJobItem->opsJobItemChannels()->delete();
                }
            }

            $opsJob->opsJobItems()->delete();
        }
        $opsJob->delete();

        return redirect()->route('ops-jobs');
    }

    public function deleteItem($id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);
        $opsJobId = $opsJobItem->ops_job_id;

        if ($opsJobItem->cms_transaction_id) {
            $this->opsJobService->deleteJobItemCMSTransaction($id);
        }

        if ($opsJobItem->opsJobItemChannels) {
            $opsJobItem->opsJobItemChannels()->delete();
        }

        $opsJobItem->delete();

        return redirect('/ops-jobs/' . $opsJobId . '/edit');
    }

    public function syncOpsJobItem(Request $request, $opsJobItemID)
    {
        $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

        $opsJobItem->update([
            'sequence' => $request->sequence,
        ]);

        return redirect()->back();
    }

    public function toggleIsIgnoreLimit(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        $opsJobItem->update([
            'is_ignore_limit' => !$opsJobItem->is_ignore_limit,
        ]);

        return redirect()->back();
    }

    public function undoItemStatus($id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        switch ($opsJobItem->status) {
            case OpsJob::STATUS_PICKED:
                $opsJobItem->status = OpsJob::STATUS_PENDING;
                $opsJobItem->last_picked_at = $opsJobItem->picked_at; // Save last picked date
                $opsJobItem->picked_at = null;
                $opsJobItem->picked_by = null;
                $opsJobItem->undo_picked_at = Carbon::now();
                $opsJobItem->undo_picked_by = auth()->id();
                $opsJobItem->refillable_amount = null;
                $opsJobItem->refillable_count = null;
                $opsJobItem->save();

                // ✅ Restore saved `picked_before_qty` to `qty`
                foreach ($opsJobItem->opsJobItemChannels as $channel) {
                    $channel->update([
                        'qty' => $channel->picked_before_qty,
                        'picked_qty' => 0, // optionally reset picked_qty too
                    ]);

                    if ($channel->saved_picked_qty != 0) {
                        ProductMovement::create([
                            'product_id' => $channel->product_id,
                            'type' => ProductMovement::TYPE_UNDO_PICKED,
                            'qty' => $channel->saved_picked_qty,
                            'operator_id' => $opsJobItem->opsJob->operator_id,
                            'user_id' => auth()->id(),
                            'batch_number' => $opsJobItem->id + 25000,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }
                break;
            case OpsJob::STATUS_DELIVERED:
                $opsJobItem->status = OpsJob::STATUS_PICKED;
                $opsJobItem->completed_at = null;
                $opsJobItem->completed_by = null;
                $opsJobItem->undo_completed_at = Carbon::now();
                $opsJobItem->undo_completed_by = auth()->id();
                $opsJobItem->save();
                break;
            case OpsJob::STATUS_VERIFIED:
                $opsJobItem->status = OpsJob::STATUS_DELIVERED;
                $opsJobItem->verified_at = null;
                $opsJobItem->verified_by = null;
                $opsJobItem->undo_verified_at = Carbon::now();
                $opsJobItem->undo_verified_by = auth()->id();
                $opsJobItem->save();
                break;
            case OpsJob::STATUS_FLAGGED:
                $opsJobItem->status = OpsJob::STATUS_DELIVERED;
                $opsJobItem->flagged_at = null;
                $opsJobItem->flagged_by = null;
                $opsJobItem->undo_flagged_at = Carbon::now();
                $opsJobItem->undo_flagged_by = auth()->id();
                $opsJobItem->save();
                break;
        }

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $opsJob = OpsJob::findOrFail($id);

        $opsJob->update($request->all());

        return redirect()->back();
    }

    public function updateItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        if ($request->cash_amount or $request->temp_cash_amount_from_vmc or $request->cashless_amount or $request->remarks) {
            $opsJobItem->update([
                'cash_amount' => $request->cash_amount,
                'cashless_amount' => $request->cashless_amount,
                'temp_cash_amount_from_vmc' => $request->temp_cash_amount_from_vmc,
                'remarks' => $request->remarks,
                'updated_at' => Carbon::now(),
                'updated_by' => auth()->id(),
            ]);
        }

        if ($request->has('sequence')) {
            $opsJobItem->update([
                'sequence' => $request->sequence,
                'updated_at' => Carbon::now(),
                'updated_by' => auth()->id(),
            ]);
        }

        if ($request->delivered_by or $request->date) {
            if (($request->delivered_by != $opsJobItem->opsJob->delivered_by) or ($request->date != $opsJobItem->opsJob->date)) {
                $opsJob = OpsJob::firstOrCreate([
                    'date' => $request->date,
                    'delivered_by' => $request->delivered_by,
                ], [
                    'code' => $this->runningNumberService->getRunningCode(new OpsJob()),
                    'created_by' => auth()->id(),
                    'operator_id' => auth()->user()->operator_id,
                    'updated_by' => auth()->id(),
                    'updated_at' => Carbon::now(),
                ]);

                $opsJobItem->update([
                    'ops_job_id' => $opsJob->id,
                    'updated_at' => Carbon::now(),
                    'updated_by' => auth()->id(),
                ]);

                $opsJobItem->opsJobItemChannels()->update([
                    'ops_job_id' => $opsJob->id,
                ]);
            }
        }

        return redirect()->back();
    }

    public function batchUpdateItems(Request $request)
    {
        $request->validate([
            'item_ids'     => 'nullable|array',
            'item_ids.*'   => 'integer|exists:ops_job_items,id',
            'task_ids'     => 'nullable|array',
            'task_ids.*'   => 'integer|exists:ops_job_tasks,id',
            'delivered_by' => 'required|integer',
            'date'         => 'required|date',
        ]);

        // At least one of item_ids or task_ids must be present
        $itemIds = $request->input('item_ids', []);
        $taskIds = $request->input('task_ids', []);

        if (empty($itemIds) && empty($taskIds)) {
            return redirect()->back()->withErrors(['item_ids' => 'At least one item or task must be selected.']);
        }

        // Resolve the target OpsJob ONCE before the loop.
        $targetOpsJob = OpsJob::firstOrCreate(
            [
                'date'         => $request->date,
                'delivered_by' => $request->delivered_by,
                'operator_id'  => auth()->user()->operator_id,
            ],
            [
                'code'       => $this->runningNumberService->getRunningCode(new OpsJob()),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'updated_at' => Carbon::now(),
            ]
        );

        // Move regular job items
        foreach ($itemIds as $itemId) {
            $opsJobItem = OpsJobItem::findOrFail($itemId);

            if ($opsJobItem->ops_job_id === $targetOpsJob->id) {
                continue;
            }

            $opsJobItem->update([
                'ops_job_id' => $targetOpsJob->id,
                'updated_at' => Carbon::now(),
                'updated_by' => auth()->id(),
            ]);

            $opsJobItem->opsJobItemChannels()->update([
                'ops_job_id' => $targetOpsJob->id,
            ]);
        }

        // Move tasks
        foreach ($taskIds as $taskId) {
            $task = OpsJobTask::findOrFail($taskId);

            if ($task->ops_job_id === $targetOpsJob->id) {
                continue;
            }

            $task->update([
                'ops_job_id' => $targetOpsJob->id,
                'updated_by' => auth()->id(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return redirect()->back();
    }

    public function undoItemCashCollected(Request $request, $opsJobItemID)
    {
        $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

        $opsJobItem->update([
            'is_cash_collected' => false,
        ]);

        $opsJobItem->save();
    }

    public function updateStockAction(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);
        $stockActionType = $request->stock_action_type;

        $opsJobItem->update([
            'stock_action_type' => $stockActionType,
        ]);

        if ($stockActionType === 'implement_new_mapping') {
            $this->applyNewMappingToItem($opsJobItem);
        } elseif ($stockActionType === 'return_stock' || $stockActionType === 'onsite_adjustment') {
            // Remove any upcoming products first
            $opsJobItem->opsJobItemChannels()->where('is_upcoming_product', true)->delete();
            $this->applyReturnStockToItem($opsJobItem);

            // No warehouse picking needed — auto-advance to Picked immediately
            if ($opsJobItem->status == OpsJob::STATUS_PENDING) {
                $stats = DB::table('ops_job_item_channels as ojic')
                    ->join('ops_jobs as oj', 'oj.id', '=', DB::raw($opsJobItem->ops_job_id))
                    ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                    ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                    ->leftJoin(DB::raw('(
                        SELECT id, product_id, qty, date
                        FROM (
                            SELECT id, product_id, qty, date,
                                ROW_NUMBER() OVER (PARTITION BY product_id, date ORDER BY id DESC) as rn
                            FROM product_limits
                        ) pl_inner
                        WHERE rn = 1
                    ) AS pl'), function ($join) {
                        $join->on('p.id', '=', 'pl.product_id')
                            ->on('pl.date', '=', 'oj.date');
                    })
                    ->where('ojic.ops_job_item_id', $opsJobItem->id)
                    ->selectRaw('
                        SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(vc.qty, 0)) ELSE (vc.capacity - COALESCE(vc.qty, 0)) END, 0) ELSE 0 END * vc.amount) as refillable_amount,
                        SUM(CASE WHEN p.is_available = 1 THEN GREATEST(CASE WHEN pl.id AND pl.qty < vc.capacity THEN (pl.qty - COALESCE(vc.qty, 0)) ELSE (vc.capacity - COALESCE(vc.qty, 0)) END, 0) ELSE 0 END) as refillable_count
                    ')->first();

                $opsJobItem->update([
                    'status' => OpsJob::STATUS_PICKED,
                    'picked_by' => auth()->id(),
                    'picked_at' => Carbon::now(),
                    'undo_picked_at' => null,
                    'undo_picked_by' => null,
                    'refillable_amount' => $stats ? $stats->refillable_amount : 0,
                    'refillable_count' => $stats ? $stats->refillable_count : 0,
                ]);
            }
        } else {
            // Remove any upcoming products if we are clearing or changing action
            $opsJobItem->opsJobItemChannels()->where('is_upcoming_product', true)->delete();
            // Reset channels to normal (clear any negative picked_qty set by return_stock)
            $opsJobItem->opsJobItemChannels()->where('is_upcoming_product', false)->update([
                'picked_qty' => null,
                'saved_picked_qty' => null,
            ]);
        }

        return redirect()->back()->with('success', 'Stock Action updated successfully.');
    }

    public function undoStockAction(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        // Only allow undo when in Picked status with return_stock or onsite_adjustment
        if (
            $opsJobItem->status != OpsJob::STATUS_PICKED ||
            !in_array($opsJobItem->stock_action_type, ['return_stock', 'onsite_adjustment'])
        ) {
            return redirect()->back()->with('error', 'Cannot undo stock action for this item.');
        }

        // Clear stock action and revert to Pending
        $opsJobItem->update([
            'stock_action_type' => null,
            'status' => OpsJob::STATUS_PENDING,
            'picked_by' => null,
            'picked_at' => null,
            'undo_picked_by' => auth()->id(),
            'undo_picked_at' => Carbon::now(),
            'refillable_amount' => null,
            'refillable_count' => null,
        ]);

        // Reset all channel picked quantities back to null (normal pending state)
        $opsJobItem->opsJobItemChannels()->update([
            'picked_qty' => null,
            'saved_picked_qty' => null,
            'picked_before_qty' => null,
        ]);

        return redirect()->back()->with('success', 'Stock action cleared and item reverted to Pending.');
    }

    public function updateJobStockAction(Request $request, $id)
    {
        $opsJob = OpsJob::findOrFail($id);
        $opsJob->update([
            'stock_action_type' => $request->stock_action_type,
        ]);

        foreach ($opsJob->opsJobItems as $item) {
            $item->update(['stock_action_type' => $request->stock_action_type]);
            if ($request->stock_action_type === 'implement_new_mapping') {
                $this->applyNewMappingToItem($item);
            } elseif ($request->stock_action_type === 'return_stock' || $request->stock_action_type === 'onsite_adjustment') {
                $item->opsJobItemChannels()->where('is_upcoming_product', true)->delete();
                $this->applyReturnStockToItem($item);
            } else {
                $item->opsJobItemChannels()->where('is_upcoming_product', true)->delete();
            }
        }

        return redirect()->back()->with('success', 'Job Stock Action updated successfully.');
    }

    private function applyNewMappingToItem($opsJobItem)
    {
        $vend = $opsJobItem->vend;
        if (!$vend) return;

        $currentMapping = $vend->productMapping;
        $upcomingMapping = $currentMapping?->upcomingProductMapping ?: $vend->upcomingProductMapping;

        if (!$upcomingMapping) return;

        $currentItems = $currentMapping ? $currentMapping->productMappingItems : collect();
        $upcomingItems = $upcomingMapping->productMappingItems;

        $opsJobItem->opsJobItemChannels()->where('is_upcoming_product', true)->delete();

        foreach ($upcomingItems as $uItem) {
            $cItem = $currentItems->where('channel_code', $uItem->channel_code)->first();

            if ($cItem && $cItem->product_id != $uItem->product_id) {
                // Product changed for this channel!
                // 1. Find the existing Ojic (Current slot)
                $ojic = $opsJobItem->opsJobItemChannels()
                    ->where('vend_channel_code', $uItem->channel_code) // Match physical slot code
                    ->where('is_upcoming_product', false)
                    ->first();

                if ($ojic) {
                    // Update current ojic: "To Pick Qty for current product isnt applicable anymore"
                    $ojic->update([
                        'picked_qty' => -$ojic->qty,
                        'saved_picked_qty' => -$ojic->qty,
                    ]);

                    // 2. Create New Ojic for Upcoming Product
                    $opsJobItem->opsJobItemChannels()->create([
                        'ops_job_id' => $opsJobItem->ops_job_id,
                        'ops_job_item_id' => $opsJobItem->id,
                        'vend_channel_id' => $ojic->vend_channel_id,
                        'vend_channel_code' => $ojic->vend_channel_code,
                        'vend_code' => $ojic->vend_code,
                        'product_id' => $uItem->product_id,
                        'capacity' => $ojic->capacity,
                        'qty' => 0, // NEW product, machine is empty for it
                        'picked_qty' => 5, // "default chosen 'To Pick Qty' = 5"
                        'saved_picked_qty' => 5,
                        'is_upcoming_product' => true,
                        'amount' => $ojic->amount, // Copy price
                    ]);
                }
            }
        }

        // Handle removed channels (cleared off without any upcoming mapping)
        foreach ($currentItems as $cItem) {
            $uItem = $upcomingItems->where('channel_code', $cItem->channel_code)->first();

            if (!$uItem) {
                // Product changed for this channel! (Completely removed)
                $ojic = $opsJobItem->opsJobItemChannels()
                    ->where('vend_channel_code', $cItem->channel_code)
                    ->where('is_upcoming_product', false)
                    ->first();

                if ($ojic) {
                    // Update current ojic: to be cleared off
                    $ojic->update([
                        'picked_qty' => -$ojic->qty,
                        'saved_picked_qty' => -$ojic->qty,
                    ]);
                }
            }
        }
    }

    private function applyReturnStockToItem($opsJobItem)
    {
        // For return stock: no picking needed (0 from warehouse),
        // the stock-in (refill) will be negative of current qty (handled on frontend)
        $opsJobItem->opsJobItemChannels()
            ->where('is_upcoming_product', false)
            ->each(function ($ojic) {
                $ojic->update([
                    'picked_qty' => 0,
                    'saved_picked_qty' => 0,
                ]);
            });
    }

    public function updateItemRemarks(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        $opsJobItem->update([
            'remarks' => $request->remarks,
            'remarks_updated_at' => Carbon::now(),
            'remarks_updated_by' => auth()->id(),
        ]);

        return redirect()->back();
    }

    public function uploadItemAttachments(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/ops-job-items';
            $storedPath = $files->storePublicly('sys/ops-job-items');
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $opsJobItem->attachments()->create([
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }

    public function verifyItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        switch ($request->verify) {
            case 0:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_FLAGGED,
                    'flagged_by' => auth()->id(),
                    'flagged_at' => Carbon::now(),
                    'undo_flagged_at' => null,
                    'undo_flagged_by' => null,
                ]);
                break;
            case 1:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_VERIFIED,
                    'verified_by' => auth()->id(),
                    'verified_at' => Carbon::now(),
                    'undo_verified_at' => null,
                    'undo_verified_by' => null,
                ]);
                break;
        }

        return redirect()->back();
    }

    private function createOpsJobItem($opsJobID, $vendID)
    {
        $vend = Vend::with('vendChannels')->find($vendID);
        $opsJob = OpsJob::find($opsJobID);

        $hasAnyUndoneOpsJobItem = OpsJobItem::query()
            ->where('vend_id', $vendID)
            ->where('ops_job_id', $opsJobID)
            ->where('status', '<', OpsJob::STATUS_DELIVERED)
            ->exists();

        if (!$hasAnyUndoneOpsJobItem) {
            $opsJobItem = OpsJobItem::create([
                'customer_id' => $vend->customer_id,
                'ops_job_id' => $opsJobID,
                'vend_id' => $vendID,
                'status' => '1',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($vend->vendChannels as $vendChannel) {
                // dd($vendChannel->toArray());
                $opsJobItem->opsJobItemChannels()->create([
                    'amount' => $vendChannel->amount,
                    'ops_job_id' => $opsJobItem->ops_job_id,
                    'product_id' => $vendChannel->product_id ?? 0,
                    'vend_channel_code' => $vendChannel->code,
                    'vend_channel_id' => $vendChannel->id,
                    'vend_code' => $vend->code,
                    'actual_qty' => 0,
                    'capacity' => $vendChannel->capacity,
                    'picked_qty' => 0,
                ]);
            }
        }
        // sync next invoice date and next invoice driver
        // temporary disable to record ops job item without interffere with cms
        // $vend->customer->update([
        //     'next_invoice_date' => $opsJobItem->opsJob->date,
        //     'next_invoice_driver_id' => $opsJobItem->opsJob->delivered_by,
        // ]);
    }

    private function generateUniqueOpsJobCode(string $errorField): string
    {
        $operatorId = auth()->user()->operator_id;
        $candidateCode = (int) $this->runningNumberService->getRunningCode(new OpsJob(), $operatorId);
        $attempts = 0;

        while (OpsJob::where('code', (string) $candidateCode)->exists()) {
            $candidateCode++;
            $attempts++;

            if ($attempts >= 50) {
                throw ValidationException::withMessages([
                    $errorField => 'Unable to generate a unique job code. Please try again.',
                ]);
            }
        }

        return (string) $candidateCode;
    }
}