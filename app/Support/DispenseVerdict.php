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
 *   isSaleCode    — did money change hands for goods, as far as sales and
 *                   GP are concerned? NULL (legacy / no verdict), 0, 6 and 99.
 *                   99 is "Machine transaction not found (NA)": a payment rail
 *                   received the money, the TRADE never arrived. Payment truth
 *                   is the rail, so it stays in revenue and product/qty data.
 *   isDispensed   — did the machine SAY the product dropped? Only 0 and 6
 *                   (and NULL for legacy rows that store no code). 99 is NOT
 *                   dispensed: dispense truth is the TRADE alone. Drives the
 *                   Dispense column (SaleStatus) and the TRADE-level
 *                   success_qty; never a money aggregate.
 *   isMachineFault— should this count as a motor/sensor fault (error rates,
 *                   Machine Health, refund "genuine non-dispense")? A present
 *                   code outside {0, 6, 99}. 99 is unknown, not a fault.
 *
 * A fourth, narrower set exists for `dispensed_qty` only: DROPPED_CODES, the
 * codes on which the motor ran even if the sensor later complained (0, 6, 7,
 * 9). It is here so the guard test and the next code addition see it.
 *
 * SQL builders return parenthesised fragments so they drop into any CASE /
 * WHERE untouched. Two shapes exist because the legacy aggregates test the
 * FK (`vend_channel_error_id`) rather than the joined code:
 *   sqlSale($code)                 (code IS NULL OR code IN (...))
 *   sqlSaleById($id, $code)        (id IS NULL OR code IN (...))
 *   sqlFault($code)                (code IS NOT NULL AND code NOT IN (...))
 *   sqlFaultById($id, $code)       (id IS NOT NULL AND (code IS NULL OR code NOT IN (...)))
 *   sqlFaultStrict($id, $code)     (id IS NOT NULL AND code NOT IN (...))
 * On prod the shapes are equivalent — `vend_channel_errors.code` is NOT NULL
 * and no `vend_transactions` row carries a dangling FK (0 of 5.06M, checked
 * 2026-09-09) — so the by-id / strict forms only exist to keep the 2026-09-08
 * refactor a pure text substitution. Collapsing them to sqlSale/sqlFault is a
 * safe follow-up once a preflight asserts the dangling count stays 0.
 *
 * Code 99 is SERVER-RESERVED: only the marking jobs write it, and the TRADE
 * ingest refuses it from a frame (VendChannelError::forFrameCode()).
 */
final class DispenseVerdict
{
    /** Machine reported a clean drop. */
    public const DISPENSED_CODES = [0, 6];

    /** Motor ran — the product most likely dropped even where the sensor complained. `dispensed_qty` only. */
    public const DROPPED_CODES = [0, 6, 7, 9];

    /** "Machine transaction not found (NA)" — rail paid, no TRADE. Server-only. */
    public const NOT_FOUND_CODE = 99;

    /** Codes a frame is never allowed to carry. */
    public const SERVER_RESERVED_CODES = [self::NOT_FOUND_CODE];

    /** Counts toward sales, revenue, GP, sold qty. */
    public const SALE_CODES = [0, 6, self::NOT_FOUND_CODE];

    // ── PHP predicates ──────────────────────────────────────────────────────

    public static function isSaleCode(int|string|null $code): bool
    {
        if (self::isAbsent($code)) {
            return true; // legacy row / no verdict stored
        }

        $c = self::code($code);

        return $c !== null && in_array($c, self::SALE_CODES, true);
    }

    public static function isDispensed(int|string|null $code): bool
    {
        if (self::isAbsent($code)) {
            return true;
        }

        $c = self::code($code);

        return $c !== null && in_array($c, self::DISPENSED_CODES, true);
    }

    public static function isMachineFault(int|string|null $code): bool
    {
        $c = self::code($code);

        return $c !== null && ! in_array($c, self::SALE_CODES, true);
    }

    public static function isServerReserved(int|string|null $code): bool
    {
        $c = self::code($code);

        return $c !== null && in_array($c, self::SERVER_RESERVED_CODES, true);
    }

    /** NULL / '' — nothing stored (legacy rows, single vends before codes were kept). */
    public static function isAbsent(int|string|null $code): bool
    {
        return $code === null || (is_string($code) && trim($code) === '');
    }

    /**
     * The one normaliser: a frame or a column may hand us an int, a numeric
     * string ("07", " 6 "), an empty string or null. Non-numeric garbage
     * normalises to null and is then neither a sale, nor dispensed, nor a
     * fault — never refund, never count, on a value we cannot read.
     */
    public static function code(int|string|null $code): ?int
    {
        if ($code === null) {
            return null;
        }
        if (is_string($code)) {
            $code = trim($code);
            if ($code === '') {
                return null;
            }
        }

        return is_numeric($code) ? (int) $code : null;
    }

    // ── SQL fragments ───────────────────────────────────────────────────────

    /** "0, 6, 99" — for an IN (...) list. */
    public static function saleList(): string
    {
        return implode(', ', self::SALE_CODES);
    }

    /** "0, 6" — TRADE-level success_qty. */
    public static function dispensedList(): string
    {
        return implode(', ', self::DISPENSED_CODES);
    }

    /** "0, 6, 7, 9" — dispensed_qty. */
    public static function droppedList(): string
    {
        return implode(', ', self::DROPPED_CODES);
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
}
