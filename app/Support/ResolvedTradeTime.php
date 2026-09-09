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
     * The audit stamp for meta_json.frame_time — only when a frame time WAS
     * given and was NOT used. A trusted frame and a frame with no TIME at all
     * both leave no stamp (nothing was rejected).
     */
    public function metaStamp(): ?array
    {
        if ($this->isTrusted() || $this->reason === TradeTimestampResolver::REASON_MISSING) {
            return null;
        }

        return ['raw' => $this->raw, 'rejected' => true, 'reason' => $this->reason];
    }
}
