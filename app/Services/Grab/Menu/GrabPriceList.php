<?php

namespace App\Services\Grab\Menu;

use App\Models\GrabMenuProduct;
use App\Models\GrabStoreCustomPrice;
use Illuminate\Support\Facades\DB;

/**
 * The Grab price of each product under one price rule (GrabPriceRule), in integer cents. It loads only what
 * the rule reads: the RP tier for an RP rule, the typed prices for a custom one — the company's typed prices
 * (grab_menu_products) for the company rule, the store's own (grab_store_custom_prices) for a store rule. A
 * product with no price under the rule has none and is not sold on Grab — never at $0.
 */
class GrabPriceList
{
    // Why a product has no Grab price.
    const NO_RP = 'no_rp';

    const NO_CUSTOM_PRICE = 'no_custom_price';

    /** @var array<int, int> product id => RP cents on the rule's tier */
    private array $rp;

    /** @var array<int, int> product id => typed cents */
    private array $custom;

    /** @param  int[]  $productIds */
    public function __construct(public readonly GrabPriceRule $rule, array $productIds)
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $this->rp = $rule->readsRp() ? self::rpPrices($productIds, $rule->rpType) : [];
        $this->custom = $rule->mode === GrabPricingMode::Custom ? self::customPrices($rule, $productIds) : [];
    }

    public function price(int $productId): ?int
    {
        return $this->rule->priceFrom($this->rp[$productId] ?? null, $this->custom[$productId] ?? null);
    }

    // The RP on the rule's tier, shown beside the Grab price as "normal".
    public function rp(int $productId): ?int
    {
        return $this->rp[$productId] ?? null;
    }

    // The typed price, in a custom rule.
    public function custom(int $productId): ?int
    {
        return $this->custom[$productId] ?? null;
    }

    public function missingReason(int $productId): ?string
    {
        return $this->price($productId) === null ? $this->rule->missingReason() : null;
    }

    /**
     * Each product's RP on one tier, in cents; the newest row wins if a tier holds two.
     *
     * @param  int[]  $productIds
     * @return array<int, int>
     */
    public static function rpPrices(array $productIds, int $rpType): array
    {
        if (! $productIds) {
            return [];
        }

        return DB::table('selling_prices')
            ->whereIn('product_id', $productIds)
            ->where('type', $rpType)
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->pluck('amount', 'product_id')
            ->map(fn ($cents) => (int) $cents)
            ->all();
    }

    /**
     * @param  int[]  $productIds
     * @return array<int, int>
     */
    private static function customPrices(GrabPriceRule $rule, array $productIds): array
    {
        if (! $productIds) {
            return [];
        }

        $query = $rule->isStoreRule()
            ? GrabStoreCustomPrice::query()->where('delivery_platform_ref_number_id', $rule->refId)->whereIn('product_id', $productIds)->select(['product_id', 'amount'])
            : GrabMenuProduct::query()->where('operator_id', $rule->operatorId)->whereIn('product_id', $productIds)->whereNotNull('custom_amount')->select(['product_id', 'custom_amount as amount']);

        return $query->pluck('amount', 'product_id')->map(fn ($cents) => (int) $cents)->all();
    }
}
