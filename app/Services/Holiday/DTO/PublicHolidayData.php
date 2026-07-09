<?php

namespace App\Services\Holiday\DTO;

/**
 * Provider-agnostic single public holiday: one calendar date + its name.
 */
class PublicHolidayData
{
    public function __construct(
        public string $date, // normalized Y-m-d
        public string $name,
    ) {
    }
}
