<?php

namespace App\Http\Controllers;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * On-demand remote screen capture — Setting ▸ Edit ▸ "View Screen".
 *
 * ONE frame per deliberate human click. No streaming, no auto-refresh, no polling
 * loop on the device. Want to see it change? Click again. That keeps the cellular
 * bill flat and gives a clean audit story: one click, one image, one log line.
 *
 * Flow
 * ----
 *   1. operator clicks   -> request()  publishes a SCREENSHOT frame on CM<code>
 *                           carrying a single-use token
 *   2. device captures   -> execShellCmd("screencap -p ...") — silent by
 *                           construction: no flash, no shutter, no notification
 *   3. device uploads    -> upload()   validates the token, holds the bytes
 *   4. UI polls          -> latest()   says when it has landed
 *   5. UI renders        -> image()    streams the bytes back, permission-checked
 *
 * NOTHING IS STORED. No migration, no S3 object, no file on disk you have to
 * remember to delete. The image lives in the cache under a per-machine key with a
 * 10-minute TTL and then evaporates on its own.
 *
 * That is deliberate, and it is stronger than "write to Spaces then clean up
 * later" on two counts:
 *   - a public Spaces URL is guessable and shareable FOREVER once it leaks; the
 *     image() endpoint re-checks permission on every single fetch
 *   - there is no orphan-cleanup job to write, schedule, or forget to run
 *
 * A machine screen can show a customer's QR payment or member details. It is
 * personal-data-adjacent, so the correct lifetime is "as long as the operator is
 * looking at it", not "until someone prunes the bucket". If a screenshot ever
 * needs to be attached to a support ticket, the operator can save the image from
 * the modal — an explicit human act, which is the right place for that decision.
 *
 * Permission is enforced by `can:update machine-settings` on the three web routes
 * (the house pattern - see the rest of routes/web.php), so it ships without a
 * seeder run. A dedicated `view machine-screen` would be tighter, since screens
 * carry customer-visible data, but needs RolePermissionSyncSeeder + db:seed.
 *
 * CACHE_DRIVER is `file`, so a ~40 KB JPEG is a ~55 KB base64 blob in
 * storage/framework/cache. One entry per machine, overwritten on each capture.
 */
class VendScreenshotController extends Controller
{
    /** How long the device has to answer before the operator is told it timed out. */
    private const TOKEN_TTL_SECONDS = 120;

    /** How long a delivered image stays viewable before it evaporates. */
    private const RESULT_TTL_SECONDS = 600;

    /** Refuse a second request for the same machine inside this window. */
    private const COOLDOWN_SECONDS = 5;

    /**
     * Hard ceiling on what we will hold in the cache. The device is asked for a
     * downscaled JPEG (~25-40 KB); the raw 1080x1920 PNG is ~90 KB. Anything near
     * this is a bug or an abuse, and the file cache is not a blob store.
     */
    private const MAX_CACHEABLE_BYTES = 2097152; // 2 MB

    /**
     * Ask a machine for one frame.
     */
    public function request(Request $httpRequest, Vend $vend)
    {
        if (empty($vend->code)) {
            return response()->json(['status' => 'error', 'message' => 'This machine has no code.'], 422);
        }

        if (Cache::get($this->key('cooldown', $vend))) {
            return response()->json([
                'status' => 'cooldown',
                'message' => 'Give the machine a few seconds before asking again.',
            ], 429);
        }

        $token = Str::random(40);

        Cache::put($this->key('token', $vend), $token, self::TOKEN_TTL_SECONDS);
        Cache::put($this->key('cooldown', $vend), 1, self::COOLDOWN_SECONDS);
        // Clear BOTH halves of the previous capture. latest() checks meta first,
        // so leaving meta behind made the UI answer "ready" instantly with the
        // image from the LAST request - the operator would never see the new one.
        Cache::forget($this->key('result', $vend));
        Cache::forget($this->key('meta', $vend));
        Cache::put($this->key('pending', $vend), Carbon::now()->timestamp, self::TOKEN_TTL_SECONDS);

        PublishMqtt::dispatch('CM' . $vend->code, $this->screenshotFrame($vend, $token))->onQueue('high');

        // Audit. The screen can show a customer's QR payment or member details, so
        // every capture is attributable to a named human.
        Log::info('Machine screen capture requested', [
            'vend_code' => $vend->code,
            'vend_id' => $vend->id,
            'user_id' => $httpRequest->user()?->id,
            'user_name' => $httpRequest->user()?->name,
            'ip' => $httpRequest->ip(),
        ]);

        return response()->json([
            'status' => 'requested',
            'expires_in' => self::TOKEN_TTL_SECONDS,
        ]);
    }

    /**
     * Poll for the result: pending / ready / timeout.
     */
    public function latest(Request $httpRequest, Vend $vend)
    {
        $meta = Cache::get($this->key('meta', $vend));

        if (is_array($meta)) {
            return response()->json([
                'status' => 'ready',
                'captured_at' => $meta['captured_at'] ?? null,
                'bytes' => $meta['bytes'] ?? null,
                // Cache-buster so the browser refetches after a new capture.
                'image_url' => route('vends.screenshot.image', ['vend' => $vend->id]) . '?v=' . ($meta['stamp'] ?? 0),
            ]);
        }

        if (Cache::get($this->key('pending', $vend))) {
            return response()->json(['status' => 'pending']);
        }

        // The pending marker expires with the token, so "no result and no pending"
        // after a request means the machine never answered.
        return response()->json(['status' => 'timeout']);
    }

    /**
     * Stream the held bytes back to the browser. Permission is re-checked here on
     * every fetch — this is the reason the image is not a public Spaces URL.
     */
    public function image(Request $httpRequest, Vend $vend)
    {
        $meta = Cache::get($this->key('meta', $vend));
        $encoded = Cache::get($this->key('result', $vend));

        if (! is_array($meta) || ! $encoded) {
            abort(404, 'No screen capture is being held for this machine.');
        }

        $bytes = base64_decode($encoded, true);

        if ($bytes === false) {
            abort(500, 'Held image could not be decoded.');
        }

        return response($bytes, 200, [
            'Content-Type' => $meta['mime'] ?? 'image/jpeg',
            'Content-Length' => strlen($bytes),
            // Never let this sit in a shared proxy or the browser's disk cache.
            'Cache-Control' => 'no-store, private, max-age=0',
            'Content-Disposition' => 'inline; filename="vend-' . $vend->code . '-screen.jpg"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Device endpoint. The APK POSTs the captured frame here.
     *
     * Deliberately NOT behind auth:api — the APK has no credential to present,
     * exactly like VendDataController::uploadLog. The single-use token is the
     * control: mark1 mints it, only the machine subscribed to CM<code> receives
     * it, it is burned on first use, and it expires in two minutes.
     */
    public function upload(Request $httpRequest, $code)
    {
        $httpRequest->validate([
            'file' => 'required|file|max:8192',
            'token' => 'required|string',
        ]);

        $vend = Vend::query()->where('code', $code)->first();

        if (! $vend) {
            return response()->json(['ok' => false, 'message' => 'Unknown machine.'], 404);
        }

        $expected = Cache::get($this->key('token', $vend));

        if (! $expected || ! hash_equals((string) $expected, (string) $httpRequest->input('token'))) {
            Log::warning('Machine screen upload rejected: bad or expired token', [
                'vend_code' => $code,
                'ip' => $httpRequest->ip(),
            ]);

            return response()->json(['ok' => false, 'message' => 'Invalid or expired token.'], 403);
        }

        // Burn it. One token, one image — a replayed upload cannot overwrite a
        // later capture, and a leaked token is worthless the moment it is used.
        Cache::forget($this->key('token', $vend));

        $file = $httpRequest->file('file');
        $size = (int) $file->getSize();

        if ($size <= 0 || $size > self::MAX_CACHEABLE_BYTES) {
            return response()->json(['ok' => false, 'message' => 'Image is empty or too large.'], 422);
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            return response()->json(['ok' => false, 'message' => 'Not a readable image.'], 422);
        }

        $bytes = @file_get_contents($file->getRealPath());

        if ($bytes === false) {
            return response()->json(['ok' => false, 'message' => 'Could not read the upload.'], 500);
        }

        $stamp = Carbon::now()->timestamp;

        Cache::put($this->key('result', $vend), base64_encode($bytes), self::RESULT_TTL_SECONDS);
        Cache::put($this->key('meta', $vend), [
            'captured_at' => Carbon::now()->toDateTimeString(),
            'bytes' => $size,
            'mime' => $info['mime'] ?? 'image/jpeg',
            'stamp' => $stamp,
        ], self::RESULT_TTL_SECONDS);

        Cache::forget($this->key('pending', $vend));

        Log::info('Machine screen capture delivered', [
            'vend_code' => $code,
            'bytes' => $size,
            'mime' => $info['mime'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /* ---------------------------------------------------------------- helpers */


    /**
     * Same frame envelope as ApkReleaseController::otaCheckFrame — fid, length,
     * base64 body, md5 signed with the machine's private key.
     */
    private function screenshotFrame(Vend $vend, string $token): string
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
            'url' => url('/api/v1/vends/' . $vend->code . '/screenshot'),
            // Longest edge after downscale, and JPEG quality. 1080x1920 PNG is
            // ~90 KB; 540x960 JPEG q60 is ~25-40 KB, which matters on cellular.
            'maxEdge' => 960,
            'quality' => 60,
        ]));
        $contentLength = strlen($content);
        $key = $vend->private_key ?: config('vend.private_key', '123456789110138A');
        $md5 = md5($fid . ',' . $contentLength . ',' . $content . $key);

        return $fid . ',' . $contentLength . ',' . $content . ',' . $md5;
    }

    private function key(string $kind, Vend $vend): string
    {
        return 'vend_screenshot_' . $kind . '_' . $vend->id;
    }
}
