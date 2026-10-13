<?php

namespace App\Services\Citybox\DTO;

use App\Services\Citybox\CityboxMoney;
use Carbon\CarbonImmutable;

/**
 * One `order_list` row (2026-10-13). Money is their decimal string, converted to cents here
 * and nowhere else. Times are their wall clock, UTC+8 — the same as mark1's (every order on
 * 2026-10-11/12 matched our own minute poll to the minute).
 *
 * `rawPayload` is the row exactly as received, so the mirror can hash it and keep it.
 */
final readonly class ChillerOrder
{
    public const STATUS_UNPAID = 0;

    public const STATUS_PAID = 1;

    public const STATUS_PAYING = 2;

    public const STATUS_REFUND_APPLIED = 3;

    public const STATUS_REFUNDED = 4;

    public const STATUS_REJECTED = 5;

    public const STATUS_CANCELLED = -1;

    /** @param list<ChillerOrderLine> $lines */
    public function __construct(
        public int $cityboxOrderId,
        public string $orderName,
        public string $deviceId,
        public ?string $deviceName,
        public int $qty,
        public int $moneyCents,
        public int $goodMoneyCents,
        public ?CarbonImmutable $orderedAt,
        public ?CarbonImmutable $paidAt,
        public ?string $refer,
        public ?int $referType,
        public int $status,
        public ?string $payLabel,
        public array $lines,
        public array $rawPayload,
    ) {}

    public static function fromApi(array $r): self
    {
        return new self(
            cityboxOrderId: (int) ($r['id'] ?? 0),
            orderName: (string) ($r['order_name'] ?? ''),
            deviceId: (string) ($r['device_id'] ?? ''),
            deviceName: isset($r['device_name']) ? (string) $r['device_name'] : null,
            qty: (int) ($r['qty'] ?? 0),
            moneyCents: (int) (self::money($r['money'] ?? null) ?? 0),
            goodMoneyCents: (int) (self::money($r['good_money'] ?? null) ?? 0),
            orderedAt: self::time($r['order_time'] ?? null),
            paidAt: self::time($r['pay_succ_time'] ?? null),
            refer: isset($r['refer']) && $r['refer'] !== '' ? (string) $r['refer'] : null,
            referType: isset($r['refer_type']) && is_numeric($r['refer_type']) ? (int) $r['refer_type'] : null,
            status: (int) ($r['order_status'] ?? self::STATUS_UNPAID),
            payLabel: isset($r['transaction']) && $r['transaction'] !== '' ? strtoupper(trim((string) $r['transaction'])) : null,
            lines: array_values(array_map(fn ($l) => ChillerOrderLine::fromApi((array) $l), (array) ($r['order_product'] ?? []))),
            rawPayload: $r,
        );
    }

    /** Units taken, from the lines (their header `qty` agrees on every order seen so far). */
    public function units(): int
    {
        return array_sum(array_map(fn (ChillerOrderLine $l) => $l->qty, $this->lines));
    }

    private static function money(mixed $v): ?int
    {
        return $v === null || $v === '' ? null : CityboxMoney::toCents((string) $v);
    }

    private static function time(mixed $v): ?CarbonImmutable
    {
        if (! is_string($v) || trim($v) === '' || str_starts_with($v, '0000')) {
            return null;
        }
        try {
            return CarbonImmutable::parse($v);
        } catch (\Throwable) {
            return null;
        }
    }
}
