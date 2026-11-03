<?php

namespace App\Services\SmartFreezer\Zijia;

/**
 * How close a Zijia library name is to a product name, for a PERSON to judge. Nothing is ever linked
 * on a score: a wrong barcode makes the AI name the wrong product and can charge the wrong amount.
 */
class ZijiaSkuMatcher
{
    public const MIN_SCORE = 60;

    /** 0–100. Spaces count for nothing, so "MusangKing" meets "Musang King"; Chinese in our names is ignored. */
    public static function score(string $a, string $b): int
    {
        $a = self::latin($a);
        $b = self::latin($b);
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $spaced);
        similar_text(str_replace(' ', '', $a), str_replace(' ', '', $b), $joined);

        return (int) round(max($spaced, $joined));
    }

    private static function latin(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[\x{4e00}-\x{9fff}]+/u', ' ', ZijiaBarcodeSync::normalise($name))));
    }
}
