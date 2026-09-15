<?php

namespace App\Services\Payment;

/**
 * Outcome of checking a gateway webhook against the gateway's own record.
 *
 * VERIFIED     the gateway confirms exactly what the webhook claims.
 * MISMATCH     the gateway's record contradicts the webhook (forged, replayed
 *              against the wrong order, or tampered amount) - never act on it.
 * UNVERIFIABLE the gateway could not be asked (timeout, 5xx, no key). Says
 *              nothing about the webhook itself; the caller decides.
 * SKIPPED      nothing to verify (a pending / declined event moves no money,
 *              or the gateway already carries its own signature check).
 */
final class WebhookVerdict
{
    public const VERIFIED = 'verified';

    public const MISMATCH = 'mismatch';

    public const UNVERIFIABLE = 'unverifiable';

    public const SKIPPED = 'skipped';

    private function __construct(
        public readonly string $outcome,
        public readonly string $reason,
        /** @var array<string, mixed> what was compared, for the log line */
        public readonly array $detail = [],
    ) {}

    public static function verified(array $detail = []): self
    {
        return new self(self::VERIFIED, 'gateway record matches', $detail);
    }

    public static function mismatch(string $reason, array $detail = []): self
    {
        return new self(self::MISMATCH, $reason, $detail);
    }

    public static function unverifiable(string $reason, array $detail = []): self
    {
        return new self(self::UNVERIFIABLE, $reason, $detail);
    }

    public static function skipped(string $reason): self
    {
        return new self(self::SKIPPED, $reason);
    }

    public function isMismatch(): bool
    {
        return $this->outcome === self::MISMATCH;
    }

    public function toLogContext(): array
    {
        return ['outcome' => $this->outcome, 'reason' => $this->reason] + $this->detail;
    }
}
