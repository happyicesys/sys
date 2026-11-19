<?php

namespace App\Services\Grab;

use App\Models\Customer;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMappingVend;
use App\Models\Vend;
use App\Services\GrabStoreService;
use Illuminate\Support\Facades\Log;

/**
 * A Grab store stops selling when its machine leaves the Site it was set up at (Brian, 2026-11-19: "when the machine
 * and the Site, their binding is broken or bound to another Site for that same machine, switch off the Grab store").
 * Grab lists the store at the old Site's address, so a customer would order from a place the machine is no longer at.
 *
 * - The machine unbound from its Site, or moved to another one → each open Grab binding of that machine that is
 *   selling is PAUSED on Grab, with the reason and `site_change` {from, to} on the store's History.
 * - The machine back at the Site the store was set up at, or that Site updated to where the machine is now (Update on
 *   Grab → Done, or the GrabMerchant check seeing the new address) → a store this rule paused is resumed. A store a
 *   person paused, or paused again after this rule, stays paused.
 *
 * The binding's `customer_id` is the Site the store was set up at (GrabStoreService::open). A binding from before
 * that column was kept has none; it takes the Site the machine just left, so the change still shows.
 * Never throws: a Site save must not fail because Grab could not be reached.
 */
class GrabSiteGuard
{
    public function __construct(private GrabStoreService $stores) {}

    /** The machine's Site changed from $from to $to (either may be null). */
    public function machineSiteChanged(Vend $vend, ?int $from, ?int $to, ?int $userId): void
    {
        if ($from === $to) {
            return;
        }

        $bindings = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->whereNull('end_date')
            ->get();

        foreach ($bindings as $binding) {
            try {
                $this->follow($binding, $vend, $from, $to, $userId);
            } catch (\Throwable $e) {
                Log::warning('Grab site guard failed', ['binding' => $binding->id, 'vend' => $vend->code, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * The binding's Site now matches its machine again (called after a Site was recorded on the binding): resume it
     * if this rule paused it. Returns true when resumed.
     */
    public function resumeIfSettled(DeliveryProductMappingVend $binding, ?int $userId, string $reason): bool
    {
        try {
            $binding->refresh();
            $vendSite = Vend::query()->withoutGlobalScopes()->whereKey($binding->vend_id)->value('customer_id');
            if ($binding->end_date || $binding->is_active || ! $vendSite || (int) $vendSite !== (int) $binding->customer_id) {
                return false;
            }
            if (! $this->pausedBySiteChange($binding)) {
                return false;
            }
            $this->stores->resume($binding, $userId, $reason, ['site_change' => 'settled']);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Grab site guard resume failed', ['binding' => $binding->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** True when the binding's latest pause was this rule's (a person's pause after it wins). */
    public function pausedBySiteChange(DeliveryProductMappingVend $binding): bool
    {
        $latest = DeliveryPlatformStoreEvent::query()
            ->where('delivery_product_mapping_vend_id', $binding->id)
            ->whereIn('event', [DeliveryPlatformStoreEvent::EVENT_PAUSED, DeliveryPlatformStoreEvent::EVENT_RESUMED])
            ->orderByDesc('id')
            ->first();

        return $latest
            && $latest->event === DeliveryPlatformStoreEvent::EVENT_PAUSED
            && is_array($latest->data)
            && isset($latest->data['site_change']);
    }

    private function follow(DeliveryProductMappingVend $binding, Vend $vend, ?int $from, ?int $to, ?int $userId): void
    {
        // A binding with no Site on record takes the one its machine just left.
        if ($binding->customer_id === null && $from !== null) {
            $binding->forceFill(['customer_id' => $from])->save();
        }
        $storeSite = $binding->customer_id !== null ? (int) $binding->customer_id : null;
        if ($storeSite === null) {
            return;
        }

        if ($to !== null && $to === $storeSite) {
            $this->resumeIfSettled($binding, $userId, __('Machine :code is back at the Site the store was set up at', ['code' => $vend->code]));

            return;
        }

        if (! $binding->is_active) {
            return;
        }

        $fromLabel = DeliveryProductMappingVend::siteLabel(Customer::query()->withoutGlobalScopes()->find($storeSite)) ?? '#'.$storeSite;
        $reason = $to === null
            ? __('Machine :code was unbound from Site :site', ['code' => $vend->code, 'site' => $fromLabel])
            : __('Machine :code moved from Site :from to :to', [
                'code' => $vend->code,
                'from' => $fromLabel,
                'to' => DeliveryProductMappingVend::siteLabel(Customer::query()->withoutGlobalScopes()->find($to)) ?? '#'.$to,
            ]);

        $this->stores->pause($binding, $userId, $reason, null, ['site_change' => ['from' => $storeSite, 'to' => $to]]);
    }
}
