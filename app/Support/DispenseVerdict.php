<?php

namespace App\Support;

/**
 * THE ONE DEFINITION of what a channel error code means for money, for the
 * dispense verdict, and for machine health. Every aggregate, rollup, export,
 * dashboard filter and refund rule reads it from here — never re-derive
 * `code = 0 OR code = 6 OR code IS NULL` inline (a guard test greps for it).
 *
 * Three different questions, three different answers
 * (Brian, 2026-09-08 — see NA_ERROR_CODE_PLAN_2026-09-08.md):
 *
 *   countsAsSale  — did money change hands for goods, as far as sales and
 *                   GP are concerned? NULL (legacy / no verdict), 0, 6 and 99.
 *                   99 is "Machine transaction not found (NA)": a payment rail
 *                   received the money, the TRADE never arrived. Payment truth
 *                   is the rail, so it stays in revenue and product/qty data.
 *   isDispensed   — did the machine SAY the product dropped? Only 0 and 6
 *                   (and NULL for legacy rows that store no code). 99 is NOT
 *                   dispensed: dispense truth is the TRADE alone. Display only.
 *   isMachineFault— should this count as a motor/sensor fault (error rates,
 *                   Machine Health, refund "genuine non-dispense")? A present
 *                   code outside {0, 6, 99}. 99 is unknown, not a fault.
 *
 * SQL builders return parenthesised fragments so they drop into any CASE /
 * WHERE untouched. Two shapes exist because the legacy aggregates test the
 * FK (`vend_channel_error_id`) rather than the joined code, and a dangling FK
 * (id set, no error row) is treated differently by the two:
 *   sqlSale($code)                 (code IS NULL OR code IN (...))
 *   sqlSaleById($id, $code)        (id IS NULL OR code IN (...))
 *   sqlFault($code)                (code IS NOT NULL AND code NOT IN (...))
 *   sqlFaultById($id, $code)       (id IS NOT NULL AND (code IS NULL OR code NOT IN (...)))
 *   sqlFaultStrict($id, $code)     (id IS NOT NULL AND code NOT IN (...))
 * Keep each call site on the shape it had: the refactor that introduced this
 * class was proven behaviour-preserving on that basis.
 *
 * Code 99 is SERVER-RESERVED: only MissingTradeMarker / the NETS orphan
 * creator may write it, and the TRADE ingest refuses it from a frame.
 */
final class DispenseVerdict
{
    /** Machine reported a clean drop. */
    public const DISPENSED_CODES = [0, 6];

    /** "Machine transaction not found (NA)" — rail paid, no TRADE. Server-only. */
    public const NOT_FOUND_CODE = 99;

    /** Codes a frame is never allowed to carry. */
    public const SERVER_RESERVED_CODES = [self::NOT_FOUND_CODE];

    /** Counts toward sales, revenue, GP, sold qty. */
    public const SALE_CODES = [0, 6, self::NOT_FOUND_CODE];

    // ── PHP predicates ──────────────────────────────────────────────────────

    public static function countsAsSale(int|string|null $code): bool
    {
        $c = self::normalise($code);

        return $c === null || in_array($c, self::SALE_CODES, true);
    }

    public static function isDispensed(int|string|null $code): bool
    {
        $c = self::normalise($code);

        return $c === null || in_array($c, self::DISPENSED_CODES, true);
    }

    public static function isMachineFault(int|string|null $code): bool
    {
        $c = self::normalise($code);

        return $c !== null && ! in_array($c, self::SALE_CODES, true);
    }

    public static function isServerReserved(int|string|null $code): bool
    {
        $c = self::normalise($code);

        return $c !== null && in_array($c, self::SERVER_RESERVED_CODES, true);
    }

    // ── SQL fragments ───────────────────────────────────────────────────────

    /** "0, 6, 99" — for an IN (...) list. */
    public static function saleList(): string
    {
        return implode(', ', self::SALE_CODES);
    }

    /** @param  int[]  $alsoExclude  extra codes that must not count as a fault (e.g. [4, 5]) */
    public static function faultList(array $alsoExclude = []): string
    {
        $codes = array_values(array_unique(array_merge(self::SALE_CODES, $alsoExclude)));
        sort($codes);

        return implode(', ', $codes);
    }

    public static function sqlSale(string $codeCol): string
    {
        return "({$codeCol} IS NULL OR {$codeCol} IN (".self::saleList().'))';
    }

    public static function sqlSaleById(string $idCol, string $codeCol): string
    {
        return "({$idCol} IS NULL OR {$codeCol} IN (".self::saleList().'))';
    }

    /** @param  int[]  $alsoExclude */
    public static function sqlFault(string $codeCol, array $alsoExclude = []): string
    {
        return "({$codeCol} IS NOT NULL AND {$codeCol} NOT IN (".self::faultList($alsoExclude).'))';
    }

    /** @param  int[]  $alsoExclude */
    public static function sqlFaultById(string $idCol, string $codeCol, array $alsoExclude = []): string
    {
        return "({$idCol} IS NOT NULL AND ({$codeCol} IS NULL OR {$codeCol} NOT IN (".self::faultList($alsoExclude).')))';
    }

    /** Legacy strict shape: a dangling FK with no code row is NOT counted as a fault. */
    public static function sqlFaultStrict(string $idCol, string $codeCol): string
    {
        return "({$idCol} IS NOT NULL AND {$codeCol} NOT IN (".self::faultList().'))';
    }

    private static function normalise(int|string|null $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        return is_numeric($code) ? (int) $code : null;
    }
}
