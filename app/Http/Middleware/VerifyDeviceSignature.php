<?php

namespace App\Http\Middleware;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a machine's HTTP request by its per-vend key: the same key that
 * signs the MQTT frames (`vends.private_key`, else the fleet fallback). Unlike
 * the rest of the device API, the card endpoints move money, so they are not
 * left open.
 *
 *   X-Device-Timestamp: <unix seconds>
 *   X-Device-Signature: hex HMAC-SHA256(key, METHOD \n PATH \n TIMESTAMP \n RAW_BODY)
 *
 * PATH is the request path as served (`/api/v1/vends/50001/card/authorize`).
 * The timestamp bounds replay to `payrallel.device_signature_skew_seconds`.
 * Limit: a machine still on the fleet fallback key is only as private as that
 * key — provision real per-vend keys before a fleet rollout.
 *
 * On success the resolved Vend is set as the request attribute `vend`.
 */
class VerifyDeviceSignature
{
    public const VEND_ATTRIBUTE = 'vend';

    public function handle(Request $request, Closure $next): Response
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)
            ->bareCode((string) $request->route('code'))
            ->first();
        if (! $vend) {
            return response()->json(['error' => 'unknown machine'], 404);
        }

        $timestamp = (string) $request->header('X-Device-Timestamp', '');
        $signature = strtolower((string) $request->header('X-Device-Signature', ''));
        $skew = (int) config('payrallel.device_signature_skew_seconds', 300);
        if ($timestamp === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $skew) {
            return response()->json(['error' => 'stale or missing timestamp'], 401);
        }

        $expected = self::sign(self::keyFor($vend), $request->getMethod(), $request->getPathInfo(), $timestamp, $request->getContent());
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'bad signature'], 401);
        }

        $request->attributes->set(self::VEND_ATTRIBUTE, $vend);

        return $next($request);
    }

    public static function sign(string $key, string $method, string $path, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', strtoupper($method)."\n".$path."\n".$timestamp."\n".$body, $key);
    }

    public static function keyFor(Vend $vend): string
    {
        return $vend->private_key ?: (string) config('vend.private_key', '123456789110138A');
    }
}
