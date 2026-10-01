<?php

namespace App\Services\CardTerminal;

use RuntimeException;

/**
 * A remote-terminal call that did not complete: not configured, transport
 * failure, or the provider refused the request. Carries the provider's own
 * message (never a token) so it can be stored on the intent as last_error.
 */
class CardTerminalException extends RuntimeException
{
    /**
     * False only when we know the request never left mark1 (missing config,
     * bad amount) or the provider explicitly refused it. A timeout or a 5xx
     * leaves it true: the terminal may be showing the request, so the caller
     * must keep watching for an approval it did not ask to wait for.
     */
    public bool $mayHaveReachedTerminal = true;

    public static function notConfigured(string $what): self
    {
        return self::notSent("remote card terminal not configured: {$what}");
    }

    public static function notSent(string $message): self
    {
        $e = new self($message);
        $e->mayHaveReachedTerminal = false;

        return $e;
    }
}
