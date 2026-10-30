<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingVend;
use App\Models\Vend;
use App\Services\GrabStoreService;
use App\Support\Grab\GrabStoreVisibility;
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

    public function __construct(protected GrabStoreService $grabStores) {}

    public function index(Request $request)
    {
        $operatorIds = GrabStoreVisibility::narrow($request->operators);
        $status = in_array($request->status, self::STATUSES, true) ? $request->status : 'all';
        $search = trim((string) $request->search);

        $base = DeliveryPlatformRefNumber::query()->whereIn('operator_id', $operatorIds);

        $refs = (clone $base)
            ->with([
                // Deactivated operators (DCVIC, NF, KEA, LEA) still own stores here.
                'operator' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name'),
                'openDeliveryProductMappingVends' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'vend:id,code,code_prefix,name,customer_id',
                    'customer:id,code,name,virtual_customer_prefix,virtual_customer_code',
                    'deliveryProductMapping:id,name,product_mapping_id',
                ]),
            ])
            ->withCount(['deliveryProductMappingVends as bindings_count' => fn ($query) => $query->withoutGlobalScopes()])
            ->addSelect([
                'last_order_at' => $this->orderSubquery('MAX(o.order_created_at)'),
                'orders_30d' => $this->orderSubquery('COUNT(*)', Carbon::now()->subDays(30)),
                'errors_7d' => DB::table('delivery_platform_api_errors')->selectRaw('COUNT(*)')
                    ->whereColumn('delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                    ->where('created_at', '>=', Carbon::now()->subDays(7)),
                'promotions_live' => DB::table('delivery_platform_live_campaigns')->selectRaw('COUNT(*)')
                    ->whereColumn('delivery_platform_ref_number_id', 'delivery_platform_ref_numbers.id')
                    ->whereNull('gone_at'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('ref_number', 'LIKE', "%{$search}%")
                        ->orWhereExists($this->openBinding(fn ($binding) => $binding->where('b.vend_code', 'LIKE', "{$search}%")));
                });
            })
            ->tap(fn ($query) => $this->filterStatus($query, $status))
            ->orderByRaw('status = ? DESC', [DeliveryPlatformRefNumber::STATUS_ACTIVE])
            ->orderBy('ref_number')
            ->paginate($request->numberPerPage === 'All' ? 10000 : (int) ($request->numberPerPage ?: 100))
            ->withQueryString();

        $partnerCodes = $this->lastPartnerMerchantIds(
            $refs->getCollection()->flatMap(fn ($ref) => $ref->openDeliveryProductMappingVends->pluck('id'))->all()
        );

        $totals = [];
        foreach (self::STATUSES as $key) {
            $totals[$key] = $this->filterStatus(clone $base, $key)->count();
        }
        $totals['all'] = (clone $base)->count();

        return Inertia::render('GrabStore/Index', [
            // The shape Paginator.vue reads (a resource collection's links + meta).
            'stores' => [
                'data' => $refs->getCollection()->map(fn ($ref) => $this->row($ref, $partnerCodes))->values(),
                'links' => ['prev' => $refs->previousPageUrl(), 'next' => $refs->nextPageUrl()],
                'meta' => ['from' => $refs->firstItem(), 'to' => $refs->lastItem(), 'total' => $refs->total()],
            ],
            'totals' => $totals,
            'filters' => [
                'operators' => $request->operators,
                'search' => $search,
                'status' => $status,
                'numberPerPage' => $request->numberPerPage ?: 100,
            ],
            'templateOptions' => DeliveryProductMapping::query()
                ->whereIn('operator_id', $operatorIds)
                ->orderBy('name')
                ->get(['id', 'name', 'operator_id', 'product_mapping_id']),
            'unbindReasons' => DeliveryProductMappingVend::UNBIND_REASONS,
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
                'site' => $this->siteName($binding),
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
            'delivery_product_mapping_id' => ['required_with:vend_code', 'nullable', 'integer'],
        ]);
        if (! GrabStoreVisibility::allows((int) $validated['operator_id'])) {
            abort(403);
        }

        // Resolve the machine before the store is saved, so a typo there saves nothing.
        $vend = ! empty($validated['vend_code']) ? $this->accessibleVend($validated['vend_code']) : null;
        $template = $vend ? $this->accessibleTemplate($validated['delivery_product_mapping_id']) : null;

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
            $this->grabStores->bind($ref, $vend, $template, auth()->id());
        }

        return back()->with('success', __('Grab store :ref added.', ['ref' => $ref->ref_number]));
    }

    public function bind(Request $request, $id)
    {
        $ref = $this->accessibleRef($id);
        $validated = $request->validate([
            'vend_code' => ['required', 'string'],
            'delivery_product_mapping_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $this->grabStores->bind(
            $ref,
            $this->accessibleVend($validated['vend_code']),
            $this->accessibleTemplate($validated['delivery_product_mapping_id']),
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

        $moved = $this->grabStores->move(
            $binding,
            $this->accessibleVend($validated['vend_code']),
            ! empty($validated['delivery_product_mapping_id']) ? $this->accessibleTemplate($validated['delivery_product_mapping_id']) : null,
            auth()->id(),
            $validated['reason'],
            $validated['remarks'] ?? null,
        );

        return back()->with('success', __('Grab store moved to machine :code.', ['code' => $moved->vend_code]));
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
            'errors_7d' => (int) $ref->errors_7d,
            'promotions_live' => (int) $ref->promotions_live,
            'binding' => $binding ? [
                'id' => $binding->id,
                'vend_code' => $binding->vend_code,
                'vend' => $binding->vend?->only(['id', 'code', 'code_prefix', 'name']),
                'site' => $this->siteName($binding),
                'template_id' => $binding->delivery_product_mapping_id,
                'template' => $binding->deliveryProductMapping?->name,
                'since' => $binding->start_date?->toIso8601String(),
                'is_active' => (bool) $binding->is_active,
                'paused_until' => $binding->paused_until?->toIso8601String(),
                'pause_reason' => $binding->pause_reason,
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

    private function siteName(DeliveryProductMappingVend $binding): ?string
    {
        $customer = $binding->customer;
        if (! $customer) {
            return null;
        }
        $code = $customer->code ?: trim(($customer->virtual_customer_prefix ?? '').($customer->virtual_customer_code ?? ''));

        return trim(($code ? $code.' ' : '').$customer->name);
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

    private function accessibleTemplate($id): DeliveryProductMapping
    {
        $template = DeliveryProductMapping::query()->find($id);
        if (! $template || ! GrabStoreVisibility::allows((int) $template->operator_id)) {
            throw ValidationException::withMessages(['delivery_product_mapping_id' => __('Pick a Grab template.')]);
        }

        return $template;
    }
}
