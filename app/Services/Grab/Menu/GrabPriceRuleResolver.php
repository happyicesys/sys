<?php

namespace App\Services\Grab\Menu;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuSetting;
use App\Models\GrabStorePriceRule;
use Illuminate\Support\Collection;

/**
 * Which price rule a Grab store follows: its own (grab_store_price_rules) when it has one, else its operator's
 * (grab_menu_settings, or the default RP2 rule while the operator has saved none). The one place that decides
 * it — the menu Grab pulls, order intake, promotions and the page all ask here.
 */
class GrabPriceRuleResolver
{
    public function forCompany(int $operatorId): GrabPriceRule
    {
        return GrabPriceRule::forCompany(GrabMenuSetting::forOperator($operatorId));
    }

    public function forStore(DeliveryPlatformRefNumber $ref): GrabPriceRule
    {
        $own = GrabStorePriceRule::query()->where('delivery_platform_ref_number_id', $ref->id)->first();

        return $own ? GrabPriceRule::forStore($own, (int) $ref->operator_id) : $this->forCompany((int) $ref->operator_id);
    }

    // A store binding's rule; a binding with no ref (a template-only row) follows its template's operator.
    public function forBinding(DeliveryProductMappingVend $binding): GrabPriceRule
    {
        $ref = $binding->deliveryPlatformRefNumber;
        if ($ref) {
            return $this->forStore($ref);
        }

        return $this->forCompany((int) $binding->deliveryProductMapping?->operator_id);
    }

    /**
     * Every store's rule at once (two queries), for the page's store list.
     *
     * @param  Collection<int, DeliveryPlatformRefNumber>  $refs
     * @return Collection<int, GrabPriceRule> keyed by ref id
     */
    public function forStores(Collection $refs): Collection
    {
        $own = GrabStorePriceRule::query()->whereIn('delivery_platform_ref_number_id', $refs->pluck('id'))->get()->keyBy('delivery_platform_ref_number_id');
        $company = [];

        return $refs->mapWithKeys(function (DeliveryPlatformRefNumber $ref) use ($own, &$company) {
            $operatorId = (int) $ref->operator_id;
            $rule = isset($own[$ref->id])
                ? GrabPriceRule::forStore($own[$ref->id], $operatorId)
                : ($company[$operatorId] ??= $this->forCompany($operatorId));

            return [$ref->id => $rule];
        });
    }
}
