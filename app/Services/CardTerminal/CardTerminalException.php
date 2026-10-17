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
    /** 4xx answers that say "not now" rather than "no": a retry may succeed. */
    private const TRANSIENT_STATUSES = [408, 409, 423, 425, 429];

    /**
     * False only when we know the request never left mark1 (missing config,
     * bad amount) or the provider explicitly refused it. A timeout or a 5xx
     * leaves it true: the terminal may be showing the request, so the caller
     * must keep watching for an approval it did not ask to wait for.
     */
    public bool $mayHaveReachedTerminal = true;

    /** The provider's HTTP status when it answered; null when no answer came. */
    public ?int $httpStatus = null;

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

    /** The provider answered this request with a 4xx: nothing was done on their side. */
    public static function refused(string $message, int $httpStatus): self
    {
        $e = self::notSent($message);
        $e->httpStatus = $httpStatus;

        return $e;
    }

    /**
     * The provider said NO to this request, and asking again will not change it: a 4xx other
     * than a timeout / conflict / lock / rate limit. A connect failure (no answer at all) is
     * not a refusal — nothing was decided, so it is retried.
     */
    public function isDefiniteRefusal(): bool
    {
        return ! $this->mayHaveReachedTerminal
            && $this->httpStatus !== null
            && $this->httpStatus >= 400 && $this->httpStatus < 500
            && ! in_array($this->httpStatus, self::TRANSIENT_STATUSES, true);
    }
}
