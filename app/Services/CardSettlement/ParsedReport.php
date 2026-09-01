<?php

namespace App\Services\CardSettlement;

class ParsedReport
{
    /** @param ParsedRow[] $rows */
    public function __construct(
        public readonly ?string $merchantAccount,
        public readonly ?string $cutoverDate,        // Y-m-d
        public readonly ?string $reportGeneratedAt,  // Y-m-d H:i:s
        public readonly array $rows,
    ) {}
}
