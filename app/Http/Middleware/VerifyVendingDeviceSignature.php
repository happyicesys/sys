<?php

namespace App\Http\Middleware;

use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * The big board's (mark1-apk) signed HTTP calls — the next-visit voucher API. Same scheme as
 * VerifyDeviceSignature (HMAC-SHA256 over METHOD \n PATH \n TIMESTAMP \n BODY), but the key is the one the
 * board ACTUALLY holds: it signs its MQTT frames with mqtt-config's client id + "A" (CvMqttService), and
 * mark1 stores that client id without the "A" in `vends.private_key` (225 of 447 active vending machines,
 * 2026-11-20), so the freezer rule (`private_key` as stored) would refuse every board. Accepted: the stored
 * key, the stored key + "A", and the fleet fallback. That is exactly as private as the fleet key — the
 * caveat VerifyDeviceSignature already records — and it is not used for anything that moves money by itself.
 */
class VerifyVendingDeviceSignature
{
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
            Log::warning('vending device signature: stale or missing timestamp', ['vend' => $vend->code, 'ts' => $timestamp, 'now' => time()]);

            return response()->json(['error' => 'stale or missing timestamp'], 401);
        }

        foreach (self::keysFor($vend) as $key) {
            $expected = VerifyDeviceSignature::sign($key, $request->getMethod(), $request->getPathInfo(), $timestamp, $request->getContent());
            if ($signature !== '' && hash_equals($expected, $signature)) {
                $request->attributes->set(VerifyDeviceSignature::VEND_ATTRIBUTE, $vend);

                return $next($request);
            }
        }
        Log::warning('vending device signature: bad signature', ['vend' => $vend->code, 'path' => $request->getPathInfo()]);

        return response()->json(['error' => 'bad signature'], 401);
    }

    /** @return list<string> */
    public static function keysFor(Vend $vend): array
    {
        $fleet = (string) config('vend.private_key', '123456789110138A');
        $stored = (string) ($vend->private_key ?? '');

        return array_values(array_unique(array_filter([$stored !== '' ? $stored.'A' : null, $stored ?: null, $fleet])));
    }
}
