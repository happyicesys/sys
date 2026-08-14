<?php

namespace App\Services\Citybox;

use InvalidArgumentException;

/**
 * Converts CityBox-Openapi money strings to mark1's integer cents.
 *
 * Their order push carries DECIMAL CURRENCY-UNIT STRINGS ("4.05", "0.00");
 * mark1 stores integer cents everywhere (estate invariant — never float).
 * Conversion happens here, at the ingestion boundary, and nowhere else.
 *
 * Currency (SGD vs CNY) and per-endpoint units are still unconfirmed with
 * Citybox (API_REQUEST_2026-08-14.md Q5) — this class deliberately does
 * pure decimal→cents string math and leaves currency semantics to the
 * caller once Q5 is answered.
 */
class CityboxMoney
{
    /**
     * "4.05" → 405. String math only — no float ever touches the value.
     * Null/'' → null (their optional money fields). More than 2 decimals is
     * rejected loudly: silently rounding supplier money invents cents.
     */
    public static function toCents(?string $amount): ?int
    {
        if ($amount === null || trim($amount) === '') {
            return null;
        }

        $amount = trim($amount);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $m)) {
            throw new InvalidArgumentException("Unparseable Citybox money value: {$amount}");
        }

        $cents = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }
}
