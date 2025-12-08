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
use Inertia\Inertia;

class ProductMovementController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
        $this->middleware(['permission:read products']);
    }

    public function index(Request $request)
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
                $request->merge(['operators' => auth()->user()->operator_id]);
            }
        }

        $request->merge([
            'productAvailableDate' => $request->productAvailableDate ? $request->productAvailableDate : Carbon::today()->addDay()->toDateString(),
        ]);

        $products = Product::query()
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
            ->orderBy('code')
            // Calculate needed_qty (same as existing)
            ->selectSub(function ($sub) use ($request) {
                $sub->from('ops_job_item_channels')
                    ->selectRaw('SUM(vend_channels.capacity - vend_channels.qty)')
                    ->leftJoin('ops_jobs', 'ops_jobs.id', '=', 'ops_job_item_channels.ops_job_id')
                    ->leftJoin('vend_channels', 'vend_channels.id', '=', 'ops_job_item_channels.vend_channel_id')
                    ->whereColumn('ops_job_item_channels.product_id', 'products.id')
                    ->whereDate('ops_jobs.date', $request->productAvailableDate)
                    ->whereDate('ops_jobs.date', '>=', Carbon::today()->toDateString());
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
                    ->whereColumn('product_movements.product_id', 'products.id');
            }, 'total_movements_qty')
            // Sum of OpsJob Delivered Qty (Out)
            ->selectSub(function ($sub) {
                $sub->from('ops_job_item_channels')
                    ->selectRaw('COALESCE(SUM(ops_job_item_channels.picked_qty), 0)')
                    ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
                    ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
                    ->whereColumn('ops_job_item_channels.product_id', 'products.id')
                    ->where('ops_job_items.status', '>=', 3) // OpsJob::STATUS_DELIVERED
                    ->where('ops_job_items.status', '!=', 99) // OpsJob::STATUS_CANCELLED
                    ->whereDate('ops_jobs.date', '>=', '2025-12-06');
            }, 'total_delivered_qty')
            ->get();

        foreach ($products as $product) {
            $product->calculated_warehouse_qty = $product->total_movements_qty - $product->total_delivered_qty;
            // Also need to get needed_qty (from ops_job_item_channels for status picking etc? or just needed?)
            // The existig logic for 'needed_needed' is OpsJobItemChannels capacity - qty for future dates.
            // warehouse Qty from API is missing here, replaced by calculated.
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
        ]);

        ProductMovement::create([
            'product_id' => $request->product_id,
            'qty' => $request->qty,
            'type' => $request->type,
            'operator_id' => auth()->user()->operator_id,
            'remarks' => $request->remarks,
        ]);

        return redirect()->back();
    }
}
