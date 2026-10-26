<?php

namespace App\Http\Controllers;

use App\Models\Vend;
use App\Models\VendScreenshot;
use App\Services\Screenshot\VendScreenshotHistory;
use App\Services\Screenshot\VendScreenshotRequester;
use App\Support\ScreenshotEligibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * On-demand remote screen capture — Setting ▸ Edit ▸ "View Screen".
 *
 * ONE frame per deliberate human click. No streaming, no auto-refresh, no polling
 * loop on the device. Want to see it change? Click again. That keeps the cellular
 * bill flat and gives a clean audit story: one click, one image, one log line.
 * The one exception is the system's own capture at 07:00 and 17:00 (Brian,
 * 2026-10-16; `screenshots:capture-scheduled`, `vend.screenshot.auto_hours`): two
 * frames a day per machine, kept in the same history with no requester ("Auto").
 * Both go through VendScreenshotRequester.
 *
 * Flow
 * ----
 *   1. operator clicks   -> request()  publishes a SCREENSHOT frame on CM<code>
 *                           carrying a single-use token
 *   2. device captures   -> only once the machine is idle (no sale, payment
 *                           screen or dispense in flight), silently: no flash,
 *                           no shutter, no notification. Big board 317+
 *                           (ScreenshotCoordinator: root `screencap`, else its
 *                           own window); freezer 12+ (its own window).
 *   3. device uploads    -> upload()   validates the token, holds the bytes —
 *                           or, when it cannot, says why: `busy` (still in use
 *                           after 60 s) / `failed`, so the operator is told
 *                           instead of waiting out the token
 *   4. UI polls          -> latest()   pending / ready / busy / failed / timeout
 *   5. UI renders        -> image()    streams the bytes back, permission-checked
 *
 * THE LIVE VIEW IS CACHE-ONLY. The image the operator is waiting for lives in the
 * cache under a per-machine key with a 10-minute TTL and is served by image(),
 * which re-checks permission on every fetch — never a public Spaces URL.
 *
 * HISTORY: THE LAST FIVE (Brian, 2026-10-15: "save the last five pictures as
 * history; if more than five, delete"). Each delivered picture is also kept by
 * VendScreenshotHistory — private on the default disk (DO Spaces in prod), a
 * `vend_screenshots` row naming who clicked — and a machine is pruned to its
 * newest five, file and row, on every save. So there is no cleanup job, and a
 * screen that showed a customer's QR payment or member details stays at most five
 * pictures deep. History images are served only by historyImage(), with the same
 * permission check as the live one.
 *
 * Permission is enforced by `can:update machine-settings` on the web routes
 * (the house pattern - see the rest of routes/web.php), so it ships without a
 * seeder run. A dedicated `view machine-screen` would be tighter, since screens
 * carry customer-visible data, but needs RolePermissionSyncSeeder + db:seed.
 *
 * CACHE_DRIVER is `file`, so a ~40 KB JPEG is a ~55 KB base64 blob in
 * storage/framework/cache. One entry per machine, overwritten on each capture.
 */
class VendScreenshotController extends Controller
{
    /** How long a delivered image stays viewable before it evaporates. */
    private const RESULT_TTL_SECONDS = 600;

    /** What the machine may say instead of sending a picture. */
    private const DEVICE_STATUSES = ['busy', 'failed'];

    /** Longest reason kept from the machine (it is shown to the operator as text). */
    private const REASON_MAX = 190;

    /**
     * Hard ceiling on what we will hold in the cache. The device is asked for a
     * downscaled JPEG (~25-40 KB); the raw 1080x1920 PNG is ~90 KB. Anything near
     * this is a bug or an abuse, and the file cache is not a blob store.
     */
    private const MAX_CACHEABLE_BYTES = 2097152; // 2 MB

    /**
     * Ask a machine for one frame.
     */
    public function request(Request $httpRequest, Vend $vend, VendScreenshotRequester $requester)
    {
        // The one rule the page's disabled button also shows: a board that cannot answer
        // would only leave the operator on a 2-minute timeout.
        if (($why = ScreenshotEligibility::reason($vend)) !== null) {
            return response()->json(['status' => 'unsupported', 'message' => $why], 422);
        }

        if ($requester->request($vend, $httpRequest->user()?->id) === VendScreenshotRequester::COOLDOWN) {
            return response()->json([
                'status' => 'cooldown',
                'message' => __('Give the machine a few seconds before asking again.'),
            ], 429);
        }

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
            'expires_in' => VendScreenshotRequester::TOKEN_TTL_SECONDS,
        ]);
    }

    /**
     * Poll for the result: pending / ready / timeout.
     */
    public function latest(Request $httpRequest, Vend $vend)
    {
        $meta = Cache::get($this->key('meta', $vend));

        if (is_array($meta) && in_array($meta['status'] ?? null, self::DEVICE_STATUSES, true)) {
            return response()->json([
                'status' => $meta['status'],
                'message' => $meta['reason'] ?? null,
                'answered_at' => $meta['captured_at'] ?? null,
            ]);
        }

        if (is_array($meta)) {
            return response()->json([
                'status' => 'ready',
                'captured_at' => $meta['captured_at'] ?? null,
                'bytes' => $meta['bytes'] ?? null,
                // Cache-buster so the browser refetches after a new capture.
                'image_url' => route('vends.screenshot.image', ['vend' => $vend->id]).'?v='.($meta['stamp'] ?? 0),
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

        if (! is_array($meta) || ! $encoded || isset($meta['status'])) {
            abort(404, __('No screen capture is being held for this machine.'));
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
            'Content-Disposition' => 'inline; filename="vend-'.$vend->codeLabel().'-screen.jpg"',
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
            // A picture, or (big board 317+) the reason there is none.
            'file' => 'required_without:status|file|max:8192',
            'status' => 'nullable|string|in:'.implode(',', self::DEVICE_STATUSES),
            'reason' => 'nullable|string|max:1000',
            'token' => 'required|string',
        ]);

        $vend = Vend::query()->bareCode($code)->first();

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

        if (! $httpRequest->hasFile('file')) {
            $status = (string) $httpRequest->input('status');
            $reason = mb_substr(trim((string) $httpRequest->input('reason', '')), 0, self::REASON_MAX);
            Cache::put($this->key('meta', $vend), [
                'status' => $status,
                'reason' => $reason !== '' ? $reason : null,
                'captured_at' => Carbon::now()->toDateTimeString(),
            ], self::RESULT_TTL_SECONDS);
            Cache::forget($this->key('pending', $vend));

            Log::info('Machine screen capture not taken', ['vend_code' => $code, 'status' => $status, 'reason' => $reason]);

            return response()->json(['ok' => true]);
        }

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

        // After the live view is answered: a Spaces hiccup must never cost the operator the picture.
        try {
            app(VendScreenshotHistory::class)->store(
                $vend, $bytes, $info['mime'] ?? 'image/jpeg', $info[0] ?? null, $info[1] ?? null,
                Cache::pull($this->key('requester', $vend)),
            );
        } catch (\Throwable $e) {
            Log::warning('Machine screen capture not kept in history', ['vend_code' => $code, 'error' => $e->getMessage()]);
        }

        Log::info('Machine screen capture delivered', [
            'vend_code' => $code,
            'bytes' => $size,
            'mime' => $info['mime'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * The machine's last five pictures, newest first, for the modal's history strip.
     */
    public function history(Vend $vend, VendScreenshotHistory $history)
    {
        return response()->json([
            'keep' => VendScreenshotHistory::KEEP,
            'items' => $history->recent($vend)->map(fn (VendScreenshot $shot) => [
                'id' => $shot->id,
                'captured_at' => $shot->captured_at?->toDateTimeString(),
                'bytes' => $shot->bytes,
                'requested_by' => $shot->requester?->name,
                // No requester = the 07:00 / 17:00 auto capture (every click has a user).
                'auto' => $shot->requested_by === null,
                'image_url' => route('vends.screenshot.history.image', ['vend' => $vend->id, 'screenshot' => $shot->id]),
            ])->values(),
        ]);
    }

    /**
     * One history picture. Private on Spaces, so it is streamed through here (permission
     * re-checked on every fetch), never linked.
     */
    public function historyImage(Vend $vend, VendScreenshot $screenshot)
    {
        abort_unless((int) $screenshot->vend_id === (int) $vend->id, 404);

        $disk = Storage::disk();
        abort_unless($disk->exists($screenshot->path), 404, __('This picture is no longer held.'));

        return $disk->response($screenshot->path, 'vend-'.$vend->codeLabel().'-screen-'.$screenshot->id.'.'.pathinfo($screenshot->path, PATHINFO_EXTENSION), [
            'Content-Type' => $screenshot->mime,
            // Never let this sit in a shared proxy or the browser's disk cache.
            'Cache-Control' => 'no-store, private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /* ---------------------------------------------------------------- helpers */

    private function key(string $kind, Vend $vend): string
    {
        return VendScreenshotRequester::key($kind, $vend);
    }
}
