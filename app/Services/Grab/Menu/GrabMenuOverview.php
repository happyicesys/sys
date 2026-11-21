<?php

namespace App\Services\Grab\Menu;

use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\GrabStorePriceRule;
use App\Models\Product;
use App\Services\Grab\GrabStoreProfiles;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What Grab Menu & Prices shows for one operator (read only): every product on its Grab
 * machines' slots with its Grab price under the operator's (company) rule, and its Grab
 * stores with the rule each follows. One store's own preview is GrabStorePreview.
 */
class GrabMenuOverview
{
    private Collection $bindings;

    public function __construct(private int $operatorId)
    {
        $this->bindings = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->whereHas('deliveryPlatformRefNumber', fn ($query) => $query->where('operator_id', $operatorId))
            ->with([
                'deliveryPlatformRefNumber',
                'customer' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name', 'virtual_customer_prefix', 'virtual_customer_code'),
                'vend:id,customer_id',
                'vend.customer' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'code', 'name', 'virtual_customer_prefix', 'virtual_customer_code'),
                // The Site's postal code against Grab's ("Grab store" column).
                'vend.customer.deliveryAddress:id,modelable_type,modelable_id,type,postcode',
            ])
            ->orderByDesc('id')
            ->get()
            ->unique('delivery_platform_ref_number_id')
            ->values();
    }

    public function setting(): GrabMenuSetting
    {
        return GrabMenuSetting::forOperator($this->operatorId);
    }

    /** One row per product on the operator's Grab machines, or with Grab facts saved. */
    public function products(): array
    {
        $rule = GrabPriceRule::forCompany($this->setting());
        $machinesByProduct = DB::table('vend_channels')
            ->whereIn('vend_id', $this->bindings->pluck('vend_id'))
            ->where('is_active', true)
            ->whereNotNull('product_id')
            ->get(['product_id', 'vend_id'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('vend_id')->unique()->count());
        $saved = GrabMenuProduct::query()->where('operator_id', $this->operatorId)->get()->keyBy('product_id');
        $productIds = $machinesByProduct->keys()->merge($saved->keys())->map(fn ($id) => (int) $id)->unique()->values()->all();

        // A rule on the Site's own tier prices each store by its tier: one price list per tier the stores use, RP2 first.
        $tiers = $rule->usesSiteTier()
            ? collect(GrabPriceList::siteTiers($this->bindings->pluck('vend_id')->filter()->all()))
                ->map(fn ($tier) => $rule->tierFor($tier))->push(GrabPriceRule::FALLBACK_TIER)->unique()->sort()->values()->all()
            : [null];
        $lists = collect($tiers)->mapWithKeys(fn ($tier) => [(int) $tier => new GrabPriceList($rule, $productIds, $tier)]);
        $prices = $rule->usesSiteTier() ? $lists[GrabPriceRule::FALLBACK_TIER] : $lists->first();
        $rp = $rule->usesSiteTier() ? null : GrabPriceList::rpPrices($productIds, $rule->rpType);
        $products = Product::query()->withoutGlobalScopes()
            ->with('thumbnail:id,full_url,attachments.modelable_type,attachments.modelable_id')
            ->whereIn('id', $productIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return collect($productIds)
            ->filter(fn ($id) => $products->has($id))
            ->map(function ($id) use ($products, $saved, $prices, $rp, $lists, $rule, $machinesByProduct) {
                $price = $prices->price($id);
                $normal = $rp === null ? $prices->rp($id) : ($rp[$id] ?? null);
                // No price on any tier a store uses = not on Grab anywhere.
                $missing = $lists->every(fn (GrabPriceList $list) => $list->price($id) === null) ? $rule->missingReason() : null;
                $sub = $saved[$id]->sub_category_json ?? null;

                return [
                    'product_id' => $id,
                    'code' => $products[$id]->code,
                    'name' => $products[$id]->name,
                    'photo' => $products[$id]->thumbnail?->full_url,
                    'sub_category' => $sub,
                    'custom_amount' => $saved[$id]->custom_amount ?? null,
                    'rp' => $normal,
                    'grab_price' => $price,
                    // Grab price vs the RP, in whole percent (+10 = 10% above).
                    'vs_rp_pct' => $price !== null && $normal ? (int) round(($price - $normal) * 100 / $normal) : null,
                    // Under a Site-tier rule: the price at a store on each tier the stores use.
                    'tier_prices' => $rule->usesSiteTier() ? $lists->map(fn (GrabPriceList $list, $tier) => [
                        'tier' => $tier,
                        'rp_tier' => $list->tierOf($id),
                        'rp' => $list->rp($id),
                        'grab_price' => $list->price($id),
                    ])->values()->all() : null,
                    'machines' => (int) ($machinesByProduct[$id] ?? 0),
                    'problem' => $missing ?? (empty($sub['id']) ? MachineMenuLine::NO_SUB_CATEGORY : null),
                ];
            })
            ->sortBy([['machines', 'desc'], ['code', 'asc']])
            ->values()
            ->all();
    }

    /** The operator's Grab stores on a machine: which menu each lists, which price rule it follows, its Grab orders. */
    public function stores(): array
    {
        $refs = $this->bindings->pluck('deliveryPlatformRefNumber')->filter()->unique('id')->values();
        $rules = app(GrabPriceRuleResolver::class)->forStores($refs);
        $own = GrabStorePriceRule::query()->with('updater:id,name')->whereIn('delivery_platform_ref_number_id', $refs->pluck('id'))->get()->keyBy('delivery_platform_ref_number_id');
        // Grab orders of the store over the last 30 and 7 days, yesterday and today (Brian, 2026-11-21).
        $orders = DB::table('delivery_platform_orders')
            ->whereIn('delivery_platform_ref_number_id', $refs->pluck('id'))
            ->where('order_created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('delivery_platform_ref_number_id')
            ->selectRaw('delivery_platform_ref_number_id AS ref_id, COUNT(*) AS d30')
            ->selectRaw('SUM(order_created_at >= ?) AS d7', [Carbon::now()->subDays(7)])
            ->selectRaw('SUM(order_created_at >= ? AND order_created_at < ?) AS yesterday', [Carbon::yesterday(), Carbon::today()])
            ->selectRaw('SUM(order_created_at >= ?) AS today', [Carbon::today()])
            ->get()
            ->keyBy('ref_id');
        $typers = DB::table('users')->whereIn('id', $refs->pluck('grab_profile_user_id')->filter()->unique())->pluck('name', 'id');

        $siteTiers = GrabPriceList::siteTiers($this->bindings->pluck('vend_id')->filter()->all());
        // Grab Menu Sync: each store's latest change and whether Grab has it ("On Grab" column).
        $onGrab = GrabMenuSyncView::latestByBinding($this->bindings->pluck('id')->all());
        $causes = GrabMenuSyncView::causesByBinding($this->bindings->mapWithKeys(fn (DeliveryProductMappingVend $binding) => [$binding->id => [
            'items' => self::iso($binding->grab_items_changed_at),
            'prices' => self::iso($binding->grab_prices_changed_at),
        ]])->all());

        return $this->bindings->map(function (DeliveryProductMappingVend $binding) use ($rules, $own, $orders, $siteTiers, $onGrab, $typers, $causes) {
            $refId = $binding->delivery_platform_ref_number_id;
            $row = $own[$refId] ?? null;
            $ref = $binding->deliveryPlatformRefNumber;
            $count = $orders[$refId] ?? null;

            return [
                'ref_id' => $refId,
                'binding_id' => $binding->id,
                'on_grab' => $onGrab[$binding->id] ?? null,
                'merchant_id' => $binding->deliveryPlatformRefNumber?->ref_number,
                'vend_code' => $binding->vend_code,
                'site' => $binding->siteName(),
                // The Site's RP tier: what a rule on the Site's own tier prices this store by.
                'site_tier' => $siteTiers[$binding->vend_id] ?? null,
                'selling' => $binding->isSelling(),
                'machine_menu_since' => $binding->deliveryPlatformRefNumber?->menu_from_machine_at?->toIso8601String(),
                'orders_30d' => (int) ($count->d30 ?? 0),
                'orders' => [
                    'd30' => (int) ($count->d30 ?? 0),
                    'd7' => (int) ($count->d7 ?? 0),
                    'yesterday' => (int) ($count->yesterday ?? 0),
                    'today' => (int) ($count->today ?? 0),
                ],
                // Nobody can open a store's live menu on Grab, so: when Grab last took it, and when what it took
                // changed — the items listed, their prices (tracked from 2026-11-21).
                'grab_times' => [
                    'pulled_at' => self::iso($binding->grab_menu_pulled_at),
                    'items_changed_at' => self::iso($binding->grab_items_changed_at),
                    'prices_changed_at' => self::iso($binding->grab_prices_changed_at),
                    // Who sent the change behind each: {by: name or NULL, kind}; NULL = not known.
                    'items_changed_by' => $causes[$binding->id]['items'] ?? null,
                    'prices_changed_by' => $causes[$binding->id]['prices'] ?? null,
                ],
                // What customers see on Grab: the GrabMerchant website read, or typed by a person on Grab Stores → Edit.
                'grab_profile' => $ref?->grab_profile_checked_at ? [
                    'name' => $ref->grab_store_name,
                    'address' => $ref->grab_address,
                    'at' => $ref->grab_profile_checked_at->toIso8601String(),
                    'typed_by' => $ref->grab_profile_source === GrabStoreProfiles::SOURCE_TYPED ? ($typers[$ref->grab_profile_user_id] ?? __('a person')) : null,
                    'match' => GrabStoreProfiles::matches($ref->grab_postal_code, $binding),
                ] : null,
                'price_rule' => isset($rules[$refId]) ? [
                    ...$rules[$refId]->toArray(),
                    'note' => $row?->note,
                    'updated_at' => $row?->updated_at?->toIso8601String(),
                    'updated_by' => $row?->updater?->name,
                ] : null,
            ];
        })->sortBy('vend_code')->values()->all();
    }

    private static function iso(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }

    /** Sub-categories the operator's products and templates already use, for the pickers. */
    public function knownSubCategories(): array
    {
        $fromTemplates = DB::table('delivery_product_mapping_items as item')
            ->join('delivery_product_mappings as template', 'template.id', '=', 'item.delivery_product_mapping_id')
            ->where('template.operator_id', $this->operatorId)
            ->pluck('item.sub_category_json')
            ->map(fn ($json) => json_decode((string) $json, true));
        $saved = GrabMenuProduct::query()->where('operator_id', $this->operatorId)->pluck('sub_category_json');

        return $fromTemplates->merge($saved)
            ->filter(fn ($sub) => ! empty($sub['id']))
            ->map(fn ($sub) => ['id' => (string) $sub['id'], 'name' => (string) ($sub['name'] ?? $sub['id'])])
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();
    }
}
