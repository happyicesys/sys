<?php

namespace App\Observers;

use App\Models\Vend;
use App\Services\VendPricingSourceService;

/**
 * Whenever a saved Vend row changes what the terminal should charge — the
 * pricing-source switch itself, or the Site it follows (customer_id) — tell
 * the terminal to re-read settings and re-fetch its menu.
 *
 * Lives on the model so EVERY writer is covered: Machine Settings save, the
 * APK Settings toggle, bind / unbind / rebind (VendController::unbindCustomer,
 * CustomerController::bindVend, the vend picker on Site edit), not just the
 * screens that remember to call the service. Site RP changes are a Customer
 * write and are handled in CustomerController::update.
 */
class VendPricingSourceObserver
{
    public function __construct(private VendPricingSourceService $pricing) {}

    public function updated(Vend $vend): void
    {
        if ($vend->wasChanged('is_using_server_price') || $vend->wasChanged('customer_id')) {
            $this->pricing->nudge($vend);
        }
    }
}
