<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UomResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\SellingPrice;
use App\Models\Tag;
use App\Models\UnitCost;
use App\Models\Uom;
use App\Models\OpsJobItemChannel;
use App\Traits\GetUserTimezone;
use App\Services\CmsService;
use App\Services\TagBindingService;
use App\Services\VendChannelService;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    use GetUserTimezone;

    protected $cmsService;
    protected $tagBindingService;
    protected $vendChannelService;
    protected $vendTransactionService;

    public function __construct()
    {
        $this->cmsService = new CmsService();
        $this->tagBindingService = new TagBindingService();
        $this->vendChannelService = new VendChannelService();
        $this->vendTransactionService = new VendTransactionService();
        $this->middleware(['permission:read products']);
    }

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $className = get_class(new Product());

        return Inertia::render('Product/Index', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'languageOptions' => config('language'),
            'measurementUnitOptions' => Product::MEASUREMENT_UNIT_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'priceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'products' => ProductResource::collection(
                Product::with([
                    'attachments',
                    'category',
                    'categoryGroup',
                    'latestUnitCost',
                    'operator',
                    'productUoms.uom',
                    'sellingPrices',
                    'tagBindings.tag',
                    'thumbnail',
                    'unitCosts' => function ($query) {
                        $query->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
                    },
                ])
                    ->filterIndex($request)
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'productTagOptions' => TagResource::collection(
                Tag::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'uoms' => UomResource::collection(
                Uom::query()
                    ->orderBy('sequence')
                    ->get()
            ),
        ]);
    }

    public function create(Request $request)
    {
        // categories: Object,
        // categoryGroups: Object,
        // languageOptions: [Array, Object],
        // measurementUnitOptions: Object,
        // priceTypeOptions: Object,
        // uoms: Object,
        // type: String,
        // operatorOptions: Object,
        // permissions: [Array, Object],
        // productTagOptions: Object,
        $className = get_class(new Product());

        return Inertia::render('Product/Create', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'languageOptions' => config('language'),
            'measurementUnitOptions' => Product::MEASUREMENT_UNIT_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'priceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'productTagOptions' => TagResource::collection(
                Tag::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'uoms' => UomResource::collection(
                Uom::query()
                    ->orderBy('sequence')
                    ->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
        ], [
            'operator_id.required' => 'Please choose the operator.',
        ]);

        $request->merge([
            'is_halal' => $request->is_halal ? true : false,
            'is_healthier_choice' => $request->is_healthier_choice ? true : false,
        ]);

        $product = new Product();
        $product = $product->fill($request->all());
        if (!$request->operator_id) {
            $product->operator_id = auth()->user()->operator_id;
        }
        if (!$request->measurement_count) {
            $product->measurement_count = 1;
        }
        $product->save();

        if ($request->hasFile('thumbnail')) {
            $request->validate([
                'thumbnail' => 'sometimes|image|max:10000',
            ]);
            $url = Storage::url($request->thumbnail->storePublicly('sys/products'));
            $product->thumbnail()->updateOrCreate([
                'type' => 1,
            ], [
                'full_url' => $url,
                'local_url' => $url,
            ]);
        }

        return redirect()->route('products.edit', ['id' => $product->id])->with('success', 'Product saved successfully');
    }

    public function availability(Request $request)
    {
        // dd($request->operators, $request->all());
        if ($request->operators == null) {
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
            'productAvailableDate' => $request->productAvailableDate ? $request->productAvailableDate : Carbon::today()->addDay()->toDateString(),
        ]);

        $userTimezone = $this->getUserTimezone();
        $productAvailableDateStart = Carbon::parse($request->productAvailableDate, $userTimezone)->startOfDay()->setTimezone('UTC');
        $productAvailableDateEnd = Carbon::parse($request->productAvailableDate, $userTimezone)->endOfDay()->setTimezone('UTC');

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
                // If sorting by calculated fields, keep simple sorts here and handle complex sorts if needed in JS or dedicated query
                if (!in_array($sortKey, ['needed_qty', 'needed_value', 'not_yet_sync_api_qty', 'picked_value_on_date'])) {
                    $query->orderBy($sortKey, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            }, function ($query) {
                $query->orderBy('code', 'desc');
            })
            ->get();

        $productIds = $products->pluck('id')->toArray();

        // 1. Calculate needed_qty and needed_value
        $neededData = OpsJobItemChannel::query()
            ->leftJoin('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->leftJoin('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->leftJoin('vend_channels', 'vend_channels.id', '=', 'ops_job_item_channels.vend_channel_id')
            ->leftJoin('product_limits', function ($join) use ($request) {
                $join->on('product_limits.product_id', '=', 'ops_job_item_channels.product_id')
                    ->whereDate('product_limits.date', '=', $request->productAvailableDate);
            })
            ->whereIn('ops_job_item_channels.product_id', $productIds)
            // Note: products.is_available = 1 check is implicit effectively since we only have IDs from the filtered product list
            // but strictly speaking we are aggregating channels for these products.
            // The original subquery filtered by products.is_available = 1. Since $productIds comes from queries with that check (or not), we are good.
            ->whereBetween('ops_jobs.date', [$productAvailableDateStart, $productAvailableDateEnd])
            ->whereDate('ops_jobs.date', '>=', Carbon::today()->toDateString())
            ->groupBy('ops_job_item_channels.product_id')
            ->selectRaw('ops_job_item_channels.product_id,
                COALESCE(SUM(
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
                            End
                        ELSE
                            COALESCE(vend_channels.capacity, ops_job_item_channels.capacity) - COALESCE(vend_channels.qty, 0)
                    END
                ), 0) as needed_qty,
                COALESCE(SUM(
                    (CASE
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
                    END) * vend_channels.amount
                ), 0) as needed_value
            ')
            ->get()
            ->keyBy('product_id');

        // 2. Calculate not_yet_sync_api_qty
        $notYetSyncData = OpsJobItemChannel::query()
            ->join('vend_channels', 'vend_channels.id', '=', 'ops_job_item_channels.vend_channel_id')
            ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->whereIn('ops_job_item_channels.product_id', $productIds)
            ->whereDate('ops_jobs.date', '>=', Carbon::today()->toDateString())
            ->whereNull('ops_job_items.cms_transaction_id')
            ->groupBy('ops_job_item_channels.product_id')
            ->selectRaw('ops_job_item_channels.product_id, SUM(ops_job_item_channels.picked_qty) as qty')
            ->get()
            ->keyBy('product_id');

        // 3. Calculate picked_value_on_date
        $pickedValueData = OpsJobItemChannel::query()
            ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->join('vend_channels', 'vend_channels.id', '=', 'ops_job_item_channels.vend_channel_id')
            ->whereIn('ops_job_item_channels.product_id', $productIds)
            ->where('ops_job_items.status', '>=', 2) // OpsJob::STATUS_PICKED
            ->where('ops_job_items.status', '!=', 99) // OpsJob::STATUS_CANCELLED
            ->whereDate('ops_jobs.date', $request->productAvailableDate)
            ->groupBy('ops_job_item_channels.product_id')
            ->selectRaw('ops_job_item_channels.product_id, COALESCE(SUM(ops_job_item_channels.picked_qty * vend_channels.amount), 0) as value')
            ->get()
            ->keyBy('product_id');


        $cmsQtyAvailableProducts = $this->cmsService->getCMSQtyAvailableApi();

        // Create a lookup map for CMS products for O(1) access
        $cmsQtyMap = [];
        if ($cmsQtyAvailableProducts) {
            foreach ($cmsQtyAvailableProducts as $item) {
                // Ensure 'code' key exists to avoid undefined index errors
                if (isset($item['code'])) {
                    $cmsQtyMap[$item['code']] = $item;
                }
            }
        }

        foreach ($products as $product) {
            // Map calculated values
            $product->needed_qty = $product->is_available ? ($neededData->get($product->id)?->needed_qty ?? 0) : 0;
            $product->needed_value = $product->is_available ? ($neededData->get($product->id)?->needed_value ?? 0) : 0;
            $product->not_yet_sync_api_qty = $notYetSyncData->get($product->id)?->qty ?? 0;
            $product->picked_value_on_date = $pickedValueData->get($product->id)?->value ?? 0;

            // Map Max Ops Job Pick Limit & Created By System from eager loaded relation
            $limit = $product->productLimits->first();
            $product->max_ops_job_pick_limit = $limit ? $limit->qty : null;
            $product->limit_is_created_by_system = $limit ? $limit->is_created_by_system : null;

            if (isset($cmsQtyMap[$product->code])) {
                $cmsQtyAvailableProduct = $cmsQtyMap[$product->code];
                $product->qty_available_pcs_api = $cmsQtyAvailableProduct['qty'] ?? 0;
                $product->net_available_qty_pcs_api = ($cmsQtyAvailableProduct['qty'] ?? 0) - $product->not_yet_sync_api_qty;
            } else {
                // Ensure defaults
                $product->qty_available_pcs_api = 0;
                $product->net_available_qty_pcs_api = 0 - $product->not_yet_sync_api_qty;
            }
        }

        return Inertia::render('Vend/ProductAvailability', [
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'products' => ProductResource::collection(
                $products
            ),
        ]);
    }

    public function update(Request $request, $productId)
    {
        // dd($request->all());
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
        ], [
            'operator_id.required' => 'Please choose the operator.',
        ]);

        $product = Product::findOrFail($productId);
        $product->fill($request->except(['is_available', 'is_available_updated_at']));
        $product->save();

        $this->tagBindingService->sync($product, Arr::wrap($request->tags));

        if ($request->hasFile('thumbnail')) {
            $request->validate([
                'thumbnail' => 'sometimes|image|max:500',
            ]);
            $url = Storage::url($request->thumbnail->storePublicly('sys/products'));
            $product->thumbnail()->updateOrCreate([
                'type' => 1,
            ], [
                'full_url' => $url,
                'local_url' => $url,
            ]);
        }

        if ($request->has('languages')) {
            $product->update([
                'translated_names_json' => $request->languages
            ]);
        }

        if ($request->has('sellingPrices')) {
            $sellingPrices = $request->sellingPrices;
            if ($sellingPrices) {
                foreach ($sellingPrices as $sellingPrice) {
                    if (!isset($sellingPrice['id'])) {
                        $product->sellingPrices()->create([
                            'amount' => $sellingPrice['amount'],
                            'type' => $sellingPrice['type'],
                        ]);
                    }
                }
            }
        }

        if ($request->has('unitCosts')) {
            $unitCosts = $request->unitCosts;
            if ($unitCosts) {
                foreach ($unitCosts as $unitCost) {
                    if (!isset($unitCost['id'])) {
                        if ($product->unitCosts()->exists()) {
                            $product->unitcosts()->update([
                                'is_current' => false,
                            ]);
                        }
                        $product->unitCosts()->create([
                            'cost' => $unitCost['cost'],
                            'date_from' => $unitCost['date_from'],
                        ]);

                        $currentUnitCost = $product->unitCosts()->whereDate('date_from', '<=', Carbon::today()->setTimezone($this->getUserTimezone())->toDateString())->latest('created_at')->first();
                        $currentUnitCost->is_current = true;
                        $currentUnitCost->save();
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Product updated successfully');
    }

    public function toggleActivateDeactivate($productId)
    {
        $product = Product::findOrFail($productId);
        $product->is_active = !$product->is_active;
        $product->save();

        $this->vendChannelService->syncAllVendChannelsJson($product->vendChannels->pluck('vend_id')->toArray());

        return redirect()->back();
    }

    public function toggleIsAvailable(Request $request)
    {
        $product = Product::findOrFail($request->product_id);
        $product->is_available = !$product->is_available;
        $product->is_available_updated_by = auth()->user()->id;
        $product->is_available_updated_at = Carbon::now();
        $product->save();

        $this->vendChannelService->syncAllVendChannelsJson($product->vendChannels->pluck('vend_id')->toArray());
    }

    public function bindUom(Request $request, $productId)
    {
        // dd($request->all());
        $product = Product::findOrFail($productId);

        if ($request->is_base_uom) {
            $product->productUoms()->update(['is_base_uom' => false]);
        }

        if ($request->is_transaction_uom) {
            $product->productUoms()->update(['is_transaction_uom' => false]);
        }

        $product->productUoms()->create($request->all());

        return redirect()->route('products');
    }

    public function delete($productId)
    {
        $product = Product::findOrFail($productId);
        $product->delete();

        return redirect()->route('products')->with('success', 'Product deleted successfully');
    }

    public function deleteProductUom($productUomId)
    {
        $productUom = ProductUom::findOrFail($productUomId);

        if ($productUom->is_base_uom) {
            $assignProductBaseUom = $productUom->product->productUoms()->where('value', 1)->first();
            if ($assignProductBaseUom) {
                $assignProductBaseUom->is_base_uom = true;
                $assignProductBaseUom->save();
            }
        }

        if ($productUom->is_transaction_uom) {
            $assignProductTransactionUom = $productUom->product->productUoms()->orderBy('value', 'desc')->first();
            if ($assignProductTransactionUom) {
                $assignProductTransactionUom->is_transaction_uom = true;
                $assignProductTransactionUom->save();
            }
        }

        $productUom->delete();

        return redirect()->back()->with('success', 'UOM deleted successfully');
    }

    public function deleteUnitCost($unitCostId)
    {
        $unitCost = UnitCost::findOrFail($unitCostId);
        $unitCost->delete();

        return redirect()->back()->with('success', 'Unit cost deleted successfully');
    }

    public function deleteSellingPrice($sellingPriceId)
    {
        $sellingPrice = SellingPrice::findOrFail($sellingPriceId);
        $sellingPrice->delete();

        return redirect()->back()->with('success', 'Selling price deleted successfully');
    }

    public function edit(Request $request, $productId)
    {
        $product = Product::with([
            'attachments',
            'category',
            'categoryGroup',
            'latestUnitCost',
            'operator',
            'productUoms.uom',
            'sellingPrices',
            'tagBindings.tag',
            'thumbnail',
            'unitCosts' => function ($query) {
                $query->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($productId);

        $className = get_class(new Product());

        return Inertia::render('Product/Edit', [
            'categories' => CategoryResource::collection(
                Category::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'languageOptions' => config('language'),
            'measurementUnitOptions' => Product::MEASUREMENT_UNIT_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(
                Operator::all()
            ),
            'priceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'productTagOptions' => TagResource::collection(
                Tag::where('classname', $className)
                    ->orderBy('name')
                    ->get()
            ),
            'uoms' => UomResource::collection(
                Uom::query()
                    ->orderBy('sequence')
                    ->get()
            ),
            'product' => ProductResource::make($product),
        ]);
    }

    public function updateMaxOpsJobPickLimit(Request $request, $productID)
    {
        $product = Product::findOrFail($productID);
        $startDateStr = $request->date;
        $newValue = $request->max_ops_job_pick_limit; // Can be a number or null ("No")

        // 1. Update the record for the specific date the user clicked
        if ($newValue === null) {
            $product->productLimits()
                ->where('date', $startDateStr)
                ->delete();
        } else {
            $product->productLimits()->updateOrCreate([
                'date' => $startDateStr,
            ], [
                'qty' => $newValue,
                'setup_date' => Carbon::now(),
                'is_created_by_system' => false,
                'created_by' => auth()->user()->id,
            ]);
        }

        // 2. Propagation: Forward seed to the next 5 days
        $startDate = Carbon::parse($startDateStr)->startOfDay();
        for ($i = 1; $i <= 5; $i++) {
            $targetDate = $startDate->copy()->addDays($i);
            $targetDateStr = $targetDate->toDateString();

            // Check if the target day already has a manual setting
            $targetLimit = $product->productLimits()
                ->where('date', $targetDateStr)
                ->first();

            // RESPECT future manual overrides (Green labels)
            if ($targetLimit && !$targetLimit->is_created_by_system) {
                // If we hit a manual override, STOP propagating this change further forward?
                // Or just skip this day? User said "propagate till before that day value being set".
                // Stop is usually the intent of "till before".
                break;
            }

            // Apply the new value (either Qty or No)
            if ($newValue === null) {
                // Propagate "No"
                $product->productLimits()
                    ->where('date', $targetDateStr)
                    ->delete();
            } else {
                // Propagate Quantity
                $product->productLimits()->updateOrCreate([
                    'date' => $targetDateStr,
                ], [
                    'qty' => $newValue,
                    'setup_date' => Carbon::now(),
                    'is_created_by_system' => true,
                    'created_by' => auth()->user()->id,
                ]);
            }
        }

        // 3. Sync JSON Cache (for Dashboard and mobile)
        $this->syncProductLimitJson($product);

        $this->vendChannelService->syncAllVendChannelsJson($product->vendChannels->pluck('vend_id')->toArray());

        return redirect()->back();
    }

    protected function syncProductLimitJson(Product $product)
    {
        // Fetch all current limits for this product and rebuild the JSON
        $limits = $product->productLimits()->orderBy('date')->get();
        $json = [];
        foreach ($limits as $limit) {
            $json[$limit->date->toDateString()] = $limit->qty;
        }
        $product->max_ops_job_pick_limit_json = $json;
        $product->save();
    }
}
