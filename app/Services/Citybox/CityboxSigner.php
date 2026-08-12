<?php

namespace App\Services\Citybox;

/**
 * Builds the request signature for the Citybox apiThredDetail API.
 *
 * Per the supplier doc (apiThredDetail, 2026-08-12): sort business params by
 * key ascending (dictionary order, `sign` excluded), join as
 * `key1=value1&key2=value2`, append the api_secret, MD5 → 32-char lowercase.
 *
 * NOTE: the doc's parameter table says "HMAC-SHA256" but its signing steps and
 * worked example are plain MD5 — MD5 is implemented here because the example
 * is verifiable. Isolated in this class so a supplier correction is a one-line
 * swap. Confirm with Citybox (request list item #5 in citybox/API_ANALYSIS.md).
 */
class CityboxSigner
{
    public static function sign(array $params, string $secret): string
    {
        return md5(self::baseString($params).$secret);
    }

    /**
     * The sorted `k=v&k=v` string that gets the secret appended.
     * Public so tests can assert the exact concatenation, not just the hash.
     */
    public static function baseString(array $params): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                // Doc: sort array elements, then JSON-encode. Only a flat scalar
                // list has an unambiguous sort; element order inside objects (e.g.
                // editEqiupmentProduct's `list`) is passed through as built.
                if (array_is_list($value) && $value === array_filter($value, 'is_scalar')) {
                    sort($value);
                }
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs);
    }
}
