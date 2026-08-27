<?php

namespace App\Services\SimcardUsage\DTO;

use Carbon\Carbon;

/**
 * Provider-agnostic snapshot of one sim's live status — exactly what the
 * Simcard Index "Status" column shows (status / active / expire / used MB).
 */
class SimcardUsageData
{
    public function __construct(
        public readonly string $simNo,
        public readonly ?string $status,
        public readonly ?Carbon $activeAt,
        public readonly ?Carbon $expireAt,
        public readonly ?float $usedMb,
    ) {}
}
