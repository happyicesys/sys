<?php

namespace App\Services\Screenshot;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The one way a machine is asked for a View Screen picture — an operator's click
 * (VendScreenshotController::request) and the 07:00 / 17:00 auto capture
 * (`screenshots:capture-scheduled`) both come through here, so the token, cooldown
 * and live-view cache rules exist once. Owns the per-machine cache keys the
 * controller's poll / image / upload endpoints read.
 */
class VendScreenshotRequester
{
    /** How long the device has to answer before the operator is told it timed out. */
    public const TOKEN_TTL_SECONDS = 120;

    /**
     * Refuse a second request for the same machine inside this window. A capture costs the
     * board a few seconds of CPU and a cellular upload; repeated clicks must not stack them.
     */
    public const COOLDOWN_SECONDS = 20;

    public const REQUESTED = 'requested';

    public const COOLDOWN = 'cooldown';

    /**
     * Mint a single-use token and publish the SCREENSHOT frame on CM<code>. Returns
     * REQUESTED, or COOLDOWN when the machine was asked moments ago. Eligibility
     * (ScreenshotEligibility) is the caller's check — it words its own refusal.
     *
     * $requestedBy is the user who clicked; null = the system's auto capture.
     */
    public function request(Vend $vend, ?int $requestedBy): string
    {
        if (Cache::get(self::key('cooldown', $vend))) {
            return self::COOLDOWN;
        }

        $token = Str::random(40);

        Cache::put(self::key('token', $vend), $token, self::TOKEN_TTL_SECONDS);
        Cache::put(self::key('cooldown', $vend), 1, self::COOLDOWN_SECONDS);
        // Who asked, for the history row — the device's upload carries no user.
        Cache::put(self::key('requester', $vend), $requestedBy, self::TOKEN_TTL_SECONDS);
        // Clear BOTH halves of the previous capture. latest() checks meta first,
        // so leaving meta behind made the UI answer "ready" instantly with the
        // image from the LAST request - the operator would never see the new one.
        Cache::forget(self::key('result', $vend));
        Cache::forget(self::key('meta', $vend));
        Cache::put(self::key('pending', $vend), Carbon::now()->timestamp, self::TOKEN_TTL_SECONDS);

        // `default`, not `high` (payment frames, served first) and not `low` (one worker shared
        // with 150 s image-model calls and OTA pieces, which could outlast the token).
        PublishMqtt::dispatch('CM'.$vend->code, $this->frame($vend, $token))->onQueue('default');

        return self::REQUESTED;
    }

    /** A request is still waiting for the machine's answer (its token has not expired). */
    public function isPending(Vend $vend): bool
    {
        return (bool) Cache::get(self::key('pending', $vend));
    }

    public static function key(string $kind, Vend $vend): string
    {
        return 'vend_screenshot_'.$kind.'_'.$vend->id;
    }

    /**
     * Same frame envelope as ApkReleaseController::otaCheckFrame — fid, length,
     * base64 body, md5 signed with the machine's private key.
     */
    private function frame(Vend $vend, string $token): string
    {
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'SCREENSHOT',
            'time' => Carbon::now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
            'token' => $token,
            // Where to send it. Carried in the frame so the endpoint can move
            // without shipping a new APK.
            'url' => url('/api/v1/vends/'.$vend->code.'/screenshot'),
            // Longest edge after downscale, and JPEG quality. 1080x1920 PNG is
            // ~90 KB; 540x960 JPEG q60 is ~25-40 KB, which matters on cellular.
            'maxEdge' => 960,
            'quality' => 60,
        ]));
        $contentLength = strlen($content);
        $key = $vend->private_key ?: config('vend.private_key', '123456789110138A');
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        return $fid.','.$contentLength.','.$content.','.$md5;
    }
}
