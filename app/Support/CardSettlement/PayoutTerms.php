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
 * `mdrRate` / `mdrNote` are LABELS ("2.5%", "2% + GST") for tooltips and are
 * never parsed. Fee arithmetic reads only the typed fields — `mdrBasisPoints`
 * (integer, 250 = 2.5%), `mdrPlusGst` and `mdrDeducted` — and works in integer
 * cents, like all money in this estate.
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
        /** The MDR as an integer in basis points (250 = 2.5%); null = not told. */
        public readonly ?int $mdrBasisPoints = null,
        /** GST is charged on top of the MDR ("2% + GST"). */
        public readonly bool $mdrPlusGst = false,
        /** true = netted off the payout; false = gross banked, MDR billed separately. */
        public readonly bool $mdrDeducted = false,
    ) {}

    /**
     * The MDR on one line of `$amountCents`, GST included when the schedule says
     * so. Rounded half-up per line — an ESTIMATE: the acquirer may round per
     * batch, so a day's total can differ from its statement by a few cents.
     * Null when the schedule carries no numeric rate.
     */
    public function mdrCents(int $amountCents, int $gstBasisPoints): ?int
    {
        if ($this->mdrBasisPoints === null) {
            return null;
        }

        $fee = (int) round($amountCents * $this->mdrBasisPoints / 10000);
        if ($this->mdrPlusGst) {
            $fee += (int) round($fee * $gstBasisPoints / 10000);
        }

        return $fee;
    }

    /** "2.5%", "2% + GST" — from the typed rate, so the label cannot drift from the maths. */
    public function mdrRateLabel(): ?string
    {
        if ($this->mdrBasisPoints === null) {
            return $this->mdrRate;
        }

        $pct = rtrim(rtrim(number_format($this->mdrBasisPoints / 100, 2, '.', ''), '0'), '.');

        return $pct.'%'.($this->mdrPlusGst ? ' + GST' : '');
    }

    public function termLabel(): string
    {
        return 'T+'.$this->termDays;
    }
}
