<?php

namespace App\Exceptions;

use Exception;

/**
 * A Citybox apiThredDetail call failed — transport error, non-200 envelope
 * code, or a business failure inside a code-200 envelope (their edit endpoint
 * reports failures as data.success_count = 0, not as an error code).
 */
class CityboxApiException extends Exception
{
    /** Their envelope `code` (200/400/401/402/403/500/501), or 0 for transport-level failures. */
    public readonly int $apiCode;

    public function __construct(string $message, int $apiCode = 0)
    {
        parent::__construct($message);
        $this->apiCode = $apiCode;
    }
}
