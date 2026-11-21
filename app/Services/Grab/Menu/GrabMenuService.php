<?php

namespace App\Services\Grab\Menu;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use App\Models\GrabMenuSync;
use App\Models\Operator;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of Grab Menu & Prices (grab_menu_settings, grab_menu_products). Every
 * change asks Grab to read the menu again of the operator's stores on the machine menu
 * (debounced, and skipped by the notify job when the menu did not change). Each change is one row on Grab Menu
 * Sync (GrabMenuSyncTracker), with the stores it reached.
 */
class GrabMenuService
{
    public function __construct(private GrabMenuSyncTracker $tracker) {}

    public function saveSetting(int $operatorId, array $data, ?int $userId): GrabMenuSetting
    {
        $setting = GrabMenuSetting::forOperator($operatorId);
        $before = $setting->exists ? GrabPriceRule::forCompany($setting)->label() : null;
        // Checked as a rule first: an impossible one is refused before anything is written.
        $rule = new GrabPriceRule(
            mode: GrabPricingMode::from((string) ($data['pricing_mode'] ?? $setting->pricing_mode->value)),
            rpType: (int) ($data['rp_type'] ?? $setting->rp_type),
            multiplierBp: (int) ($data['multiplier_bp'] ?? $setting->multiplier_bp),
            roundUpCents: (int) ($data['round_up_cents'] ?? $setting->round_up_cents),
            scope: GrabPriceRuleScope::Company,
            operatorId: $operatorId,
            tierMultipliersBp: array_key_exists('tier_multipliers_bp', $data) ? $data['tier_multipliers_bp'] : $setting->tier_multipliers_json,
        );
        $setting->fill(collect($data)->only(['pricing_mode', 'rp_type', 'multiplier_bp', 'round_up_cents', 'category_json'])->all());
        $setting->tier_multipliers_json = $rule->tierMultipliersBp ?: null;
        $setting->updated_by = $userId;
        $setting->save();
        $after = GrabPriceRule::forCompany($setting->fresh())->label();

        $summary = $before === null || $before === $after
            ? "Company price rule of {$this->operatorCode($operatorId)} saved: {$after}"
            : "Company price rule of {$this->operatorCode($operatorId)}: {$before} → {$after}";
        $this->tracker->record(GrabMenuSync::KIND_COMPANY_RULE, $operatorId, $userId, $summary,
            fn () => $this->notifyOperator($operatorId), ['before' => $before, 'after' => $after]);

        return $setting;
    }

    /**
     * One product's Grab sub-category and / or custom price (integer cents); keys left
     * out of $data are kept. A changed custom price keeps the one before it.
     */
    public function saveProduct(int $operatorId, int $productId, array $data, ?int $userId): GrabMenuProduct
    {
        $row = GrabMenuProduct::query()->firstOrNew(['operator_id' => $operatorId, 'product_id' => $productId]);
        $before = ['sub' => $row->sub_category_json['name'] ?? null, 'price' => $row->custom_amount];
        if (array_key_exists('sub_category_json', $data)) {
            $row->sub_category_json = $data['sub_category_json'];
        }
        if (array_key_exists('custom_amount', $data) && $row->custom_amount !== $data['custom_amount']) {
            $row->previous_custom_amount = $row->custom_amount;
            $row->custom_amount = $data['custom_amount'];
        }
        if ($row->isDirty()) {
            $priceChanged = $row->isDirty('custom_amount');
            $row->updated_by = $userId;
            $row->save();
            $product = $this->productLabel($productId);
            $summary = $priceChanged
                ? "Typed Grab price of {$product} ({$this->operatorCode($operatorId)}): ".self::money($before['price']).' → '.self::money($row->custom_amount)
                : "Grab sub-category of {$product} ({$this->operatorCode($operatorId)}): ".($row->sub_category_json['name'] ?? 'none');
            $this->tracker->record($priceChanged ? GrabMenuSync::KIND_CUSTOM_PRICE : GrabMenuSync::KIND_SUB_CATEGORY, $operatorId, $userId, $summary,
                fn () => $this->notifyOperator($operatorId, $productId));
        }

        return $row;
    }

    /** @param  int[]  $productIds */
    public function saveSubCategory(int $operatorId, array $productIds, array $subCategory, ?int $userId): int
    {
        $saved = 0;
        $count = count(array_unique($productIds));
        $summary = "Grab sub-category {$subCategory['name']} set on {$count} product".($count === 1 ? '' : 's')." ({$this->operatorCode($operatorId)})";
        $this->tracker->record(GrabMenuSync::KIND_SUB_CATEGORY, $operatorId, $userId, $summary, fn () => DB::transaction(function () use ($operatorId, $productIds, $subCategory, $userId, &$saved) {
            foreach (array_unique($productIds) as $productId) {
                $row = $this->saveProduct($operatorId, (int) $productId, ['sub_category_json' => $subCategory], $userId);
                $saved += $row->wasChanged() || $row->wasRecentlyCreated ? 1 : 0;
            }
        }));

        return $saved;
    }

    /**
     * Fill what is still empty from the operator's old Grab templates: the Grab category,
     * and per product the sub-category and price it carries most often. Nothing already
     * set is overwritten.
     *
     * @return array{category: bool, sub_categories: int, prices: int}
     */
    public function fillFromTemplates(int $operatorId, ?int $userId): array
    {
        $result = ['category' => false, 'sub_categories' => 0, 'prices' => 0];
        $templates = DB::table('delivery_product_mappings')->where('operator_id', $operatorId)->pluck('category_json', 'id');
        if ($templates->isEmpty()) {
            return $result;
        }

        DB::transaction(function () use ($operatorId, $userId, $templates, &$result) {
            $setting = GrabMenuSetting::forOperator($operatorId);
            if (empty($setting->category_json['id'])) {
                $category = $templates->map(fn ($json) => json_decode((string) $json, true))
                    ->filter(fn ($json) => ! empty($json['id']))
                    ->map(fn ($json) => ['id' => (string) $json['id'], 'name' => (string) ($json['name'] ?? $json['id'])])
                    ->countBy(fn ($json) => json_encode($json))
                    ->sortDesc()
                    ->keys()
                    ->first();
                if ($category) {
                    $setting->category_json = json_decode($category, true);
                    $setting->updated_by = $userId;
                    $setting->save();
                    $result['category'] = true;
                }
            }

            $items = DB::table('delivery_product_mapping_items')
                ->whereIn('delivery_product_mapping_id', $templates->keys())
                ->get(['product_id', 'amount', 'sub_category_json'])
                ->groupBy('product_id');
            $existing = GrabMenuProduct::query()->where('operator_id', $operatorId)->get()->keyBy('product_id');

            foreach ($items as $productId => $rows) {
                $row = $existing[$productId] ?? new GrabMenuProduct(['operator_id' => $operatorId, 'product_id' => (int) $productId]);
                if (empty($row->sub_category_json['id'])) {
                    $sub = self::mostCommon($rows->map(fn ($item) => json_decode((string) $item->sub_category_json, true))
                        ->filter(fn ($json) => ! empty($json['id']))
                        ->map(fn ($json) => ['id' => (string) $json['id'], 'name' => (string) ($json['name'] ?? $json['id'])]));
                    if ($sub) {
                        $row->sub_category_json = $sub;
                        $result['sub_categories']++;
                    }
                }
                if ($row->custom_amount === null) {
                    $cents = self::mostCommon($rows->pluck('amount')->map(fn ($cents) => (int) $cents)->filter(fn ($cents) => $cents > 0));
                    if ($cents) {
                        $row->custom_amount = $cents;
                        $result['prices']++;
                    }
                }
                if ($row->isDirty()) {
                    $row->updated_by = $userId;
                    $row->save();
                }
            }
        });

        $this->tracker->record(GrabMenuSync::KIND_SUB_CATEGORY, $operatorId, $userId,
            "Filled from the old Grab menus ({$this->operatorCode($operatorId)}): {$result['sub_categories']} sub-categories, {$result['prices']} typed prices",
            fn () => $this->notifyOperator($operatorId));

        return $result;
    }

    /**
     * A product changed on Product → Edit (an RP, its name or photo): every machine-menu
     * store whose machine holds it reads its menu again.
     */
    public function productChanged(int $productId): void
    {
        $this->tracker->record(GrabMenuSync::KIND_PRODUCT, null, auth()->id(), "{$this->productLabel($productId)} changed on Product → Edit (price, name or photo)",
            fn () => self::notifySpread($this->machineMenuBindings()
                ->filter(fn ($binding) => DB::table('vend_channels')->where('vend_id', $binding->vend_id)->where('product_id', $productId)->exists())));
    }

    public function notifyOperator(int $operatorId, ?int $productId = null): void
    {
        self::notifySpread($this->machineMenuBindings($operatorId)
            ->when($productId, fn ($bindings) => $bindings->filter(
                fn ($binding) => DB::table('vend_channels')->where('vend_id', $binding->vend_id)->where('product_id', $productId)->exists()
            )));
    }

    // Grab Menu Sync spaces the asks of one change a few seconds apart (debounced() reads its place in the change).
    private static function notifySpread(Collection $bindings): void
    {
        $bindings->each(fn ($binding) => NotifyDeliveryPlatformUpdateMenu::debounced($binding));
    }

    private function operatorCode(int $operatorId): string
    {
        return (string) (Operator::withoutGlobalScopes()->whereKey($operatorId)->value('code') ?? $operatorId);
    }

    private function productLabel(int $productId): string
    {
        $product = Product::withoutGlobalScopes()->whereKey($productId)->first(['id', 'code', 'name']);

        return $product ? trim($product->code.' '.$product->name) : 'Product '.$productId;
    }

    public static function money(?int $cents): string
    {
        return $cents === null ? 'none' : '$'.number_format($cents / 100, 2);
    }

    /** @return Collection<int, DeliveryProductMappingVend> selling bindings of stores on the machine menu */
    private function machineMenuBindings(?int $operatorId = null): Collection
    {
        return DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->selling()
            ->whereHas('deliveryPlatformRefNumber', fn ($query) => $query
                ->whereNotNull('menu_from_machine_at')
                ->when($operatorId, fn ($query) => $query->where('operator_id', $operatorId)))
            ->get();
    }

    // The value seen most often (ties: the first seen), or null for none.
    private static function mostCommon(Collection $values): mixed
    {
        if ($values->isEmpty()) {
            return null;
        }
        $key = $values->countBy(fn ($value) => json_encode($value))->sortDesc()->keys()->first();

        return json_decode($key, true);
    }
}
