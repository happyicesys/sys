<?php

namespace App\Support\CardSettlement;

/**
 * How one acquirer pays out one family of card transactions: which payment
 * gateway consolidates it, how many banking days after the transaction the
 * money reaches the bank, and at what merchant discount rate.
 *
 * Pure value object — it knows the schedule, not the dates. Turning it into a
 * date is App\Services\CardSettlement\Payout\SettlementPayoutResolver's job,
 * because that needs a banking calendar and this must stay trivially testable.
 *
 * The MDR fields are LABELS ("2.5%", "2% + GST"), not numbers: nothing in mark1
 * computes a fee from them today, and inventing a float here would invite a
 * future reader to treat a display string as money. Money is integer cents
 * everywhere in this estate; when fee computation lands it gets its own typed
 * field rather than parsing these.
 */
class PayoutTerms
{
    public function __construct(
        /** Payment gateway that consolidates the payout: COS / POS / DBS CARD CENTER / AURESYS. */
        public readonly string $gateway,
        /** N in "T+N", counted in banking days. */
        public readonly int $termDays,
        /** Human name for the card family this schedule covers ("NETS / NETS QR"). */
        public readonly ?string $methodLabel = null,
        /** Merchant discount rate, as printed on the acquirer's schedule. */
        public readonly ?string $mdrRate = null,
        /** Whether the MDR is netted off the payout or billed separately. */
        public readonly ?string $mdrNote = null,
    ) {}

    public function termLabel(): string
    {
        return 'T+'.$this->termDays;
    }
}
