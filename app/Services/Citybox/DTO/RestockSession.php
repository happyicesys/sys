<?php

namespace App\Services\Citybox\DTO;

use Carbon\CarbonImmutable;

/**
 * Result of a successful `zyy_ls_open_door`. `msgId` is the handle a later
 * `device_stock_submit` MUST carry; `openLogId` is their audit id.
 */
final readonly class RestockSession
{
    public function __construct(
        public string $deviceId,
        public string $msgId,
        public string $openLogId,
        public CarbonImmutable $openedAt,
    ) {}

    public static function fromApi(array $body, CarbonImmutable $openedAt): self
    {
        return new self(
            deviceId: (string) ($body['device_id'] ?? ''),
            msgId: (string) ($body['msg_id'] ?? ''),
            openLogId: (string) ($body['open_log_id'] ?? ''),
            openedAt: $openedAt,
        );
    }
}
