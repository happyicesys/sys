<?php

namespace App\Enums\Citybox;

/**
 * CityBox operational status (`box_list.status`): 0 禁运 / 1 启运 / 98 撤机中 / 99 已撤机.
 * Their API returns it as int OR numeric string — always construct via fromApi().
 */
enum DeviceOpsStatus: int
{
    case Banned = 0;
    case Running = 1;
    case Removing = 98;
    case Removed = 99;

    public static function fromApi(mixed $raw): ?self
    {
        return is_numeric($raw) ? self::tryFrom((int) $raw) : null;
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function label(): string
    {
        return match ($this) {
            self::Banned => 'Banned (禁运)',
            self::Running => 'Running (启运)',
            self::Removing => 'Being removed (撤机中)',
            self::Removed => 'Removed (已撤机)',
        };
    }
}
