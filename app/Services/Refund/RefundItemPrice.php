<?php

namespace App\Services\Refund;

use App\Models\HappyHourCampaign;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\VendTransactionItem;
use App\Services\HappyHour\HappyHourPricing;

/**
 * What one line of a multiple purchase cost the customer — the unit price a refund claim on that
 * line pays back. Every refund surface that prices a `vend_transaction_items` row reads it here.
 *
 * A vending TRADE carries no per-line price, so the ingest stores each line at its channel's LIST
 * price, and a failed line at 0 (no revenue). `HappyHourSales::repriceVendingBasket` then rewrites a
 * Happy Hour line that came out to its promo, but a failed line stays at 0 — so a claim on it fell back
 * to the list price although the customer paid the promo (Brian, 2026-10-16, RF-261016005: 2 × Walls's
 * Ice Ball at $2.40 = $4.80 paid, one failed, claim showed $3.40). A tagged line is priced the way the
 * screen charged it: the slot's discount on the channel's price with the campaign's rounding
 * (HappyHourPricing::channelSlots).
 */
final class RefundItemPrice
{
    public static function cents(VendTransactionItem $item): int
    {
        $stored = (int) $item->unit_price_amount;
        $list = (int) ($item->vendChannel?->amount ?? 0);
        $slot = $item->happy_hour_slot_id ? $item->happyHourSlot : null;
        if (! $slot) {
            // unit_price_amount can be 0/unset; fall back to the channel's price
            return $stored ?: $list;
        }

        // Below the list price, a stored price is already what was charged: repriced at tagging, or a
        // freezer TRADE's own promo price.
        if ($stored > 0 && ($list <= 0 || $stored < $list)) {
            return $stored;
        }
        $base = $stored ?: $list;
        if ($base <= 0) {
            return (int) $slot->promo_price;
        }

        // The campaign may be deleted or belong to another operator than the refund viewer.
        $step = HappyHourCampaign::withTrashed()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)
            ->whereKey($slot->happy_hour_campaign_id)
            ->value('price_step_cents');

        return HappyHourPricing::promoPrice($base, (int) $slot->discount_pct, (int) ($step ?? 1)) ?? $base;
    }
}
