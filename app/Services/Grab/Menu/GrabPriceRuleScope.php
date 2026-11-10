<?php

namespace App\Services\Grab\Menu;

// Whose price rule a store follows (Brian, 2026-11-10).
enum GrabPriceRuleScope: string
{
    // The operator's rule, shared by every store that has none of its own.
    case Company = 'company';
    // The store's own rule, for that store only.
    case Store = 'store';
}
