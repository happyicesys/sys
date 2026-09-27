<?php

namespace App\Services\SmartFreezer\Zijia;

use InvalidArgumentException;

/**
 * The algorithm's answer for one door session — the `bizContent` of a
 * `cabinet.algorithm.order.result` callback (§6.2).
 */
final class RecognitionResult
{
    public const STATUS_NORMAL = 0;

    /** `orderStatus` meanings, §6.2. Anything else is shown by its number. */
    private const STATUS_LABELS = [
        0 => 'normal',
        401 => 'video error / frames lost',
        501 => 'recognition error',
        502 => 'unfriendly behaviour',
        503 => 'goods not listed in the model',
        504 => 'goods packaging not updated',
        505 => 'unsafe behaviour',
    ];

    /** @param  array<string, int>  $items  barcode => units taken */
    public function __construct(
        public readonly string $tradeId,
        public readonly int $orderStatus,
        public readonly array $items,
        public readonly ?string $errorMessage,
        public readonly ?string $taskType,
    ) {}

    /** @param  array<string, mixed>  $bizContent */
    public static function fromBizContent(array $bizContent): self
    {
        $tradeId = trim((string) ($bizContent['tradeId'] ?? ''));
        if ($tradeId === '') {
            throw new InvalidArgumentException('result has no tradeId');
        }

        // The same code can appear on two rows (two camera angles, two positions): add them up.
        $items = [];
        foreach ((array) ($bizContent['items'] ?? []) as $item) {
            $code = trim((string) ($item['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $items[$code] = ($items[$code] ?? 0) + max(0, (int) ($item['number'] ?? 0));
        }

        return new self(
            tradeId: $tradeId,
            orderStatus: (int) ($bizContent['orderStatus'] ?? -1),
            items: $items,
            errorMessage: self::blankToNull($bizContent['errorMessage'] ?? null),
            taskType: self::blankToNull($bizContent['taskType'] ?? null),
        );
    }

    public function isNormal(): bool
    {
        return $this->orderStatus === self::STATUS_NORMAL;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->orderStatus] ?? "status {$this->orderStatus}";
    }

    private static function blankToNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
