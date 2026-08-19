<?php

namespace App\Services\Citybox;

/**
 * Signature builder/verifier for the CityBox-Openapi surface
 * (citybox/openapi_2026-08-14.pdf). Note the retired apiThredDetail API
 * joined pairs with '&' and no trailing separator; THIS API's own PHP
 * sample appends '&' after EVERY pair (including the last, so the secret is concatenated after a dangling
 * '&') and flattens arrays with join('').
 *
 * The doc contradicts itself for webhook pushes: the worked example string
 * "a=b&c=d&e=f" has NO trailing '&'. Until Citybox answers (request doc Q2),
 * verify() accepts either variant and reports which one matched so the
 * ambiguity can be settled from real traffic and then pinned down.
 */
class OpenapiSigner
{
    public const VARIANT_TRAILING = 'trailing_amp';

    public const VARIANT_NO_TRAILING = 'no_trailing_amp';

    /** Sign outbound request params (their get_access_token PHP sample form). */
    public static function sign(array $params, string $secret): string
    {
        return md5(self::baseString($params, true).$secret);
    }

    /**
     * Verify an inbound webhook signature against both concatenation variants.
     * Params must be the RAW form fields as received (data stays the raw JSON
     * string — re-encoding it would change the bytes and always fail).
     *
     * @return string|null the matching variant constant, or null if neither matches
     */
    public static function verify(array $params, string $secret): ?string
    {
        $sign = $params['sign'] ?? null;
        if (! is_string($sign) || $sign === '') {
            return null;
        }

        foreach ([
            self::VARIANT_TRAILING => self::baseString($params, true),
            self::VARIANT_NO_TRAILING => self::baseString($params, false),
        ] as $variant => $base) {
            if (hash_equals(md5($base.$secret), strtolower($sign))) {
                return $variant;
            }
        }

        return null;
    }

    /** Public so tests can assert the exact concatenation, not just the hash. */
    public static function baseString(array $params, bool $trailingAmp): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                // Their sample: if(is_array($v)) $v = join('', $v);
                $value = implode('', $value);
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $pairs[] = $key.'='.$value;
        }

        return implode('&', $pairs).($trailingAmp && $pairs ? '&' : '');
    }
}
