<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPlatformCampaignRead;
use App\Models\DeliveryPlatformLiveCampaign;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingItem;
use App\Models\DeliveryProductMappingVend;
use App\Services\GrabCampaignMirror;
use App\Support\Grab\GrabStoreVisibility;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Grab Promotions, first part (plan §9.3, 2026-10-30): what Grab has live per store,
 * read from Grab every 10 minutes, and which of it mark1 believes has ended. Read only;
 * creating and ending promotions here comes with the next part.
 */
class GrabPromotionController extends Controller
{
    const SOURCES = [
        DeliveryPlatformLiveCampaign::SOURCE_MARK1,
        DeliveryPlatformLiveCampaign::SOURCE_LEFTOVER,
        DeliveryPlatformLiveCampaign::SOURCE_MERCHANT_APP,
        DeliveryPlatformLiveCampaign::SOURCE_GRAB_TEAM,
        DeliveryPlatformLiveCampaign::SOURCE_UNKNOWN,
    ];

    public function index(Request $request)
    {
        $view = $request->view === 'ended' ? 'ended' : 'live';
        $state = in_array($request->state, ['ongoing', 'upcoming'], true) ? $request->state : 'all';
        $source = in_array($request->source, self::SOURCES, true) ? $request->source : 'all';
        $search = trim((string) $request->search);
        $refIds = $this->visibleRefIds();

        // Which machine each store is on now.
        $machines = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->whereIn('delivery_platform_ref_number_id', $refIds)
            ->orderByDesc('id')
            ->get(['delivery_platform_ref_number_id', 'vend_code'])
            ->unique('delivery_platform_ref_number_id')
            ->pluck('vend_code', 'delivery_platform_ref_number_id');

        $campaigns = DeliveryPlatformLiveCampaign::query()
            ->whereIn('delivery_platform_ref_number_id', $refIds)
            ->when($view === 'live', fn ($query) => $query->whereNull('gone_at'), fn ($query) => $query->whereNotNull('gone_at'))
            ->with([
                'deliveryPlatformRefNumber:id,ref_number,operator_id',
                'deliveryPlatformRefNumber.operator' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code'),
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

        // Totals over the view before the chip filters, so each chip shows its count.
        $totals = ['all' => $campaigns->count(), 'ongoing' => 0, 'upcoming' => 0] + array_fill_keys(self::SOURCES, 0);
        foreach ($campaigns as $campaign) {
            $totals[$campaign->state] = ($totals[$campaign->state] ?? 0) + 1;
            $totals[$campaign->source()]++;
        }

        $filtered = $campaigns
            ->when($state !== 'all', fn ($rows) => $rows->where('state', $state))
            ->when($source !== 'all', fn ($rows) => $rows->filter(fn ($campaign) => $campaign->source() === $source))
            ->values();

        $perPage = 50;
        $page = max(1, (int) $request->page);
        $pageRows = $filtered->forPage($page, $perPage)->values();
        $names = $this->objectNames($pageRows);

        $paginator = new LengthAwarePaginator($pageRows, $filtered->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return Inertia::render('GrabPromotion/Index', [
            'campaigns' => [
                'data' => $pageRows->map(fn ($campaign) => $this->row($campaign, $machines, $names))->values(),
                'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
                'meta' => ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()],
            ],
            'totals' => $totals,
            'reads' => $this->readsSummary($refIds, $machines),
            'filters' => ['view' => $view, 'state' => $state, 'source' => $source, 'search' => $search],
            'keepGoneDays' => DeliveryPlatformLiveCampaign::KEEP_GONE_DAYS,
        ]);
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
            'no_account' => back()->with('error', __('This operator has no Grab account in mark1.')),
            default => back()->with('error', __('Grab did not answer for :ref (HTTP :code); see Grab Errors.', ['ref' => $ref->ref_number, 'code' => $result['code'] ?? '?'])),
        };
    }

    private function row(DeliveryPlatformLiveCampaign $campaign, $machines, array $names): array
    {
        $json = $campaign->campaign_json ?? [];
        $objectIds = array_map('strval', $json['discount']['scope']['objectIDs'] ?? []);
        $itemVend = $campaign->deliveryPlatformCampaignItemVend;

        return [
            'id' => $campaign->id,
            'grab_campaign_id' => $campaign->grab_campaign_id,
            'merchant_id' => $campaign->merchant_id,
            'ref_id' => $campaign->delivery_platform_ref_number_id,
            'operator_code' => $campaign->deliveryPlatformRefNumber?->operator?->code,
            'vend_code' => $machines[$campaign->delivery_platform_ref_number_id] ?? null,
            'state' => $campaign->state,
            'source' => $campaign->source(),
            'created_by' => $campaign->created_by,
            'name' => $campaign->name,
            'discount' => $json['discount'] ?? null,
            'conditions' => collect($json['conditions'] ?? [])->except('workingHour')->all(),
            'quotas' => $json['quotas'] ?? null,
            'objects' => array_map(fn ($id) => ['id' => $id, 'name' => $names[$campaign->scope_type][$id] ?? null], $objectIds),
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'first_seen_at' => $campaign->first_seen_at?->toIso8601String(),
            'last_seen_at' => $campaign->last_seen_at?->toIso8601String(),
            'gone_at' => $campaign->gone_at?->toIso8601String(),
            // The mark1 campaign behind it, and what mark1 believes about it.
            'mark1_campaign_id' => $itemVend?->delivery_platform_campaign_id,
            'mark1_vend_code' => $itemVend?->vend_code,
            'mark1_ended_at' => $itemVend?->datetime_to?->toIso8601String(),
            'mark1_active' => $itemVend ? (bool) $itemVend->is_active : null,
            'mark1_binding_closed' => $itemVend ? (bool) $itemVend->deliveryProductMappingVend?->end_date : null,
            'campaign_json' => $json,
        ];
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
