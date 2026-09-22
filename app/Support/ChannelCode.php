<?php

namespace App\Support;

/**
 * A channel code as ops type it: digits, optionally one letter — "17",
 * "101", "101A". The number is the position (vending slot, freezer
 * <basket><division>, chiller <layer><position>); the letter splits one
 * position between several SKUs on a SKU-stocked machine (Brian, 2026-09-22:
 * 101A / 101B / 101C). Stored split on vend_channels (`code` int + `suffix`
 * char) and joined on product_mapping_items.channel_code (varchar).
 *
 * Ordering is number first, then letter: 101 < 101A < 101B < 102. In SQL the
 * same order is CAST(channel_code AS UNSIGNED), channel_code — MySQL casts the
 * leading digits and the raw string breaks the tie.
 */
final class ChannelCode
{
    public const SQL_ORDER = 'CAST(channel_code AS UNSIGNED), channel_code';

    /**
     * @return array{code:int,suffix:?string}|null null when the string is not a channel code
     */
    public static function parse(string|int|null $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (! preg_match('/^\s*(\d+)\s*([A-Za-z])?\s*$/', (string) $raw, $m)) {
            return null;
        }

        return ['code' => (int) $m[1], 'suffix' => isset($m[2]) && $m[2] !== '' ? strtoupper($m[2]) : null];
    }

    public static function label(int $code, ?string $suffix): string
    {
        return $code.($suffix !== null && $suffix !== '' ? strtoupper($suffix) : '');
    }

    /** Canonical spelling of what ops typed ("101a " → "101A"), or the input unchanged when it does not parse. */
    public static function normalize(string|int|null $raw): string
    {
        $parsed = self::parse($raw);

        return $parsed ? self::label($parsed['code'], $parsed['suffix']) : trim((string) $raw);
    }

    public static function hasSuffix(string|int|null $raw): bool
    {
        return (self::parse($raw)['suffix'] ?? null) !== null;
    }

    /** Natural order: number, then letter; anything unparseable sorts last, lexically. */
    public static function compare(string|int|null $a, string|int|null $b): int
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        if ($pa && $pb) {
            return [$pa['code'], $pa['suffix'] ?? ''] <=> [$pb['code'], $pb['suffix'] ?? ''];
        }
        if ($pa xor $pb) {
            return $pa ? -1 : 1;
        }

        return strcmp((string) $a, (string) $b);
    }
}
