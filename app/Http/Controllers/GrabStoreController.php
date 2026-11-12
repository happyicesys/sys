<?php

namespace App\Http\Controllers;

use App\Http\Requests\Grab\GrabPriceRuleRequest;
use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Models\DeliveryProductMappingVendChannel;
use App\Models\GrabMenuSetting;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Services\DeliveryPlatformService;
use App\Services\DeliveryProductMappingService;
use App\Services\Grab\GrabStoreChangeRequests;
use App\Services\Grab\GrabStoreProfiles;
use App\Services\Grab\GrabStoreSetup;
use App\Services\Grab\Menu\GrabMachineMenu;
use App\Services\Grab\Menu\MachineMenuLine;
use App\Services\GrabStoreService;
use App\Support\Grab\GrabStoreVisibility;
use App\Support\VendCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Grab Stores (plan §9 step 1, Brian 2026-10-29): Grab Platform ID + Machines on one
 * page, one row per Grab store (merchantID). Every write goes through
 * GrabStoreService; this controller only reads and checks who may touch what.
 */
class GrabStoreController extends Controller
{
    public const STATUSES = ['selling', 'paused', 'off', 'no_machine'];

    // Grab sales columns, sortable (Brian, 2026-11-01: which machines to keep on Grab).
    public const SORTS = ['sales_7d', 'sales_30d'];

    public function __construct(
        protected GrabStoreService $grabStores,
        protected GrabStoreChangeRequests $changeRequests,
        protected GrabStoreSetup $setup,
    ) {}

    public function index(Request $request)
    {
        $operatorIds = GrabStoreVisibility::narrow($request->operators);
        $status = in_array($request->status, self::STATUSES, true) ? $request->status : 'all';
        $search = trim((string) $request->search);
        $codes = trim((string) $request->codes);
        $sort = in_array($request->sort, self::SORTS, true) ? $request->sort : null;

        $base = DeliveryPlatformRefNumber::query()->whereIn('operator_id', $operatorIds);
        // What the search box, the Machine ID box and the status chips narrow the list to.
        $listed = function ($query) use ($search, $codes, $status) {
            $query->when($search !== '', fn ($query) => $query->where('ref_number', 'LIKE', "%{$search}%"));
            // The machine the store is on now, by the Operation Dashboard's rule: "," = an exact list.
            $query->when($codes !== '', fn ($query) => $query->whereExists($this->openBinding(function ($binding) use ($codes) {
                $binding->join('vends as v', 'v.id', '=', 'b.vend_id');
                VendCode::whereSearch($binding, $codes, contains: true, table: 'v');
            })));
            $this->filterStatus($query, $status);
        };

        $refs = (clone $base)
            ->with([
                // Deactivated operators (DCVIC, NF, KEA, LEA) still own stores here.
                'operator' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name'),
                'openDeliveryProductMappingVends' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'vend:id,code,code_prefix,name,customer_id',
                    // An open link's Site is its machine's current one (DeliveryProductMappingVend::currentSite).
                    'vend.customer' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name', 'virtual_customer_prefix', 'virtual_customer_code', 'grab_driver_instruction')
                        // Its address against Grab's (GrabStoreProfiles), shown side by side in the Edit window.
                        ->with('deliveryAddress:id,modelable_id,modelable_type,type,postcode,full_address,block_num,street_name,unit_num,building'),
                    'customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                    'deliveryProductMapping:id,name,product_mapping_id',
                ]),
            ])
            ->withCount(['deliveryProductMappingVends as bindings_count' => fn ($query) => $query->withoutGlobalScopes()])
            ->addSelect([
                'last_order_at' => $this->orderSubquery('MAX(o.order_created_at)'),
                'orders_30d' => $this->orderSubquery('COUNT(*)', Carbon::now()->subDays(30)),
                'sales_7d' => $this->salesSubquery('COALESCE(SUM(o.subtotal_amount - o.promo_amount), 0)', 7),
                'sales_7d_orders' => $this->salesSubquery('COUNT(*)', 7),
                'sales_30d' => $this->salesSubquery('COALESCE(SUM(o.subtotal_amount - o.promo_amount), 0)', 30),
                'sales_30d_orders' => $this->salesSubquery('COUNT(*)', 30),
                'errors_7d' => DB::table('delivery_platform_api_errors')->selectRaw('COUNT(*)')
                    ->whereColumn('delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                    ->where('created_at', '>=', Carbon::now()->subDays(7)),
                'promotions_live' => DB::table('delivery_platform_live_campaigns')->selectRaw('COUNT(*)')
                    ->whereColumn('delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                    ->whereNull('gone_at'),
            ])
            ->tap($listed)
            ->when($sort, fn ($query, $sort) => $query->orderByDesc($sort))
            ->orderByRaw('status = ? DESC', [DeliveryPlatformRefNumber::STATUS_ACTIVE])
            ->orderBy('ref_number')
            ->paginate($request->numberPerPage === 'All' ? 10000 : (int) ($request->numberPerPage ?: 100))
            ->withQueryString();

        $partnerCodes = $this->lastPartnerMerchantIds(
            $refs->getCollection()->flatMap(fn ($ref) => $ref->openDeliveryProductMappingVends->pluck('id'))->all()
        );
        $openChanges = $this->changeRequests->open($refs->getCollection()->pluck('id'));
        // Which stores list their machine once put on one or moved (GrabStoreService::willUseMachineMenu, one query).
        $switchedBefore = DeliveryPlatformStoreEvent::query()
            ->whereIn('delivery_platform_ref_number_id', $refs->getCollection()->pluck('id'))
            ->where('event', DeliveryPlatformStoreEvent::EVENT_MENU_SOURCE)
            ->distinct()->pluck('delivery_platform_ref_number_id')->map(fn ($id) => (int) $id)->all();

        $totals = [];
        foreach (self::STATUSES as $key) {
            $totals[$key] = $this->filterStatus(clone $base, $key)->count();
        }
        $totals['all'] = (clone $base)->count();

        // The same figures summed over every store the filters list, not just this page.
        $listedIds = (clone $base)->tap($listed)->select('id');
        $salesTotals = DB::table('delivery_platform_orders as o')
            ->join('delivery_product_mapping_vend as ob', 'ob.id', '=', 'o.delivery_product_mapping_vend_id')
            ->whereIn('ob.delivery_platform_ref_number_id', $listedIds)
            ->whereNull('ob.end_date')
            ->where('o.status', DeliveryPlatformOrder::STATUS_DELIVERED)
            ->where('o.order_created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('COALESCE(SUM(CASE WHEN o.order_created_at >= ? THEN o.subtotal_amount - o.promo_amount END), 0) AS sales_7d', [Carbon::now()->subDays(7)])
            ->selectRaw('COALESCE(SUM(o.subtotal_amount - o.promo_amount), 0) AS sales_30d')
            ->first();

        return Inertia::render('GrabStore/Index', [
            // The shape Paginator.vue reads (a resource collection's links + meta).
            'stores' => [
                'data' => $refs->getCollection()->map(fn ($ref) => [
                    ...$this->row($ref, $partnerCodes),
                    // Asking Grab to change the store (HIPL only: the others do it in the GrabMerchant app themselves).
                    'grab_change' => $openChanges[$ref->id] ?? null,
                    'can_ask_grab' => $this->changeRequests->eligible($ref),
                    'will_use_machine_menu' => $ref->usesMachineMenu() || ! in_array((int) $ref->id, $switchedBefore, true),
                ])->values(),
                'links' => ['prev' => $refs->previousPageUrl(), 'next' => $refs->nextPageUrl()],
                'meta' => ['from' => $refs->firstItem(), 'to' => $refs->lastItem(), 'total' => $refs->total()],
            ],
            'totals' => $totals,
            // The daily GrabMerchant website read: when it last ran for these operators' stores.
            'grabProfilesCheckedAt' => DeliveryPlatformRefNumber::query()->whereIn('operator_id', $operatorIds)->max('grab_profile_checked_at'),
            'salesTotals' => [
                'sales_7d' => (int) $salesTotals->sales_7d,
                'sales_30d' => (int) $salesTotals->sales_30d,
            ],
            'filters' => [
                'operators' => $request->operators,
                'search' => $search,
                'codes' => $codes,
                'status' => $status,
                'sort' => $sort,
                'numberPerPage' => $request->numberPerPage ?: 100,
            ],
            'templateOptions' => DeliveryProductMapping::query()
                ->whereIn('operator_id', $operatorIds)
                ->orderBy('name')
                ->get(['id', 'name', 'operator_id', 'product_mapping_id']),
            'unbindReasons' => DeliveryProductMappingVend::UNBIND_REASONS,
            // The put-on-machine window's price rule choice (the same tiers and steps as Grab Menu & Prices).
            'rpTypes' => collect(SellingPrice::TYPE_MAPPINGS)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'roundUpSteps' => GrabMenuSetting::ROUND_UP_STEPS,
            'canUpdate' => (bool) auth()->user()?->can('update delivery-platform-vends'),
        ]);
    }

    /** The store's history: every machine period with its orders, and every event. */
    public function history($id)
    {
        $ref = $this->accessibleRef($id);

        $bindings = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->where('delivery_platform_ref_number_id', $ref->id)
            ->with([
                'vend:id,code,code_prefix,name',
                'customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                'deliveryProductMapping:id,name',
                'boundBy:id,name',
                'unboundBy:id,name',
            ])
            ->addSelect([
                'delivery_product_mapping_vend.*',
                'orders_count' => DB::table('delivery_platform_orders')->selectRaw('COUNT(*)')
                    ->whereColumn('delivery_product_mapping_vend_id', 'delivery_product_mapping_vend.id'),
                'orders_subtotal' => DB::table('delivery_platform_orders')->selectRaw('COALESCE(SUM(subtotal_amount), 0)')
                    ->whereColumn('delivery_product_mapping_vend_id', 'delivery_product_mapping_vend.id'),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($binding) => [
                'id' => $binding->id,
                'vend_code' => $binding->vend_code,
                'vend' => $binding->vend?->only(['id', 'code', 'code_prefix', 'name']),
                'site' => $binding->siteName(),
                'template' => $binding->deliveryProductMapping?->name,
                'from' => $binding->start_date?->toIso8601String(),
                'to' => $binding->end_date?->toIso8601String(),
                'bound_by' => $binding->boundBy?->name,
                'unbound_by' => $binding->unboundBy?->name,
                'unbind_reason' => $binding->unbind_reason,
                'unbind_reason_label' => DeliveryProductMappingVend::UNBIND_REASONS[$binding->unbind_reason] ?? null,
                'unbind_remarks' => $binding->unbind_remarks,
                'is_active' => (bool) $binding->is_active,
                'orders_count' => (int) $binding->orders_count,
                'orders_subtotal' => (int) $binding->orders_subtotal,
            ]);

        $events = $ref->storeEvents()->with(['user:id,name', 'vend:id,code,code_prefix'])->limit(300)->get()
            ->map(fn (DeliveryPlatformStoreEvent $event) => [
                'id' => $event->id,
                'event' => $event->event,
                'at' => $event->created_at?->toIso8601String(),
                'user' => $event->user?->name,
                'is_system' => ! $event->user_id,
                'vend_code' => $event->vend?->code,
                'reason' => $event->reason,
                'data' => $event->data,
            ]);

        $errors = DeliveryPlatformApiError::query()
            ->where('delivery_platform_ref_number_id', $ref->id)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (DeliveryPlatformApiError $error) => GrabErrorController::row($error));

        return response()->json([
            'store' => ['id' => $ref->id, 'ref_number' => $ref->ref_number, 'created_at' => $ref->created_at?->toIso8601String()],
            'bindings' => $bindings,
            'events' => $events,
            'errors' => $errors,
        ]);
    }

    public function checkGrab($id)
    {
        $ref = $this->accessibleRef($id);

        return response()->json($this->grabStores->checkWithGrab((int) $ref->operator_id, $ref->ref_number));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'operator_id' => ['required', 'exists:operators,id'],
            'ref_number' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'skip_grab_check' => ['nullable', 'boolean'],
            'vend_code' => ['nullable', 'string'],
            // Not asked for an operator on the machine menu: its new stores list their machine (GrabStoreService::bind).
            'delivery_product_mapping_id' => ['nullable', 'integer'],
        ]);
        if (! GrabStoreVisibility::allows((int) $validated['operator_id'])) {
            abort(403);
        }

        // Resolve the machine before the store is saved, so a typo there saves nothing.
        $vend = ! empty($validated['vend_code']) ? $this->accessibleVend($validated['vend_code']) : null;
        $template = $vend && ! empty($validated['delivery_product_mapping_id']) ? $this->accessibleTemplate($validated['delivery_product_mapping_id']) : null;
        $setup = $vend ? $this->validatedSetup($request) : null;

        // No outer transaction: bind() queues the menu sync, which must see a committed
        // row. A bind refused here leaves the store saved with no machine, as shown.
        $ref = $this->grabStores->addStore(
            (int) $validated['operator_id'],
            $validated['ref_number'],
            $validated['remarks'] ?? null,
            auth()->id(),
            (bool) ($validated['skip_grab_check'] ?? false),
        );
        if ($vend) {
            $this->setup->apply($ref, $setup, auth()->id());
            $this->grabStores->bind($ref, $vend, $template, auth()->id());
        }

        return back()->with('success', $vend
            ? __('Grab store :ref added on machine :code. Grab shows its menu within a few minutes.', ['ref' => $ref->ref_number, 'code' => $vend->code])
            : __('Grab store :ref added.', ['ref' => $ref->ref_number]));
    }

    /**
     * Edit a store: operator and remarks. The merchant ID is the store on Grab and stays as added (Brian, 2026-11-11:
     * "no need to be editable here"); a wrong one is switched off and the right one added.
     */
    public function update(Request $request, $id)
    {
        $ref = $this->accessibleRef($id);
        $validated = $request->validate([
            'operator_id' => ['required', 'exists:operators,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! GrabStoreVisibility::allows((int) $validated['operator_id'])) {
            abort(403);
        }

        $this->grabStores->editStore(
            $ref,
            $ref->ref_number,
            (int) $validated['operator_id'],
            $validated['remarks'] ?? null,
            auth()->id(),
        );

        return back()->with('success', __('Grab store :ref saved.', ['ref' => $ref->ref_number]));
    }

    /**
     * The GrabMerchant website's Store profile pages, read once a day in a signed-in browser (the scheduled
     * "Grab store address check"): name, address and hours as customers see them on Grab. JSON in, JSON out.
     */
    public function importProfiles(Request $request, GrabStoreProfiles $profiles)
    {
        $validated = $request->validate([
            'stores' => ['required', 'array', 'max:500'],
            'stores.*.merchant_id' => ['nullable', 'string', 'max:64'],
            'stores.*.text' => ['nullable', 'string', 'max:20000'],
            'stores.*.name' => ['nullable', 'string', 'max:500'],
            'stores.*.address' => ['nullable', 'string', 'max:1000'],
            'stores.*.hours' => ['nullable', 'string', 'max:500'],
            'checked_at' => ['nullable', 'date'],
        ]);

        return response()->json($profiles->import(
            $validated['stores'],
            GrabStoreVisibility::operatorIds(),
            auth()->id(),
            isset($validated['checked_at']) ? Carbon::parse($validated['checked_at']) : null,
        ));
    }

    /**
     * What Grab gets for this store now: the menu Grab pulls (DeliveryPlatformService::buildMenu,
     * no call to Grab), one line per item, each with the machine slot behind it and, when
     * Grab cannot sell it, why. Read only: replaces the old Machines page's channel view.
     */
    public function menu($id)
    {
        $ref = $this->accessibleRef($id);
        $binding = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->first();
        if (! $binding) {
            return response()->json(['binding' => null, 'lines' => []]);
        }
        $binding->setRelation('deliveryPlatformRefNumber', $ref);

        $selling = $binding->isSelling();
        $base = [
            'binding' => ['id' => $binding->id, 'vend_code' => $binding->vend_code, 'is_active' => (bool) $binding->is_active],
            'selling' => $selling,
        ];

        if ($ref->usesMachineMenu()) {
            return response()->json($base + $this->machineMenu($binding));
        }

        // Built as Grab's pull builds it: with no viewer, so the viewer's own product and
        // machine limits cannot drop a line Grab does get (the store was checked above).
        $mappingService = app(DeliveryProductMappingService::class);
        try {
            [$menu, $channels] = $this->withoutViewer(fn () => [
                app(DeliveryPlatformService::class)->buildMenu($binding),
                DeliveryProductMappingVendChannel::query()
                    ->where('delivery_product_mapping_vend_id', $binding->id)
                    ->with([
                        'vendChannel.vendChannelLatestError.vendChannelError',
                        'deliveryProductMappingItem:id,is_active,product_id',
                        'deliveryProductMappingItem.product:id,code',
                    ])
                    ->orderBy('id')
                    ->get(),
            ]);
        } catch (\Throwable $e) {
            return response()->json($base + ['error' => __('The menu could not be built: :error', ['error' => $e->getMessage()]), 'lines' => []]);
        }
        $held = $mappingService->heldQtyByChannel($channels->pluck('id'));

        // The menu lists one item per channel, in channel order, so the n-th time an item
        // id appears it is that item's n-th channel.
        $channelsByItem = $channels->groupBy('delivery_product_mapping_item_id')->map->values();
        $seen = [];

        // Mart: one category holding the sub-categories; Food: the sub-categories themselves.
        $subCategories = isset($menu['categories'][0]['subCategories']) ? $menu['categories'][0]['subCategories'] : ($menu['categories'] ?? []);
        $lines = [];
        foreach ($subCategories as $subCategory) {
            foreach ($subCategory['items'] ?? [] as $item) {
                $itemId = (int) $item['id'];
                $nth = $seen[$itemId] = ($seen[$itemId] ?? -1) + 1;
                $channel = $channelsByItem->get($itemId)?->get($nth);
                $available = ($item['availableStatus'] ?? null) === Grab::STATUS_AVAILABLE;

                $lines[] = [
                    'sub_category' => $subCategory['name'] ?? null,
                    'item_id' => $itemId,
                    'name' => $item['name'] ?? null,
                    'product_code' => $channel?->deliveryProductMappingItem?->product?->code,
                    'slot' => $channel?->vend_channel_code,
                    'price' => (int) ($item['price'] ?? 0),
                    'available' => $available,
                    'max_stock' => (int) ($item['maxStock'] ?? 0),
                    'machine_qty' => $channel?->vendChannel?->qty,
                    'held' => $channel ? (int) ($held[$channel->id] ?? 0) : 0,
                    'has_photo' => ! empty($item['photos']),
                    'reason' => $available || ! $channel ? null : $this->unavailableReason($binding, $channel, $held[$channel->id] ?? 0, $mappingService),
                ];
            }
        }

        // Grab's last pull of this store, compared the way the menu notify compares it.
        $grabHasIt = $binding->last_menu_json
            ? DeliveryPlatformService::comparableMenu($menu) === DeliveryPlatformService::comparableMenu($binding->last_menu_json)
            : null;

        return response()->json($base + [
            'grab_has_it' => $grabHasIt,
            'lines' => $lines,
            'counts' => [
                'items' => count($lines),
                'available' => count(array_filter($lines, fn ($line) => $line['available'])),
            ],
        ]);
    }

    /**
     * "On Grab now" for a store on the machine menu (Grab Menu & Prices): one line per
     * product on the machine, listed or not, and why not.
     */
    private function machineMenu(DeliveryProductMappingVend $binding): array
    {
        try {
            [$menu, $machineLines] = $this->withoutViewer(fn () => [
                app(DeliveryPlatformService::class)->buildMenu($binding),
                app(GrabMachineMenu::class)->lines($binding),
            ]);
        } catch (\Throwable $e) {
            return ['error' => __('The menu could not be built: :error', ['error' => $e->getMessage()]), 'lines' => []];
        }

        $lines = $machineLines->map(fn (MachineMenuLine $line) => [
            'sub_category' => $line->subCategory['name'] ?? null,
            'item_id' => $line->itemId(),
            'name' => $line->product->name,
            'product_code' => $line->product->code,
            'slot' => $line->slots->pluck('code')->implode(', '),
            'price' => (int) $line->price,
            'available' => $line->isListed() && $line->available() > 0,
            'max_stock' => $line->available(),
            'machine_qty' => null,
            'held' => 0,
            'has_photo' => (bool) $line->product->thumbnail?->full_url,
            'reason' => $line->problem() ?? ($line->available() > 0 ? null : 'sold_out'),
        ])->values()->all();

        return [
            'machine_menu' => true,
            'grab_has_it' => $binding->last_menu_json
                ? DeliveryPlatformService::comparableMenu($menu) === DeliveryPlatformService::comparableMenu($binding->last_menu_json)
                : null,
            'lines' => $lines,
            'counts' => [
                'items' => count($lines),
                'available' => count(array_filter($lines, fn ($line) => $line['available'])),
            ],
        ];
    }

    /** Runs $read with no signed-in user (the global scopes then see what Grab's own calls see). */
    private function withoutViewer(callable $read)
    {
        $user = auth()->user();
        auth()->forgetUser();
        try {
            return $read();
        } finally {
            if ($user) {
                auth()->setUser($user);
            }
        }
    }

    /** Why the menu tells Grab this slot cannot be sold, in the order the menu decides it. */
    private function unavailableReason(DeliveryProductMappingVend $binding, DeliveryProductMappingVendChannel $channel, int $held, DeliveryProductMappingService $mappingService): string
    {
        $vendChannel = $channel->vendChannel;

        return match (true) {
            ! $binding->is_active => 'store_paused',
            ! $channel->deliveryProductMappingItem?->is_active => 'item_off',
            $vendChannel && $mappingService->getVendChannelErrorStatus($vendChannel) => 'slot_fault',
            ! $vendChannel || (int) $vendChannel->capacity <= 0 => 'no_capacity',
            ! $mappingService->getDeliveryVendChannelStatus($vendChannel, $channel, $held)['status'] => 'sold_out',
            default => 'next_sync',
        };
    }

    public function bind(Request $request, $id)
    {
        $ref = $this->accessibleRef($id);
        $validated = $request->validate([
            'vend_code' => ['required', 'string'],
            'delivery_product_mapping_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $machineMenu = $this->grabStores->willUseMachineMenu($ref);
        $vend = $this->accessibleVend($validated['vend_code']);
        $template = $this->optionalTemplate($validated['delivery_product_mapping_id'] ?? null, $machineMenu);
        if ($machineMenu) {
            $this->setup->apply($ref, $this->validatedSetup($request), auth()->id());
        }
        $this->grabStores->bind(
            $ref,
            $vend,
            $template,
            auth()->id(),
            $validated['remarks'] ?? null,
        );

        return back()->with('success', __('Grab store :ref is now on machine :code.', ['ref' => $ref->ref_number, 'code' => $validated['vend_code']]));
    }

    public function move(Request $request, $bindingId)
    {
        $binding = $this->accessibleBinding($bindingId);
        $validated = $request->validate([
            'vend_code' => ['required', 'string'],
            'delivery_product_mapping_id' => ['nullable', 'integer'],
            'reason' => ['required', 'in:'.implode(',', array_keys(DeliveryProductMappingVend::UNBIND_REASONS))],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $vend = $this->accessibleVend($validated['vend_code']);
        $ref = $binding->deliveryPlatformRefNumber;
        if ($this->grabStores->willUseMachineMenu($ref)) {
            $this->setup->apply($ref, $this->validatedSetup($request), auth()->id());
        }
        $moved = $this->grabStores->move(
            $binding,
            $vend,
            ! empty($validated['delivery_product_mapping_id']) ? $this->accessibleTemplate($validated['delivery_product_mapping_id']) : null,
            auth()->id(),
            $validated['reason'],
            $validated['remarks'] ?? null,
        );

        $siteMoved = (int) $moved->customer_id !== (int) $binding->customer_id;

        return back()->with('success', $siteMoved && $this->changeRequests->eligible($binding->deliveryPlatformRefNumber)
            ? __('Grab store moved to machine :code. Grab still shows the old address: use Update on Grab to send the new one.', ['code' => $moved->vend_code])
            : __('Grab store moved to machine :code.', ['code' => $moved->vend_code]));
    }

    public function unbind(Request $request, $bindingId)
    {
        $binding = $this->accessibleBinding($bindingId);
        $validated = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(DeliveryProductMappingVend::UNBIND_REASONS))],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $this->grabStores->unbind($binding, auth()->id(), $validated['reason'], $validated['remarks'] ?? null);

        return back()->with('success', __('Grab store taken off machine :code.', ['code' => $binding->vend_code]));
    }

    public function pause(Request $request, $bindingId)
    {
        $binding = $this->accessibleBinding($bindingId);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'until' => ['nullable', 'date', 'after:now'],
        ]);
        if ($binding->end_date) {
            throw ValidationException::withMessages(['reason' => __('This binding has already ended; reload the page.')]);
        }

        $this->grabStores->pause($binding, auth()->id(), $validated['reason'], ! empty($validated['until']) ? Carbon::parse($validated['until']) : null);

        return back()->with('success', __('Grab store paused.'));
    }

    public function resume($bindingId)
    {
        $binding = $this->accessibleBinding($bindingId);
        if ($binding->end_date) {
            throw ValidationException::withMessages(['reason' => __('This binding has already ended; reload the page.')]);
        }

        $this->grabStores->resume($binding, auth()->id());

        return back()->with('success', __('Grab store resumed.'));
    }

    public function setStatus(Request $request, $id)
    {
        $ref = $this->accessibleRef($id);
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->grabStores->setStatus($ref, (bool) $validated['active'], auth()->id(), $validated['reason'] ?? null);

        return back()->with('success', $validated['active'] ? __('Grab store switched on.') : __('Grab store switched off.'));
    }

    private function row(DeliveryPlatformRefNumber $ref, array $partnerCodes): array
    {
        $open = $ref->openDeliveryProductMappingVends;
        $binding = $open->first();
        $isOff = (int) $ref->status === DeliveryPlatformRefNumber::STATUS_INACTIVE;

        $status = match (true) {
            $isOff => 'off',
            ! $binding => 'no_machine',
            ! $binding->is_active => 'paused',
            default => 'selling',
        };

        $partnerCode = $binding ? ($partnerCodes[$binding->id] ?? null) : null;

        return [
            'id' => $ref->id,
            'ref_number' => $ref->ref_number,
            'remarks' => $ref->remarks,
            'operator' => $ref->operator?->only(['id', 'code', 'name']),
            'operator_id' => $ref->operator_id,
            'status' => $status,
            'is_off' => $isOff,
            'bindings_count' => (int) $ref->bindings_count,
            'last_order_at' => $ref->last_order_at ? Carbon::parse($ref->last_order_at)->toIso8601String() : null,
            'orders_30d' => (int) $ref->orders_30d,
            'sales_7d' => (int) $ref->sales_7d,
            'sales_7d_orders' => (int) $ref->sales_7d_orders,
            'sales_30d' => (int) $ref->sales_30d,
            'sales_30d_orders' => (int) $ref->sales_30d_orders,
            'errors_7d' => (int) $ref->errors_7d,
            'promotions_live' => (int) $ref->promotions_live,
            'binding' => $binding ? [
                'id' => $binding->id,
                'vend_code' => $binding->vend_code,
                'vend' => $binding->vend?->only(['id', 'code', 'code_prefix', 'name']),
                'site' => $binding->siteName(),
                'site_id' => $binding->currentSite()?->id,
                // The Site's side of the Edit window's "Site and Grab" comparison.
                'site_address' => ($address = $binding->currentSite()?->deliveryAddress) ? GrabStoreChangeRequests::addressLine($address) : null,
                'site_driver_instruction' => $binding->currentSite()?->grab_driver_instruction,
                // The machine moved to another Site since the store was put on it: Grab may still show the old address.
                'site_changed_from' => $binding->siteChangedFrom(),
                'template_id' => $binding->delivery_product_mapping_id,
                'template' => $binding->deliveryProductMapping?->name,
                // Grab Menu & Prices: the store lists its machine's products, so the template is not its menu.
                'machine_menu' => $ref->usesMachineMenu(),
                'since' => $binding->start_date?->toIso8601String(),
                'is_active' => (bool) $binding->is_active,
                'paused_until' => $binding->paused_until?->toIso8601String(),
                'pause_reason' => $binding->pause_reason,
            ] : null,
            // What customers see on Grab, from the daily GrabMerchant website read, and whether its postal code is the Site's.
            'grab_profile' => $ref->grab_profile_checked_at ? [
                'name' => $ref->grab_store_name,
                'address' => $ref->grab_address,
                'postal_code' => $ref->grab_postal_code,
                'hours' => $ref->grab_hours,
                'checked_at' => $ref->grab_profile_checked_at->toIso8601String(),
                'site_postal_code' => GrabStoreProfiles::sitePostalCode($binding),
                'match' => GrabStoreProfiles::matches($ref->grab_postal_code, $binding),
            ] : null,
            // The 2658 case: one store still open on a second machine.
            'other_open_bindings' => $open->slice(1)->map(fn ($other) => [
                'id' => $other->id,
                'vend_code' => $other->vend_code,
                'is_active' => (bool) $other->is_active,
                'since' => $other->start_date?->toIso8601String(),
            ])->values(),
            // The machine code Grab holds for the store (its last order's partnerMerchantID).
            'grab_machine_code' => $partnerCode,
            'grab_machine_code_mismatch' => $partnerCode !== null && $binding && (string) $partnerCode !== (string) $binding->vend_code,
        ];
    }

    private function filterStatus($query, string $status)
    {
        $inactive = DeliveryPlatformRefNumber::STATUS_INACTIVE;

        return match ($status) {
            'off' => $query->where('status', $inactive),
            'no_machine' => $query->where('status', '!=', $inactive)->whereNotExists($this->openBinding()),
            'paused' => $query->where('status', '!=', $inactive)->whereExists($this->openBinding(fn ($b) => $b->where('b.is_active', false))),
            'selling' => $query->where('status', '!=', $inactive)->whereExists($this->openBinding(fn ($b) => $b->where('b.is_active', true))),
            default => $query,
        };
    }

    private function openBinding(?callable $also = null): \Closure
    {
        return function ($query) use ($also) {
            $query->selectRaw('1')->from('delivery_product_mapping_vend as b')
                ->whereColumn('b.delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                ->whereNull('b.end_date');
            if ($also) {
                $also($query);
            }
        };
    }

    private function orderSubquery(string $select, ?Carbon $since = null)
    {
        return DB::table('delivery_platform_orders as o')
            ->join('delivery_product_mapping_vend as ob', 'ob.id', '=', 'o.delivery_product_mapping_vend_id')
            ->selectRaw($select)
            ->whereColumn('ob.delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
            ->when($since, fn ($query) => $query->where('o.order_created_at', '>=', $since));
    }

    /**
     * Grab sales of the store's machine now (its open binding): delivered orders only,
     * less Grab promo, in cents — the rule the Grab Orders page totals by.
     */
    private function salesSubquery(string $select, int $days)
    {
        return DB::table('delivery_platform_orders as o')
            ->join('delivery_product_mapping_vend as ob', 'ob.id', '=', 'o.delivery_product_mapping_vend_id')
            ->selectRaw($select)
            ->whereColumn('ob.delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
            ->whereNull('ob.end_date')
            ->where('o.status', DeliveryPlatformOrder::STATUS_DELIVERED)
            ->where('o.order_created_at', '>=', Carbon::now()->subDays($days));
    }

    /** @return array<int, string> binding id => partnerMerchantID of its latest order */
    private function lastPartnerMerchantIds(array $bindingIds): array
    {
        if (! $bindingIds) {
            return [];
        }

        $latest = DB::table('delivery_platform_orders')
            ->selectRaw('MAX(id) as id')
            ->whereIn('delivery_product_mapping_vend_id', $bindingIds)
            ->groupBy('delivery_product_mapping_vend_id');

        return DB::table('delivery_platform_orders')
            ->whereIn('id', $latest)
            ->get(['delivery_product_mapping_vend_id', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(order_json, '$.partnerMerchantID')) as partner")])
            ->filter(fn ($row) => $row->partner !== null && $row->partner !== 'null' && $row->partner !== '')
            ->mapWithKeys(fn ($row) => [$row->delivery_product_mapping_vend_id => $row->partner])
            ->all();
    }

    /** What the "Update on Grab" window starts with. */
    public function changeDraft($id)
    {
        $ref = $this->accessibleRef($id);
        abort_unless($this->changeRequests->eligible($ref), 404);

        return response()->json($this->changeRequests->draft($ref));
    }

    // A person sent the change to Grab (GrabMerchant app or Help Centre form): kept on the store's history.
    public function changeRequested(Request $request, $id)
    {
        $ref = $this->accessibleRef($id);
        abort_unless($this->changeRequests->eligible($ref), 404);
        $validated = $request->validate([
            'kind' => ['required', 'in:'.implode(',', GrabStoreChangeRequests::KINDS)],
            'route' => ['required', 'in:'.implode(',', GrabStoreChangeRequests::ROUTES)],
            'address' => ['nullable', 'required_if:kind,address', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'go_live_on' => ['nullable', 'date'],
            'details' => ['nullable', 'required_unless:kind,address', 'string', 'max:1000'],
            'driver_instruction' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->changeRequests->requested($ref, $validated, auth()->id());

        return back()->with('success', __('Saved: :ref waits for Grab. Press Done once Grab shows the change.', ['ref' => $ref->ref_number]));
    }

    public function changeDone(Request $request, $id)
    {
        $ref = $this->accessibleRef($id);
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->changeRequests->done($ref, $request->note, auth()->id());

        return back()->with('success', __(':ref: the change on Grab is marked done.', ['ref' => $ref->ref_number]));
    }

    private function accessibleRef($id): DeliveryPlatformRefNumber
    {
        $ref = DeliveryPlatformRefNumber::findOrFail($id);
        if (! GrabStoreVisibility::allows((int) $ref->operator_id)) {
            abort(403);
        }

        return $ref;
    }

    private function accessibleBinding($id): DeliveryProductMappingVend
    {
        $binding = DeliveryProductMappingVend::query()->withoutGlobalScopes()->findOrFail($id);
        $ref = $binding->deliveryPlatformRefNumber;
        if (! $ref || ! GrabStoreVisibility::allows((int) $ref->operator_id)) {
            abort(403);
        }

        return $binding;
    }

    // Through Vend's own scopes, so a user can only name a machine they can see.
    private function accessibleVend(string $code): Vend
    {
        $vend = Vend::query()->where('code', trim($code))->first();
        if (! $vend) {
            throw ValidationException::withMessages(['vend_code' => __('Machine :code not found.', ['code' => $code])]);
        }

        return $vend;
    }

    /**
     * The put-on-machine window: what Grab would list from this machine, at the price rule being chosen, and what is
     * missing (GrabStoreSetup). Read only.
     */
    public function machinePreview(Request $request)
    {
        $validated = $request->validate([
            'operator_id' => ['required', 'integer'],
            'vend_code' => ['required', 'string'],
            'ref_id' => ['nullable', 'integer'],
            'use_company_rule' => ['nullable', 'boolean'],
            ...GrabPriceRuleRequest::ruleFields(required: false),
        ]);
        abort_unless(GrabStoreVisibility::allows((int) $validated['operator_id']), 403);
        $ref = ! empty($validated['ref_id']) ? $this->accessibleRef($validated['ref_id']) : null;
        $vend = $this->accessibleVend($validated['vend_code']);
        $vend->loadMissing(['customer' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'name')]);

        $useCompany = $request->boolean('use_company_rule', true) || ! $request->filled('pricing_mode');

        return response()->json($this->setup->preview((int) $validated['operator_id'], $vend, $ref, [
            'use_company_rule' => $useCompany,
            ...($useCompany ? [] : GrabPriceRuleRequest::valuesFrom($request->all())),
        ]));
    }

    /**
     * What the window chose: the store's price rule (company, or its own with a note), the sub-categories picked for
     * products that had none, and typed prices. Missing = the company rule and nothing filled in.
     *
     * @return array{use_company_rule: bool, pricing_mode?: string, rp_type?: int, multiplier_bp?: int, round_up_cents?: int, note?: ?string, sub_categories: array, custom_prices: array}
     */
    private function validatedSetup(Request $request): array
    {
        $own = $request->has('setup') && ! $request->boolean('setup.use_company_rule', true);
        $request->validate([
            'setup' => ['nullable', 'array'],
            'setup.use_company_rule' => ['nullable', 'boolean'],
            'setup.note' => ['nullable', 'string', 'max:255'],
            ...($own ? GrabPriceRuleRequest::ruleFields('setup.') : []),
            'setup.sub_categories' => ['nullable', 'array'],
            'setup.sub_categories.*.product_id' => ['required', 'integer'],
            'setup.sub_categories.*.id' => ['required', 'string', 'max:64'],
            'setup.sub_categories.*.name' => ['nullable', 'string', 'max:255'],
            'setup.custom_prices' => ['nullable', 'array'],
            'setup.custom_prices.*.product_id' => ['required', 'integer'],
            'setup.custom_prices.*.price' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ]);
        $setup = (array) $request->input('setup', []);

        return [
            'use_company_rule' => ! $own,
            ...($own ? GrabPriceRuleRequest::valuesFrom($setup) : []),
            'note' => $setup['note'] ?? null,
            'sub_categories' => $setup['sub_categories'] ?? [],
            'custom_prices' => $setup['custom_prices'] ?? [],
        ];
    }

    // A template is asked for only when the store will sell from one; a machine-menu store gets its operator's usual one.
    private function optionalTemplate($id, bool $machineMenu): ?DeliveryProductMapping
    {
        if (! $id) {
            if ($machineMenu) {
                return null;
            }
            throw ValidationException::withMessages(['delivery_product_mapping_id' => __('Pick a Grab template.')]);
        }

        return $this->accessibleTemplate($id);
    }

    private function accessibleTemplate($id): DeliveryProductMapping
    {
        $template = DeliveryProductMapping::query()->find($id);
        if (! $template || ! GrabStoreVisibility::allows((int) $template->operator_id)) {
            throw ValidationException::withMessages(['delivery_product_mapping_id' => __('Pick a Grab template.')]);
        }

        return $template;
    }
}
