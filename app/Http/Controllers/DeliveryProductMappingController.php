<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeliveryProductMappingResource;
use App\Http\Resources\DeliveryProductMappingBulkResource;
use App\Http\Resources\DeliveryProductMappingItemResource;
use App\Http\Resources\DeliveryPlatformOperatorResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\ProductMappingItemResource;
use App\Http\Resources\VendResource;
use App\Models\DeliveryPlatform;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingBulk;
use App\Models\DeliveryProductMappingItem;
use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryProductMappingVendChannel;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Services\DeliveryPlatformService;
use App\Services\DeliveryProductMappingService;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryProductMappingController extends Controller
{
    protected $deliveryPlatformService;
    protected $deliveryProductMappingService;

    public function __construct(
        DeliveryPlatformService $deliveryPlatformService,
        DeliveryProductMappingService $deliveryProductMappingService
    )
    {
        $this->deliveryPlatformService = $deliveryPlatformService;
        $this->deliveryProductMappingService = $deliveryProductMappingService;
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
                        'operator:id,name',
                        'deliveryProductMappingVends' => function($query) {
                            $query->whereNull('end_date');
                        },
                        'deliveryProductMappingVends.vend:id,code,name',
                        'deliveryProductMappingVends.vend.customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                    ])
                    ->filterIndex($request)
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function bindVend(Request $request, $id)
    {
        $request->validate([
            'platform_ref_id' => 'required',
        ]);

        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);
        $vend = Vend::findOrFail($request->vend_id);

        $deliveryProductMappingVend = DeliveryProductMappingVend::create([
            'delivery_product_mapping_id' => $deliveryProductMapping->id,
            'platform_ref_id' => $request->platform_ref_id,
            'vend_code' => $vend->code,
            'vend_id' => $vend->id,
        ]);

        // save delivery product mapping vend channels to delivery product mapping vend as json
        $this->deliveryProductMappingService->syncVendChannels($deliveryProductMapping->id, $vend->id);

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

    public function delete($id)
    {
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);
        $deliveryProductMapping->deliveryProductMappingItems()->delete();
        if($deliveryProductMapping->deliveryProductMappingVends()->exists()) {
            foreach($deliveryProductMapping->deliveryProductMappingVends as $deliveryProductMappingVend) {
                $deliveryProductMappingVend->deliveryProductMappingVendChannels()->delete();
            }
            $deliveryProductMapping->deliveryProductMappingVends()->delete();
        }
        $deliveryProductMapping->delete();

        return redirect()->route('delivery-product-mappings');
    }

    public function deleteDeliveryProductMappingBulk($deliveryProductMappingBuldID)
    {
        $deliveryProductMappingBulk = DeliveryProductMappingBulk::findOrFail($deliveryProductMappingBuldID);
        $deliveryProductMappingBulk->deliveryProductMappingBulkItems()->delete();
        $deliveryProductMappingBulk->delete();

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingBulk->deliveryProductMapping->id]);
    }

    public function deleteDeliveryProductMappingItem($id)
    {
        $deliveryProductMappingItem = DeliveryProductMappingItem::findOrFail($id);
        $deliveryProductMapping = $deliveryProductMappingItem->deliveryProductMapping;

        if($deliveryProductMappingItem->deliveryProductMappingVendChannels()->exists()) {
            $deliveryProductMappingItem->deliveryProductMappingVendChannels()->delete();
        }

        $deliveryProductMappingItem->delete();

        if(! $deliveryProductMapping->deliveryProductMappingItems()->exists()) {
            $deliveryProductMapping->update([
                'is_active' => false,
            ]);
        }

        // re sync vend channels check whether delivery product mapping items is empty make inactive
        $this->deliveryProductMappingService->syncVendChannels($deliveryProductMapping->id, null);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }

    public function saveBundleSales(Request $request, $id)
    {
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);

        DB::beginTransaction();

        $deliveryProductMappingBulk = $deliveryProductMapping->deliveryProductMappingBulks()->create([
            'amount' => $request->bundle_amount,
            'name' => $request->bundle_name,
            'promo_desc' => $request->bundle_desc,
            'promo_label' => $request->bundle_label,
            'promo_type' => $request->bundle_type,
            'promo_value' => $request->bundle_value,
            'total_qty' => $request->total_qty,
        ]);

        if(isset($request->bundleSalesItems)) {
            foreach($request->bundleSalesItems as $bundleSalesItem) {
                $deliveryProductMappingBulk->deliveryProductMappingBulkItems()->create([
                    'delivery_product_mapping_item_id' => $bundleSalesItem['id']
                ]);
            }
        }

        DB::commit();

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
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
                ->get(),
        ]);

        $this->deliveryProductMappingService->syncVendChannels($deliveryProductMappingId);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingItem->delivery_product_mapping_id]);
    }

    public function edit(Request $request, $id)
    {
        $deliveryProductMapping = DeliveryProductMapping::query()
            ->with([
                'deliveryPlatformOperator:id,delivery_platform_id,operator_id,type',
                'deliveryPlatformOperator.deliveryPlatform:id,name,slug',
                'deliveryProductMappingBulks.deliveryProductMappingBulkItems.deliveryProductMappingItem.product.thumbnail',
                'deliveryProductMappingItems.product:id,code,name',
                'deliveryProductMappingItems.product.thumbnail:id,full_url,attachments.modelable_id,attachments.modelable_type',
                'deliveryProductMappingVends' => function($query) {
                    $query->whereNull('end_date')
                        ->select('id', 'delivery_product_mapping_id', 'platform_ref_id', 'vend_code', 'vend_id', 'is_active');
                },
                'deliveryProductMappingVends.vend:id,code,name',
                'deliveryProductMappingVends.vend.customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                'deliveryProductMappingVends.deliveryProductMappingVendChannels.vendChannel:id,code,capacity,qty',
                'deliveryProductMappingVends.deliveryProductMappingVendChannels.deliveryProductMappingItem:id,amount,channel_code,delivery_product_mapping_id,product_mapping_item_id,sub_category_json',
                'operator:id,code,name,country_id',
                'operator.country',
                'productMapping:id,name',
            ])
            ->select(
                'id',
                'category_json',
                'delivery_platform_operator_id',
                'is_active',
                'name',
                'operator_id',
                'product_mapping_id',
                'remarks',
                'reserved_percent',
                'reserved_qty'
            )
            ->findOrFail($id);

            // dd($this->deliveryProductMappingService->getBundleSalesOptions($deliveryProductMapping));

        return Inertia::render('DeliveryPlatform/Edit', [
            'bundleSalesOptions' => $this->deliveryProductMappingService->getBundleSalesOptions($deliveryProductMapping),
            'deliveryProductMapping' => DeliveryProductMappingResource::make(
                $deliveryProductMapping
            ),
            'productOptions' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->where('is_active', true)
                ->whereNotIn('id', function($query) use ($deliveryProductMapping) {
                    $query->select('product_id')
                        ->from('delivery_product_mapping_items')
                        ->where('delivery_product_mapping_id', $deliveryProductMapping->id);
                })
                ->orderBy('code')
                ->get()
            ),
            'type' => 'edit',
            'unbindedVendOptions' => VendResource::collection(
                Vend::with([
                    'customer:id,code,name,person_id,virtual_customer_code,virtual_customer_prefix',
                ])
                ->whereHas('customer', function($query) use ($deliveryProductMapping) {
                    $query->where(function($query) use ($deliveryProductMapping) {
                        $query->where('is_active', true)
                            ->where('operator_id', $deliveryProductMapping->operator_id);
                    });
                })
                ->where(function ($query) use ($deliveryProductMapping) {
                    $query
                    ->whereDoesntHave('deliveryProductMappingVends.deliveryProductMapping', function($query) use ($deliveryProductMapping) {
                        $query->where('delivery_platform_operator_id', $deliveryProductMapping->delivery_platform_operator_id);
                    })
                    ->orDoesntHave('deliveryProductMappingVends.deliveryProductMapping');

                    if($deliveryProductMapping->deliveryPlatformOperator->type == 'production') {
                        $query->has('customer')->where('customers.is_active', true);
                    }
                })
                ->when($deliveryProductMapping->deliveryPlatformOperator->type == '', function($query, $search) use ($request) {
                    $query->where('vends.code', 'LIKE', "{$request->vend_code}%");
                })
                ->orderBy('vends.code')
                // ->select('vends.id', 'vends.code', 'vends.name')
                ->get()
            ),
        ]);
    }

    // pause vends operation in delivery product mapping
    public function togglePauseAllVends(Request $request, $id)
    {
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);

        if($deliveryProductMapping->deliveryProductMappingVends()->exists()) {
            foreach($deliveryProductMapping->deliveryProductMappingVends as $deliveryProductMappingVend) {
                $deliveryProductMappingVend->update([
                    'is_active' => ! $deliveryProductMapping->is_active,
                ]);
            }
        }

        $deliveryProductMapping->update([
            'is_active' => ! $deliveryProductMapping->is_active,
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$id]);
    }

    // pause vends operation in delivery product mapping
    public function pauseAllVends(Request $request, $id)
    {
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);

        if($deliveryProductMapping->deliveryProductMappingVends()->exists()) {
            foreach($deliveryProductMapping->deliveryProductMappingVends as $deliveryProductMappingVend) {
                $deliveryProductMappingVend->update([
                    'is_active' => false,
                ]);
                $this->deliveryPlatformService->pauseStore($deliveryProductMappingVend);
            }
        }

        $deliveryProductMapping->update([
            'is_active' => false,
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$id]);
    }

    // pause single vend channel
    public function togglePauseChannel($deliveryProductMappingVendChannelId)
    {
        $deliveryProductMappingVendChannel = DeliveryProductMappingVendChannel::findOrFail($deliveryProductMappingVendChannelId);
        $deliveryProductMappingVendChannel->update([
            'is_active' => ! $deliveryProductMappingVendChannel->is_active,
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingVendChannel->deliveryProductMappingVend->deliveryProductMapping->id]);
    }

    public function togglePauseDeliveryProductMappingItem($deliveryProductMappingItemId)
    {
        $deliveryProductMappingItem = DeliveryProductMappingItem::findOrFail($deliveryProductMappingItemId);
        $deliveryProductMappingItem->update([
            'is_active' => ! $deliveryProductMappingItem->is_active,
        ]);
        if($deliveryProductMappingItem->deliveryProductMappingVendChannels()->exists()) {
            $deliveryProductMappingItem->deliveryProductMappingVendChannels()->update([
                'is_active' => $deliveryProductMappingItem->is_active,
            ]);
        }

        // if($deliveryProductMappingItem->is_active) {
            $this->deliveryProductMappingService->syncVendChannels($deliveryProductMappingItem->deliveryProductMapping->id);
        // }

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingItem->delivery_product_mapping_id]);
    }

    // pause indiviual vend operation in delivery product mapping
    public function togglePauseVend($deliveryProductMappingVendId)
    {
        $deliveryProductMappingVend = DeliveryProductMappingVend::findOrFail($deliveryProductMappingVendId);
        $deliveryProductMappingVend->update([
            'is_active' => ! $deliveryProductMappingVend->is_active,
        ]);
        if($deliveryProductMappingVend->deliveryProductMappingVendChannels()->exists()) {
            $deliveryProductMappingVend->deliveryProductMappingVendChannels()->update([
                'is_active' => $deliveryProductMappingVend->is_active,
            ]);
        }
        if($deliveryProductMappingVend->is_active) {
            $this->deliveryProductMappingService->syncVendChannels($deliveryProductMappingVend->deliveryProductMapping->id, $deliveryProductMappingVend->vend->id);
        }else {
            $this->deliveryPlatformService->pauseStore($deliveryProductMappingVend);
        }

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingVend->delivery_product_mapping_id]);
    }

    public function store(Request $request)
    {
        // handle creation form validation, array
        $request->validate([
            'category_json' => 'required',
            'name' => 'required',
            'operator_id' => 'required',
            'delivery_platform_operator_id' => 'required',
            'product_mapping_id' => 'required',
            'productMappingItems' => 'required',
            'productMappingItems.*.delivery_platform_amount' => 'required|numeric|gt:0',
            'productMappingItems.*.delivery_platform_sub_category_json' => 'required',
            'reserved_percent' => 'numeric|integer',
            'reserved_qty' => 'numeric|integer',
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
                'product_mapping_item_id' => isset($productMappingItem['id']) ? $productMappingItem['id'] : null,
                'sub_category_json' => $productMappingItem['delivery_platform_sub_category_json'],
            ]);
        }

        $deliveryProductMapping->update([
            'delivery_product_mapping_items_json' => $deliveryProductMapping->deliveryProductMappingItems()->with('product.thumbnail')->get(),
        ]);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }

    public function unbindVend($deliveryProductMappingVendId)
    {
        $deliveryProductMappingVend = DeliveryProductMappingVend::findOrFail($deliveryProductMappingVendId);
        $deliveryProductMappingVend->end_date = Carbon::now();
        $deliveryProductMappingVend->save();

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingVend->delivery_product_mapping_id]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'reserved_percent' => 'numeric|integer',
            'reserved_qty' => 'numeric|integer',
        ]);
        $deliveryProductMapping = DeliveryProductMapping::findOrFail($id);
        $deliveryProductMapping->update($request->all());

        $deliveryProductMapping->update([
            'delivery_product_mapping_items_json' =>
                $deliveryProductMapping->deliveryProductMappingItems()->with([
                    'product.thumbnail',
                    'deliveryProductMapping' => function($query) {
                        $query->select('id', 'name', 'operator_id');
                    }])->get(),
        ]);

        // update reserved percent and qty for all delivery product mapping vend channels
        if($deliveryProductMapping->deliveryProductMappingVends()->exists()) {
            $deliveryProductMapping->deliveryProductMappingVends->each(function($deliveryProductMappingVend) use ($deliveryProductMapping) {
                $deliveryProductMappingVend->deliveryProductMappingVendChannels()->update([
                    'reserved_percent' => $deliveryProductMapping->reserved_percent,
                    'reserved_qty' => $deliveryProductMapping->reserved_qty,
                ]);
            });
        }
        $this->deliveryProductMappingService->syncVendChannels($deliveryProductMapping->id);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMapping->id]);
    }

    public function updateDeliveryProductMappingItem(Request $request, $id)
    {
        $deliveryProductMappingItem = DeliveryProductMappingItem::findOrFail($id);
        if($deliveryProductMappingItem->amount != $request->amount or $deliveryProductMappingItem->sub_category_json != $request->sub_category_json) {
            $deliveryProductMappingItem->update([
                'amount' => $request->amount,
                'sub_category_json' => $request->sub_category_json,
            ]);
            $this->deliveryProductMappingService->syncVendChannels($deliveryProductMappingItem->delivery_product_mapping_id);
        }

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingItem->delivery_product_mapping_id]);
    }

    public function updateChannel(Request $request, $id)
    {
        $deliveryProductMappingVendChannel = DeliveryProductMappingVendChannel::findOrFail($id);
        $deliveryProductMappingVendChannel->update([
            'reserved_percent' => $request->reserved_percent,
            'reserved_qty' => $request->reserved_qty,
        ]);
        $this->deliveryProductMappingService->syncVendChannels($deliveryProductMappingVendChannel->deliveryProductMappingVend->deliveryProductMapping->id, $deliveryProductMappingVendChannel->deliveryProductMappingVend->id);

        return redirect()->route('delivery-product-mappings.edit', [$deliveryProductMappingVendChannel->deliveryProductMappingVend->deliveryProductMapping->id]);
    }
}
