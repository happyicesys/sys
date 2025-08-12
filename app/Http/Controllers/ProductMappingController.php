<?php

namespace App\Http\Controllers;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendPrefixResource;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendPrefix;
use App\Services\ProductMappingService;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductMappingController extends Controller
{
    private $productMappingService;

    public function __construct()
    {
        $this->middleware(['permission:read product-mappings']);
        $this->productMappingService = new ProductMappingService();
    }

    public function index(Request $request)
    {
        // dd($request->all());
        $request->merge([
            'is_active' => $request->is_active ? $request->is_active : true,
            'vendStatus' => $request->vendStatus ? $request->vendStatus : 'active',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 5,
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
                    'vends' => function ($query) use ($request) {
                        $query->select('id', 'code', 'name', 'product_mapping_id', 'customer_id', 'is_active', 'is_testing', 'is_disposed');

                        if ($request->vendStatus and $request->vendStatus !== 'all') {
                            switch($request->vendStatus) {
                                case 'disposed':
                                    $query->where('is_disposed', true);
                                    break;
                                case 'factory':
                                    $query->where('is_testing', true);
                                    break;
                                case 'active':
                                    $query->where('is_active', true);
                                    break;
                                case 'inactive':
                                    $query->where('is_active', false);
                                    break;
                            }
                        }
                    },
                    'vends.customer:id,code,is_active,name,person_id,virtual_customer_prefix,virtual_customer_code',
                    'vendPrefixes'
                ])
                ->leftJoin('vend_prefixes', function ($join) {
                    $join->on('product_mappings.id', '=', 'vend_prefixes.product_mapping_id')
                         ->whereIn('vend_prefixes.id', function ($query) {
                             $query->select(DB::raw('MIN(id)'))
                                   ->from('vend_prefixes as vp')
                                   ->whereColumn('vp.product_mapping_id', 'product_mappings.id')
                                   ->orderBy('vp.name', 'asc')
                                   ->groupBy('vp.product_mapping_id');
                         });
                })
                ->when($request->name, function($query, $search) {
                    $query->where('product_mappings.name', 'LIKE', "%{$search}%");
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
                ->when($request->vendPrefixes, function($query, $search) {
                    $query->whereHas('vendPrefixes', function($query) use ($search) {
                        if(in_array('single-ud', $search)) {
                            $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                            unset($search[array_search('single-ud', $search)]);
                        }
                        $query->whereIn('vend_prefix_id', $search);
                    });
                })
                ->when($request->vendStatus, function($query, $search) {
                    if($search != 'all') {
                        $query->whereHas('vends', function($query) use ($search) {
                            switch($search) {
                                case 'disposed':
                                    $query->where('is_disposed', true);
                                    break;
                                case 'factory':
                                    $query->where('is_testing', true);
                                    break;
                                case 'active':
                                    $query->where('is_active', true);
                                    break;
                                case 'inactive':
                                    $query->where('is_active', false);
                                    break;
                            }
                        });
                    }
                })
                ->when($request->is_active, function($query, $search) use ($request) {
                    $query->where('product_mappings.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                })
                ->select('product_mappings.*', 'vend_prefixes.name as vend_prefix_name')
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
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::orderBy('name')->get()
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


    public function createItem(Request $request, $productMappingId)
    {
        $validated = $request->validate([
            'channel_code' => ['required'],
            'product_id'   => ['required','exists:products,id'],
            'sequence'     => ['nullable','integer','min:1'],
        ]);

        return DB::transaction(function () use ($validated, $productMappingId) {
            // Normalize seq: ensure null or >=1 int
            $seq = array_key_exists('sequence', $validated)
                 ? ($validated['sequence'] !== null ? (int)$validated['sequence'] : null)
                 : null;

            // Create item without risky mass-assign
            $item = new ProductMappingItem();
            $item->product_mapping_id = $productMappingId;
            $item->channel_code       = $validated['channel_code'];
            $item->product_id         = $validated['product_id'];
            $item->sequence           = null; // set after clearing others
            $item->save();

            // If a sequence was provided, clear duplicates atomically then set
            if ($seq !== null) {
                ProductMappingItem::where('product_mapping_id', $productMappingId)
                    ->where('sequence', $seq)
                    ->update(['sequence' => null]);

                $item->sequence = $seq;
                $item->save();
            }

            return redirect()->back();
        });
    }

    public function edit(Request $request, $id)
    {
        $productMappingInit = ProductMapping::findOrFail($id);

        // carry forward selected price type
        $request->merge([
            'selling_price_type' => $request->selling_price_type
                ?: ($productMappingInit->selling_price_type ?: null),
        ]);

        // read sort inputs (sortBy=true => DESC, false => ASC)
        $sortKey  = $request->input('sortKey');                // 'sequence' | 'channel_code' | null
        $sortDesc = filter_var($request->input('sortBy'), FILTER_VALIDATE_BOOLEAN); // bool
        $dir      = $sortDesc ? 'DESC' : 'ASC';

        if(!in_array($sortKey, ['sequence', 'channel_code'])) {
            // default to sequence if not specified
            $sortKey = 'channel_code';
        }

        $productMapping = ProductMapping::with([
            'attachments',
            // apply ordering here
            'productMappingItemsNormalSequence' => function ($q) use ($sortKey, $dir) {
                if ($sortKey === 'sequence') {
                    // nulls last, then sequence asc/desc, then channel_code as tiebreaker
                    $q->orderByRaw('CASE WHEN sequence IS NULL THEN 1 ELSE 0 END ASC')
                      ->orderBy('sequence', $dir)
                      ->orderByRaw('CAST(channel_code AS UNSIGNED), channel_code');
                } elseif ($sortKey === 'channel_code') {
                    // try numeric sort, fall back to lexical; keep a stable tiebreaker
                    $q->orderByRaw("CASE WHEN channel_code REGEXP '^[0-9]+$' THEN 0 ELSE 1 END ASC")
                      ->orderByRaw("CAST(channel_code AS UNSIGNED) $dir")
                      ->orderBy('channel_code', $dir);
                }
                // else: leave DB default order
            },
            'productMappingItemsNormalSequence.product:id,code,name,is_active,category_id,category_group_id',
            'productMappingItemsNormalSequence.product.thumbnail',
            'productMappingItemsNormalSequence.product.category',
            'productMappingItemsNormalSequence.product.categoryGroup',
            'productMappingItemsNormalSequence.product.sellingPrices' => function($query) use ($request) {
                if($request->selling_price_type) {
                    $query->where('type', $request->selling_price_type);
                }
            },
            'productMappingItemsNormalSequence.product:id,code,name,is_active',
            'upcomingProductMappings',
            'vends:id,code,name,product_mapping_id,customer_id',
            'vends.customer:id,code,name,person_id,virtual_customer_prefix,virtual_customer_code',
        ])->findOrFail($id);

        return Inertia::render('ProductMapping/Edit', [
            'priceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'productMapping'   => ProductMappingResource::make($productMapping),
            'upcomingProductMappingOptions' => ProductMappingResource::collection(
                ProductMapping::query()
                    ->whereHas('vendPrefixes', function($query) use ($productMapping) {
                        $query->whereIn('vend_prefix_id', $productMapping->vendPrefixes->pluck('id'));
                    })
                    ->where('id', '!=', $id)
                    ->orderBy('name')
                    ->get()
            ),
            'products' => ProductResource::collection(
                Product::with(['thumbnail'])
                    ->where('is_inventory', true)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get()
            ),
            // send current sort back so the header arrows know what to show
            'sortKey' => $sortKey,
            'sortBy'  => $sortDesc,
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

        $productMapping->upcomingProductMappings()->sync($request->upcomingProductMappings);

        if($request->productMappingItems) {
           $productMapping->productMappingItems()->delete();
           foreach($request->productMappingItems as $productMappingItem) {
                $productMapping->productMappingItems()->create([
                    'channel_code' => $productMappingItem['channel_code'],
                    'product_id' => $productMappingItem['product']['id'],
                    'selling_price_id' => isset($productMappingItem['selling_price_id']) ? $productMappingItem['selling_price_id'] : null,
                    'sequence' => $productMappingItem['sequence'],
                ]);
           }
        }

        $productMapping->save();

        $this->productMappingService->syncChannels($productMapping->id);

        return redirect()->route('product-mappings.edit', ['id' => $productMapping->id]);
    }

    public function updateItem(Request $request, $productMappingItemID)
    {
        $productMappingItem = ProductMappingItem::findOrFail($productMappingItemID);
        $productMappingItem->fill($request->all());
        $productMappingItem->save();

        return redirect()->route('product-mappings.edit', ['id' => $productMappingItem->productMapping->id]);
    }

    public function updateItemSequence(Request $request, ProductMappingItem $item)
    {
        $data = $request->validate([
            'sequence' => ['nullable','integer','min:1'],
        ]);
        return DB::transaction(function () use ($item, $data) {
            $seq = $data['sequence'] ?? null;

            if ($seq !== null) {
                // "latest wins": clear others with the same seq
                ProductMappingItem::where('product_mapping_id', $item->product_mapping_id)
                    ->where('id', '!=', $item->id)
                    ->where('sequence', $seq)
                    ->update(['sequence' => null]);

                // set this item
                $item->sequence = $seq;
                $item->save();
            } else {
                // allow clearing
                $item->sequence = null;
                $item->save();
            }

            return redirect()->back();
        });
    }

    public function uploadAttachment(Request $request, $id)
    {
        $productMapping = ProductMapping::findOrFail($id);

        if($request->files) {
            $files = $request->file('files');
            $dir = 'sys/product-mappings';
            $storedPath = $files->storePublicly('sys/product-mappings');
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $productMapping->attachments()->create([
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
        $productMapping->save();

        $this->productMappingService->syncChannels($productMapping->id);

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
}
