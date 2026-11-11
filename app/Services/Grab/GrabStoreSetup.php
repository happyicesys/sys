<?php

namespace App\Services\Grab;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\GrabStorePriceRule;
use App\Models\Vend;
use App\Services\Grab\Menu\GrabMachineMenu;
use App\Services\Grab\Menu\GrabMenuOverview;
use App\Services\Grab\Menu\GrabMenuService;
use App\Services\Grab\Menu\GrabPriceRule;
use App\Services\Grab\Menu\GrabPriceRuleResolver;
use App\Services\Grab\Menu\GrabPricingMode;
use App\Services\Grab\Menu\GrabStorePriceRuleService;
use App\Services\Grab\Menu\MachineMenuLine;

/**
 * Putting a Grab store on a machine in one step (Brian, 2026-11-11: "straight away just using … the product mapping
 * from that machine … if there is any complication, prompt the user to finish … then save, and it runs with the new
 * menu and the price"). Grab Stores' window asks for the merchant ID and the machine ID only; this class shows what
 * Grab would list from that machine under the price rule chosen there (the company's or the store's own), names what
 * is missing — a product with no Grab sub-category, or no price under a typed-price rule — and saves what the person
 * filled in before the store goes live. No old Grab menu (template) is involved.
 */
class GrabStoreSetup
{
    public function __construct(
        private GrabMachineMenu $machineMenu,
        private GrabPriceRuleResolver $rules,
        private GrabMenuService $menus,
        private GrabStorePriceRuleService $storeRules,
    ) {}

    /**
     * What the window shows for $vend: each product Grab would list, with its price and what is missing.
     *
     * @param  array{use_company_rule?: bool, pricing_mode?: ?string, rp_type?: ?int, multiplier_bp?: ?int, round_up_cents?: ?int}  $choice
     */
    public function preview(int $operatorId, Vend $vend, ?DeliveryPlatformRefNumber $ref, array $choice): array
    {
        $company = $this->rules->forCompany($operatorId);
        $own = $ref ? GrabStorePriceRule::query()->where('delivery_platform_ref_number_id', $ref->id)->first() : null;
        $rule = $this->ruleFor($operatorId, $ref, $choice, $company);

        $lines = $this->machineMenu->linesFor($vend->id, $operatorId, $rule)->values();
        $rows = $lines->map(fn (MachineMenuLine $line) => [
            'product_id' => $line->product->id,
            'code' => $line->product->code,
            'name' => $line->product->name,
            'photo' => $line->product->thumbnail?->full_url,
            'sub_category' => $line->subCategory,
            'rp' => $line->rp,
            'price' => $line->price,
            'available' => $line->available(),
            'problem' => $line->problem(),
        ])->sortBy(fn ($row) => [$row['problem'] === null ? 1 : 0, $row['code']])->values();

        return [
            'machine' => ['code' => $vend->code, 'site' => $vend->customer?->name],
            'company_rule' => $company->toArray(),
            'own_rule' => $own ? GrabPriceRule::forStore($own, $operatorId)->toArray() + ['note' => $own->note] : null,
            'rule' => $rule->toArray(),
            'rows' => $rows->all(),
            'counts' => [
                'products' => $rows->count(),
                'listed' => $rows->whereNull('problem')->count(),
                'missing' => $rows->whereNotNull('problem')->count(),
            ],
            'sub_categories' => (new GrabMenuOverview($operatorId))->knownSubCategories(),
        ];
    }

    /**
     * Saves what the person chose in the window, before the store goes on the machine: the store's price rule, each
     * product's Grab sub-category (the operator's, shared by its stores) and typed prices (the company's under a
     * company typed-price rule, else this store's).
     *
     * @param  array{use_company_rule: bool, pricing_mode?: ?string, rp_type?: ?int, multiplier_bp?: ?int, round_up_cents?: ?int, note?: ?string, sub_categories?: array, custom_prices?: array}  $setup
     */
    public function apply(DeliveryPlatformRefNumber $ref, array $setup, ?int $userId): void
    {
        $operatorId = (int) $ref->operator_id;
        foreach ($setup['sub_categories'] ?? [] as $pick) {
            $this->menus->saveProduct($operatorId, (int) $pick['product_id'], ['sub_category_json' => ['id' => (string) $pick['id'], 'name' => (string) ($pick['name'] ?? $pick['id'])]], $userId);
        }

        if ($setup['use_company_rule']) {
            $this->storeRules->useCompanyRule($ref, $userId);
        } else {
            $this->storeRules->useOwnRule($ref, $setup, $setup['note'] ?? null, $userId);
        }

        $typedForStore = ! $setup['use_company_rule'];
        $typedForCompany = $setup['use_company_rule'] && $this->rules->forCompany($operatorId)->mode === GrabPricingMode::Custom;
        foreach ($setup['custom_prices'] ?? [] as $typed) {
            $cents = (int) round(((float) $typed['price']) * 100);
            if ($typedForStore) {
                $this->storeRules->saveCustomPrice($ref, (int) $typed['product_id'], $cents, $userId);
            } elseif ($typedForCompany) {
                $this->menus->saveProduct($operatorId, (int) $typed['product_id'], ['custom_amount' => $cents], $userId);
            }
        }
    }

    // The rule the window prices by: the company's, the store's own as saved, or the draft being chosen.
    private function ruleFor(int $operatorId, ?DeliveryPlatformRefNumber $ref, array $choice, GrabPriceRule $company): GrabPriceRule
    {
        if (($choice['use_company_rule'] ?? true) || empty($choice['pricing_mode'])) {
            return $company;
        }

        return GrabPriceRule::draftForStore([
            'pricing_mode' => $choice['pricing_mode'],
            'rp_type' => (int) ($choice['rp_type'] ?? 2),
            'multiplier_bp' => (int) ($choice['multiplier_bp'] ?? 10000),
            'round_up_cents' => (int) ($choice['round_up_cents'] ?? 10),
        ], $operatorId, $ref?->id ?? 0);
    }
}
