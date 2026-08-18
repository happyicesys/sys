<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductMappingResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VendPrefixResource;
use App\Http\Resources\VendResource;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendPrefix;
use App\Services\ProductMappingService;
use App\Services\SmartFreezerCatalogPush;
use App\Services\VendJobService;
use App\Support\SiteSearch;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductMappingController extends Controller
{
    private $productMappingService;

    /**
     * Pushes a "re-pull your menu" MQTT nudge to bound Smart-Freezer terminals after
     * ANY planogram write on this controller (Save, bind, unbind, reorder, sequence,
     * smart toggle, machine re-bind). No-op for vending mappings. See
     * {@see \App\Services\SmartFreezerCatalogPush} and {@see nudgeSmartFreezers()}.
     */
    private $smartFreezerCatalogPush;

    private $vendJobService;

    public function __construct()
    {
        $this->middleware(['permission:read product-mappings']);
        $this->productMappingService = new ProductMappingService;
        $this->smartFreezerCatalogPush = new SmartFreezerCatalogPush;
        $this->vendJobService = new VendJobService;
    }

    /**
     * Fire-and-forget catalog nudge for the incremental (non-Save) planogram write
     * paths — bind, unbind, channel edit, sequence edit, basket reorder, smart toggle.
     *
     * These endpoints commit immediately and page-level Save is optional, so without
     * this a freezer stays stale whenever the operator edits the grid and closes the
     * tab. Result is intentionally ignored: each of these has its own UI toast, the
     * service logs its own failures, and a nudge must never fail an already-committed
     * write. Save (update()) is the one path that reports the push outcome.
     *
     * MUST be called AFTER any surrounding DB::transaction has committed — the queue
     * is redis, so a worker can publish before an open transaction commits and the
     * device would re-pull the OLD menu.
     */
    private function nudgeSmartFreezers($productMappingId): void
    {
        $mapping = ProductMapping::find($productMappingId);

        if ($mapping) {
            $this->smartFreezerCatalogPush->pushForMapping($mapping);
        }
    }

    public function index(Request $request)
    {
        // dd($request->all());
        $request->merge([
            'is_active' => $request->is_active ? $request->is_active : true,
            'vendStatus' => $request->vendStatus ? $request->vendStatus : 'active',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 5,
            'sortBy' => $request->sortBy ? $request->sortBy : true,
            // DEPRECATED sort keys — map stale/bookmarked URLs back to `name` so the
            // orderBy below never references a column/alias that no longer exists
            // (MySQL would throw "Unknown column ... in order clause"):
            //   vend_prefix_name        (2026-07) retired with the Binded Prefix column
            //   avg_mthly_sales_amount  (2026-07-31) retired with the Avg Mthly Sales
            //                           group total — the column is now a per-machine
            //                           list, which has no single value to sort on
            'sortKey' => $request->sortKey && ! in_array($request->sortKey, ['vend_prefix_name', 'avg_mthly_sales_amount'], true) ? $request->sortKey : 'name',
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
            // Site filter — restrict to mappings whose binded machines sit at a
            // matching site (customer). Mirrors the "Site" box everywhere else:
            // Site Name, virtual code/prefix, CMS code or the displayed Site ID.
            // See App\Support\SiteSearch.
            ->when($request->site, function ($query, $search) {
                $query->whereHas('vends.customer', fn ($customer) => SiteSearch::for($search)->applyTo($customer));
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
            ->when($request->is_active, function ($query, $search) {
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

        // Correlated sub-select feeding the "N Machine(s) at upcoming stage"
        // figure on every row — see the long note at the ->addSelect() below for
        // why it resolves the EFFECTIVE upcoming mapping instead of reading
        // vends.upcoming_product_mapping_id alone.
        //
        // ->toBase() is deliberate: Vend carries the OperatorVendFilterScope
        // global scope, and handing a raw Eloquent builder to addSelect() would
        // compile the scope's SQL from a scoped clone while taking the bindings
        // from the unscoped original (Query\Builder::parseSub) — the scope's
        // operator_id placeholder would then have no binding for operator users.
        // toBase() applies the scopes and returns that same builder, so SQL and
        // bindings stay in step.
        $upcomingVendsCountSub = Vend::query()
            ->selectRaw('count(*)')
            ->leftJoin(
                'product_mappings as current_product_mappings',
                'current_product_mappings.id',
                '=',
                'vends.product_mapping_id'
            )
            ->whereRaw('coalesce(vends.upcoming_product_mapping_id, current_product_mappings.upcoming_product_mapping_id) = product_mappings.id')
            ->whereRaw('(vends.product_mapping_id is null or vends.product_mapping_id <> product_mappings.id)')
            ->when($request->vendStatus && $request->vendStatus !== 'all', function ($query) use ($request) {
                switch ($request->vendStatus) {
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
            })
            ->toBase();

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
                                ->orderByRaw('CAST(channel_code AS UNSIGNED) ASC')
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
                            $query->select('id', 'code', 'name', 'product_mapping_id', 'upcoming_product_mapping_id', 'customer_id', 'vend_prefix_id', 'is_active', 'is_testing', 'is_disposed', 'binded_at', 'updated_at', 'vend_transaction_totals_json');

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
                        // begin_date — the denominator of the per-machine "Avg Mthly
                        // Sales" column (lifetime sales / months the SITE has been
                        // operating). Same pair of columns Vend/CustomerIndex selects
                        // for its own "Avg Mthly Sales $" (customers.totals_json +
                        // customers.begin_date), so both pages show the same figure for
                        // the same machine. MUST be selected here: the Vue reads
                        // customer.begin_date_nullable, and a column left out of a
                        // partial eager-load resolves to null → the reporting floor.
                        'vends.customer:id,code,is_active,name,person_id,virtual_customer_prefix,virtual_customer_code,selling_price_type,totals_json,begin_date',
                        'vends.vendPrefix:id,name',
                        'vends.deliveryProductMappingVends:id,vend_id,delivery_product_mapping_id',
                        'vends.deliveryProductMappingVends.deliveryProductMapping:id,delivery_platform_operator_id',
                        'vends.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator:id,delivery_platform_id',
                        'vends.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform:id,name',
                        // Each binded machine's OWN upcoming product mapping (the vend
                        // may override this mapping's preset upcoming). Only id+name are
                        // needed to render the per-vend override badge in Index.vue.
                        'vends.upcomingProductMapping:id,name',
                        // DEPRECATED (2026-07): 'vendPrefixes' eager-load dropped with
                        // the Binded Prefix column (ProductMappingResource guards with
                        // whenLoaded()).
                        'upcomingProductMapping',
                        // The preset upcoming mapping's own channel rows, so the
                        // "Channel - Product" column can diff each channel of THIS
                        // mapping against what it becomes at changeover (yellow "!"
                        // = product changes, red "!" = channel disappears). Only the
                        // three product scalars the tooltip prints are selected.
                        'upcomingProductMapping.productMappingItemsNormalSequence',
                        'upcomingProductMapping.productMappingItemsNormalSequence.product:id,code,name',
                    ])

                    ->select('product_mappings.*')
                    // "At upcoming stage" count — machines queued to switch ONTO
                    // this mapping but not updated to it yet.
                    //
                    // FIX (2026-07-30): this used to count ONLY vends whose own
                    // `vends.upcoming_product_mapping_id` = this mapping. That
                    // column is written solely by the vend-binding paths
                    // (bindVends / Machine Settings), so a machine inherits its
                    // changeover from its CURRENT mapping's preset
                    // (`product_mappings.upcoming_product_mapping_id`) and very
                    // often has NULL of its own — those machines were invisible
                    // here and the cell read "0 Machine(s) at upcoming stage"
                    // even when whole mappings were queued to move onto this one.
                    //
                    // Now we count on the EFFECTIVE upcoming mapping, the same
                    // resolution order the rest of the app already uses (see
                    // OpsJobItem::resolveUpcomingMapping, Vend/CustomerIndex.vue,
                    // OpsJob/Edit.vue): the vend's own upcoming wins, otherwise
                    // fall back to its current mapping's preset upcoming.
                    //   COALESCE(vends.upcoming_product_mapping_id,
                    //            <current mapping>.upcoming_product_mapping_id)
                    // A vend already binded to this mapping can never be
                    // "pending" onto it, so it is excluded outright (belt and
                    // braces — update() forbids self-referencing upcoming and
                    // CleanUpcomingProductMapping nulls any stale self-refs).
                    //
                    // Mirrors the same vendStatus filter used by the binded
                    // `vends` eager-load above so both figures in the cell are
                    // counted on the same population. Columns are table-qualified
                    // because of the `current_product_mappings` self-join (both
                    // tables carry is_active). MUST stay after ->select() —
                    // select() resets the column list and would drop this
                    // count subquery.
                    ->addSelect(['upcoming_vends_count' => $upcomingVendsCountSub])
                    // NOTE (2026-07-31): the "Avg Mthly Sales" column used to add a
                    // second sub-select here — one summed figure per mapping over all
                    // its binded sites, which is what made the header sortable. Ops
                    // read that total as a per-machine number, so the column now shows
                    // ONE FIGURE PER BINDED MACHINE, derived client-side in
                    // ProductMapping/Index.vue from the site totals already
                    // eager-loaded above (exactly like Vend/CustomerIndex.vue). A
                    // per-machine list can't be a sort key, so the sub-select, the
                    // avg_mthly_sales_amount alias and the sortable header all went
                    // away together.
                    ->orderBy($request->sortKey, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc')
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()

            ),
            'products' => ProductResource::collection(
                Product::with([
                    'thumbnail',
                ])
                    ->where('is_inventory', true)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get()
            ),
            'unbindedVends' => fn () => VendResource::collection(
                Vend::with([
                    // This unbinded-vends dropdown only renders full_name (built
                    // from customer code/name/person_id/virtual_customer_code) and
                    // the nested customer.code / customer.name. Load just those
                    // scalars instead of select * dragging customers' JSON columns
                    // (totals_json, person_json, snap_*, cms_invoice_history) for
                    // every customer — that was the ~800ms `select * from customers`.
                    'customer:id,name,code,person_id,virtual_customer_code',
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

    /**
     * The machines behind the "N Machine(s) at upcoming stage" figure on the
     * Product Mapping Index — fetched on demand when ops clicks that line.
     *
     * The WHERE here is the row-level twin of index()'s $upcomingVendsCountSub:
     * same EFFECTIVE-upcoming resolution (the vend's own Upcoming Product
     * Mapping wins, else the preset inherited from the mapping it is binded to
     * today), same "already on this mapping can't be pending onto it" guard, and
     * the same vendStatus filter — which the frontend replays from the page's
     * current filter. If those two ever drift, the popup would list a different
     * number of machines than the link that opened it, so they must be changed
     * together.
     *
     * Loaded lazily rather than shipped with the page: this index already sends
     * every mapping's full binded-machine list, and inlining a second list per
     * row would grow a payload that has previously hit transport limits (see the
     * OpsJob edit() note about oversized Inertia pages).
     *
     * Read-only. Vend's OperatorVendFilterScope still applies (this goes through
     * Vend::query(), not a raw builder), so operator users only ever see their
     * own machines — and the whole controller is behind
     * `permission:read product-mappings` via the constructor.
     */
    public function upcomingVends(Request $request, $id)
    {
        $productMapping = ProductMapping::findOrFail($id);

        $vends = Vend::query()
            ->leftJoin(
                'product_mappings as current_product_mappings',
                'current_product_mappings.id',
                '=',
                'vends.product_mapping_id'
            )
            ->whereRaw(
                'coalesce(vends.upcoming_product_mapping_id, current_product_mappings.upcoming_product_mapping_id) = ?',
                [$productMapping->id]
            )
            ->whereRaw(
                '(vends.product_mapping_id is null or vends.product_mapping_id <> ?)',
                [$productMapping->id]
            )
            ->when($request->vendStatus && $request->vendStatus !== 'all', function ($query) use ($request) {
                switch ($request->vendStatus) {
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
            })
            ->with([
                // Only what the popup renders: Site ID (id + 20000, the display
                // code that replaced virtual_customer_code), site name and
                // whether the site is still active (greys the name out).
                'customer:id,name,is_active',
                'vendPrefix:id,name',
            ])
            ->select(
                'vends.id',
                'vends.code',
                'vends.customer_id',
                'vends.product_mapping_id',
                'vends.upcoming_product_mapping_id',
                'vends.vend_prefix_id',
                'vends.is_active',
                // Straight off the join — the mapping the machine is on TODAY,
                // plus that mapping's rollout start date. The start date lives
                // ONLY on the mapping (there is no per-vend one), and it gates
                // the changeover for own and inherited alike
                // (ProductMapping::isUpcomingMappingEffective), which is why it
                // is shown for both.
                'current_product_mappings.name as current_product_mapping_name',
                'current_product_mappings.upcoming_product_mapping_start_date as current_upcoming_start_date'
            )
            ->orderBy('vends.code')
            ->get();

        return response()->json([
            'productMapping' => [
                'id' => $productMapping->id,
                'name' => $productMapping->name,
            ],
            'vends' => $vends->map(function ($vend) {
                // "own" = this machine was pointed at the mapping directly on its
                // Machine Settings page; "inherited" = it simply rides the preset
                // upcoming of the mapping it is binded to. Safe to decide on the
                // vend column alone: coalesce() only falls through to the preset
                // when that column is null, so a non-null value that survived the
                // WHERE can only be this mapping.
                $isOwn = $vend->upcoming_product_mapping_id !== null;

                return [
                    'id' => $vend->id,
                    'code' => $vend->code,
                    'is_active' => (bool) $vend->is_active,
                    'vend_prefix_name' => $vend->vendPrefix?->name,
                    'customer_id' => $vend->customer?->id,
                    // Site ID = customers.id + 20000 (see the site-id display
                    // swap); null when the machine sits in no site at all.
                    'site_id' => $vend->customer ? $vend->customer->id + 20000 : null,
                    'customer_name' => $vend->customer?->name,
                    'customer_is_active' => (bool) ($vend->customer?->is_active),
                    'current_product_mapping_id' => $vend->product_mapping_id,
                    'current_product_mapping_name' => $vend->current_product_mapping_name,
                    'source' => $isOwn ? 'own' : 'inherited',
                    'start_date' => $vend->current_upcoming_start_date
                        ? \Illuminate\Support\Carbon::parse($vend->current_upcoming_start_date)->format('Y-m-d')
                        : null,
                ];
            })->values(),
        ]);
    }

    /**
     * Mapping names must be unique among the mappings the caller can see.
     *
     * Two mappings sharing a name is how the 2026-08-15 "Mindef 2608 Jelly"
     * mix-up happened: the wrong twin got preset as an upcoming mapping and ops
     * saw the right NAME with the wrong remarks in the job. Names are the only
     * handle users have in every dropdown, so a duplicate is never intentional.
     *
     * Runs on the scoped ProductMapping query so the boundary is exactly what
     * the user sees (own operator + shared null-operator rows; everything for
     * Happy Ice). Trailing/leading whitespace is ignored and the compare is
     * case-insensitive (utf8mb4_unicode_ci), so "Foo " cannot sneak past "foo".
     * Inactive mappings count too — they still appear in history and reports.
     */
    private function uniqueNameRule(?int $ignoreId = null): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($ignoreId) {
            $name = trim((string) $value);
            if ($name === '') {
                return; // 'required' reports the empty case
            }

            $clash = ProductMapping::query()
                ->where('name', $name)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->first(['id', 'is_active']);

            if ($clash) {
                $fail(sprintf(
                    'A product mapping named "%s" already exists (#%d%s). Pick a distinct name — duplicates get mixed up when preset as upcoming mappings.',
                    $name,
                    $clash->id,
                    $clash->is_active ? '' : ', inactive'
                ));
            }
        };
    }

    public function create(Request $request)
    {
        $request->merge(['name' => trim((string) $request->name)]);
        $request->validate([
            'name' => ['required', $this->uniqueNameRule()],
            // Smart-freezer planogram flag. Optional; defaults to false at the
            // DB layer. The UI sends it from the create modal radio.
            'is_smart' => ['nullable', 'boolean'],
            // Machine taxonomy; the create modal sends this (3-way radio). is_smart is derived.
            'machine_type' => ['nullable', 'in:vending_machine,smart_freezer,smart_chiller'],
            // basket_layout_json is set later from the Edit page once the
            // mapping exists — not required at create time.
        ]);

        $productMapping = new ProductMapping;
        $productMapping->fill($request->all());
        $productMapping->operator_id = auth()->user()->operator_id;
        $this->syncMachineType($productMapping, $request);
        // upcoming_product_mapping_id is fillable, so an API caller can preset it at create.
        $this->assertPresetUpcomingCompatible($productMapping);

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

    /**
     * Flip a mapping between Vending and Smart Freezer type (the editor mode).
     *
     * When converting an existing mapping TO smart, seed basket_layout_json from
     * the channel codes already bound so the grid opens fully shaped — a code
     * like "64" means basket 6 needs 4 divisions. Baskets with no items fall
     * back to 2 divisions (the create-time default). Converting back to vending
     * leaves the layout untouched (harmless; the vending editor ignores it).
     */
    /**
     * machine_type ↔ is_smart invariant: is_smart stays the freezer-planogram switch the Edit UI
     * and getVendMenu branch on; machine_type is the taxonomy (VM / freezer / chiller). Whichever
     * field the caller sent, derive the other so the two can never drift.
     */
    private function syncMachineType(ProductMapping $productMapping, Request $request): void
    {
        if ($request->filled('machine_type')) {
            $productMapping->is_smart = $request->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER;
        } elseif ($request->has('is_smart')) {
            $productMapping->machine_type = $request->boolean('is_smart')
                ? Vend::MACHINE_TYPE_SMART_FREEZER
                : Vend::MACHINE_TYPE_VENDING_MACHINE;
        }
    }

    /**
     * Block a machine_type change while machines of the OLD kind are still on this mapping
     * (bound as current, or queued as upcoming). Without this, retyping the mapping would
     * mis-pair every bound machine in one write — the exact mismatch the vend-side bind guard
     * exists to prevent, arriving through the back door. Disposed/sold machines don't block;
     * inactive ones do (China freezers/chillers stay bound for life, active or not).
     * Call after machine_type has been set on the (unsaved) model.
     */
    private function assertMachineTypeChangeAllowed(ProductMapping $productMapping): void
    {
        $originalType = $productMapping->getOriginal('machine_type') ?: Vend::MACHINE_TYPE_VENDING_MACHINE;
        $newType = $productMapping->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE;
        if (! $productMapping->exists || $newType === $originalType) {
            return;
        }

        $mismatched = Vend::withoutGlobalScopes()
            ->where(function ($query) use ($productMapping) {
                $query->where('product_mapping_id', $productMapping->id)
                    ->orWhere('upcoming_product_mapping_id', $productMapping->id);
            })
            ->where('is_disposed', false)
            ->where('is_sold', false)
            ->whereRaw("coalesce(machine_type, 'vending_machine') != ?", [$newType])
            ->orderBy('code')
            ->get(['id', 'code', 'machine_type']);

        if ($mismatched->isNotEmpty()) {
            throw ValidationException::withMessages([
                'machine_type' => sprintf(
                    'Cannot change this mapping to %s — %d machine(s) of another kind are still on it (%s%s). Move them to a matching mapping, or change their Machine Type first.',
                    Vend::MACHINE_TYPE_MAPPINGS[$newType] ?? $newType,
                    $mismatched->count(),
                    $mismatched->take(5)->pluck('code')->implode(', '),
                    $mismatched->count() > 5 ? ', …' : ''
                ),
            ]);
        }

        // Mirror direction: other mappings PRESETTING this one as their upcoming. Retyping
        // this mapping would leave them holding a cross-kind preset — the exact state
        // assertPresetUpcomingCompatible forbids — which every changeover then silently
        // drops/skips, stalling the fleet's scheduled mapping change with only a log line.
        $presetters = ProductMapping::withoutGlobalScopes()
            ->where('upcoming_product_mapping_id', $productMapping->id)
            ->where('name', '!=', 'N/A')
            ->whereRaw("coalesce(machine_type, 'vending_machine') != ?", [$newType])
            ->orderBy('name')
            ->get(['id', 'name', 'machine_type']);

        if ($presetters->isNotEmpty()) {
            throw ValidationException::withMessages([
                'machine_type' => sprintf(
                    'Cannot change this mapping to %s — %d mapping(s) of another kind preset it as their upcoming mapping (%s%s). Clear or retarget those presets first.',
                    Vend::MACHINE_TYPE_MAPPINGS[$newType] ?? $newType,
                    $presetters->count(),
                    $presetters->take(5)->pluck('name')->implode(', '),
                    $presetters->count() > 5 ? ', …' : ''
                ),
            ]);
        }
    }

    /**
     * A mapping's preset upcoming must be for the same machine kind as the mapping itself —
     * the preset flows onto every bound vend at changeover (VendController fallback merge,
     * replaceProductMapping, the OpsJob advance), so a cross-kind preset would queue a
     * mismatch fleet-wide. N/A placeholder presets are machine-agnostic and allowed.
     */
    private function assertPresetUpcomingCompatible(ProductMapping $productMapping): void
    {
        if (! $productMapping->upcoming_product_mapping_id) {
            return;
        }

        $upcoming = ProductMapping::withoutGlobalScopes()->find($productMapping->upcoming_product_mapping_id);
        if (! $upcoming || $upcoming->name === 'N/A') {
            return;
        }

        $ownType = $productMapping->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE;
        $upcomingType = $upcoming->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE;
        if ($upcomingType === $ownType) {
            return;
        }

        throw ValidationException::withMessages([
            'upcoming_product_mapping_id' => sprintf(
                'Upcoming mapping "%s" is a %s mapping, but this is a %s mapping. The preset upcoming must be built for the same machine kind.',
                $upcoming->name,
                Vend::MACHINE_TYPE_MAPPINGS[$upcomingType] ?? $upcomingType,
                Vend::MACHINE_TYPE_MAPPINGS[$ownType] ?? $ownType
            ),
        ]);
    }

    public function toggleSmart($id)
    {
        $productMapping = ProductMapping::with('productMappingItems')->findOrFail($id);

        // The freezer-planogram toggle has no meaning for a chiller mapping (chiller layout rules
        // — multiple products per level — are a different editor, not this flip).
        if (($productMapping->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE) === Vend::MACHINE_TYPE_SMART_CHILLER) {
            throw ValidationException::withMessages([
                'is_smart' => 'This is a Smart Chiller mapping — the Smart Freezer planogram toggle does not apply to it.',
            ]);
        }

        // Converting vending → smart: a planogram can't hold two products on one
        // slot, so refuse if the existing items already have duplicate channel
        // codes (e.g. a vending mapping with two "61" rows). The user resolves
        // them in the vending table first, then converts cleanly.
        if (! $productMapping->is_smart) {
            $dupes = $this->duplicateChannelCodes($productMapping->productMappingItems->pluck('channel_code'));
            if (! empty($dupes)) {
                throw ValidationException::withMessages([
                    'is_smart' => 'Cannot convert to Smart Freezer: duplicate channel(s) '.implode(', ', $dupes).'. Resolve them in the vending table first — a planogram needs one product per channel.',
                ]);
            }
        }

        $productMapping->is_smart = ! $productMapping->is_smart;
        $productMapping->machine_type = $productMapping->is_smart
            ? Vend::MACHINE_TYPE_SMART_FREEZER
            : Vend::MACHINE_TYPE_VENDING_MACHINE;
        // Same back-door as an Edit-page retype: flipping while machines of the old kind are
        // bound would mis-pair all of them at once.
        $this->assertMachineTypeChangeAllowed($productMapping);
        $this->assertPresetUpcomingCompatible($productMapping);

        if ($productMapping->is_smart && empty($productMapping->basket_layout_json)) {
            $productMapping->basket_layout_json = $this->deriveBasketLayout($productMapping->productMappingItems);
        }

        $productMapping->save();

        $this->nudgeSmartFreezers($productMapping->id);

        return redirect()->back();
    }

    /**
     * Shape a 6-basket layout from bound channel codes. Numeric "<basket><division>"
     * codes (e.g. "11","64") set that basket's division count to the highest
     * division seen (clamped 1-4); baskets with no numeric code default to 2.
     */
    private function deriveBasketLayout($items)
    {
        $maxDivision = [];
        foreach ($items as $item) {
            $code = (string) $item->channel_code;
            if (ctype_digit($code) && strlen($code) === 2) {
                $basket = (int) $code[0];
                $division = (int) $code[1];
                if ($basket >= 1 && $basket <= 6 && $division >= 1 && $division <= 4) {
                    $maxDivision[$basket] = max($maxDivision[$basket] ?? 0, $division);
                }
            }
        }

        return collect(range(1, 6))
            ->map(fn ($basket) => [
                'basket' => $basket,
                'divisions' => $maxDivision[$basket] ?? 2,
            ])
            ->all();
    }

    /**
     * PLANOGRAM VALIDATION #1 — channel-code uniqueness.
     *
     * A smart-freezer planogram is a physical map: one product per slot, so no
     * two items may declare the same channel_code within the mapping. This is the
     * single choke point every write path (create / edit item / bulk save)
     * routes through, so a duplicate can never enter the planogram regardless of
     * how the item was submitted. Vending mappings are exempt — their channel
     * rules differ and legacy data may already hold duplicates.
     *
     * @param  int|null  $ignoreItemId  item to exclude (its own row, when editing).
     *
     * @throws ValidationException when $channelCode already exists on another item.
     */
    private function assertUniqueChannelCode(ProductMapping $mapping, string $channelCode, $ignoreItemId = null): void
    {
        if (! $mapping->is_smart) {
            return;
        }

        $exists = ProductMappingItem::where('product_mapping_id', $mapping->id)
            ->where('channel_code', $channelCode)
            ->when($ignoreItemId, fn ($q) => $q->where('id', '!=', $ignoreItemId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'channel_code' => "Channel {$channelCode} is already used in this planogram. Each slot can hold only one product.",
            ]);
        }
    }

    /**
     * The channel codes that appear more than once in $codes (normalised to
     * strings). Used to reject a bulk save or a vending→smart conversion that
     * would put two products on one slot.
     */
    private function duplicateChannelCodes($codes): array
    {
        return collect($codes)
            ->map(fn ($c) => (string) $c)
            ->duplicates()
            ->unique()
            ->values()
            ->all();
    }

    public function createItem(Request $request, $productMappingId)
    {
        $validated = $request->validate([
            'channel_code' => ['required'],
            'product_id' => ['required', 'exists:products,id'],
            'sequence' => ['nullable', 'integer', 'min:1'],
        ]);

        $response = DB::transaction(function () use ($validated, $productMappingId) {
            $mapping = ProductMapping::find($productMappingId);
            if ($mapping) {
                $this->assertUniqueChannelCode($mapping, (string) $validated['channel_code']);
            }

            // Normalize seq: ensure null or >=1 int
            $seq = array_key_exists('sequence', $validated)
                ? ($validated['sequence'] !== null ? (int) $validated['sequence'] : null)
                : null;

            // Create item without risky mass-assign
            $item = new ProductMappingItem;
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

        $this->nudgeSmartFreezers($productMappingId); // after commit — see helper docblock

        return $response;
    }

    public function deleteItem($productMappingItemID)
    {
        $item = ProductMappingItem::findOrFail($productMappingItemID);
        $productMappingId = $item->product_mapping_id;
        $item->delete();

        $this->nudgeSmartFreezers($productMappingId);

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

        if (! in_array($sortKey, ['sequence', 'channel_code'])) {
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
        $request->merge(['name' => trim((string) $request->name)]);
        $request->validate([
            'name' => ['required', $this->uniqueNameRule((int) $productMappingId)],
            'upcoming_product_mapping_id' => [
                'nullable',
                'not_in:'.$productMappingId,
            ],
            'upcoming_product_mapping_start_date' => ['nullable', 'date'],
            // Smart-freezer planogram fields. is_smart can be toggled on Edit
            // (cheap migration of mapping type); basket_layout_json is the
            // per-basket division shape sent by the SmartFreezerLayout grid.
            'is_smart' => ['nullable', 'boolean'],
            'machine_type' => ['nullable', 'in:vending_machine,smart_freezer,smart_chiller'],
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
        $this->syncMachineType($productMapping, $request);
        $this->assertMachineTypeChangeAllowed($productMapping);

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
        $this->assertPresetUpcomingCompatible($productMapping);

        if ($request->productMappingItems) {
            // Smart freezers: one product per physical slot. Block a bulk save
            // that carries the same channel_code twice before we wipe + recreate.
            if ($productMapping->is_smart) {
                $dupes = $this->duplicateChannelCodes(
                    collect($request->productMappingItems)->pluck('channel_code')
                );
                if (! empty($dupes)) {
                    throw ValidationException::withMessages([
                        'productMappingItems' => 'Duplicate channel(s) '.implode(', ', $dupes).' — each smart-freezer slot can hold only one product.',
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

        // Smart freezers don't poll for menu changes, so tell any bound Smart-Freezer
        // to re-pull now instead of waiting for its next reboot. Queued + fail-safe:
        // the planogram is already committed, so a broker hiccup must not fail the save
        // (the manual "Push Products Info to Machine" button remains the fallback).
        // Vending mappings resolve to zero targets => zero behaviour change.
        ['targets' => $targets, 'pushed' => $pushed] = $this->smartFreezerCatalogPush->pushForMapping($productMapping);

        $redirect = redirect()->route('product-mappings.edit', ['id' => $productMapping->id]);

        // No bound smart freezers (the vending case) => stay silent, exactly as before.
        if ($targets === 0) {
            return $redirect;
        }

        // Some or all nudges could not even be queued: say so, and name the fallback.
        // Reporting this as plain success would leave the operator believing machines
        // were refreshed when they were not.
        if ($pushed < $targets) {
            return $redirect->with(
                'error',
                'Saved, but the menu refresh could not be sent to '.($targets - $pushed)." of {$targets} smart freezer(s). "
                .'Use "Push Products Info to Machine" on the affected machine(s).'
            );
        }

        return $redirect->with('success', "Saved. Menu refresh pushed to {$pushed} smart freezer(s).");
    }

    public function updateItem(Request $request, $productMappingItemID)
    {
        $productMappingItem = ProductMappingItem::findOrFail($productMappingItemID);

        // Same one-product-per-slot rule as create — a channel_code edit must not
        // collide with another item in a smart planogram (this row excepted).
        if ($request->filled('channel_code') && $productMappingItem->productMapping) {
            $this->assertUniqueChannelCode(
                $productMappingItem->productMapping,
                (string) $request->channel_code,
                $productMappingItem->id
            );
        }

        $productMappingItem->fill($request->all());
        $productMappingItem->save();

        $this->nudgeSmartFreezers($productMappingItem->product_mapping_id);

        return redirect()->route('product-mappings.edit', ['id' => $productMappingItem->productMapping->id]);
    }

    /**
     * Drag-and-drop reorder of the products WITHIN one basket of a smart planogram.
     *
     * Channel codes are positional and never move — the leftmost division is
     * always the basket's smallest code ("11"), the rightmost the largest. What
     * the drag changes is which product sits in each slot. The client sends the
     * new left→right product order for the basket; we reassign each channel code
     * ("{basket}1", "{basket}2", …) to the product now in that position (null =
     * clear the slot). Uniqueness holds for free — each channel code is written
     * exactly once — so this can never create a duplicate.
     */
    public function reorderBasket(Request $request, $productMappingId)
    {
        $validated = $request->validate([
            'basket' => ['required', 'integer', 'min:1', 'max:6'],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $mapping = ProductMapping::findOrFail($productMappingId);
        $basket = (int) $validated['basket'];

        $response = DB::transaction(function () use ($mapping, $basket, $validated) {
            foreach (array_values($validated['product_ids']) as $index => $productId) {
                $code = $basket.($index + 1); // "11","12","13"… — 1-indexed division.
                $item = $mapping->productMappingItems()->where('channel_code', $code)->first();

                if ($productId === null) {
                    // Slot emptied by the reorder.
                    if ($item) {
                        $item->delete();
                    }

                    continue;
                }

                if ($item) {
                    $item->product_id = $productId;
                    $item->save();
                } else {
                    $mapping->productMappingItems()->create([
                        'channel_code' => $code,
                        'product_id' => $productId,
                    ]);
                }
            }

            return redirect()->back();
        });

        $this->nudgeSmartFreezers($mapping->id);

        return $response;
    }

    public function updateItemSequence(Request $request, ProductMappingItem $item)
    {
        $data = $request->validate([
            'sequence' => ['nullable', 'integer', 'min:1'],
        ]);

        $response = DB::transaction(function () use ($item, $data) {
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

        $this->nudgeSmartFreezers($item->product_mapping_id);

        return $response;
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
                'local_url' => $dir.'/'.$fileName,
            ]);
        }

        return true;
    }

    public function delete($productMappingId)
    {
        $productMapping = ProductMapping::withoutGlobalScopes()->findOrFail($productMappingId);

        if (! $productMapping->operator_id) {
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

        // Names are unique (see uniqueNameRule); replicating the same source
        // twice must not collide, so walk "-replicated", "-replicated-2", ...
        // until free within the caller's visible mappings.
        $base = $productMapping->name.'-replicated';
        $name = $base;
        for ($n = 2; ProductMapping::query()->where('name', $name)->exists(); $n++) {
            $name = $base.'-'.$n;
        }

        return DB::transaction(function () use ($productMapping, $name) {
            $replicated = $productMapping->replicate()->fill([
                'name' => $name,
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
                    $newFileName = Str::random(40).($extension ? '.'.$extension : '');
                    $newLocalUrl = $dir.'/'.$newFileName;

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
        if (! empty($vendsToRemoveIds)) {
            $vendsToRemove = Vend::whereIn('id', $vendsToRemoveIds)->get();
            $this->unbindProductFromChannels($vendsToRemove);
            Vend::whereIn('id', $vendsToRemoveIds)->update([
                'product_mapping_id' => null,
                'upcoming_product_mapping_id' => null,
                'binded_at' => null,
            ]);
        }

        // Guard: upcoming must never equal the product mapping's own ID when cascading to vends
        $safeUpcomingId = ($productMapping->upcoming_product_mapping_id != $productMapping->id)
            ? $productMapping->upcoming_product_mapping_id
            : null;

        // Machine-type ↔ mapping guard: this bulk bind must pass the same gate as every other
        // bind path (VendController::update etc.) — without it, one save here could pair a whole
        // fleet of smart freezers with a vending planogram. N/A mappings are machine-agnostic.
        if (! empty($vendsToAddIds) && $productMapping->name !== 'N/A') {
            $mappingType = $productMapping->machine_type ?: Vend::MACHINE_TYPE_VENDING_MACHINE;
            $mismatched = Vend::withoutGlobalScopes()
                ->whereIn('id', $vendsToAddIds)
                ->whereRaw("coalesce(machine_type, 'vending_machine') != ?", [$mappingType])
                ->orderBy('code')
                ->get(['id', 'code', 'machine_type']);
            if ($mismatched->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'productMappingVends' => sprintf(
                        'This is a %s mapping — %d selected machine(s) are another kind (%s%s). Change their Machine Type first, or bind them to a matching mapping.',
                        Vend::MACHINE_TYPE_MAPPINGS[$mappingType] ?? $mappingType,
                        $mismatched->count(),
                        $mismatched->take(5)->pluck('code')->implode(', '),
                        $mismatched->count() > 5 ? ', …' : ''
                    ),
                ]);
            }
        }

        // 2. Add new vends
        if (! empty($vendsToAddIds)) {
            Vend::whereIn('id', $vendsToAddIds)->update([
                'product_mapping_id' => $productMapping->id,
                'upcoming_product_mapping_id' => $safeUpcomingId,
                'binded_at' => now(),
            ]);
        }

        // 3. Keep existing vends, only update upcoming mapping in case mapping itself changed
        if (! empty($vendsToKeepIds)) {
            Vend::whereIn('id', $vendsToKeepIds)->update([
                'upcoming_product_mapping_id' => $safeUpcomingId,
            ]);
        }

        $this->productMappingService->syncChannels($productMapping->id);

        // Both sides need telling: the machines now on this mapping get its menu, and a
        // machine just unbound is no longer reachable via pushForMapping even though its
        // menu just went empty.
        $this->smartFreezerCatalogPush->pushForMapping($productMapping);
        $this->smartFreezerCatalogPush->pushForVendIds(array_values($vendsToRemoveIds));

        // Vending-machine terminals need the same nudge (2026-08-13, the 2031
        // rebind report): they only re-fetch their slot list on boot or on a
        // TYPESYNCAPICHANNELSLOTLIST push, so a machine moved between mappings
        // here kept selling the OLD menu until reboot. Push to machines that
        // joined this mapping AND machines that just left it (their menu went
        // empty above). Kept machines only had their upcoming mapping adjusted
        // — the live menu is unchanged, so no push. Sent AFTER syncChannels so
        // the re-fetch reads the committed rows. Non-vending machine types are
        // skipped inside the service (freezers were nudged just above).
        // Fetched in ONE query (withoutGlobalScopes, matching what the service
        // does for a bare id) — a big rebind must not turn into a point-SELECT
        // per machine.
        $nudgeVends = Vend::withoutGlobalScopes()
            ->findMany([...array_values($vendsToAddIds), ...array_values($vendsToRemoveIds)]);
        foreach ($nudgeVends as $nudgeVend) {
            $this->vendJobService->syncChannelSlotListToVend($nudgeVend);
        }

        return redirect()->route('product-mappings');
    }

    public function toggleActivateDeactivate($productMappingID)
    {
        $productMapping = ProductMapping::findOrFail($productMappingID);
        $productMapping->is_active = ! $productMapping->is_active;
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
