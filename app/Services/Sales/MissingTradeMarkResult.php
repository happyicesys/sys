<?php

namespace App\Services\Sales;

/** What one MissingTradeMarker::mark() run found (report mode) or wrote (apply mode). */
final class MissingTradeMarkResult
{
    /** Item rows of multiples marked (or that would be). */
    public int $items = 0;

    /** Whether the watermark moved (apply mode, contiguous window). */
    public bool $watermarkAdvanced = false;

    /** @var array<string, int> Y-m-d → header rows */
    public array $perDay = [];

    public function countDay(string $day): void
    {
        $this->perDay[$day] = ($this->perDay[$day] ?? 0) + 1;
    }

    public function headers(): int
    {
        return array_sum($this->perDay);
    }
}
