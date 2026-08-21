<?php

namespace App\Services;

use App\Models\Vend;

/**
 * Pricing source ("Is Using Server Price?") for a machine, and the nudge the
 * terminal needs when its effective prices change.
 *
 * Why both nudges: the APK decides between board price and server price from
 * selectedPricingSource (read from /parameters on TYPESYNCSETTINGSPARAM) and
 * takes the server price itself from the slot list (/thumbnails on
 * TYPESYNCAPICHANNELSLOTLIST). Flipping the flag, rebinding the Site or
 * changing the Site's RP alters those payloads — so the two nudges are sent
 * together and are safe to send late or twice (see VendJobService).
 *
 * Vend-side triggers (flag flip, customer_id change) are caught by
 * VendPricingSourceObserver on save, so no controller has to remember;
 * Customer-side triggers (Site RP change) call nudge() per follower.
 */
class VendPricingSourceService
{
    public function __construct(private VendJobService $vendJobService) {}

    /**
     * Persist the switch. Returns true when it actually changed (the save
     * fires VendPricingSourceObserver, which nudges the terminal); an
     * unchanged save stays silent on the wire.
     */
    public function setUsingServerPrice(Vend $vend, bool $on): bool
    {
        if ($vend->usesServerPrice() === $on) {
            return false;
        }

        $vend->is_using_server_price = $on;
        $vend->save();

        return true;
    }

    /**
     * Tell the terminal to re-read its settings (selectedPricingSource) and
     * re-fetch its menu (server_price per channel).
     */
    public function nudge(Vend $vend): void
    {
        $this->vendJobService->syncSettingsToVend($vend);
        $this->vendJobService->syncChannelSlotListToVend($vend);
    }
}
