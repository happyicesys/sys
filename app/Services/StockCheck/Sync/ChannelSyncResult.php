<?php

namespace App\Services\StockCheck\Sync;

/** The outcome of syncing one counted channel. */
final class ChannelSyncResult
{
    private function __construct(
        public readonly int $channelCode,
        public readonly bool $applied,
        public readonly ?int $qtyBefore,
        public readonly ?int $qtyAfter,
        public readonly ?string $reason,
    ) {}

    public static function applied(int $channelCode, int $qtyBefore, int $qtyAfter, ?string $note = null): self
    {
        return new self($channelCode, true, $qtyBefore, $qtyAfter, $note);
    }

    public static function skipped(int $channelCode, string $reason): self
    {
        return new self($channelCode, false, null, null, $reason);
    }

    public function toArray(): array
    {
        return [
            'channel_code' => $this->channelCode,
            'applied' => $this->applied,
            'qty_before' => $this->qtyBefore,
            'qty_after' => $this->qtyAfter,
            'reason' => $this->reason,
        ];
    }
}
