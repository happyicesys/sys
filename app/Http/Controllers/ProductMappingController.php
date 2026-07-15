<?php

namespace App\Http\Controllers;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\VendResource;
use App\Http\Resources\VendPrefixResource;
use App\Models\Product;
use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendPrefix;
use App\Http\Resources\OperatorResource;
use App\Services\ProductMappingService;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
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
            // DEPRECATED (2026-07): vend_prefix_name sort retired with the Binded
            // Prefix column — map stale/bookmarked URLs back to name so the
            // orderBy below never references the dropped join column.
            'sortKey' => $request->sortKey && $request->sortKey !== 'vend_prefix_name' ? $request->sortKey : 'name'
        ]);

        // NOTE: the "first vend_prefix per mapping" leftJoin (used only to select
        // vend_prefixes.name for the LIST) is added on the list query below, NOT
        // here. It yields <=1 row per mapping so it never affected the
        // $totalBindedVends COUNT — but leaving it on this shared base query made
        // that COUNT run the correlated MIN(id) subquery per mapping (~750ms). The
        // filters below all use whereHas(), so they don't need the join either.
        $query = ProductMapping::query()
            ->when($request->name, function ($query, $search) {
                $query->where('product_mappings.name', 'LIKE', "%{$search}%");
            })
            ->when($request->upcoming_product_mapping, function ($query, $search) {
                $query->whereHas('upcomingProductMapping', function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->product, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('productMappingItems.product', function ($query) use ($search) {
                        $query->where('code', 'LIKE', "%{$search}%")
                            ->orWhere('name', 'LIKE', "%{$search}%");
                    });
                });
            })
            ->when($request->vend_code, function ($query, $search) {
                $query->whereHas('vends', function ($query) use ($search) {
                    $query->where('code', 'LIKE', "{$search}%");
                });
            })
            // DEPRECATED (2026-07): the Machine Prefix filter (whereHas vendPrefixes)
            // was removed together with the prefix→mapping binding.
            ->when($request->vendStatus, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vends', function ($query) use ($search) {
                        switch ($search) {
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
                            case 'sold':
                                $query->where('is_sold', true);
                                break;
                        }
                    });
                }
            })
            ->when($request->is_active, function ($query, $search) use ($request) {
                $query->where('product_mappings.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            });

        $totalBindedVends = (clone $query)
            ->join('vends', 'product_mappings.id', '=', 'vends.product_mapping_id')
            ->when($request->vendStatus, function ($query, $search) {
                if ($search != 'all') {
                    switch ($search) {
                        case 'disposed':
                            $query->where('vends.is_disposed', true);
                            break;
                        case 'factory':
                            $query->where('vends.is_testing', true);
                            break;
                        case 'active':
                            $query->where('vends.is_active', true);
                            break;
                        case 'inactive':
                            $query->where('vends.is_active', false);
                            break;
                        case 'sold':
                            $query->where('vends.is_sold', true);
                            break;
                    }
                }
            })
            ->count('vends.id');

        return Inertia::render('ProductMapping/Index', [
            'cmsEndpoint' => env('CMS_URL'),
            'totalBindedVends' => $totalBindedVends,
            'productMappings' => ProductMappingResource::collection(
                (clone $query)
                    // DEPRECATED (2026-07): the "first vend_prefix per mapping"
                    // leftJoin (legacy vend_prefixes.product_mapping_id column) was
                    // removed with the Binded Prefix column.
                    ->with([
                    'attachments',
                    'operator',
                    'productMappingItemsNormalSequence' => function ($q) {
                        $q->orderByRaw("CASE WHEN channel_code REGEXP '^[0-9]+$' THEN 0 ELSE 1 END ASC")
                            ->orderByRaw("CAST(channel_code AS UNSIGNED) ASC")
                            ->orderBy('channel_code', 'asc');
                    },
                    'productMappingItemsNormalSequence.product:id,code,name,is_active',
                    'productMappingItemsNormalSequence.product.thumbnail',
                    'vends' => function ($query) use ($request) {
                        // NOTE: the L30d Sales chip in ProductMapping/Index.vue now
                        // reads the CUSTOMER's rolling totals (customers.totals_json,
                        // eager-loaded on vends.customer below), NOT this vend column.
                        // vend_transaction_totals_json is kept selected for any other
                        // consumer of VendResource but is no longer the L30d source.
                        $query->select('id', 'code', 'name', 'product_mapping_id', 'customer_id', 'vend_prefix_id', 'is_active', 'is_testing', 'is_disposed', 'binded_at', 'updated_at', 'vend_transaction_totals_json');

                        if ($request->vendStatus and $request->vendStatus !== 'all') {
                            switch ($request->vendStatus) {
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
                                case 'sold':
                                    $query->where('is_sold', true);
                                    break;
                            }
                        }
                    },
                    // selling_price_type — drives the RP1..RP5 chip we render
                    // next to each binded machine in ProductMapping/Index.vue
                    // (same source customers.selling_price_type used on the
                    // Vend/CustomerIndex Ref Price column).
                    // totals_json — surfaces the L30d Sales chip per machine.
                    // Read from the CUSTOMER (customers.totals_json), NOT the vend's
                    // own vend_transaction_totals_json: the vend total follows the
                    // machine's vend_id and would keep showing sales made under a
                    // PREVIOUS customer after the machine is moved. The customer
                    // total is keyed on customer_id so it only reflects this site.
                    'vends.customer:id,code,is_active,name,person_id,virtual_customer_prefix,virtual_customer_code,selling_price_type,totals_json',
                    'vends.vendPrefix:id,name',
                    'vends.deliveryProductMappingVends:id,vend_id,delivery_product_mapping_id',
                    'vends.deliveryProductMappingVends.deliveryProductMapping:id,delivery_platform_operator_id',
                    'vends.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator:id,delivery_platform_id',
                    'vends.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name',
                    // DEPRECATED (2026-07): 'vendPrefixes' eager-load dropped with
                    // the Binded Prefix column (ProductMappingResource guards with
                    // whenLoaded()).
                    'upcomingProductMapping',
                ])

                    ->select('product_mappings.*')
                    ->orderBy($request->sortKey, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc')
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
            'unbindedVends' => fn() =>
                VendResource::collection(
                    Vend::with([
                        // This unbinded-vends dropdown only renders full_name (built
                        // from customer code/name/person_id/virtual_customer_code) and
                        // the nested customer.code / customer.name. Load just those
                        // scalars instead of select * dragging customers' JSON columns
                        // (totals_json, person_json, snap_*, cms_invoice_history) for
                        // every customer — that was the ~800ms `select * from customers`.
                        'customer:id,name,code,person_id,virtual_customer_code'
                    ])
                        // customer_id is a FK with referential integrity and Customer
                        // is not soft-deleted, so a non-null customer_id guarantees the
                        // customer exists — replace the per-vend EXISTS(customers)
                        // semi-join (has('customer')) with a plain NOT NULL check.
                        // Same optimisation already applied in OpsJobController.
                        ->whereNotNull('customer_id')
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
            // Smart-freezer planogram flag. Optional; defaults to false at the
            // DB layer. The UI sends it from the create modal radio.
            'is_smart' => ['nullable', 'boolean'],
            // basket_layout_json is set later from the Edit page once the
            // mapping exists — not required at create time.
        ]);

        $productMapping = new ProductMapping();
        $productMapping->fill($request->all());
        $productMapping->operator_id = auth()->user()->operator_id;

        // Seed a sensible default basket layout for smart-freezer mappings so
        // the Edit page can render the grid immediately. Six baskets, each with
        // two divisions (numeric slots 1 & 2, e.g. "11"/"12") — the common
        // physical shape for our smart freezers. Users can set divisions 1-4
        // per basket on the Edit UI to match the real unit. Vending stays null.
        if ($productMapping->is_smart && empty($productMapping->basket_layout_json)) {
            $productMapping->basket_layout_json = collect(range(1, 6))
                ->map(fn ($basket) => ['basket' => $basket, 'divisions' => 2])
                ->all();
        }

        $productMapping->save();

        // Land the user straight on the new mapping's Edit page so they can
        // start binding products immediately — especially important for smart
        // freezers, where the basket grid is the whole reason they came here.
        // Mirrors the redirect target `update()` already uses on save.
        return redirect()->route('product-mappings.edit', ['id' => $productMapping->id]);
    }


    public function createItem(Request $request, $productMappingId)
    {
        $validated = $request->validate([
            'channel_code' => ['required'],
            'product_id' => ['required', 'exists:products,id'],
            'sequence' => ['nullable', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($validated, $productMappingId) {
            // Smart-freezer channel_code is a physical slot address — one product
            // per slot. Reject a second binding to a channel_code already used in
            // this planogram (e.g. two products both on "61"). Vending mappings
            // are left untouched.
            $mapping = ProductMapping::find($productMappingId);
            if ($mapping && $mapping->is_smart) {
                $duplicate = ProductMappingItem::where('product_mapping_id', $productMappingId)
                    ->where('channel_code', $validated['channel_code'])
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'channel_code' => "Channel {$validated['channel_code']} is already used in this smart-freezer planogram. Each slot can hold only one product.",
                    ]);
                }
            }

            // Normalize seq: ensure null or >=1 int
            $seq = array_key_exists('sequence', $validated)
                ? ($validated['sequence'] !== null ? (int) $validated['sequence'] : null)
                : null;

            // Create item without risky mass-assign
            $item = new ProductMappingItem();
            $item->product_mapping_id = $productMappingId;
            $item->channel_code = $validated['channel_code'];
            $item->product_id = $validated['product_id'];
            $item->sequence = null; // set after clearing others
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

    public function deleteItem($productMappingItemID)
    {
        $item = ProductMappingItem::findOrFail($productMappingItemID);
        $productMappingId = $item->product_mapping_id;
        $item->delete();

        return redirect()->back();
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
        $sortKey = $request->input('sortKey');                // 'sequence' | 'channel_code' | null
        $sortDesc = filter_var($request->input('sortBy'), FILTER_VALIDATE_BOOLEAN); // bool
        $dir = $sortDesc ? 'DESC' : 'ASC';

        if (!in_array($sortKey, ['sequence', 'channel_code'])) {
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
            'productMappingItemsNormalSequence.product:id,code,name,is_active,is_parent_sku,category_id,category_group_id',
            'productMappingItemsNormalSequence.product.thumbnail',
            'productMappingItemsNormalSequence.product.category',
            'productMappingItemsNormalSequence.product.categoryGroup',
            'productMappingItemsNormalSequence.product.sellingPrices' => function ($query) use ($request) {
                if ($request->selling_price_type) {
                    $query->where('type', $request->selling_price_type);
                }
            },
            // Blind SKU: read-only flavour summary under a parent row (defined on
            // the product; shown here for visibility, edited in Product → Edit).
            'productMappingItemsNormalSequence.product.blindChildren.childProduct:id,code,name',
            'productMappingItemsNormalSequence.product.blindChildren.childProduct.thumbnail',
            'upcomingProductMappings',
            'upcomingProductMapping',
            'vends:id,code,name,product_mapping_id,customer_id,vend_prefix_id,binded_at,updated_at',
            'vends.customer:id,code,name,person_id,virtual_customer_prefix,virtual_customer_code',
            'vends.vendPrefix:id,name',
        ])->findOrFail($id);

        return Inertia::render('ProductMapping/Edit', [
            'priceTypeOptions' => SellingPrice::TYPE_MAPPINGS,
            'operatorOptions' => OperatorResource::collection(Operator::active()->orderBy('name')->get()),
            'productMapping' => ProductMappingResource::make($productMapping),
            'products' => ProductResource::collection(
                Product::with(['thumbnail'])
                    ->where('is_active', true)
                    // Channel-facing products: always active + stocked SKUs.
                    // Parent-SKU "blind housings" only make sense on smart-freezer
                    // planograms, so keep them out of vending-machine dropdowns
                    // (they read as inactive/irrelevant options there).
                    ->where(function ($q) use ($productMapping) {
                        $q->where('is_inventory', true);
                        if ($productMapping->is_smart) {
                            $q->orWhere('is_parent_sku', true);
                        }
                    })
                    ->orderBy('code')
                    ->get()
            ),
            'upcomingProductMappingOptions' => ProductMappingResource::collection(
                ProductMapping::where('id', '!=', $id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
            ),
            // send current sort back so the header arrows know what to show
            'sortKey' => $sortKey,
            'sortBy' => $sortDesc,
        ]);
    }

    public function update(Request $request, $productMappingId)
    {
        $request->validate([
            'name' => 'required',
            'upcoming_product_mapping_id' => [
                'nullable',
                'not_in:' . $productMappingId,
            ],
            'upcoming_product_mapping_start_date' => ['nullable', 'date'],
            // Smart-freezer planogram fields. is_smart can be toggled on Edit
            // (cheap migration of mapping type); basket_layout_json is the
            // per-basket division shape sent by the SmartFreezerLayout grid.
            'is_smart' => ['nullable', 'boolean'],
            'basket_layout_json' => ['nullable', 'array'],
            'basket_layout_json.*.basket' => ['required_with:basket_layout_json', 'integer', 'min:1'],
            'basket_layout_json.*.divisions' => ['required_with:basket_layout_json', 'integer', 'min:0', 'max:26'],
        ], [
            'upcoming_product_mapping_id.not_in' => 'Upcoming product mapping cannot be the same as the current product mapping.',
        ]);
        // $request->merge([
        //     'is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN),
        // ]);
        $productMapping = ProductMapping::findOrFail($productMappingId);
        $productMapping->fill($request->all());

        // Normalise empty string → null so the relationship is truly cleared
        // (the frontend sends '' when the user picks "--- Clear ---")
        if (empty($request->upcoming_product_mapping_id)) {
            $productMapping->upcoming_product_mapping_id = null;
            // No upcoming mapping => start date is meaningless; clear it too.
            $productMapping->upcoming_product_mapping_start_date = null;
        } elseif (empty($request->upcoming_product_mapping_start_date)) {
            // Cleared / never set: store null rather than an empty string.
            $productMapping->upcoming_product_mapping_start_date = null;
        }

        if ($request->productMappingItems) {
            // Smart freezers: one product per physical slot. Block a bulk save
            // that carries the same channel_code twice before we wipe + recreate.
            if ($productMapping->is_smart) {
                $codes = collect($request->productMappingItems)
                    ->pluck('channel_code')
                    ->map(fn ($c) => (string) $c);
                $dupes = $codes->duplicates()->unique()->values();
                if ($dupes->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'productMappingItems' => 'Duplicate channel(s) ' . $dupes->implode(', ') . ' — each smart-freezer slot can hold only one product.',
                    ]);
                }
            }

            $productMapping->productMappingItems()->delete();
            foreach ($request->productMappingItems as $productMappingItem) {
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
            'sequence' => ['nullable', 'integer', 'min:1'],
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

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/product-mappings';
            $storedPath = $files->storePublicly('sys/product-mappings');
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $productMapping->attachments()->create([
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }

    public function delete($productMappingId)
    {
        $productMapping = ProductMapping::withoutGlobalScopes()->findOrFail($productMappingId);

        if (!$productMapping->operator_id) {
            return redirect()->route('product-mappings')->withErrors([
                'delete' => 'Global Product Mappings cannot be deleted.',
            ]);
        }

        $productMapping->productMappingItems()->delete();
        $productMapping->delete();

        return redirect()->route('product-mappings');
    }

    public function replicate(Request $request)
    {
        $productMapping = ProductMapping::withoutGlobalScopes()
            ->with(['productMappingItems', 'attachments'])
            ->findOrFail($request->id);

        return DB::transaction(function () use ($productMapping) {
            $replicated = $productMapping->replicate()->fill([
                'name' => $productMapping->name . '-replicated',
                'operator_id' => auth()->user()->operator_id,
            ]);
            $replicated->save();

            // Replicate the channel items (carry over display sequence and
            // selling price level so the copy matches the source).
            foreach ($productMapping->productMappingItems as $productMappingItem) {
                ProductMappingItem::create([
                    'channel_code' => $productMappingItem->channel_code,
                    'product_id' => $productMappingItem->product_id,
                    'selling_price_id' => $productMappingItem->selling_price_id,
                    'sequence' => $productMappingItem->sequence,
                    'product_mapping_id' => $replicated->id,
                ]);
            }

            // Replicate the attachments. We copy the underlying file to a new
            // path so the original and the replica stay independent (deleting an
            // attachment hard-deletes its file). The file name (name) and the
            // price level (type) are carried over.
            foreach ($productMapping->attachments as $attachment) {
                $localUrl = $attachment->local_url;
                $fullUrl = $attachment->full_url;

                if ($attachment->local_url && Storage::disk('public')->exists($attachment->local_url)) {
                    $dir = trim(dirname($attachment->local_url), '.');
                    $dir = $dir !== '' ? $dir : 'sys/product-mappings';
                    $extension = pathinfo($attachment->local_url, PATHINFO_EXTENSION);
                    $newFileName = Str::random(40) . ($extension ? '.' . $extension : '');
                    $newLocalUrl = $dir . '/' . $newFileName;

                    Storage::disk('public')->copy($attachment->local_url, $newLocalUrl);

                    $localUrl = $newLocalUrl;
                    $fullUrl = Storage::disk('public')->url($newLocalUrl);
                }

                $replicated->attachments()->create([
                    'local_url' => $localUrl,
                    'full_url' => $fullUrl,
                    'name' => $attachment->name,
                    'type' => $attachment->type,
                    'sequence' => $attachment->sequence,
                    'desc' => $attachment->desc,
                    'is_active' => $attachment->is_active,
                ]);
            }

            return redirect()->route('product-mappings.edit', ['id' => $replicated->id]);
        });
    }

    public function bindVends(Request $request, $productMappingId)
    {
        $productMapping = ProductMapping::findOrFail($productMappingId);
        
        $requestedVendIds = collect($request->productMappingVends)->pluck('id')->toArray();
        $existingVends = $productMapping->vends;
        $existingVendIds = $existingVends->pluck('id')->toArray();

        $vendsToRemoveIds = array_diff($existingVendIds, $requestedVendIds);
        $vendsToAddIds = array_diff($requestedVendIds, $existingVendIds);
        $vendsToKeepIds = array_intersect($existingVendIds, $requestedVendIds);

        // 1. Unbind removed vends
        if (!empty($vendsToRemoveIds)) {
            $vendsToRemove = Vend::whereIn('id', $vendsToRemoveIds)->get();
            $this->unbindProductFromChannels($vendsToRemove);
            Vend::whereIn('id', $vendsToRemoveIds)->update([
                'product_mapping_id' => null,
                'upcoming_product_mapping_id' => null,
                'binded_at' => null
            ]);
        }

        // Guard: upcoming must never equal the product mapping's own ID when cascading to vends
        $safeUpcomingId = ($productMapping->upcoming_product_mapping_id != $productMapping->id)
            ? $productMapping->upcoming_product_mapping_id
            : null;

        // 2. Add new vends
        if (!empty($vendsToAddIds)) {
            Vend::whereIn('id', $vendsToAddIds)->update([
                'product_mapping_id' => $productMapping->id,
                'upcoming_product_mapping_id' => $safeUpcomingId,
                'binded_at' => now()
            ]);
        }

        // 3. Keep existing vends, only update upcoming mapping in case mapping itself changed
        if (!empty($vendsToKeepIds)) {
            Vend::whereIn('id', $vendsToKeepIds)->update([
                'upcoming_product_mapping_id' => $safeUpcomingId
            ]);
        }

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
        if ($vends) {
            foreach ($vends as $vend) {
                $vendData = Vend::findOrFail($vend->id);
                $vendData->vendChannels()->update(['product_id' => null]);
            }
        }
    }
}
