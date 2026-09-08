<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Outcome of TradeTimestampResolver: the instant to book, and whether the frame's own time was believed. */
final class ResolvedTradeTime
{
    private function __construct(
        public readonly CarbonInterface $at,
        public readonly bool $trusted,
        public readonly ?string $raw,
        public readonly ?string $reason,
    ) {}

    public static function trusted(CarbonInterface $at, ?string $raw): self
    {
        return new self($at->copy(), true, $raw, null);
    }

    public static function rejected(CarbonInterface $now, ?string $raw, string $reason): self
    {
        return new self($now->copy(), false, $raw, $reason);
    }

    /** The audit stamp for meta_json.frame_time when the frame time was NOT used. */
    public function metaStamp(): ?array
    {
        if ($this->trusted) {
            return null;
        }

        return ['raw' => $this->raw, 'rejected' => true, 'reason' => $this->reason];
    }
}
