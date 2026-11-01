<?php

namespace App\Services\Grab\Menu;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuProduct;
use App\Models\GrabMenuSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of Grab Menu & Prices (grab_menu_settings, grab_menu_products). Every
 * change asks Grab to read the menu again of the operator's stores on the machine menu
 * (debounced, and skipped by the notify job when the menu did not change).
 */
class GrabMenuService
{
    public function saveSetting(int $operatorId, array $data, ?int $userId): GrabMenuSetting
    {
        $setting = GrabMenuSetting::forOperator($operatorId);
        $setting->fill(collect($data)->only(['pricing_mode', 'rp_type', 'multiplier_bp', 'round_up_cents', 'category_json'])->all());
        $setting->updated_by = $userId;
        $setting->save();

        $this->notifyOperator($operatorId);

        return $setting;
    }

    /**
     * One product's Grab sub-category and / or custom price (integer cents); keys left
     * out of $data are kept. A changed custom price keeps the one before it.
     */
    public function saveProduct(int $operatorId, int $productId, array $data, ?int $userId): GrabMenuProduct
    {
        $row = GrabMenuProduct::query()->firstOrNew(['operator_id' => $operatorId, 'product_id' => $productId]);
        if (array_key_exists('sub_category_json', $data)) {
            $row->sub_category_json = $data['sub_category_json'];
        }
        if (array_key_exists('custom_amount', $data) && $row->custom_amount !== $data['custom_amount']) {
            $row->previous_custom_amount = $row->custom_amount;
            $row->custom_amount = $data['custom_amount'];
        }
        if ($row->isDirty()) {
            $row->updated_by = $userId;
            $row->save();
            $this->notifyOperator($operatorId, $productId);
        }

        return $row;
    }

    /** @param  int[]  $productIds */
    public function saveSubCategory(int $operatorId, array $productIds, array $subCategory, ?int $userId): int
    {
        $saved = 0;
        DB::transaction(function () use ($operatorId, $productIds, $subCategory, $userId, &$saved) {
            foreach (array_unique($productIds) as $productId) {
                $row = $this->saveProduct($operatorId, (int) $productId, ['sub_category_json' => $subCategory], $userId);
                $saved += $row->wasChanged() || $row->wasRecentlyCreated ? 1 : 0;
            }
        });

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

        $this->notifyOperator($operatorId);

        return $result;
    }

    /**
     * A product changed on Product → Edit (an RP, its name or photo): every machine-menu
     * store whose machine holds it reads its menu again.
     */
    public function productChanged(int $productId): void
    {
        $this->machineMenuBindings()
            ->filter(fn ($binding) => DB::table('vend_channels')->where('vend_id', $binding->vend_id)->where('product_id', $productId)->exists())
            ->each(fn ($binding) => NotifyDeliveryPlatformUpdateMenu::debounced($binding));
    }

    public function notifyOperator(int $operatorId, ?int $productId = null): void
    {
        $this->machineMenuBindings($operatorId)
            ->when($productId, fn ($bindings) => $bindings->filter(
                fn ($binding) => DB::table('vend_channels')->where('vend_id', $binding->vend_id)->where('product_id', $productId)->exists()
            ))
            ->each(fn ($binding) => NotifyDeliveryPlatformUpdateMenu::debounced($binding));
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
