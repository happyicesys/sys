<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Outcome of TradeTimestampResolver: the instant to book, and whether the frame's own time was believed. */
final class ResolvedTradeTime
{
    private function __construct(
        public readonly CarbonInterface $at,
        public readonly ?string $raw,
        public readonly ?string $reason,
    ) {}

    public static function trusted(CarbonInterface $at, ?string $raw): self
    {
        return new self($at->copy(), $raw, null);
    }

    public static function rejected(CarbonInterface $now, ?string $raw, string $reason): self
    {
        return new self($now->copy(), $raw, $reason);
    }

    /** The frame's TIME was used as the transaction moment. */
    public function isTrusted(): bool
    {
        return $this->reason === null;
    }

    /**
     * The audit stamp for meta_json.frame_time — whenever the frame's own
     * time was NOT used, a missing TIME included (`raw` null, reason
     * `missing`), so a row booked at arrival always says why. A trusted frame
     * leaves no stamp.
     */
    public function metaStamp(): ?array
    {
        if ($this->isTrusted()) {
            return null;
        }

        return ['raw' => $this->raw, 'rejected' => true, 'reason' => $this->reason];
    }
}
