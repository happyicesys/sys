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
use App\Models\Uom;
use App\Traits\GetUserTimezone;
use App\Services\CmsService;
use App\Services\TagBindingService;
use App\Services\VendChannelService;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
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
            ->orderBy('code')
            ->selectSub(function ($sub) use ($request) {
                $sub->from('product_limits')
                    ->select('qty')
                    ->whereColumn('product_limits.product_id', 'products.id')
                    ->whereDate('product_limits.date', $request->productAvailableDate)
                    ->limit(1);
            }, 'max_ops_job_pick_limit')
            ->selectSub(function ($sub) use ($request) {
                $sub->from('product_limits')
                    ->select('is_created_by_system')
                    ->whereColumn('product_limits.product_id', 'products.id')
                    ->whereDate('product_limits.date', $request->productAvailableDate)
                    ->limit(1);
            }, 'limit_is_created_by_system')
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
            ->selectSub(function ($sub) {
                $sub->from('ops_job_item_channels')
                    ->selectRaw('SUM(ops_job_item_channels.picked_qty)')
                    ->join('vend_channels', 'vend_channels.id', '=', 'ops_job_item_channels.vend_channel_id')
                    ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
                    ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
                    ->whereColumn('ops_job_item_channels.product_id', 'products.id')
                    ->whereDate('ops_jobs.date', '>=', Carbon::today()->toDateString())
                    ->whereNull('ops_job_items.cms_transaction_id');
            }, 'not_yet_sync_api_qty')
            ->get();

        $cmsQtyAvailableProducts = $this->cmsService->getCMSQtyAvailableApi();

        foreach ($products as $product) {


            if ($cmsQtyAvailableProducts) {
                foreach ($cmsQtyAvailableProducts as $cmsQtyAvailableProduct) {
                    if ($product->code == $cmsQtyAvailableProduct['code']) {
                        $product->qty_available_pcs_api = $cmsQtyAvailableProduct['qty'];
                        $product->net_available_qty_pcs_api = $cmsQtyAvailableProduct['qty'] - $product->not_yet_sync_api_qty;
                    }
                }
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

        return redirect()->route('products');
    }

    public function toggleActivateDeactivate($productId)
    {
        $product = Product::findOrFail($productId);
        $product->is_active = !$product->is_active;
        $product->save();

        $this->vendChannelService->syncAllVendChannelsJson($product->vendChannels->pluck('vend_id')->toArray());

        return redirect()->route('products');
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

        return redirect()->route('products');
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

        return redirect()->route('products');
    }

    public function deleteSellingPrice($sellingPriceId)
    {
        $sellingPrice = SellingPrice::findOrFail($sellingPriceId);
        $sellingPrice->delete();

        return redirect()->route('products');
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

        if ($request->max_ops_job_pick_limit === null) {
            $product->productLimits()
                ->where('date', '>=', $request->date)
                ->delete();

            return redirect()->back();
        }
        // find the latest previous productlimit
        $previousProductLimit = $product->productLimits()
            ->where('date', '<', $request->date)
            ->latest('date')
            ->first();

        $product->productLimits()->updateOrCreate([
            'date' => $request->date,
        ], [
            'qty' => $request->max_ops_job_pick_limit,
            'setup_date' => Carbon::now(),
            'is_created_by_system' => false,
            'created_by' => auth()->user()->id,
        ]);

        // Find and update any future product limits for the product
        $product->productLimits()
            ->where('date', '>', $request->date)
            ->update([
                'qty' => $request->max_ops_job_pick_limit,
                'is_created_by_system' => true,
            ]);

        // Retrieve the current `max_ops_job_pick_limit_json` as an associative array
        $maxOpsJobPickLimitJson = $product->max_ops_job_pick_limit_json;

        // Check if new data is provided in the request, then add it to the array
        if ($request->date && $request->max_ops_job_pick_limit) {
            // Add or update the entry for the new date
            $maxOpsJobPickLimitJson[$request->date] = $request->max_ops_job_pick_limit;
        }

        // Encode the updated array back to JSON and save it
        $product->max_ops_job_pick_limit_json = $maxOpsJobPickLimitJson;
        $product->save();

        $this->vendChannelService->syncAllVendChannelsJson($product->vendChannels->pluck('vend_id')->toArray());

        return redirect()->back();
    }
}
