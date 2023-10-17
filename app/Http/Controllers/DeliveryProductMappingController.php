<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\ProductMappingItemResource;
use App\Http\Resources\VendResource;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Services\DeliveryPlatformService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryProductMappingController extends Controller
{
    protected $deliveryPlatformService;

    public function __construct(DeliveryPlatformService $deliveryPlatformService)
    {
        $this->deliveryPlatformService = $deliveryPlatformService;
    }

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('DeliveryPlatform/Index', [
            'deliveryProductMappings' => DeliveryProductMappingResource::collection(
                DeliveryProductMapping::query()
                    ->with([
                        'deliveryPlatformOperator.deliveryPlatform',
                        'deliveryProductMappingItems',
                        'deliveryProductMappingItems.product:id,code,name,is_active',
                        'deliveryProductMappingItems.product.thumbnail',
                        'operator:id,name',
                        'vends:id,code,name',
                        'vends.latestVendBinding.customer:id,code,name',
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vend_code, function($query, $search) use ($request) {
                        $query->whereHas('vends', function($query) use ($request) {
                            $query->where('code', 'LIKE', "{$request->vend_code}%");
                        });
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function bindVend($id, $vendId)
    {
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);
        $vend = Vend::findOrFail($vendId);

        $vend->deliveryProductMappings()->attach($deliveryProductMapping->id);
        $deliveryProductMapping->update([
            'vends_json' => $deliveryProductMapping->vends()->with('latestVendBinding.customer:id,code,name')->get(),
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }

    public function create(Request $request)
    {
        return Inertia::render('DeliveryPlatform/Create', [
            'categoryApiOptions' => Inertia::lazy(fn() =>[
                $this->deliveryPlatformService->getCategories(DeliveryPlatformOperator::find($request->delivery_platform_operator_id)),
            ]),
            'operatorOptions' => OperatorResource::collection(
                Operator::query()
                    ->with('deliveryPlatformOperators.deliveryPlatform')
                    ->orderBy('name')
                    ->get()
            ),
            'productMappingItems' => Inertia::lazy(fn() => [
                ProductMappingItemResource::collection(
                    ProductMappingItem::query()
                        ->with('product.thumbnail')
                        ->where('product_mapping_id', $request->product_mapping_id)
                        ->get()
                )
            ]),
            'productMappingOptions' => Inertia::lazy(fn() =>[
                ProductMappingResource::collection(
                    ProductMapping::query()
                        ->where('operator_id', $request->operator_id)
                        ->get()
                ),
            ]),
            'productOptions' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
            'type' => $request->id ? 'edit' : 'create',
        ]);
    }

    public function deleteDeliveryProductMappingItem($id)
    {
        $deliveryProductMappingItem = DeliveryProductMappingItem::findOrFail($id);
        $deliveryProductMappingId = $deliveryProductMappingItem->deliveryProductMapping->id;

        $deliveryProductMappingItem->delete();

        DeliveryProductMapping::findOrFail($deliveryProductMappingItem->deliveryProductMapping->id)->update([
            'delivery_product_mapping_items_json' =>
                DeliveryProductMapping::findOrFail($deliveryProductMappingId)
                ->deliveryProductMappingItems()
                ->with([
                    'product:id,code,name',
                    'product.thumbnail'
                ])
                ->select(
                    'id',
                    'amount',
                    'channel_code',
                    'product_id',
                )
                ->get()
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingId]);
    }

    public function storeDeliveryProductMappingItem(Request $request, $deliveryProductMappingId)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'channel_code' => 'required',
            'sub_category_json' => 'required',
            'product_id' => 'required',
        ], [
            'amount.required' => 'Amount is required',
            'amount.gt' => 'Amount must be greater than 0',
            'channel_code.required' => 'Channel Code is required',
            'sub_category_json.required' => 'Delivery Platform Sub Category is required',
            'product_id.required' => 'Product is required',
        ]);

        $hasChannelCode = DeliveryProductMappingItem::query()
            ->where('delivery_product_mapping_id', $deliveryProductMappingId)
            ->where('channel_code', $request->channel_code)
            ->exists();
        if($hasChannelCode) {
            return;
        }

        $deliveryProductMappingItem = DeliveryProductMappingItem::create([
            'amount' => $request->amount,
            'channel_code' => $request->channel_code,
            'sub_category_json' => $request->sub_category_json,
            'delivery_product_mapping_id' => $deliveryProductMappingId,
            'product_id' => $request->product_id,
            // 'product_mapping_id' => $request->product_mapping_id,
            // 'product_mapping_item_id' => $request->product_mapping_item_id,
        ]);
        // dd($deliveryProductMappingItem()->with('deliveryProductMapping')->first()->toArray(), $deliveryProductMappingItem->deliveryProductMapping);

        DeliveryProductMapping::findOrFail($deliveryProductMappingId)->update([
            'delivery_product_mapping_items_json' =>
                DeliveryProductMapping::findOrFail($deliveryProductMappingId)
                ->deliveryProductMappingItems()
                ->with([
                    'product:id,code,name',
                    'product.thumbnail'
                ])
                ->select(
                    'id',
                    'amount',
                    'channel_code',
                    'product_id',
                )
                ->get()
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingItem->delivery_product_mapping_id]);
    }

    public function edit(Request $request, $id)
    {
        $deliveryProductMapping = DeliveryProductMapping::query()
            ->with([
                'deliveryProductMappingItems.product.thumbnail',
                'vends:id,code,name',
                'vends.latestVendBinding.customer:id,code,name',
            ])
            ->findOrFail($id);

        return Inertia::render('DeliveryPlatform/Edit', [
            'categoryApiOptions' => fn() =>[
                $this->deliveryPlatformService->getCategories(DeliveryPlatformOperator::find($deliveryProductMapping->delivery_platform_operator_id))
            ],
            'deliveryProductMapping' => DeliveryProductMappingResource::make(
                $deliveryProductMapping
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::query()
                    ->with('deliveryPlatformOperators.deliveryPlatform')
                    ->orderBy('name')
                    ->get()
            ),
            'productMappingOptions' => fn() =>[
                ProductMappingResource::collection(
                    ProductMapping::query()
                        ->where('operator_id', $request->has('operator_id') ? $request->operator_id : $deliveryProductMapping->operator_id)
                        ->get()
                ),
            ],
            'productOptions' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
            'type' => 'edit',
            'unbindedVendOptions' => VendResource::collection(
                Vend::with([
                    'latestVendBinding.customer:id,code,name'
                ])
                ->when($request->operator_id, function($query, $search) {
                    $query->whereIn('vends.id', DB::table('operator_vend')
                        ->select('vend_id')
                        ->where('operator_id', $search)
                        ->pluck('vend_id')
                    );
                })
                ->whereDoesntHave('latestOperator.deliveryPlatformOperators', function($query) use ($deliveryProductMapping) {
                    $query->where('id', $deliveryProductMapping->delivery_platform_operator_id);
                })
                ->select(
                    'id',
                    'code',
                    'name'
                )
                ->orderBy('code')
                ->get()
            ),
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'category_json' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
            'delivery_platform_operator_id' => 'required',
            'product_mapping_id' => 'required',
            'productMappingItems' => 'required',
            'productMappingItems.*.delivery_platform_amount' => 'required|numeric|gt:0',
            'productMappingItems.*.delivery_platform_sub_category_json' => 'required',
        ], [
            'category_json.required' => 'Platform Category is required',
            'delivery_platform_operator_id.required' => 'Delivery Platform is required',
            'name.required' => 'Name is required',
            'operator_id.required' => 'Operator is required',
            'product_mapping_id.required' => 'Product Mapping is required',
            'productMappingItems.required' => 'At Least One Product is required',
            'productMappingItems.*.delivery_platform_amount' => 'required, more than 0',
            'productMappingItems.*.delivery_platform_sub_category_json' => 'required',
        ]);

        $deliveryProductMapping = DeliveryProductMapping::create([
            'category_json' => $request->category_json,
            'name' => $request->name,
            'operator_id' => $request->operator_id,
            'delivery_platform_operator_id' => $request->delivery_platform_operator_id,
            'product_mapping_id' => $request->product_mapping_id,
        ]);

        foreach($request->productMappingItems as $productMappingItem) {
            $deliveryProductMapping->deliveryProductMappingItems()->create([
                'amount' => $productMappingItem['delivery_platform_amount'],
                'channel_code' => $productMappingItem['channel_code'],
                'delivery_product_mapping_id' => $deliveryProductMapping->id,
                'product_id' => $productMappingItem['product']['id'],
                'product_mapping_id' => $deliveryProductMapping->product_mapping_id,
                'product_mapping_item_id' => $productMappingItem['id'],
                'sub_category_json' => $productMappingItem['delivery_platform_sub_category_json'],
            ]);
        }

        $deliveryProductMapping->update([
            'delivery_product_mapping_items_json' => $deliveryProductMapping->deliveryProductMappingItems()->with('product.thumbnail')->get(),
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }

    public function unbindVend($id, $vendId)
    {
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);
        $vend = Vend::findOrFail($vendId);
        $vend->deliveryProductMappings()->detach($deliveryProductMapping->id);
        $deliveryProductMapping->update([
            'vends_json' => $deliveryProductMapping->vends()->with('latestVendBinding.customer:id,code,name')->get(),
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);
        $deliveryProductMapping->update($request->all());

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }
}
