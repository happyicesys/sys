<?php

namespace App\Services\SmartFreezer\Claude;

use RuntimeException;
use Throwable;

/** A count that did not produce an answer. `usage` is set when the call reached Claude and was billed anyway. */
final class CountFailed extends RuntimeException
{
    public function __construct(string $message, public readonly ?CallUsage $usage = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
