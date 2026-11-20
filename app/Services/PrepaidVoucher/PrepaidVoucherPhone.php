<?php

namespace App\Services\PrepaidVoucher;

use App\Support\SgMobile;

/**
 * The customer's mobile number, as typed on the machine, to E.164 — the only key a voucher has. Singapore
 * follows SgMobile (8 digits from 8 or 9); other countries take 7–12 national digits behind their
 * `countries.phone_code`. Null = not a number the machine should have accepted.
 */
final class PrepaidVoucherPhone
{
    /** The keypad rule the machine shows for a country (digits, allowed first digits). */
    public static function rule(?string $phoneCode): array
    {
        $cc = self::countryCode($phoneCode);

        return match ($cc) {
            '65' => ['country_code' => '65', 'min_digits' => 8, 'max_digits' => 8, 'first_digits' => ['8', '9']],
            '60' => ['country_code' => '60', 'min_digits' => 9, 'max_digits' => 10, 'first_digits' => ['1']],
            '62' => ['country_code' => '62', 'min_digits' => 9, 'max_digits' => 12, 'first_digits' => ['8']],
            default => ['country_code' => $cc, 'min_digits' => 7, 'max_digits' => 12, 'first_digits' => []],
        };
    }

    public static function normalise(?string $raw, ?string $phoneCode): ?string
    {
        $cc = self::countryCode($phoneCode);
        if ($cc === '65') {
            return SgMobile::e164($raw);
        }

        $s = preg_replace('/[\s\-.()]/', '', trim((string) $raw));
        $s = ltrim((string) $s, '+');
        if ($s === '' || ! ctype_digit($s)) {
            return null;
        }
        if (str_starts_with($s, '00'.$cc)) {
            $s = substr($s, 2 + strlen($cc));
        } elseif (str_starts_with($s, $cc) && strlen($s) > self::rule($cc)['max_digits']) {
            $s = substr($s, strlen($cc));
        }
        $s = ltrim($s, '0'); // a trunk 0 typed out of habit (Malaysia, Indonesia)

        $rule = self::rule($cc);
        $len = strlen($s);
        if ($len < $rule['min_digits'] || $len > $rule['max_digits']) {
            return null;
        }
        if ($rule['first_digits'] !== [] && ! in_array($s[0], $rule['first_digits'], true)) {
            return null;
        }

        return '+'.$cc.$s;
    }

    private static function countryCode(?string $phoneCode): string
    {
        $cc = preg_replace('/\D/', '', (string) $phoneCode);

        return $cc !== '' ? $cc : '65';
    }
}
