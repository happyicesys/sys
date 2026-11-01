<?php

namespace App\Services\Grab\Menu;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What Grab Menu & Prices shows for one operator (read only): every product on its Grab
 * machines' slots with its Grab price under the operator's rule, and its Grab stores.
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
        $setting = $this->setting();
        $machinesByProduct = DB::table('vend_channels')
            ->whereIn('vend_id', $this->bindings->pluck('vend_id'))
            ->where('is_active', true)
            ->whereNotNull('product_id')
            ->get(['product_id', 'vend_id'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('vend_id')->unique()->count());
        $saved = GrabMenuProduct::query()->where('operator_id', $this->operatorId)->get()->keyBy('product_id');
        $productIds = $machinesByProduct->keys()->merge($saved->keys())->map(fn ($id) => (int) $id)->unique()->values()->all();

        $prices = new GrabPriceList($setting, $productIds);
        $rp = GrabPriceList::rpPrices($productIds, $setting->rp_type);
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

    /** The operator's Grab stores on a machine, and which menu each lists. */
    public function stores(): array
    {
        return $this->bindings->map(fn (DeliveryProductMappingVend $binding) => [
            'ref_id' => $binding->delivery_platform_ref_number_id,
            'merchant_id' => $binding->deliveryPlatformRefNumber?->ref_number,
            'vend_code' => $binding->vend_code,
            'site' => $binding->siteName(),
            'selling' => $binding->isSelling(),
            'machine_menu_since' => $binding->deliveryPlatformRefNumber?->menu_from_machine_at?->toIso8601String(),
        ])->sortBy('vend_code')->values()->all();
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

    /** A store's machine menu as Grab would get it, for the preview. */
    public static function preview(DeliveryPlatformRefNumber $ref): array
    {
        $binding = $ref->currentDeliveryProductMappingVend()->withoutGlobalScopes()->first();
        if (! $binding) {
            return [];
        }

        return app(GrabMachineMenu::class)->lines($binding)->map(fn (MachineMenuLine $line) => [
            'item_id' => $line->itemId(),
            'code' => $line->product->code,
            'name' => $line->product->name,
            'sub_category' => $line->subCategory['name'] ?? null,
            'price' => $line->price,
            'available' => $line->available(),
            'slots' => $line->slots->pluck('code')->values()->all(),
            'problem' => $line->problem(),
        ])->values()->all();
    }
}
