<?php

namespace App\Services\Citybox;

/**
 * Which payment_methods code a CityBox order is booked under (Brian, 2026-10-13: "Citybox - Card").
 * Their own label (`transaction`) decides: refer_type 2 and 52 are both CARD, so the number alone
 * would split one rail. The codes are seeded by 2026_10_13_100300_add_citybox_payment_methods.
 */
final class CityboxPayMethod
{
    public const OTHER = 900;

    public const CARD = 901;

    public const APPLE_PAY = 902;

    public const TNG = 903;

    public const ALL = [self::OTHER, self::CARD, self::APPLE_PAY, self::TNG];

    public static function codeFor(?string $payLabel): int
    {
        return match (strtoupper(trim((string) $payLabel))) {
            'CARD' => self::CARD,
            'APPLEPAY', 'APPLE_PAY', 'APPLE PAY' => self::APPLE_PAY,
            'TNG' => self::TNG,
            default => self::OTHER,
        };
    }
}
