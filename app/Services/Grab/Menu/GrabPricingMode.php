<?php

namespace App\Services\Grab\Menu;

// How an operator prices its Grab menu (Brian, 2026-11-01).
enum GrabPricingMode: string
{
    // A chosen RP tier's price (selling_prices), as set on Product → Edit.
    case Rp = 'rp';
    // That RP × a multiplier, always rounded UP to a step.
    case RpMultiplier = 'rp_multiplier';
    // A Grab price typed per SKU.
    case Custom = 'custom';

    public function readsRp(): bool
    {
        return $this !== self::Custom;
    }
}
