<?php

namespace App\Support;

/**
 * Singapore mobile number rule for PayNow payouts — our own logic, no phone
 * library (decision 2026-09-02, Brian).
 *
 * A number is a valid SG mobile when, after dropping formatting (spaces,
 * dashes, dots, parens, a leading "+") and an optional "65" / "0065" country
 * prefix, it is exactly 8 digits starting with 8 or 9. That is the whole rule.
 *
 * We used to delegate to propaganistas/laravel-phone (libphonenumber), whose
 * bundled SG metadata lags IMDA's range allocations: on 2026-09-01 it rejected
 * 89844833, a real customer's number, so they could not file a refund at all
 * (RefundFormPaynowDestinationTest). Newly-opened ranges must never cost a
 * customer their refund, so the rule is now deliberately simple and ours.
 * Contact.php still uses the library for general phone formatting; this class
 * is only for the PayNow mobile checks (form validation, settlement sanity
 * flag, CIMB E.164 export, RefundTicket storage canonicalisation).
 */
final class SgMobile
{
    /** The bare 8-digit national number, or null when the value is not a valid SG mobile. */
    public static function normalise(?string $value): ?string
    {
        $s = trim((string) $value);
        $s = preg_replace('/[\s\-.()]/', '', $s);
        $s = ltrim($s, '+');

        if (! ctype_digit($s)) {
            return null;
        }

        // Strip an SG country prefix in front of an 8-digit local number.
        if (strlen($s) === 12 && str_starts_with($s, '0065')) {
            $s = substr($s, 4);
        } elseif (strlen($s) === 10 && str_starts_with($s, '65')) {
            $s = substr($s, 2);
        }

        return preg_match('/^[89]\d{7}$/', $s) === 1 ? $s : null;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalise($value) !== null;
    }

    /** "+65XXXXXXXX" for a valid mobile; null otherwise. */
    public static function e164(?string $value): ?string
    {
        $n = self::normalise($value);

        return $n === null ? null : '+65'.$n;
    }
}
