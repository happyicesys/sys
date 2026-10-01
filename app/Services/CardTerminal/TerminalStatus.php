<?php

namespace App\Services\CardTerminal;

/**
 * Terminal reachability and mode, normalised. `ready` is the only state in
 * which a new payment request will be shown to the customer.
 */
final class TerminalStatus
{
    public const STATE_READY = 'ready';

    public const STATE_IN_PAYMENT = 'in_payment';

    public const STATE_POS = 'pos';

    public const STATE_WEBSOCKET = 'websocket';

    public function __construct(
        public readonly bool $online,
        public readonly ?string $state,
        public readonly array $raw = [],
    ) {}

    public function isReady(): bool
    {
        return $this->online && $this->state === self::STATE_READY;
    }
}
