<?php

namespace App\Services\Grab\Menu;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\GrabMenuSync;
use App\Models\GrabStoreCustomPrice;
use App\Models\GrabStorePriceRule;
use App\Models\Product;
use App\Services\GrabStoreService;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of a Grab store's own price rule (grab_store_price_rules) and its typed prices
 * (grab_store_custom_prices). Every change is in the store's history (event price_rule) and, for a store on the
 * machine menu, asks Grab to read its menu again (debounced; skipped when nothing changed).
 */
class GrabStorePriceRuleService
{
    public function __construct(private GrabStoreService $stores, private GrabMenuSyncTracker $tracker) {}

    /**
     * Give a store its own rule. $values carries pricing_mode, rp_type, multiplier_bp, tier_multipliers_bp and round_up_cents; an
     * impossible rule is refused by GrabPriceRule before anything is written.
     */
    public function useOwnRule(DeliveryPlatformRefNumber $ref, array $values, ?string $note, ?int $userId): GrabStorePriceRule
    {
        $rule = GrabPriceRule::draftForStore($values, (int) $ref->operator_id, $ref->id);
        $note = ($note = trim((string) $note)) === '' ? null : mb_substr($note, 0, 255);

        $row = DB::transaction(function () use ($ref, $rule, $note, $userId) {
            $row = GrabStorePriceRule::query()->firstOrNew(['delivery_platform_ref_number_id' => $ref->id]);
            $row->fill([
                'pricing_mode' => $rule->mode,
                'rp_type' => $rule->rpType,
                'multiplier_bp' => $rule->multiplierBp,
                'tier_multipliers_json' => $rule->tierMultipliersBp ?: null,
                'round_up_cents' => $rule->roundUpCents,
                'note' => $note,
            ]);
            if (! $row->isDirty()) {
                return $row;
            }
            $row->updated_by = $userId;
            $row->save();
            $this->stores->event($ref, DeliveryPlatformStoreEvent::EVENT_PRICE_RULE, $userId, null, null, ['own' => true, ...$rule->toArray(), 'note' => $note]);

            return $row;
        });

        if ($row->wasChanged() || $row->wasRecentlyCreated) {
            $this->notify($ref, GrabMenuSync::KIND_STORE_RULE, $userId, "Own price rule for store {$this->storeLabel($ref)}: {$rule->label()}".($note ? " — {$note}" : ''));
        }

        return $row;
    }

    // Put a store back on its operator's rule. Its typed prices are kept, so giving it a custom rule again restores them.
    public function useCompanyRule(DeliveryPlatformRefNumber $ref, ?int $userId): bool
    {
        $deleted = DB::transaction(function () use ($ref, $userId) {
            $deleted = GrabStorePriceRule::query()->where('delivery_platform_ref_number_id', $ref->id)->delete() > 0;
            if ($deleted) {
                $this->stores->event($ref, DeliveryPlatformStoreEvent::EVENT_PRICE_RULE, $userId, null, null, ['own' => false]);
            }

            return $deleted;
        });

        if ($deleted) {
            $this->notify($ref, GrabMenuSync::KIND_STORE_RULE, $userId, "Store {$this->storeLabel($ref)} back on the company price rule");
        }

        return $deleted;
    }

    // One product's typed Grab price at this store (integer cents); null removes it, so the product is not listed.
    public function saveCustomPrice(DeliveryPlatformRefNumber $ref, int $productId, ?int $cents, ?int $userId): void
    {
        $row = GrabStoreCustomPrice::query()->firstOrNew(['delivery_platform_ref_number_id' => $ref->id, 'product_id' => $productId]);
        if ($cents === null) {
            if ($row->exists) {
                $before = $row->amount;
                $row->delete();
                $this->notify($ref, GrabMenuSync::KIND_CUSTOM_PRICE, $userId, "Typed Grab price of {$this->productLabel($productId)} at store {$this->storeLabel($ref)}: ".GrabMenuService::money($before).' → none');
            }

            return;
        }
        if ($row->exists && $row->amount === $cents) {
            return;
        }

        $row->previous_amount = $row->exists ? $row->amount : null;
        $row->amount = $cents;
        $row->updated_by = $userId;
        $row->save();
        $this->notify($ref, GrabMenuSync::KIND_CUSTOM_PRICE, $userId, "Typed Grab price of {$this->productLabel($productId)} at store {$this->storeLabel($ref)}: ".GrabMenuService::money($row->previous_amount).' → '.GrabMenuService::money($cents));
    }

    /**
     * A store still on its old Grab menu is not priced by any rule yet; its rule applies once it switches. The
     * change is one row on Grab Menu Sync either way (a store rule with no store asked says why).
     */
    private function notify(DeliveryPlatformRefNumber $ref, string $kind, ?int $userId, string $summary): void
    {
        $this->tracker->record($kind, (int) $ref->operator_id ?: null, $userId, $summary, function () use ($ref) {
            if (! $ref->usesMachineMenu()) {
                return;
            }
            foreach ($ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->get() as $binding) {
                if ($binding->isSelling()) {
                    NotifyDeliveryPlatformUpdateMenu::debounced($binding);
                }
            }
        });
    }

    // "2338 · 4-C6…" for the log: the machine(s) it is on, then the merchant ID.
    private function storeLabel(DeliveryPlatformRefNumber $ref): string
    {
        $codes = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->pluck('vend_code')->filter()->implode('/');

        return trim(($codes !== '' ? $codes.' · ' : '').$ref->ref_number);
    }

    private function productLabel(int $productId): string
    {
        $product = Product::withoutGlobalScopes()->whereKey($productId)->first(['id', 'code', 'name']);

        return $product ? trim($product->code.' '.$product->name) : 'Product '.$productId;
    }
}
