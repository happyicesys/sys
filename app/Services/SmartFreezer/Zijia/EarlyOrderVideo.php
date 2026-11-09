<?php

namespace App\Services\SmartFreezer\Zijia;

use App\Jobs\FetchEarlyFreezerVideo;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\SmartFreezer\FreezerRecognitionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds a door session's order video on Zijia's storage ourselves, instead of waiting ~6 minutes for
 * their push (Brian, 2026-11-09; evidence in apk/smart-freezer/ZIJIA_VIDEO_PUSH_DELAY_2026-11-07.md).
 *
 * The host uploads the order video while the door is open, so the finished mp4 is on their OSS
 * about 1 s after the door closes, but their cloud waits ~5 min 50 s before it pushes the link to us
 * (26 of 26 sales on 50001, 333–358 s). The link is predictable: the machine's own last push with the
 * order number swapped for this one. The freezer's TRADE reaches mark1 at door close (±3 s), so it
 * starts the search: a few seconds later mark1 asks OSS for the file (HEAD), and once every camera
 * the machine's last push carried is there, it records the session as if pushed and opens the AI
 * check through the one path a push takes (FreezerRecognitionService::open).
 *
 * Zijia's push stays the fallback and the check: it lands on the same recognition (same trade id),
 * and when it was not sent yet its videos are the ones sent (`sendableVideos`); when it carries
 * a video the early fetch did not send, that is logged, since it means their file naming moved.
 * The search never guesses beyond the machine's own last push: no push on record, a link not on an
 * allowed host or not naming its order, or files not there by `give_up_seconds` → nothing is
 * recorded and the push works exactly as before.
 *
 * Off unless the machine is listed (`smart_freezer.zijia.early_video.vends`).
 */
class EarlyOrderVideo
{
    /** Our order ref, whole: the sale's "SF-<code>-<epoch>-<seq>", plus "R<n>" for a door try (app 54+). */
    private const ORDER_REF = '/^SF-(\d+)-\d+-\d+(?:R\d+)?$/';

    /** The camera in a Zijia order-video name: "<order>-d1c3-25f-1280x720.mp4" = door 1, camera 3. */
    private const CAMERA = '/-d(\d+)c([34])-/';

    public function __construct(private readonly FreezerRecognitionService $recognitions) {}

    /** Whether this machine is switched on: listed by code, or "*" for every smart freezer. */
    public static function enabledFor(Vend $vend): bool
    {
        if (! $vend->isSmartFreezer()) {
            return false;
        }
        $listed = array_map('strval', (array) config('smart_freezer.zijia.early_video.vends'));

        return in_array('*', $listed, true) || in_array((string) $vend->code, $listed, true);
    }

    /**
     * Called by the TRADE ingest once the sale is committed. Never throws: a sale must never fail
     * over a speed-up. Queues the first look a few seconds out.
     */
    public static function afterTrade(Vend $vend, VendTransaction $sale): void
    {
        try {
            if (! self::enabledFor($vend) || ! config('smart_freezer.zijia.algorithm.auto_submit')) {
                return;
            }
            $orderRef = self::orderRefOf($vend, $sale);
            if ($orderRef === null) {
                return;
            }
            // An outbox replay of an old sale: its push has long been and gone (or is the only hope).
            $at = $sale->transaction_datetime ? Carbon::parse($sale->transaction_datetime) : null;
            if ($at === null || abs(Carbon::now()->diffInSeconds($at, false)) > (int) config('smart_freezer.zijia.early_video.max_trade_age_seconds')) {
                return;
            }

            FetchEarlyFreezerVideo::dispatch($vend->id, $orderRef, Carbon::now()->timestamp + (int) config('smart_freezer.zijia.early_video.give_up_seconds'))
                ->delay(now()->addSeconds(max(0, (int) config('smart_freezer.zijia.early_video.first_check_seconds'))));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The host order whose video to look for: the door try that opened (`SFORD`, app 54+), else the
     * sale's own ref (`SFREF`). Only our own refs, and only this machine's.
     */
    public static function orderRefOf(Vend $vend, VendTransaction $sale): ?string
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }
        if (! is_array($frame)) {
            return null;
        }
        foreach (['SFORD', 'SFREF'] as $key) {
            $ref = trim((string) ($frame[$key] ?? ''));
            if ($ref !== '' && preg_match(self::ORDER_REF, $ref, $m) === 1) {
                return $m[1] === (string) $vend->code ? $ref : null;
            }
        }

        return null;
    }

    /**
     * One look. Returns what happened: `done` (a recognition already exists — pushed, or found
     * earlier), `found` (recorded now), `waiting` (not there yet, look again), `gave_up`, or
     * `no_template` (nothing to build a link from).
     */
    public function fetch(int $vendId, string $orderRef, int $giveUpAt): string
    {
        if (SmartFreezerRecognition::where('trade_id', $orderRef)->exists()) {
            return 'done';
        }
        $vend = Vend::withoutGlobalScopes()->find($vendId);
        if ($vend === null || ! self::enabledFor($vend)) {
            return 'gave_up';
        }

        $template = $this->template($vend);
        $plan = $template ? $this->links($template, $orderRef) : null;
        if ($plan === null) {
            Log::info('freezer early video: no push to build a link from; waiting for Zijia', ['vend_id' => $vendId, 'order' => $orderRef]);

            return 'no_template';
        }

        foreach ($plan['required'] as $url) {
            if (! $this->exists($url)) {
                if (Carbon::now()->timestamp >= $giveUpAt) {
                    Log::info('freezer early video not found in time; waiting for Zijia', ['vend_id' => $vendId, 'order' => $orderRef, 'missing' => $url]);

                    return 'gave_up';
                }

                return 'waiting';
            }
        }
        $urls = $plan['required'];
        foreach ($plan['optional'] as $url) {
            if ($this->exists($url)) {
                $urls[] = $url;
            }
        }
        usort($urls, fn (string $a, string $b) => self::cameraOf($a) <=> self::cameraOf($b));

        // The push may have landed while we looked.
        if (SmartFreezerRecognition::where('trade_id', $orderRef)->exists()) {
            return 'done';
        }

        // The device as their own push names it, so the algorithm is asked about the same deviceId.
        $theirs = ZijiaVideoPush::fromPayload((array) $template->payload);
        $payload = array_filter([
            'source' => SmartFreezerVideo::SOURCE_EARLY_FETCH,
            'orderNo' => $orderRef,
            'deviceNo' => $theirs->deviceNo ?? $template->device_id,
            'imei' => $theirs->imei,
            'doorId' => $plan['door'],
            'templateVideoId' => $template->id,
        ], fn ($v) => $v !== null);
        foreach ($urls as $i => $url) {
            $payload['video'.($i + 1)] = ['videoUrl' => $url];
        }
        $push = ZijiaVideoPush::fromPayload($payload);

        $video = SmartFreezerVideo::create([
            'supplier' => 'zijia',
            'vend_id' => $vend->id,
            'order_no' => $orderRef,
            'device_id' => $template->device_id,
            'video_urls' => $urls,
            'payload' => $payload,
        ]);
        $recognition = $this->recognitions->open($video, $push, $vend);

        Log::info('freezer early video found', [
            'vend_id' => $vend->id, 'order' => $orderRef, 'video' => $video->id, 'urls' => count($urls),
            'recognition' => $recognition?->id, 'blocked' => $recognition?->status_reason,
        ]);

        return 'found';
    }

    /** The machine's latest real push (never an early-fetch row), when recent enough to trust. */
    private function template(Vend $vend): ?SmartFreezerVideo
    {
        $days = max(1, (int) config('smart_freezer.zijia.early_video.template_max_age_days'));

        return SmartFreezerVideo::query()
            ->where('vend_id', $vend->id)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('order_no')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->first(fn (SmartFreezerVideo $v) => ! $v->isEarlyFetch() && (array) $v->video_urls !== []);
    }

    /**
     * The links this order's videos will have: each video of the template push with its order number
     * swapped for this one and its query dropped (Zijia adds "?door=unopened" when the lid never
     * lifted). `required` = the cameras the template carried; `optional` = the other of cameras 3/4,
     * taken when it is there (a camera back online). Null when any template link cannot be trusted.
     *
     * @return array{required: list<string>, optional: list<string>, door: int}|null
     */
    private function links(SmartFreezerVideo $template, string $orderRef): ?array
    {
        $old = (string) $template->order_no;
        $hosts = array_map('strtolower', (array) config('smart_freezer.zijia.early_video.hosts'));
        $required = [];
        $door = 1;
        foreach ((array) $template->video_urls as $url) {
            $parts = parse_url((string) $url);
            $path = (string) ($parts['path'] ?? '');
            if (($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower((string) ($parts['host'] ?? '')), $hosts, true)
                || substr_count($path, '/'.$old.'/') !== 1 || ! str_ends_with(strtolower($path), '.mp4')
                || preg_match(self::CAMERA, basename($path), $m) !== 1 || ! str_starts_with(basename($path), $old.'-')) {
                return null;
            }
            $door = (int) $m[1];
            $required[] = 'https://'.$parts['host'].str_replace($old, $orderRef, $path);
        }
        if ($required === []) {
            return null;
        }

        $optional = [];
        foreach ($required as $url) {
            $sibling = preg_replace_callback(self::CAMERA, fn ($m) => '-d'.$m[1].'c'.($m[2] === '3' ? '4' : '3').'-', $url, 1);
            if (! in_array($sibling, $required, true) && ! in_array($sibling, $optional, true)) {
                $optional[] = $sibling;
            }
        }

        return ['required' => array_values(array_unique($required)), 'optional' => $optional, 'door' => max(1, $door)];
    }

    private function exists(string $url): bool
    {
        try {
            return Http::timeout(max(1, (int) config('smart_freezer.zijia.early_video.head_timeout')))
                ->withoutRedirecting()
                ->head($url)
                ->status() === 200;
        } catch (Throwable) {
            return false;
        }
    }

    private static function cameraOf(string $url): int
    {
        return preg_match(self::CAMERA, basename((string) parse_url($url, PHP_URL_PATH)), $m) === 1 ? (int) $m[2] : 99;
    }
}
