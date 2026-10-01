<?php

namespace App\Http\Middleware;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\CardTerminal\CardTerminalEventLog;
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
            return $this->reject($request, $vend, 'stale or missing timestamp', [
                'device_timestamp' => $timestamp, 'server_time' => time(),
            ]);
        }

        $expected = self::sign(self::keyFor($vend), $request->getMethod(), $request->getPathInfo(), $timestamp, $request->getContent());
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return $this->reject($request, $vend, 'bad signature', [
                'key' => $vend->private_key ? 'vend private_key' : 'fleet fallback',
            ]);
        }

        $request->attributes->set(self::VEND_ATTRIBUTE, $vend);

        return $next($request);
    }

    /** 401, written to the trial timeline: a clock or key problem must be visible without adb. */
    private function reject(Request $request, Vend $vend, string $why, array $detail): Response
    {
        app(CardTerminalEventLog::class)->record('device.rejected', [
            'why' => $why, 'method' => $request->getMethod(), 'path' => $request->getPathInfo(),
        ] + $detail, null, null, null, 'warning', $vend->id);

        return response()->json(['error' => $why], 401);
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
