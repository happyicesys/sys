<?php

namespace App\Services\Grab\Menu;

use App\Models\DeliveryPlatformRefNumber;

/**
 * One Grab store as the page's store panel shows it: the rule it follows (or a draft rule being tried), and
 * what its customers would see — each product's price on Grab today against its price on the machine menu under
 * that rule (GrabMenuChange). Read only: nothing is saved and Grab is not asked.
 */
class GrabStorePreview
{
    public function __construct(
        private GrabMachineMenu $machineMenu,
        private GrabPriceRuleResolver $rules,
    ) {}

    public function for(DeliveryPlatformRefNumber $ref, ?GrabPriceRule $draft = null): array
    {
        $binding = $ref->currentDeliveryProductMappingVend()->withoutGlobalScopes()->first();
        $rule = $draft ?? $this->rules->forStore($ref);
        if (! $binding) {
            return ['rule' => $rule->toArray(), 'summary' => null, 'rows' => []];
        }

        $change = new GrabMenuChange($binding->last_menu_json, $this->machineMenu->lines($binding, $rule));

        return [
            'rule' => $rule->toArray(),
            'machine_menu' => $ref->usesMachineMenu(),
            'summary' => $change->summary(),
            'rows' => $change->rows(),
        ];
    }
}
