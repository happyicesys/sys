<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\OpsJobResource;
use App\Http\Resources\OpsJobItemResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Jobs\SyncOpsJobTransactionCMS;
use App\Jobs\SyncOpsJobItemTransactionItemCMS;
use App\Models\Address;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
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
    protected $runningNumberService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->mapService = new MapService();
        $this->opsJobService = new OpsJobService();
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
                        Operator::where('code', 'DCVIC')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'IP')->first()?->id,
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
                    SUM(CASE WHEN cms_transaction_id IS NOT NULL THEN 1 ELSE 0 END) as cms_transaction_count
                ', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED, OpsJob::STATUS_VERIFIED])
                ->groupBy('ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 2. Channel Stats Aggregation (Filtered by IDs)
            $channelStats = DB::table('ops_job_item_channels as ojic')
                ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
                ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                ->leftJoin('unit_costs as uc', function ($join) {
                    $join->on('p.id', '=', 'uc.product_id')
                        ->where('uc.is_current', '=', true);
                })
                ->whereIn('oji.ops_job_id', $opsJobIds)
                ->selectRaw('
                    oji.ops_job_id,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * vc.amount ELSE 0 END) as picked_amount,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty ELSE 0 END) as picked_count,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * COALESCE(uc.cost, 0) ELSE 0 END) as picked_cost,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * vc.amount ELSE 0 END) as stock_in_amount,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty ELSE 0 END) as stock_in_count,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * COALESCE(uc.cost, 0) ELSE 0 END) as stock_in_cost
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
                ->groupBy('oji.ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 3. Merge Data
            foreach ($opsJobs as $job) {
                $iStat = $itemStats->get($job->id);
                $cStat = $channelStats->get($job->id);

                $job->ops_job_items_count = $iStat?->ops_job_items_count ?? 0;
                $job->ops_job_items_delivered_count = $iStat?->ops_job_items_delivered_count ?? 0;
                $job->ops_job_items_picked_count = $iStat?->ops_job_items_picked_count ?? 0;
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

                $job->picked_amount = $cStat?->picked_amount ?? 0;
                $job->picked_count = $cStat?->picked_count ?? 0;
                $job->picked_cost = $cStat?->picked_cost ?? 0;
                $job->stock_in_amount = $cStat?->stock_in_amount ?? 0;
                $job->stock_in_count = $cStat?->stock_in_count ?? 0;
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
                $job->cms_transaction_percentage = $job->ops_job_items_delivered_count > 0
                    ? ($job->cms_transaction_count / $job->ops_job_items_delivered_count) * 100
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
                        Operator::where('code', 'DCVIC')->first()?->id,
                        Operator::where('code', 'HIESG')->first()?->id,
                        Operator::where('code', 'IP')->first()?->id,
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
                    SUM(CASE WHEN cms_transaction_id IS NOT NULL THEN 1 ELSE 0 END) as cms_transaction_count
                ', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED, OpsJob::STATUS_VERIFIED])
                ->groupBy('ops_job_id')
                ->get()
                ->keyBy('ops_job_id');

            // 2. Channel Stats Aggregation
            $channelStats = DB::table('ops_job_item_channels as ojic')
                ->join('ops_job_items as oji', 'ojic.ops_job_item_id', '=', 'oji.id')
                ->join('vend_channels as vc', 'ojic.vend_channel_id', '=', 'vc.id')
                ->leftJoin('products as p', 'vc.product_id', '=', 'p.id')
                ->leftJoin('unit_costs as uc', function ($join) {
                    $join->on('p.id', '=', 'uc.product_id')
                        ->where('uc.is_current', '=', true);
                })
                ->whereIn('oji.ops_job_id', $opsJobIds)
                ->selectRaw('
                    oji.ops_job_id,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * vc.amount ELSE 0 END) as picked_amount,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty ELSE 0 END) as picked_count,
                    SUM(CASE WHEN oji.status >= ? THEN ojic.picked_qty * COALESCE(uc.cost, 0) ELSE 0 END) as picked_cost,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * vc.amount ELSE 0 END) as stock_in_amount,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty ELSE 0 END) as stock_in_count,
                    SUM(CASE WHEN oji.status >= ? AND oji.status <> ? THEN ojic.actual_qty * COALESCE(uc.cost, 0) ELSE 0 END) as stock_in_cost
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
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_PICKED,
                    'picked_by' => auth()->id(),
                    'picked_at' => Carbon::now(),
                    'undo_picked_at' => null,
                    'undo_picked_by' => null,
                ]);

                if ($request->channels) {
                    foreach ($request->channels as $channel) {
                        $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                        $opsJobItemChannel->update([
                            'picked_before_qty' => $channel['qty'],
                            'picked_qty' => $channel['picked'],
                            'qty' => $channel['qty'],
                            'saved_picked_qty' => $channel['picked'],
                        ]);

                        if ($channel['picked'] > 0) {
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

    public function saveItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        if ($request->channels) {
            // dd($request->channels);
            foreach ($request->channels as $channel) {

                $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                if ($opsJobItemChannel) {
                    $opsJobItemChannel->update([
                        'saved_picked_qty' => $channel['picked'],
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
                    CAST(
                        SUM(
                            CASE
                                WHEN ops_job_items.status = " . OpsJob::STATUS_PICKED . " THEN ops_job_item_channels.picked_qty
                                WHEN ops_job_items.status >= " . OpsJob::STATUS_DELIVERED . " THEN ops_job_item_channels.actual_qty
                                ELSE 0
                            END
                        ) AS UNSIGNED
                    ) as topup_qty
                ")
            )
            ->groupBy('vend_channels.product_id')
            ->orderBy('products.code')
            ->having('topup_qty', '>', 0)
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
                        'completed_at',
                        'completed_by',
                        'remarks',
                        'remarks_updated_at',
                        'remarks_updated_by',
                        'temp_cash_amount_from_vmc',
                        'vend_channel_record_id',
                        'verified_at',
                        'verified_by',
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

                    $query->selectRaw('(
                    SELECT SUM(oj_items.acc_total_amount)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                    ) as acc_vend_transactions_amount');

                    $query->selectRaw('(
                    SELECT SUM(oj_items.acc_total_count)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                    ) as acc_vend_transactions_count');

                    $query->selectRaw('(
                        SELECT SUM(oj_items.acc_total_cash_amount)
                        FROM ops_job_items oj_items
                        WHERE oj_items.id = ops_job_items.id
                    ) as acc_vend_transactions_cash_amount');

                    $query->selectRaw('(
                    SELECT SUM(oj_items.cash_amount)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                    ) as total_cash_amount');

                    // $query->selectRaw('(
                    //     SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(vend_channel_records.before_statis_json, "$.CashAmt")) AS DECIMAL(10, 2)))
                    //     FROM ops_job_items oj_items
                    //     JOIN vend_channel_records ON vend_channel_records.id = oj_items.vend_channel_record_id
                    //     WHERE oj_items.id = ops_job_items.id
                    // ) as total_cash_amount_from_vmc');
                    $query->selectRaw('(
                    SELECT SUM(oj_items.temp_cash_amount_from_vmc)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                ) as total_cash_amount_from_vmc');

                    $query->selectRaw('(
                    SELECT SUM(oj_items.cash_amount) - SUM(oj_items.temp_cash_amount_from_vmc)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                ) as delta_cash_amount');

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
                'opsJobItems.vend:id,customer_id,code,vend_prefix_id',
                'opsJobItems.cmsTransactionBy',
                'opsJobItems.customer.deliveryAddress',
                'opsJobItems.opsJobItemChannels.vendChannel.product.thumbnail',
                'opsJobItems.remarksUpdatedBy:id,name',
                'opsJobItems.vend.vendPrefix',
                'opsJobItems.pickedBy:id,name',
                'opsJobItems.previousOpsJobItem',
                'opsJobItems.statusBy',
                'opsJobItems.completedBy:id,name',
                'opsJobItems.vendChannelRecord',
                'opsJobItems.verifiedBy',
                'updatedBy:id,name'
            ])
            ->findOrFail($id);

        $unbindedVendOptions = Vend::query()
            ->select(['id', 'customer_id', 'operator_id', 'code']) // Select necessary columns
            ->with(['customer:id,name']) // Select necessary columns
            ->has('customer')
            ->whereDoesntHave('opsJobItems', function ($query) use ($opsJob) {
                $query->where('ops_job_id', $opsJob->id);
            })
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
                'vend:id,customer_id,code,vend_prefix_id',
                'vend.productMapping',
                'cmsTransactionBy',
                'createdBy',
                'customer.deliveryAddress',
                'vend.vendPrefix',
                'opsJob',
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

        $opsJobItems = collect($request->opsJobItems);
        $ids = $opsJobItems->pluck('id')->toArray(); // Extract the ids in the provided order

        $opsJob->opsJobItems()
            ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')') // Use FIELD to order by the provided id sequence
            ->get()
            ->each(function ($opsJobItem, $index) {
                $opsJobItem->update([
                    'sequence' => $index + 1,
                ]);
            });

        // $sequence = 1;
        // foreach($opsJob->opsJobItems as $opsJobItem) {
        //     $opsJobItem->update([
        //         'sequence' => $sequence,
        //     ]);
        //     $sequence++;
        // }

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
                'opsJobItems.vend.vendPrefix'
            ])
            ->find($id);

        $opsJobAddresses = $opsJob->opsJobItems->pluck('customer.deliveryAddress')->filter()->unique('id');

        return Inertia::render('OpsJob/Route', [
            'destinationAddresses' => AddressResource::collection(
                Address::where('type', '100')
                    ->latest()
                    ->get()
                // ->merge($opsJobAddresses)
                // ->unique('id')
            ),
            'originAddresses' => AddressResource::collection(
                Address::where('type', '90')
                    ->latest()
                    ->get()
                // ->merge($opsJobAddresses)
                // ->unique('id')
            ),
            'mapApiKey' => $this->mapService->getMapApiKeyByUser(auth()->user()),
            'opsJob' => OpsJobResource::make($opsJob),
        ]);
    }

    public function saveSequence(Request $request, $id)
    {
        $opsJob = OpsJob::findOrFail($id);

        foreach ($request->opsJobItems as $opsJobItemRequest) {
            if (isset($opsJobItemRequest['isOpsJobItem']) and $opsJobItemRequest['isOpsJobItem'] == false) {
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

        if ($opsJobItem->cms_transaction_id) {
            $this->opsJobService->deleteJobItemCMSTransaction($id);
        }

        if ($opsJobItem->opsJobItemChannels) {
            $opsJobItem->opsJobItemChannels()->delete();
        }

        $opsJobItem->delete();

        return redirect()->back();
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
                $opsJobItem->save();

                // ✅ Restore saved `picked_before_qty` to `qty`
                foreach ($opsJobItem->opsJobItemChannels as $channel) {
                    $channel->update([
                        'qty' => $channel->picked_before_qty,
                        'picked_qty' => 0, // optionally reset picked_qty too
                    ]);

                    if ($channel->saved_picked_qty > 0) {
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

    public function undoItemCashCollected(Request $request, $opsJobItemID)
    {
        $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

        $opsJobItem->update([
            'is_cash_collected' => false,
        ]);

        return redirect()->back();
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
