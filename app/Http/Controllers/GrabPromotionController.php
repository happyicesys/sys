<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPlatformCampaignRead;
use App\Models\DeliveryPlatformCampaignSend;
use App\Models\DeliveryPlatformLiveCampaign;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryProductMapping;
use App\Models\DeliveryProductMappingItem;
use App\Models\DeliveryProductMappingVend;
use App\Services\GrabCampaignMirror;
use App\Services\GrabPromotionService;
use App\Services\GrabStoreService;
use App\Support\Grab\GrabStoreVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Grab Promotions, one page (Brian, 2026-10-31: "single source of truth … from Grab API
 * only"). The list is what Grab runs, read from Grab every 10 minutes (GrabCampaignMirror)
 * and grouped: one promotion sent to 48 stores is one row. New / Edit / End call Grab
 * store by store through GrabPromotionService; ConnectVend keeps no campaign of its own,
 * only the log of what it asked Grab.
 */
class GrabPromotionController extends Controller
{
    const SOURCES = [
        DeliveryPlatformLiveCampaign::SOURCE_CONNECTVEND,
        DeliveryPlatformLiveCampaign::SOURCE_LEFTOVER,
        DeliveryPlatformLiveCampaign::SOURCE_MERCHANT_APP,
        DeliveryPlatformLiveCampaign::SOURCE_GRAB_TEAM,
        DeliveryPlatformLiveCampaign::SOURCE_UNKNOWN,
    ];

    // How far back the page lists ConnectVend's sends.
    const SENDS_HOURS = 24;

    public function index(Request $request)
    {
        $view = $request->view === 'ended' ? 'ended' : 'live';
        $state = in_array($request->state, ['ongoing', 'upcoming'], true) ? $request->state : 'all';
        $source = in_array($request->source, self::SOURCES, true) ? $request->source : 'all';
        $search = trim((string) $request->search);
        $refIds = $this->visibleRefIds();
        $bindings = $this->openBindings($refIds);
        $machines = $bindings->pluck('vend_code', 'delivery_platform_ref_number_id');

        $campaigns = DeliveryPlatformLiveCampaign::query()
            ->whereIn('delivery_platform_ref_number_id', $refIds)
            ->when($view === 'live', fn ($query) => $query->whereNull('gone_at'), fn ($query) => $query->whereNotNull('gone_at'))
            ->with([
                'deliveryPlatformRefNumber:id,ref_number,operator_id',
                'deliveryPlatformRefNumber.operator' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code'),
                'createSend.user:id,name',
                'deliveryPlatformCampaignItemVend:id,delivery_platform_campaign_id,delivery_product_mapping_vend_id,is_active,datetime_to,vend_code',
                'deliveryPlatformCampaignItemVend.deliveryProductMappingVend' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'end_date', 'vend_code'),
            ])
            ->orderByRaw("state = 'ongoing' DESC")
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $campaigns = $campaigns->filter(fn ($campaign) => str_contains(mb_strtolower($campaign->merchant_id), $needle)
                || str_contains(mb_strtolower((string) $campaign->name), $needle)
                || str_contains(mb_strtolower((string) ($machines[$campaign->delivery_platform_ref_number_id] ?? '')), $needle));
        }

        $groups = $this->group($campaigns);

        // Totals over the view before the chip filters, so each chip shows its count.
        $totals = ['all' => $groups->count(), 'ongoing' => 0, 'upcoming' => 0] + array_fill_keys(self::SOURCES, 0);
        foreach ($groups as $group) {
            $totals[$group['state']]++;
            $totals[$group['source']]++;
        }

        $filtered = $groups
            ->when($state !== 'all', fn ($rows) => $rows->where('state', $state))
            ->when($source !== 'all', fn ($rows) => $rows->where('source', $source))
            ->values();

        $perPage = 30;
        $page = max(1, (int) $request->page);
        $pageGroups = $filtered->forPage($page, $perPage)->values();
        $names = $this->objectNames($pageGroups->map(fn ($group) => $group['first']));

        $paginator = new LengthAwarePaginator($pageGroups, $filtered->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $user = auth()->user();

        return Inertia::render('GrabPromotion/Index', [
            'promotions' => [
                'data' => $pageGroups->map(fn ($group) => $this->groupRow($group, $machines, $bindings, $names))->values(),
                'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
                'meta' => ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()],
            ],
            'totals' => $totals,
            'reads' => $this->readsSummary($refIds, $machines),
            'sends' => $this->recentSends($refIds, $machines),
            'menus' => $this->menus($bindings),
            'campaignTypes' => Grab::CAMPAIGN_MAPPINGS,
            'filters' => ['view' => $view, 'state' => $state, 'source' => $source, 'search' => $search],
            'keepGoneDays' => DeliveryPlatformLiveCampaign::KEEP_GONE_DAYS,
            'can' => [
                'create' => $user->can('create delivery-platform-campaigns'),
                'update' => $user->can('update delivery-platform-campaigns'),
                'delete' => $user->can('delete delivery-platform-campaigns'),
            ],
        ]);
    }

    // A Grab menu's stores and items, for the New / Edit form.
    public function menu($mappingId)
    {
        $mapping = DeliveryProductMapping::query()->withoutGlobalScopes()->findOrFail($mappingId);
        $bindings = $this->openBindings($this->visibleRefIds())->where('delivery_product_mapping_id', $mapping->id);
        abort_if($bindings->isEmpty(), 404);

        $refs = DeliveryPlatformRefNumber::query()
            ->whereIn('id', $bindings->pluck('delivery_platform_ref_number_id'))
            ->with(['operator' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code')])
            ->get()
            ->keyBy('id');
        $grabStores = app(GrabStoreService::class);

        return response()->json([
            'id' => $mapping->id,
            'name' => $mapping->name,
            'stores' => $bindings->map(fn ($binding) => [
                'ref_id' => $binding->delivery_platform_ref_number_id,
                'merchant_id' => $refs[$binding->delivery_platform_ref_number_id]?->ref_number,
                'vend_code' => $binding->vend_code,
                'operator_code' => $refs[$binding->delivery_platform_ref_number_id]?->operator?->code,
                'has_account' => (bool) $grabStores->grabAccountFor((int) $refs[$binding->delivery_platform_ref_number_id]?->operator_id)?->externalOauthToken?->access_token,
            ])->sortBy('vend_code')->values(),
            'items' => $this->menuItems($mapping->id)->map(fn ($item) => [
                'id' => (string) $item->id,
                'slot' => $item->channel_code,
                'product_code' => $item->product?->code,
                'product_name' => $item->product?->name,
                'sub_category' => $item->sub_category_json,
            ])->values(),
            'categories' => $this->menuCategories($mapping->id),
        ]);
    }

    public function store(Request $request, GrabPromotionService $service)
    {
        $request->validate([
            'delivery_product_mapping_id' => ['required', 'integer'],
            'ref_ids' => ['required', 'array', 'min:1'],
            'ref_ids.*' => ['integer'],
        ]);

        $mappingId = (int) $request->delivery_product_mapping_id;
        $bound = $this->openBindings($this->visibleRefIds())
            ->where('delivery_product_mapping_id', $mappingId)
            ->pluck('delivery_platform_ref_number_id')
            ->map(fn ($id) => (int) $id);
        $refIds = collect($request->ref_ids)->map(fn ($id) => (int) $id)->unique();
        if ($refIds->diff($bound)->isNotEmpty()) {
            throw ValidationException::withMessages(['ref_ids' => __('Pick stores that are on this Grab menu now.')]);
        }

        $setup = $this->setup($request, $mappingId);
        $refs = DeliveryPlatformRefNumber::query()->whereIn('id', $refIds)->get();
        $service->create($setup, $refs, auth()->id());

        return back()->with('success', trans_choice('Sending to :count Grab store. The list shows Grab\'s answer in a few seconds.|Sending to :count Grab stores. The list shows Grab\'s answer in a few seconds.', $refs->count(), ['count' => $refs->count()]));
    }

    public function update(Request $request, GrabPromotionService $service)
    {
        $request->validate(['delivery_product_mapping_id' => ['required', 'integer']]);
        $campaigns = $this->changeable($request, 'update');
        $mappingId = (int) $request->delivery_product_mapping_id;

        // The items are checked against this menu, so it must be the one the stores sell now.
        $onMenu = $this->openBindings($campaigns->pluck('delivery_platform_ref_number_id'))
            ->where('delivery_product_mapping_id', $mappingId)
            ->pluck('delivery_platform_ref_number_id');
        if ($campaigns->pluck('delivery_platform_ref_number_id')->diff($onMenu)->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => __('Some of these stores are not on this Grab menu now; change them one menu at a time.')]);
        }

        $setup = $this->setup($request, $mappingId);
        $service->update($campaigns, $setup, auth()->id());

        return back()->with('success', trans_choice('Saving on :count Grab store.|Saving on :count Grab stores.', $campaigns->count(), ['count' => $campaigns->count()]));
    }

    public function end(Request $request, GrabPromotionService $service)
    {
        $campaigns = $this->changeable($request, 'end');
        $service->end($campaigns, auth()->id());

        return back()->with('success', trans_choice('Ending on :count Grab store.|Ending on :count Grab stores.', $campaigns->count(), ['count' => $campaigns->count()]));
    }

    // Read one store from Grab now (a GET on Grab; nothing changes there).
    public function refresh(GrabCampaignMirror $mirror, $refId)
    {
        $ref = DeliveryPlatformRefNumber::query()->findOrFail($refId);
        abort_unless($ref->operator_id && GrabStoreVisibility::allows((int) $ref->operator_id), 403);

        $result = $mirror->refresh($ref);

        return match ($result['result']) {
            'ok' => back()->with('success', __('Read from Grab: :ongoing live, :upcoming upcoming on :ref.', ['ongoing' => $result['ongoing'], 'upcoming' => $result['upcoming'], 'ref' => $ref->ref_number])),
            'refused' => back()->with('error', __('Grab refuses us this store (:ref); see Grab Errors.', ['ref' => $ref->ref_number])),
            'not_found' => back()->with('error', __('Grab does not know this store (:ref) under this operator\'s account; asked again after a day.', ['ref' => $ref->ref_number])),
            'sandbox' => back()->with('error', __('This store is on a Grab sandbox account; only live stores are read.')),
            'no_account' => back()->with('error', __('This operator has no Grab account in ConnectVend.')),
            default => back()->with('error', __('Grab did not answer for :ref (HTTP :code); see Grab Errors.', ['ref' => $ref->ref_number, 'code' => $result['code'] ?? '?'])),
        };
    }

    /**
     * The live campaigns a Save / End names (the group's stores, or one of them), each
     * on a store the viewer may see and with nothing already on its way to Grab.
     */
    private function changeable(Request $request, string $what): Collection
    {
        $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);

        $campaigns = DeliveryPlatformLiveCampaign::query()->live()
            ->whereIn('id', $request->ids)
            ->whereIn('delivery_platform_ref_number_id', $this->visibleRefIds())
            ->with('deliveryPlatformRefNumber')
            ->get();
        if ($campaigns->count() !== count(array_unique($request->ids))) {
            throw ValidationException::withMessages(['ids' => __('Grab no longer lists some of these; read the page again.')]);
        }

        $service = app(GrabPromotionService::class);
        $busy = $campaigns->filter(fn ($campaign) => $service->hasOpenSend($campaign->grab_campaign_id));
        if ($busy->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => __('A change to :ref is still on its way to Grab; wait for its answer.', ['ref' => $busy->first()->merchant_id])]);
        }

        return $campaigns;
    }

    /**
     * The setup the form sends, checked against Grab's rules for the type and against
     * the menu's own items, in the shape GrabPromotionService::payload() takes.
     */
    private function setup(Request $request, int $mappingId): array
    {
        $types = collect(Grab::CAMPAIGN_MAPPINGS)->keyBy('id');
        $request->validate([
            'type' => ['required', 'string', 'in:'.$types->keys()->implode(',')],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'string'],
            'eater_type' => ['required', 'string'],
            // Required by type below: a free item has no value.
            'value' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'cap' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
            'min_basket' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'total_count' => ['nullable', 'integer', 'min:0'],
            'total_count_per_user' => ['nullable', 'integer', 'min:0'],
            'object_ids' => ['nullable', 'array'],
            'object_ids.*' => ['string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'after:now'],
        ]);

        $type = $types[$request->type];
        $scope = collect($type['scope'])->firstWhere('id', $request->scope);
        $errors = [];
        if (! $scope) {
            $errors['scope'] = __('This promotion type cannot apply to that.');
        } elseif (! collect($scope['eaterType'])->contains('id', $request->eater_type)) {
            $errors['eater_type'] = __('Pick who the promotion is for.');
        }

        $objectIds = collect($request->object_ids ?? [])->map(fn ($id) => (string) $id)->unique()->values();
        if ($scope && $scope['id'] !== Grab::CAMPAIGN_SCOPE_ORDER) {
            $allowed = $scope['id'] === Grab::CAMPAIGN_SCOPE_CATEGORY
                ? collect($this->menuCategories($mappingId))->pluck('id')
                : $this->menuItems($mappingId)->pluck('id')->map(fn ($id) => (string) $id);
            if ($objectIds->isEmpty()) {
                $errors['object_ids'] = $scope['id'] === Grab::CAMPAIGN_SCOPE_CATEGORY ? __('Pick a category.') : __('Pick at least one item.');
            } elseif ($scope['singleObjectOnly'] && $objectIds->count() > 1) {
                $errors['object_ids'] = __('This promotion type takes one item only.');
            } elseif ($objectIds->diff($allowed)->isNotEmpty()) {
                $errors['object_ids'] = __('Pick items from this Grab menu.');
            }
        }

        $value = (float) $request->value;
        if ($type['promo_value'] && $type['id'] !== Grab::CAMPAIGN_TYPE_FREEITEM && $value <= 0) {
            $errors['value'] = __('Enter the promotion value.');
        }
        if (str_contains(strtolower($type['id']), 'percentage') && $value > 100) {
            $errors['value'] = __('A percentage is at most 100.');
        }
        if ($type['qty'] && (int) $request->qty < 2) {
            $errors['qty'] = __('A bundle needs at least 2 items.');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $cents = fn ($dollars) => $dollars === null || $dollars === '' ? null : (int) round(((float) $dollars) * 100);

        return [
            'type' => $type['id'],
            'name' => trim($request->name),
            'scope' => $scope['id'],
            'object_ids' => $scope['id'] === Grab::CAMPAIGN_SCOPE_ORDER ? [] : $objectIds->all(),
            'eater_type' => $request->eater_type,
            'value_cents' => $cents($request->value) ?? 0,
            'cap_cents' => $type['cap'] ? $cents($request->cap) : null,
            'qty' => $type['qty'] ? (int) $request->qty : null,
            'min_basket_cents' => ($scope['minBasketAmount'] ?? false) ? $cents($request->min_basket) : null,
            'total_count' => $request->total_count ? (int) $request->total_count : null,
            'total_count_per_user' => $request->total_count_per_user ? (int) $request->total_count_per_user : null,
            'starts_at' => Carbon::parse($request->starts_at, config('app.timezone')),
            'ends_at' => Carbon::parse($request->ends_at, config('app.timezone')),
        ];
    }

    /**
     * One promotion is one Grab campaign per store; the same setup on several stores is
     * one row. Same maker, Grab account, name, discount, conditions and quotas = same
     * promotion; a leftover is never folded into a promotion that is still wanted.
     */
    private function group(Collection $campaigns): Collection
    {
        return $campaigns
            ->groupBy(function ($campaign) {
                $json = $campaign->campaign_json ?? [];

                return md5(json_encode([
                    $campaign->source(),
                    $campaign->delivery_platform_operator_id,
                    $campaign->name,
                    $json['discount'] ?? null,
                    collect($json['conditions'] ?? [])->except('workingHour')->sortKeys()->all(),
                    $json['quotas'] ?? null,
                ]));
            })
            ->map(function ($rows, $key) {
                return [
                    'key' => $key,
                    'rows' => $rows->values(),
                    'first' => $rows->first(),
                    'state' => $rows->contains('state', DeliveryPlatformLiveCampaign::STATE_ONGOING) ? DeliveryPlatformLiveCampaign::STATE_ONGOING : DeliveryPlatformLiveCampaign::STATE_UPCOMING,
                    'source' => $rows->first()->source(),
                ];
            })
            ->values();
    }

    private function groupRow(array $group, $machines, Collection $bindings, array $names): array
    {
        $first = $group['first'];
        $json = $first->campaign_json ?? [];
        $objectIds = array_map('strval', $json['discount']['scope']['objectIDs'] ?? []);
        $mappingByRef = $bindings->pluck('delivery_product_mapping_id', 'delivery_platform_ref_number_id');
        $leftover = $group['rows']->first(fn ($campaign) => $campaign->source() === DeliveryPlatformLiveCampaign::SOURCE_LEFTOVER);
        $itemVend = $leftover?->deliveryPlatformCampaignItemVend;

        return [
            'key' => $group['key'],
            'state' => $group['state'],
            'source' => $group['source'],
            'name' => $first->name,
            'discount' => $json['discount'] ?? null,
            'conditions' => collect($json['conditions'] ?? [])->except('workingHour')->all(),
            'quotas' => $json['quotas'] ?? null,
            'objects' => array_map(fn ($id) => ['id' => $id, 'name' => $names[$first->scope_type][$id] ?? null], $objectIds),
            'starts_at' => $first->starts_at?->toIso8601String(),
            'ends_at' => $first->ends_at?->toIso8601String(),
            'gone_at' => $group['rows']->max('gone_at')?->toIso8601String(),
            'made_by_user' => $group['rows']->map(fn ($campaign) => $campaign->createSend?->user?->name)->filter()->first(),
            'created_by' => $first->created_by,
            // Edit picks items from the menu the stores are on now.
            'mapping_id' => $group['rows']->map(fn ($campaign) => $mappingByRef[$campaign->delivery_platform_ref_number_id] ?? null)->filter()->first(),
            'leftover_vend_code' => $itemVend?->vend_code,
            'leftover_binding_closed' => $itemVend ? (bool) $itemVend->deliveryProductMappingVend?->end_date : null,
            'campaign_json' => $json,
            'stores' => $group['rows']->map(fn ($campaign) => [
                'id' => $campaign->id,
                'grab_campaign_id' => $campaign->grab_campaign_id,
                'merchant_id' => $campaign->merchant_id,
                'ref_id' => $campaign->delivery_platform_ref_number_id,
                'operator_code' => $campaign->deliveryPlatformRefNumber?->operator?->code,
                'vend_code' => $machines[$campaign->delivery_platform_ref_number_id] ?? null,
                'state' => $campaign->state,
                'source' => $campaign->source(),
                'first_seen_at' => $campaign->first_seen_at?->toIso8601String(),
                'last_seen_at' => $campaign->last_seen_at?->toIso8601String(),
                'gone_at' => $campaign->gone_at?->toIso8601String(),
            ])->sortBy('vend_code')->values(),
        ];
    }

    /** @return Collection<int, DeliveryProductMappingItem> */
    private function menuItems(int $mappingId): Collection
    {
        return DeliveryProductMappingItem::query()
            ->where('delivery_product_mapping_id', $mappingId)
            ->with(['product' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name')])
            ->orderBy('channel_code')
            ->orderBy('id')
            ->get(['id', 'product_id', 'sub_category_json', 'channel_code']);
    }

    private function menuCategories(int $mappingId): array
    {
        return $this->menuItems($mappingId)
            ->map(fn ($item) => $item->sub_category_json)
            ->filter(fn ($sub) => ! empty($sub['id']))
            ->unique('id')
            ->map(fn ($sub) => ['id' => (string) $sub['id'], 'name' => $sub['name'] ?? $sub['id']])
            ->values()
            ->all();
    }

    // Grab menus a store of the viewer's is on now, for the New form's first pick.
    private function menus(Collection $bindings): array
    {
        $counts = $bindings->countBy('delivery_product_mapping_id');

        return DeliveryProductMapping::query()->withoutGlobalScopes()
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($mapping) => ['id' => $mapping->id, 'name' => $mapping->name, 'stores' => $counts[$mapping->id] ?? 0])
            ->all();
    }

    // The store's open machine binding (one per store), newest first.
    private function openBindings($refIds): Collection
    {
        return DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->whereIn('delivery_platform_ref_number_id', $refIds)
            ->orderByDesc('id')
            ->get(['id', 'delivery_platform_ref_number_id', 'delivery_product_mapping_id', 'vend_code'])
            ->unique('delivery_platform_ref_number_id')
            ->values();
    }

    /**
     * ConnectVend's sends of the last day, one entry per press, newest first: what was
     * asked, who asked, and what Grab answered store by store.
     */
    private function recentSends($refIds, $machines): array
    {
        $sends = DeliveryPlatformCampaignSend::query()
            ->whereIn('delivery_platform_ref_number_id', $refIds)
            ->where('created_at', '>=', now()->subHours(self::SENDS_HOURS))
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return $sends->groupBy('batch')
            ->take(10)
            ->map(function ($rows) use ($machines) {
                $first = $rows->first();

                return [
                    'batch' => $first->batch,
                    'action' => $first->action,
                    'name' => $first->request_json['name'] ?? null,
                    'user' => $first->user?->name,
                    'created_at' => $first->created_at?->toIso8601String(),
                    'total' => $rows->count(),
                    'ok' => $rows->where('status', DeliveryPlatformCampaignSend::STATUS_OK)->count(),
                    'open' => $rows->filter->isOpen()->count(),
                    'failed' => $rows->where('status', DeliveryPlatformCampaignSend::STATUS_FAILED)->map(fn ($send) => [
                        'merchant_id' => $send->merchant_id,
                        'vend_code' => $machines[$send->delivery_platform_ref_number_id] ?? null,
                        'message' => $send->response_message,
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Product names for the item ids (delivery_product_mapping_items.id, what the menu
     * sends Grab) and category names for the sub-category ids on this page.
     *
     * @return array{items: array<string, string>, category: array<string, string>}
     */
    private function objectNames($campaigns): array
    {
        $ids = ['items' => [], 'category' => []];
        foreach ($campaigns as $campaign) {
            $scope = $campaign->scope_type;
            if (isset($ids[$scope])) {
                foreach ($campaign->campaign_json['discount']['scope']['objectIDs'] ?? [] as $id) {
                    $ids[$scope][] = (string) $id;
                }
            }
        }

        $items = $ids['items'] ? DeliveryProductMappingItem::query()
            ->whereIn('id', array_filter(array_unique($ids['items']), 'ctype_digit'))
            ->with(['product' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name')])
            ->get(['id', 'product_id'])
            ->filter(fn ($item) => $item->product)
            ->mapWithKeys(fn ($item) => [(string) $item->id => trim($item->product->code.' '.$item->product->name)])
            ->all() : [];

        $categories = $ids['category'] ? DeliveryProductMappingItem::query()
            ->select(DB::raw('json_unquote(json_extract(sub_category_json, "$.id")) AS sub_id'), DB::raw('json_unquote(json_extract(sub_category_json, "$.name")) AS sub_name'))
            ->whereIn(DB::raw('json_unquote(json_extract(sub_category_json, "$.id"))'), array_unique($ids['category']))
            ->distinct()
            ->get()
            ->pluck('sub_name', 'sub_id')
            ->all() : [];

        return ['items' => $items, 'category' => $categories];
    }

    // Stores read by the mirror: when, and which ones Grab did not answer for.
    private function readsSummary($refIds, $machines): array
    {
        $reads = DeliveryPlatformCampaignRead::query()->whereIn('delivery_platform_ref_number_id', $refIds)->get();
        $failing = $reads->filter(fn ($read) => ! $read->last_ok_at || $read->last_ok_at->lt($read->last_read_at));

        return [
            'stores' => $reads->count(),
            'last_read_at' => $reads->max('last_read_at')?->toIso8601String(),
            'failing' => $failing->sortBy('merchant_id')->map(fn ($read) => [
                'ref_id' => $read->delivery_platform_ref_number_id,
                'merchant_id' => $read->merchant_id,
                'vend_code' => $machines[$read->delivery_platform_ref_number_id] ?? null,
                'status' => $read->last_status,
                'message' => $read->last_message,
                'last_ok_at' => $read->last_ok_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function visibleRefIds()
    {
        return DeliveryPlatformRefNumber::query()
            ->when(! GrabStoreVisibility::isHipl(), fn ($query) => $query->whereIn('operator_id', GrabStoreVisibility::operatorIds()))
            ->pluck('id');
    }
}
