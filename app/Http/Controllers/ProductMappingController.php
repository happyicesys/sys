<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\VendResource;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductMappingController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        // $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('ProductMapping/Index', [
            'productMappings' => ProductMappingResource::collection(
                ProductMapping::with([
                    'attachments',
                    'productMappingItems',
                    'productMappingItems.product',
                    'productMappingItems.product.thumbnail',
                    'vends',
                    'vends.latestVendBinding.customer',
                ])
                ->when($request->name, function($query, $search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                // ->when($sortKey, function($query, $search) use ($sortBy) {
                //     $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                // })
                ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                ->withQueryString()
            ),
            'products' => ProductResource::collection(
                Product::with([
                    'thumbnail'
                ])
                ->where('is_inventory', true)
                ->orderBy('code')
                ->get()
            ),
            'unbindedVends' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'latestVendBinding.customer'
                    ])->whereDoesntHave('productMapping', function($query) use ($request) {
                        $query->where('product_mappings.id', '!=', $request->id);
                    })
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

    public function update(Request $request, $productMappingId)
    {
        $request->validate([
            'name' => 'required',
        ]);

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

        return redirect()->route('product-mappings');
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
            'name' => $productMapping->name.'-replicated-'.Carbon::now()->toDateTimeString()
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

        return redirect()->route('product-mappings');
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
                }
            }
        }
    }
}
