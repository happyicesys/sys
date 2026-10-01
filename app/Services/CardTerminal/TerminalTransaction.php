<?php

namespace App\Services\CardTerminal;

/**
 * A provider's answer to "what happened to this order id?", normalised.
 *
 * Money-safety rule: only an exact provider `approved` becomes APPROVED.
 * Anything the provider says that we do not recognise stays PROCESSING, so
 * an unexpected word can delay a sale but can never open the door.
 */
final class TerminalTransaction
{
    public const PROCESSING = 'processing';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const VOIDED = 'voided';

    /** The provider has no record yet — normal for a card txn before the tap. */
    public const NOT_FOUND = 'not_found';

    public function __construct(
        public readonly string $status,
        public readonly ?string $providerStatus = null,
        public readonly ?string $providerTxnId = null,
        public readonly ?string $paymentMethod = null,
        public readonly array $raw = [],
    ) {}

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }

    /** The attempt ended without money moving. */
    public function isFailed(): bool
    {
        return $this->status === self::DECLINED;
    }
}
