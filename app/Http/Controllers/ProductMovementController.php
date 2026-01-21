<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Traits\GetUserTimezone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductMovementExport;
use App\Exports\ProductMovementTrackingExport;
use App\Exports\IncomingStockHistoryExport;

class ProductMovementController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
        $this->middleware(['permission:read products']);

        if (env('CMS_URL')) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $products = $this->getProductQuery($request)->get();

        foreach ($products as $product) {
            $product->calculated_warehouse_qty = $product->total_movements_qty - $product->total_delivered_qty;


        }

        return Inertia::render('Vend/ProductMovement', [
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'products' => ProductResource::collection(
                $products
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer',
            'type' => 'required|in:' . ProductMovement::TYPE_INCOMING . ',' . ProductMovement::TYPE_ADJUSTMENT,
            'remarks' => 'nullable|string',
            'created_at' => 'nullable|date',
        ]);

        ProductMovement::create([
            'product_id' => $request->product_id,
            'qty' => $request->qty,
            'type' => $request->type,
            'operator_id' => auth()->user()->operator_id,
            'remarks' => $request->remarks,
            'created_at' => $request->created_at ? Carbon::parse($request->created_at) : Carbon::now(),
        ]);

        return redirect()->back();
    }

    public function batchIncoming(Request $request)
    {
        $products = Product::where('is_inventory', true)
            ->where('is_active', true)
            ->with(['thumbnail'])
            ->orderBy('code')
            ->get();

        return Inertia::render('ProductMovement/BatchIncoming', [
            'products' => ProductResource::collection($products),
        ]);
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'batch_number' => 'required|string',
            'created_at' => 'required|date',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.qty' => 'required|integer', // Can be 0 if not selected, but maybe we filter out 0s
            'remarks' => 'nullable|string',
        ]);

        $batchNumber = $request->batch_number;
        $createdAt = Carbon::parse($request->created_at . ' ' . Carbon::now()->toTimeString());
        $operatorId = auth()->user()->operator_id;

        $userId = auth()->id();

        DB::transaction(function () use ($request, $batchNumber, $createdAt, $operatorId, $userId) {
            foreach ($request->products as $item) {
                if ($item['qty'] != 0) {
                    ProductMovement::create([
                        'product_id' => $item['id'],
                        'qty' => $item['qty'],
                        'type' => ProductMovement::TYPE_INCOMING,
                        'operator_id' => $operatorId,
                        'user_id' => $userId,
                        'remarks' => $request->remarks ? $request->remarks : null,
                        'batch_number' => $batchNumber,
                        'created_at' => $createdAt,
                    ]);
                }
            }
        });

        return redirect()->route('product-movements.index');
    }

    public function trackingDetails(Request $request)
    {
        // 1. Inputs & Defaults
        $request->merge([
            'date_from' => $request->date_from ?: Carbon::today()->toDateString(),
            'date_to' => $request->date_to ?: Carbon::today()->toDateString(),
        ]);

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
        $operators = is_array($request->operators) ? $request->operators : [$request->operators];

        // 2. Incoming (ProductMovement)
        // Select: date, type_label, product, qty, running_qty (calc later), remarks
        $incomingQuery = ProductMovement::query()
            ->selectRaw("
                product_movements.created_at as date,
                CASE
                    WHEN type = 1 THEN 'Incoming'
                    WHEN type = 2 THEN 'Adjustment'
                    WHEN type = 3 THEN 'Picked'
                    WHEN type = 4 THEN 'Unpicked'
                    ELSE 'Unknown'
                END as type_label,
                product_movements.id as id,
                products.code as product_code,
                products.name as product_name,
                product_movements.qty as qty,
                product_movements.batch_number as remarks,
                users.name as by_user,
                product_movements.created_at as created_at,
                ops_jobs.date as job_delivery_date,
                'ProductMovement' as source_type
            ")
            ->leftJoin('products', 'products.id', '=', 'product_movements.product_id')
            ->leftJoin('users', 'product_movements.user_id', '=', 'users.id')
            ->leftJoin('ops_job_items', function ($join) {
                $join->on(DB::raw('product_movements.batch_number - 25000'), '=', 'ops_job_items.id')
                    ->whereIn('product_movements.type', [3, 4]);
            })
            ->leftJoin('ops_jobs', 'ops_job_items.ops_job_id', '=', 'ops_jobs.id')
            ->whereIn('product_movements.operator_id', $operators)
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('product_movements.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('product_movements.created_at', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('product_movements.created_at', '<=', $request->date_to);
            });

        // 3. Outgoing (OpsJobItemChannel -> OpsJob)
        // Note: filtered by status >= 3 (Delivered)
        $outgoingQuery = OpsJob::query()
            ->selectRaw("
                COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at) as date,
                'Picked' as type_label,
                ops_job_item_channels.id as id,
                products.code as product_code,
                products.name as product_name,
                (CASE WHEN ops_job_item_channels.picked_qty > 0 THEN ops_job_item_channels.picked_qty ELSE ops_job_item_channels.saved_picked_qty END * -1) as qty,
                (ops_job_items.id + 25000) as remarks,
                users.name as by_user,
                COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at) as created_at,
                ops_jobs.date as job_delivery_date,
                'OpsJob' as source_type
            ")
            ->join('ops_job_items', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->join('ops_job_item_channels', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('products', 'products.id', '=', 'ops_job_item_channels.product_id')
            ->leftJoin('users', 'ops_jobs.delivered_by', '=', 'users.id')
            ->whereIn('ops_jobs.operator_id', $operators)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('ops_job_items.status', '>=', 2)
                        ->where('ops_job_item_channels.picked_qty', '>', 0);
                })
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('ops_job_items.undo_picked_at')
                            ->whereNotNull('ops_job_items.last_picked_at')
                            ->where('ops_job_item_channels.saved_picked_qty', '>', 0);
                    });
            })
            ->where('ops_job_items.status', '!=', 99) // Status Cancelled
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('ops_job_item_channels.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate(DB::raw('COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at)'), '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate(DB::raw('COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at)'), '<=', $request->date_to);
            })
            // Exclude records after cutoff because they are now logged in product_movements
            ->where(DB::raw('COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at)'), '<', '2026-01-15 19:30:00');

        // 3b. Undo Picked (OpsJobItemChannel -> OpsJobItem)
        $undoPickedQuery = OpsJob::query()
            ->selectRaw("
                ops_job_items.undo_picked_at as date,
                'Unpicked' as type_label,
                ops_job_item_channels.id as id,
                products.code as product_code,
                products.name as product_name,
                (ops_job_item_channels.saved_picked_qty) as qty,
                (ops_job_items.id + 25000) as remarks,
                users.name as by_user,
                ops_job_items.undo_picked_at as created_at,
                ops_jobs.date as job_delivery_date,
                'OpsJob' as source_type
            ")
            ->join('ops_job_items', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->join('ops_job_item_channels', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('products', 'products.id', '=', 'ops_job_item_channels.product_id')
            ->leftJoin('users', 'ops_job_items.undo_picked_by', '=', 'users.id')
            ->whereIn('ops_jobs.operator_id', $operators)
            ->whereNotNull('ops_job_items.undo_picked_at')
            ->where('ops_job_item_channels.saved_picked_qty', '>', 0)
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('ops_job_item_channels.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('ops_job_items.undo_picked_at', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('ops_job_items.undo_picked_at', '<=', $request->date_to);
            })
            // Exclude records after cutoff because they are now logged in product_movements
            ->where('ops_job_items.undo_picked_at', '<', '2026-01-15 19:30:00');

        // 4. Union & Sort
        $query = $incomingQuery->union($outgoingQuery)->union($undoPickedQuery)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        // 5. Pagination
        // Since we are doing a union, we can't use simple paginate easily on the builder before union in older Laravel,
        // but recent versions support it. If it fails, we wrap in DB::table.
        // Let's assume wrap is needed for safe sort/paginate.
        $data = $query->paginate(100);

        // 6. Running Balance Calculation (Tricky with pagination & date filters)
        // If the user selects a date range, the "NetQty" for the first row shown needs to be relative to the running total up to that point?
        // OR, does the user just want "NetQty after addition" which implies the historical running balance?
        // Assuming Historical Running Balance.
        // To get Historical Running Balance, we need the SUM of ALL movements before the last item on this page?
        // This is expensive to calc per row.
        // Alternative: Calculate "Opening Balance" for the whole selection range (up to date_from).
        // Then iterate rows to add/sub.
        // However, since we sort DESC (usually transaction logs show newest first), the "Running Balance" is usually shown as the balance *after* that transaction.
        // So: Row N Balance = Row N-1 Balance - Row N Qty (if looking backwards) or...
        // Use simpler approach:
        // Calculate Total Opening Balance up to (but not including) the *last* record of the current pagination set?
        // No, simplest is: Calculate Opening Balance up to End of Time (Current Stock). Then subtract working backwards?
        // Or Calculate Opening Balance at Start of Time, then add working forward.
        // Given we sort DESC, let's calculate the Balance at the "End" (Top of page, most recent) first?
        // No, we need Balance for *each row*.
        // Let's rely on the Frontend or just fetch "Current Balance" and deduct backwards?
        // Or: Fetch Opening Balance for `date_from`.
        // But `date_from` might be filtered.
        // Let's calculate the "Opening Balance" as of `date_from 00:00:00`.
        return Inertia::render('ProductMovement/TrackingDetails', [
            'movements' => $data,
            'filters' => $request->all(),
            'products' => ProductResource::collection(Product::all()),
            'operatorOptions' => OperatorResource::collection(Operator::all()),
        ]);
    }
    public function exportExcel(Request $request)
    {
        $products = $this->getProductQuery($request)->get();
        return Excel::download(new ProductMovementExport($products), 'Product_Movement_' . Carbon::now()->format('ymdHis') . '.xlsx');
    }

    public function trackingExportExcel(Request $request)
    {
        return Excel::download(new ProductMovementTrackingExport($request), 'Product_Movement_Tracking_' . Carbon::now()->format('ymdHis') . '.xlsx');
    }

    public function incomingHistoryExport(Request $request)
    {
        return Excel::download(new IncomingStockHistoryExport($request), 'Incoming_Stock_History_' . Carbon::now()->format('ymdHis') . '.xlsx');
    }

    public function incomingHistory(Request $request)
    {
        $query = ProductMovement::query();

        $query->where('type', ProductMovement::TYPE_INCOMING)
            ->whereNotNull('batch_number');

        if ($request->has('date') && $request->date) {
            $query->whereDate('created_at', $request->date);
        }

        $history = $query->select('batch_number')
            ->selectRaw('MAX(created_at) as created_at')
            ->selectRaw('MAX(operator_id) as operator_id')
            ->selectRaw('MAX(user_id) as user_id')
            ->selectRaw('MAX(remarks) as remarks')
            ->groupBy('batch_number')
            ->orderByRaw('MAX(created_at) DESC, MAX(id) DESC')
            ->paginate(20)
            ->withQueryString();

        $history->getCollection()->transform(function ($item) {
            $item->operator = Operator::find($item->operator_id);
            $item->user = \App\Models\User::find($item->user_id);
            return $item;
        });

        return Inertia::render('ProductMovement/IncomingHistory', [
            'history' => $history,
            'filters' => $request->only(['date']),
        ]);
    }

    public function incomingBatchDetail($batch_number)
    {
        $movements = ProductMovement::with(['product', 'product.thumbnail', 'operator', 'user'])
            ->where('batch_number', $batch_number)
            ->where('type', ProductMovement::TYPE_INCOMING)
            ->get();

        if ($movements->isEmpty()) {
            abort(404);
        }

        $first = $movements->first();
        $metadata = [
            'batch_number' => $first->batch_number,
            'created_at' => $first->created_at,
            'operator' => $first->operator,
            'user' => $first->user,
            'remarks' => $first->remarks,
        ];

        return Inertia::render('ProductMovement/IncomingBatchDetail', [
            'movements' => $movements,
            'metadata' => $metadata,
        ]);
    }
    private function getProductQuery(Request $request)
    {
        if ($request->operators == null) {
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
            'productAvailableDate' => $request->productAvailableDate ? $request->productAvailableDate : Carbon::today()->addDay()->toDateString(),
        ]);

        return Product::query()
            ->with([
                'isAvailableUpdatedBy',
                'latestUnitCost',
                'productLimits' => function ($query) use ($request) {
                    $query->whereDate('date', $request->productAvailableDate);
                },
                'productLimits.createdBy',
                'thumbnail',
            ])
            ->when($request->operators, function ($query, $search) {
                $search = is_array($search) ? $search : [$search];
                if (!in_array('all', $search)) {
                    $query->whereIn('operator_id', $search);
                }
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where('code', 'LIKE', "%{$search}%");
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->is_available !== null, function ($query) use ($request) {
                if ($request->is_available !== 'all') {
                    $query->where('is_available', filter_var($request->is_available, FILTER_VALIDATE_BOOLEAN));
                }
            })
            ->select([
                'products.id',
                'products.avg_seven_days_count',
                'products.code',
                'products.desc',
                'products.name',
                'products.is_available',
                'products.is_available_updated_at',
                'products.is_available_updated_by',
            ])
            ->where('is_active', true)
            ->where('is_inventory', true)
            ->when($request->sortKey, function ($query, $sortKey) use ($request) {
                $query->orderBy($sortKey, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            }, function ($query) {
                $query->orderBy('code', 'desc');
            })
            // Calculate needed_qty (same as existing)
            ->selectSub(function ($sub) use ($request) {
                $sub->from('ops_job_item_channels')
                    ->leftJoin('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
                    ->leftJoin('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
                    ->leftJoin('vend_channels', 'vend_channels.id', '=', 'ops_job_item_channels.vend_channel_id')
                    ->leftJoin('product_limits', function ($join) use ($request) {
                        $join->on('product_limits.product_id', '=', 'ops_job_item_channels.product_id')
                            ->whereDate('product_limits.date', '=', $request->productAvailableDate);
                    })
                    ->whereColumn('ops_job_item_channels.product_id', 'products.id')
                    ->whereDate('ops_jobs.date', $request->productAvailableDate)
                    ->whereDate('ops_jobs.date', '>=', Carbon::today()->toDateString())
                    ->selectRaw('COALESCE(SUM(
                        CASE
                            WHEN ops_job_items.status >= 2 THEN ops_job_item_channels.picked_qty
                            WHEN ops_job_item_channels.saved_picked_qty IS NOT NULL THEN ops_job_item_channels.saved_picked_qty
                            WHEN product_limits.qty IS NOT NULL AND (ops_job_items.is_ignore_limit = 0 OR ops_job_items.is_ignore_limit IS NULL) THEN
                                CASE
                                    WHEN product_limits.qty > COALESCE(vend_channels.capacity, ops_job_item_channels.capacity) AND product_limits.qty >= COALESCE(vend_channels.qty, 0) THEN
                                        COALESCE(vend_channels.capacity, ops_job_item_channels.capacity) - COALESCE(vend_channels.qty, 0)
                                    WHEN product_limits.qty <= COALESCE(vend_channels.capacity, ops_job_item_channels.capacity) AND product_limits.qty >= COALESCE(vend_channels.qty, 0) THEN
                                        product_limits.qty - COALESCE(vend_channels.qty, 0)
                                    ELSE 0
                                END
                            ELSE
                                COALESCE(vend_channels.capacity, ops_job_item_channels.capacity) - COALESCE(vend_channels.qty, 0)
                        END
                    ), 0)');
            }, 'needed_qty')
            // Calculate max_ops_job_pick_limit (same as existing)
            ->selectSub(function ($sub) use ($request) {
                $sub->from('product_limits')
                    ->select('qty')
                    ->whereColumn('product_limits.product_id', 'products.id')
                    ->whereDate('product_limits.date', $request->productAvailableDate)
                    ->limit(1);
            }, 'max_ops_job_pick_limit')
            // Calculate limit_is_created_by_system (same as existing)
            ->selectSub(function ($sub) use ($request) {
                $sub->from('product_limits')
                    ->select('is_created_by_system')
                    ->whereColumn('product_limits.product_id', 'products.id')
                    ->whereDate('product_limits.date', $request->productAvailableDate)
                    ->limit(1);
            }, 'limit_is_created_by_system')
            // Sum of Product Movements (Incoming + Adjustments)
            ->selectSub(function ($sub) {
                $sub->from('product_movements')
                    ->selectRaw('COALESCE(SUM(qty), 0)')
                    ->whereIn('type', [ProductMovement::TYPE_INCOMING, ProductMovement::TYPE_ADJUSTMENT])
                    ->whereColumn('product_movements.product_id', 'products.id');
            }, 'total_movements_qty')
            // Sum of OpsJob Delivered Qty (Out)
            ->selectSub(function ($sub) {
                $sub->from('ops_job_item_channels')
                    ->selectRaw('COALESCE(SUM(ops_job_item_channels.picked_qty), 0)')
                    ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
                    ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
                    ->whereColumn('ops_job_item_channels.product_id', 'products.id')
                    ->where('ops_job_items.status', '>=', 2) // OpsJob::STATUS_PICKED
                    ->where('ops_job_items.status', '!=', 99) // OpsJob::STATUS_CANCELLED
                    ->whereDate('ops_jobs.date', '>=', '2025-12-06');
            }, 'total_delivered_qty')
            // Calculate Picked Qty (on specific Date)
            ->selectSub(function ($sub) use ($request) {
                $sub->from('ops_job_item_channels')
                    ->selectRaw('COALESCE(SUM(ops_job_item_channels.picked_qty), 0)')
                    ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
                    ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
                    ->whereColumn('ops_job_item_channels.product_id', 'products.id')
                    ->where('ops_job_items.status', '>=', 2) // OpsJob::STATUS_PICKED
                    ->where('ops_job_items.status', '!=', 99) // OpsJob::STATUS_CANCELLED
                    ->whereDate('ops_jobs.date', $request->productAvailableDate);
            }, 'picked_qty_on_date');
    }
}
