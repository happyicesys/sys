<?php

namespace App\Services\HappyHour;

/**
 * One SKU of one freezer as the ranker saw it: the stock, sales and prices behind its rank, or the
 * reason it was left out. Shown as is by the campaign page's lineup preview.
 */
final class HappyHourCandidate
{
    public function __construct(
        public readonly int $productId,
        public readonly ?string $productCode,
        public readonly ?string $productName,
        public readonly int $qty,
        public readonly ?int $capacity,
        public readonly int $soldInLookback,
        public readonly int $lookbackDays,
        public readonly ?int $originalPrice,
        public readonly ?int $promoPrice,
        public readonly ?int $unitCost,
        public ?string $excludedReason = null,
        public ?int $rank = null,
        // On the campaign's Prioritize list: offered first in every other slot (HappyHourPlanner).
        public bool $priority = false,
        // Why it is left out, as a key the page can group on (HappyHourRanker::EXCLUDED_*); null = eligible.
        public ?string $excludedCode = null,
        // Bestseller hold only: when it ranks again, its place in the top Y and its units over the hold's window.
        public ?\Carbon\CarbonInterface $heldUntil = null,
        public ?int $holdPlace = null,
        public ?int $holdSold = null,
    ) {}

    /** qty ÷ capacity in %, or null when the SKU's capacity was never measured. */
    public function balancePct(): ?float
    {
        return $this->capacity ? round($this->qty * 100 / $this->capacity, 1) : null;
    }

    public function avgDailySales(): float
    {
        return $this->lookbackDays > 0 ? $this->soldInLookback / $this->lookbackDays : 0.0;
    }

    /** Days the stock lasts at the recent sales rate; null = nothing sold (lasts indefinitely). */
    public function daysOfCover(): ?float
    {
        $avg = $this->avgDailySales();

        return $avg > 0 ? round($this->qty / $avg, 2) : null;
    }

    public function isEligible(): bool
    {
        return $this->excludedReason === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'product_code' => $this->productCode,
            'product_name' => $this->productName,
            'qty' => $this->qty,
            'capacity' => $this->capacity,
            'balance_pct' => $this->balancePct(),
            'sold_in_lookback' => $this->soldInLookback,
            'lookback_days' => $this->lookbackDays,
            'avg_daily_sales' => round($this->avgDailySales(), 3),
            'days_of_cover' => $this->daysOfCover(),
            'original_price' => $this->originalPrice,
            'promo_price' => $this->promoPrice,
            'unit_cost' => $this->unitCost,
            'excluded_reason' => $this->excludedReason,
            'excluded_code' => $this->excludedCode,
            'rank' => $this->rank,
            'priority' => $this->priority,
        ];
    }
}
