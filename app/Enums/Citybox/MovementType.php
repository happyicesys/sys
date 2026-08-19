<?php

namespace App\Enums\Citybox;

/**
 * Why a chiller's stock moved between two polls (design §5b.1).
 *  sale        qty fell, no ops visit in the window — a customer took it
 *  restock     qty rose inside a visit window — the driver put it in
 *  correction  qty fell inside a visit window — driver removed expired/damaged
 *  unknown     anything else (rise with no visit = their portal edit; a large
 *              fall = AI recount) — surfaced, never silently absorbed
 */
enum MovementType: string
{
    case Sale = 'sale';
    case Restock = 'restock';
    case Correction = 'correction';
    case Unknown = 'unknown';
}
