<?php

namespace App\Support;

/**
 * What mark1 may keep of a card number: the first 2 and last 4 digits, nothing else (Brian,
 * 2026-11-05). Payrallel's capture and refund answers carry EMV tag 57, the whole number and
 * expiry in clear text, inside `extraData`: a JSON string within JSON, so a key can be quoted at
 * any escape depth, and CardTerminalEventLog stores those bodies whole.
 *
 * Works on text so it also reads a body cut short and a log line. Only named fields and the two
 * shapes of a card number are touched: transaction ids ("C261104132649D4F") must survive, since
 * a refund names them.
 */
class CardNumberMask
{
    /** EMV tags and fields that hold the card number. */
    private const NUMBER_KEYS = '57|5A|9F6B|maskedPan|accountMasked|pan|cardNumber|accountNumber';

    /** Track 1, track 2 discretionary data, cardholder name, expiry and effective date: never kept. */
    private const DROPPED_KEYS = '56|9F1F|9F20|5F20|5F24|5F25';

    /** The issuer prefix: more than the first 2 digits. */
    private const PREFIX_KEYS = 'accountBin|cardBin|bin';

    public static function text(string $text): string
    {
        $masked = preg_replace_callback(
            '/(\\\\*")('.self::NUMBER_KEYS.'|'.self::DROPPED_KEYS.'|'.self::PREFIX_KEYS.')\1(\s*:\s*)\1([^"\\\\]*)/i',
            fn (array $m) => $m[1].$m[2].$m[1].$m[3].$m[1].self::valueFor($m[2], $m[4]),
            $text,
        );
        // Whatever key they arrive under: track 2 data, and a number masked to its first 6.
        $masked = $masked === null ? null : preg_replace_callback(
            '/(?<![0-9A-Za-z])(\d{12,19})[D=]\d{4}\d*/',
            fn (array $m) => self::number($m[1]),
            $masked,
        );
        $masked = $masked === null ? null : preg_replace_callback(
            '/(?<![0-9A-Za-z*])\d{6}[*Xx•]+\d{4}(?![0-9A-Za-z])/u',
            fn (array $m) => self::number($m[0]),
            $masked,
        );

        // A pattern that failed to run must not hand the original back to be stored.
        return $masked ?? '[card data removed]';
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function data(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $masked = $json === false ? null : json_decode(self::text($json), true);

        return is_array($masked) ? $masked : ['masked' => 'card data removed'];
    }

    private static function valueFor(string $key, string $value): string
    {
        if (preg_match('/^('.self::DROPPED_KEYS.')$/i', $key)) {
            return '';
        }
        if (preg_match('/^('.self::PREFIX_KEYS.')$/i', $key)) {
            return substr($value, 0, 2);
        }
        // Track 2 layout: the number, a separator, the expiry and the rest.
        $number = preg_split('/[D=]/i', $value)[0];

        return self::number(str_contains($number, '*') ? $number : rtrim($number, 'Ff'));
    }

    /** "411111******1111" or the bare digits → "41**********1111". Too short to tell apart: nothing. */
    private static function number(string $number): string
    {
        $chars = mb_str_split($number);
        if (count($chars) < 12) {
            return '';
        }

        return implode('', array_slice($chars, 0, 2)).str_repeat('*', count($chars) - 6).implode('', array_slice($chars, -4));
    }
}
