<?php

namespace App\Contracts\Holiday;

/**
 * A regional public-holiday source. Each region/app implements this once and
 * registers itself in config/holiday.php; the ingestion + storage layer is
 * shared, so a new region only needs to normalize its API into the shared DTO.
 */
interface HolidayProvider
{
    /** Stable short key used in config, e.g. "sg". */
    public function key(): string;

    /** Human-readable region label, e.g. "Singapore". */
    public function region(): string;

    /**
     * Fetch official public holidays and normalize them to shared DTOs.
     *
     * @return \App\Services\Holiday\DTO\PublicHolidayData[]
     *
     * @throws \RuntimeException on transport or payload errors.
     */
    public function fetchPublicHolidays(): array;
}
