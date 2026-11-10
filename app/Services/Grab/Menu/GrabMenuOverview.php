<?php

namespace App\Services\Grab\Menu;

use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\GrabStorePriceRule;
use App\Models\Product;
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

        $prices = new GrabPriceList($rule, $productIds);
        $rp = GrabPriceList::rpPrices($productIds, $rule->rpType);
        $products = Product::query()->withoutGlobalScopes()
            ->with('thumbnail:id,full_url,attachments.modelable_type,attachments.modelable_id')
            ->whereIn('id', $productIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return collect($productIds)
            ->filter(fn ($id) => $products->has($id))
            ->map(function ($id) use ($products, $saved, $prices, $rp, $machinesByProduct) {
                $price = $prices->price($id);
                $normal = $rp[$id] ?? null;
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
                    'machines' => (int) ($machinesByProduct[$id] ?? 0),
                    'problem' => $prices->missingReason($id) ?? (empty($sub['id']) ? MachineMenuLine::NO_SUB_CATEGORY : null),
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
        $orders = DB::table('delivery_platform_orders')
            ->whereIn('delivery_platform_ref_number_id', $refs->pluck('id'))
            ->where('order_created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('delivery_platform_ref_number_id')
            ->selectRaw('delivery_platform_ref_number_id AS ref_id, COUNT(*) AS n')
            ->pluck('n', 'ref_id');

        return $this->bindings->map(function (DeliveryProductMappingVend $binding) use ($rules, $own, $orders) {
            $refId = $binding->delivery_platform_ref_number_id;
            $row = $own[$refId] ?? null;

            return [
                'ref_id' => $refId,
                'merchant_id' => $binding->deliveryPlatformRefNumber?->ref_number,
                'vend_code' => $binding->vend_code,
                'site' => $binding->siteName(),
                'selling' => $binding->isSelling(),
                'machine_menu_since' => $binding->deliveryPlatformRefNumber?->menu_from_machine_at?->toIso8601String(),
                'orders_30d' => (int) ($orders[$refId] ?? 0),
                'price_rule' => isset($rules[$refId]) ? [
                    ...$rules[$refId]->toArray(),
                    'note' => $row?->note,
                    'updated_at' => $row?->updated_at?->toIso8601String(),
                    'updated_by' => $row?->updater?->name,
                ] : null,
            ];
        })->sortBy('vend_code')->values()->all();
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
