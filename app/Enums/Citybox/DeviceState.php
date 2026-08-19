<?php

namespace App\Enums\Citybox;

/**
 * Live session state from `get_device_status_new.body.code`. Observed live:
 * FREE (idle), OPENING (after an ops door-open), NOT_FOUND (offline device).
 * The doc lists more (BUSY, MAINTENANCE, TROUBLE, …); anything unlisted maps
 * to Other so a new code never throws.
 */
enum DeviceState: string
{
    case Free = 'FREE';
    case Opening = 'OPENING';
    case Busy = 'BUSY';
    case Maintenance = 'MAINTENANCE';
    case NotFound = 'NOT_FOUND';
    case Other = 'OTHER';

    public static function fromApi(mixed $raw): self
    {
        return is_string($raw) ? (self::tryFrom(strtoupper($raw)) ?? self::Other) : self::Other;
    }

    public function canOpenDoor(): bool
    {
        return $this === self::Free;
    }
}
