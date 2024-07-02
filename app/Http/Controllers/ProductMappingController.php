<?php

namespace App\Http\Controllers;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\VendResource;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductMappingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read product-mappings']);
    }

    public function index(Request $request)
    {
        // dd($request->all());
        $request->merge([
            'is_active' => $request->is_active ? $request->is_active : true,
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortBy' => $request->sortBy ? $request->sortBy : true,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name'
        ]);

        return Inertia::render('ProductMapping/Index', [
            'cmsEndpoint' => env('CMS_URL'),
            'productMappings' => ProductMappingResource::collection(
                ProductMapping::with([
                    'attachments',
                    'productMappingItems',
                    'productMappingItems.product:id,code,name,is_active',
                    'productMappingItems.product.thumbnail',
                    'vends:id,code,name,product_mapping_id,customer_id',
                    'vends.customer:id,code,is_active,name,person_id,virtual_customer_prefix,virtual_customer_code',
                    'vendPrefixes'
                ])
                ->when($request->name, function($query, $search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->when($request->product, function($query, $search) {
                    $query->where(function($query) use ($search) {
                        $query->whereHas('productMappingItems.product', function($query) use ($search) {
                            $query->where('code', 'LIKE', "%{$search}%")
                                ->orWhere('name', 'LIKE', "%{$search}%");
                        });
                    });
                })
                ->when($request->vend_code, function($query, $search) {
                    $query->whereHas('vends', function($query) use ($search) {
                        $query->where('code', 'LIKE', "{$search}%");
                    });
                })
                ->when($request->is_active, function($query, $search) use ($request) {
                    $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                })
                ->orderBy($request->sortKey, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' )
                ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                ->withQueryString()

            ),
            'products' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
            'unbindedVends' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'customer'
                    ])
                    ->has('customer')
                    ->whereNull('product_mapping_id')
                    ->select(
                        'id',
                        'code',
                        'customer_id',
                        'name',
                    )
                    ->orderBy('code')
                    ->get()
                ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $productMapping = new ProductMapping();
        $productMapping->fill($request->all());
        $productMapping->operator_id = auth()->user()->operator_id;
        $productMapping->save();

        return redirect()->route('product-mappings');
    }

    public function edit($id)
    {
        $productMapping = ProductMapping::with([
            'attachments',
            'productMappingItems',
            'productMappingItems.product:id,code,name,is_active',
            'productMappingItems.product.thumbnail',
            'vends:id,code,name,product_mapping_id,customer_id',
            'vends.customer:id,code,name,person_id,virtual_customer_prefix,virtual_customer_code',
        ])->findOrFail($id);

        return Inertia::render('ProductMapping/Edit', [
            'productMapping' => ProductMappingResource::make($productMapping),
            'products' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
            ),
        ]);
    }

    public function update(Request $request, $productMappingId)
    {
        $request->validate([
            'name' => 'required',

        ]);

        // $request->merge([
        //     'is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN),
        // ]);
        $productMapping = ProductMapping::findOrFail($productMappingId);
        $productMapping->fill($request->all());

        if($request->productMappingItems) {
           $productMapping->product_mapping_items_json =  $request->productMappingItems;
           $productMapping->productMappingItems()->delete();
           foreach($request->productMappingItems as $productMappingItem) {
                $productMapping->productMappingItems()->create([
                    'channel_code' => $productMappingItem['channel_code'],
                    'product_id' => $productMappingItem['product']['id'],
                ]);
           }
        }

        $productMapping->save();

        $this->syncProductMappingChannels($productMapping);

        return redirect()->route('product-mappings.edit', ['id' => $productMapping->id]);
    }

    public function uploadAttachment(Request $request, $id)
    {
        $productMapping = ProductMapping::findOrFail($id);

        if($request->hasFile('files')) {
            $files = $request->file('files');
            $dir = 'sys/product-mappings';
            $storedPath = $files->storePublicly('sys/product-mappings');
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $productMapping->attachments()->create([
                'type' => 1,
                'full_url' => $url,
                'local_url' => $dir.'/'.$fileName,
            ]);
        }
        return true;
    }

    public function delete($productMappingId)
    {
        $productMapping = ProductMapping::findOrFail($productMappingId);
        $productMapping->productMappingItems()->delete();
        $productMapping->delete();

        return redirect()->route('product-mappings');
    }

    public function replicate(Request $request)
    {
        $productMapping = ProductMapping::findOrFail($request->id);
        $replicated = $productMapping->replicate()->fill([
            'name' => $productMapping->name.'-replicated',
        ]);
        $replicated->save();

        if($productMapping->productMappingItems()->exists()) {
            foreach($productMapping->productMappingItems as $productMappingItem) {
                ProductMappingItem::create([
                    'channel_code' => $productMappingItem->channel_code,
                    'product_id' => $productMappingItem->product_id,
                    'product_mapping_id' => $replicated->id,
                ]);
            }
        }

        return redirect()->route('product-mappings.edit', ['id' => $replicated->id]);
    }

    public function bindVends(Request $request, $productMappingId)
    {
        $productMapping = ProductMapping::findOrFail($productMappingId);
        $this->unbindProductFromChannels($productMapping->vends);

        $productMapping->vends()->update(['product_mapping_id' => null]);
        if($request->productMappingVends) {
            foreach($request->productMappingVends as $vendData) {
                $vend = Vend::findOrFail($vendData['id']);
                $vend->product_mapping_id = $productMapping->id;
                $vend->save();
            }
        }
        $productMapping->vends_json = $request->productMappingVends;
        $productMapping->save();

        $this->syncProductMappingChannels($productMapping);

        return redirect()->route('product-mappings');
    }

    public function toggleActivateDeactivate($productMappingID)
    {
        $productMapping = ProductMapping::findOrFail($productMappingID);
        $productMapping->is_active = !$productMapping->is_active;
        $productMapping->save();

        return redirect()->route('product-mappings');
    }

    private function unbindProductFromChannels($vends)
    {
        if($vends) {
            foreach($vends as $vend) {
                $vendData = Vend::findOrFail($vend->id);
                $vendData->vendChannels()->update(['product_id' => null]);
            }
        }
    }

    private function syncProductMappingChannels(ProductMapping $productMapping)
    {
        if($productMapping->vends()->exists()) {
            foreach($productMapping->vends as $vend) {
                if($vend->vendChannels()->exists() and $productMapping->productMappingItems()->exists()) {
                    $vendData = Vend::findOrFail($vend->id);
                    $vendData->vendChannels()->update(['product_id' => null]);

                    $vendChannels = $vend->vendChannels;
                    $productMappingItems = $productMapping->productMappingItems;
                    foreach($productMappingItems as $productMappingItem) {
                        $vendChannel = $vendChannels->where('code', $productMappingItem->channel_code)->first();
                        if($vendChannel) {
                            $vendChannel->product_id = $productMappingItem->product_id;
                            $vendChannel->save();
                        }
                    }
                    SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
                }
            }
        }
    }
}
