<?php

namespace App\Services\Sales;

use Carbon\CarbonInterface;

/** What one MissingTradeMarker::mark() run found (report mode) or wrote (apply mode). */
final class MissingTradeMarkResult
{
    public int $headers = 0;

    public int $items = 0;

    /** @var array<string, int> Y-m-d → headers */
    public array $perDay = [];

    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $until,
        public readonly bool $applied,
    ) {}

    public function countDay(string $day): void
    {
        $this->perDay[$day] = ($this->perDay[$day] ?? 0) + 1;
    }

    /** @return string[] */
    public function days(): array
    {
        $days = array_keys($this->perDay);
        sort($days);

        return $days;
    }
}
