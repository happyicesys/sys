<?php

namespace App\Services\SmartFreezer\Zijia;

/**
 * Zijia's request signature (智佳算法服务接口文档 §3.2), both directions.
 *
 * Every parameter except `sign`, ASCII-sorted by name, empty values dropped, joined as
 * `k=v&k=v`, then `&key=<secret>` appended, MD5, upper-case hex. `bizContent` is signed as the
 * exact JSON STRING that travels — which is why callers must sign the string they send and never
 * re-encode it afterwards.
 *
 * Proven against their live service on 2026-09-27: a request signed this way with our appSecret
 * passed their check, the same request signed with the key printed in their document did not.
 */
final class ZijiaSigner
{
    public function __construct(private readonly string $secret) {}

    /** @param  array<string, scalar|null>  $params */
    public function sign(array $params): string
    {
        return strtoupper(md5($this->canonical($params).'&key='.$this->secret));
    }

    /** @param  array<string, mixed>  $params  a received envelope, `sign` included */
    public function verify(array $params): bool
    {
        $presented = $params['sign'] ?? null;
        if (! is_string($presented) || $presented === '') {
            return false;
        }

        return hash_equals($this->sign($params), strtoupper($presented));
    }

    /** @param  array<string, mixed>  $params */
    private function canonical(array $params): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);

        $pairs = [];
        foreach ($params as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $pairs[] = $name.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return implode('&', $pairs);
    }
}
