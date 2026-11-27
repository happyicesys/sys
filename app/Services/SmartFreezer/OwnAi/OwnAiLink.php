<?php

namespace App\Services\SmartFreezer\OwnAi;

use App\Jobs\PublishFreezerAiJob;
use App\Models\SmartFreezerOwnAiRecognition;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideoCopy;
use App\Models\VendTransaction;
use App\Services\Mqtt\MqttPublisher;
use App\Services\SmartFreezer\Claude\FfmpegDoorFrames;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\FreezerSaleLocator;
use App\Services\SmartFreezer\RecognitionVerdict;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The link between mark1 and our own freezer AI on the office Macs (Brian, 2026-11-19), over the fleet's MQTT broker.
 *
 *   mark1 ──<prefix>/jobs {"v":1,"session":<recognition id>,…}──▶ every Mac          (QoS 1, IDs only)
 *   Mac   ──<prefix>/results/<worker> {"v":1,"session":…,"items":{product id: units},…}──▶ mark1
 *   mark1 ──<prefix>/ack/<worker> {"session":…,"ok":true}──▶ that Mac
 *   Mac   ──<prefix>/status/<worker> {"state":"online"|"offline",…}  (its last will says offline)
 *   mark1 ──<prefix>/done {"session":…,"worker":…}──▶ every Mac       (the first answer is in: nobody counts it again)
 *
 * Each Mac is a WORKER with its own name and its own broker client id, so the MacBook M1 and the Mac mini can both be
 * connected: both receive every job, the Macs pick who answers (the mini while it is up), and the FIRST worker to
 * answer owns the session — a later answer from another Mac is acked with `done_by` and not stored (Brian,
 * 2026-11-27). Video links never travel over MQTT (the broker is not encrypted): a Mac fetches the
 * session itself. SHADOW ONLY — nothing here charges, releases, refunds or moves stock.
 */
class OwnAiLink
{
    public const WORKER_PATTERN = '/^[a-z0-9][a-z0-9-]{0,31}$/';

    private const WORKERS_KEY = 'freezer-ai:workers';

    public function __construct(
        private readonly MqttPublisher $publisher,
        private readonly FreezerSaleLocator $sales,
    ) {}

    public function prefix(): string
    {
        return trim((string) config('smart_freezer.own_ai.topic_prefix', 'freezer-ai'), '/');
    }

    public function enabledFor(SmartFreezerRecognition $recognition): bool
    {
        if (! config('smart_freezer.own_ai.enabled') || $recognition->vend === null) {
            return false;
        }
        $vends = (array) config('smart_freezer.own_ai.vends');

        return $vends === [] || in_array((string) $recognition->vend->code, $vends, true);
    }

    /**
     * Called when a door session opens. Never throws: our AI must never hold up Zijia's check.
     *
     * The job goes out the moment the Mac can count the session (Brian, 2026-11-19: "as long at the moment of the
     * transactions come in … don't drag"): the sale's TRADE is in and our copy of camera 3 is stored. That is usually
     * decided by {@see videosCopied()} a few seconds after the session opens; this only sends at once when both are
     * already there, and queues a late safety net (`delay_seconds`) for a session whose copy fails or never names
     * camera 3 — the Mac then says "not yet" and retries on its own.
     */
    public function schedule(SmartFreezerRecognition $recognition): void
    {
        try {
            if (! $this->enabledFor($recognition)) {
                return;
            }
            // One safety net per session, however many pushes it arrives as (one per camera is possible).
            if (Cache::add('freezer-ai:job-scheduled:'.$recognition->id, 1, now()->addDay())) {
                PublishFreezerAiJob::dispatch($recognition->id)
                    ->delay(now()->addSeconds(max(0, (int) config('smart_freezer.own_ai.delay_seconds'))));
            }
            $this->publishIfReady($recognition);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Called when a video's copy is stored ({@see \App\Jobs\CopyZijiaVideo}). Never throws. */
    public function videosCopied(\App\Models\SmartFreezerVideo $video): void
    {
        try {
            $recognition = $video->smart_freezer_recognition_id
                ? SmartFreezerRecognition::with('vend')->find($video->smart_freezer_recognition_id)
                : null;
            if ($recognition !== null && $this->enabledFor($recognition)) {
                $this->publishIfReady($recognition);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** True when the Mac can count it now: the sale is in and camera 3 is copied (what freezer-ai fast.py checks). */
    public function readyToCount(SmartFreezerRecognition $recognition): bool
    {
        $camera3 = false;
        foreach (FreezerRecognitionService::sendableVideos($recognition->videos()->get()) as $video) {
            foreach ((array) $video->video_urls as $url) {
                if (is_string($url) && FfmpegDoorFrames::cameraOf($url) === 3
                    && SmartFreezerVideoCopy::where('url_hash', SmartFreezerVideoCopy::hashOf($url))->first()?->isCopied()) {
                    $camera3 = true;
                    break 2;
                }
            }
        }

        return $camera3 && $this->saleOf($recognition) !== null;
    }

    /** Sends the job now when the session is ready and has not been sent. Throws on a broker failure. */
    public function publishIfReady(SmartFreezerRecognition $recognition): bool
    {
        if ($this->wasPublished($recognition->id) || ! $this->readyToCount($recognition)) {
            return false;
        }
        $this->publishJob($recognition);

        return true;
    }

    public function wasPublished(int $recognitionId): bool
    {
        return Cache::has('freezer-ai:job-sent:'.$recognitionId);
    }

    /** @return array<string, mixed> */
    public function jobPayload(SmartFreezerRecognition $recognition): array
    {
        return [
            'v' => 1,
            'session' => (int) $recognition->id,
            'vend' => (string) ($recognition->vend?->code ?? ''),
            'session_ref' => $recognition->session_ref,
            'opened_at' => $recognition->created_at?->toIso8601String(),
            'sent_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Announce one session to every connected Mac. Throws on a broker failure, so the job retries. Returns false when
     * it was already sent (nothing published); `$resend` (a person's `--send`) publishes it again anyway.
     */
    public function publishJob(SmartFreezerRecognition $recognition, bool $resend = false): bool
    {
        // Claimed first, so the copy job and the safety net never both send it; released again if the broker fails.
        $key = 'freezer-ai:job-sent:'.$recognition->id;
        if (! Cache::add($key, Carbon::now()->toIso8601String(), now()->addDays(7))) {
            if (! $resend) {
                return false;
            }
            Cache::put($key, Carbon::now()->toIso8601String(), now()->addDays(7));
        }
        try {
            $this->publisher->publish($this->prefix().'/jobs', json_encode($this->jobPayload($recognition)), 1);
        } catch (Throwable $e) {
            Cache::forget($key);
            throw $e;
        }

        return true;
    }

    /**
     * One message the listener received. Returns the [topic, payload] to answer with (an ack for a result), or null.
     * Never throws: a bad message from one Mac must not stop the listener.
     *
     * @return array{0: string, 1: string}|null
     */
    public function receive(string $topic, string $message): ?array
    {
        $prefix = $this->prefix();
        if (! preg_match('#^'.preg_quote($prefix, '#').'/(results|status)/([^/]+)$#', $topic, $m)) {
            return null;
        }
        [$kind, $worker] = [$m[1], $m[2]];
        if (! preg_match(self::WORKER_PATTERN, $worker)) {
            Log::warning('freezer-ai message from an invalid worker name refused', ['topic' => $topic]);

            return null;
        }
        $data = json_decode($message, true);
        if (! is_array($data)) {
            Log::warning('freezer-ai message is not JSON', ['topic' => $topic, 'raw' => mb_substr($message, 0, 300)]);

            return $kind === 'results' ? $this->ack($worker, null, false, 'not JSON') : null;
        }
        try {
            if ($kind === 'status') {
                $this->seen($worker, $data);

                return null;
            }

            return $this->result($worker, $data);
        } catch (Throwable $e) {
            report($e);

            return $kind === 'results' ? $this->ack($worker, $data['session'] ?? null, false, 'server error') : null;
        }
    }

    /** @return array{0: string, 1: string} */
    private function result(string $worker, array $data): array
    {
        $this->seen($worker, ['state' => 'online', 'model' => $data['model'] ?? null]);
        $session = filter_var($data['session'] ?? null, FILTER_VALIDATE_INT);
        $recognition = $session ? SmartFreezerRecognition::find($session) : null;
        if ($recognition === null) {
            return $this->ack($worker, $data['session'] ?? null, false, 'unknown session');
        }
        $items = $this->items($data['items'] ?? null);
        if ($items === null) {
            return $this->ack($worker, $session, false, 'bad items');
        }
        // One Mac per session (Brian, 2026-11-27: "only the available one at the moment will do the job, if done, dont
        // need to redo when that offline one turn online"). The first Mac to answer owns the session; another Mac's
        // later answer is acknowledged (so it stops re-sending) but never stored. Its own re-answer still updates.
        $owner = SmartFreezerOwnAiRecognition::where('smart_freezer_recognition_id', $recognition->id)
            ->where('worker', '!=', $worker)->orderBy('received_at')->value('worker');
        if ($owner !== null) {
            return $this->ack($worker, $session, true, null, $owner);
        }
        $confidence = $data['confidence'] ?? null;
        $confidence = is_numeric($confidence) ? max(0, min(100, (int) round((float) $confidence))) : null;
        $now = Carbon::now();
        $sent = Cache::get('freezer-ai:job-sent:'.$recognition->id);

        $row = SmartFreezerOwnAiRecognition::firstOrNew(['smart_freezer_recognition_id' => $recognition->id, 'worker' => $worker]);
        $first = ! $row->exists;
        $row->fill([
            'vend_id' => $recognition->vend_id,
            'session_ref' => $recognition->session_ref,
            'items' => $items,
            'confidence' => $confidence,
            'auto_accept' => isset($data['auto']) ? (bool) $data['auto'] : null,
            'why_person' => isset($data['why_person']) ? mb_substr((string) $data['why_person'], 0, 255) : null,
            'model' => isset($data['model']) ? mb_substr((string) $data['model'], 0, 64) : null,
            'answer' => array_intersect_key($data, array_flip(['v', 'session', 'items', 'confidence', 'auto', 'why_person', 'model', 'answered_at', 'detail'])),
            'latency_ms' => $recognition->created_at ? (int) min(4294967295, max(0, $recognition->created_at->diffInMilliseconds($now))) : null,
            'compute_ms' => $this->computeMs($data['detail'] ?? null),
            'job_sent_at' => $sent ? Carbon::parse($sent) : null,
            'received_at' => $now,
        ]);
        $row->answers = $row->exists ? $row->answers + 1 : 1;
        $row->save();
        if ($first) {
            $this->announceDone($recognition->id, $worker);
        }

        return $this->ack($worker, $session, true);
    }

    /**
     * Tells every Mac that a session is answered: `<prefix>/done` {"session","worker"}, QoS 1. A Mac that was off gets it
     * on reconnect, queued AFTER the job it settles, so it can drop that job instead of counting it again. Never throws:
     * the answer is stored and the ack must still go out.
     */
    private function announceDone(int $recognitionId, string $worker): void
    {
        try {
            $this->publisher->publish($this->prefix().'/done', json_encode(['session' => $recognitionId, 'worker' => $worker]), 1);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** product id => units, ints only; null when malformed. An empty map is a real answer: nothing taken. */
    /**
     * How long the Mac took to count it, from its `detail.seconds` (a day at most; anything else = not known).
     */
    private function computeMs(mixed $detail): ?int
    {
        $seconds = is_array($detail) ? ($detail['seconds'] ?? null) : null;

        return is_numeric($seconds) && $seconds >= 0 && $seconds <= 86400 ? (int) round($seconds * 1000) : null;
    }

    /**
     * The Mac's per-stage seconds (`detail.timing`), only plain numbers under known-looking names, for the page's hover.
     *
     * @return array<string, float>
     */
    public function stages(SmartFreezerOwnAiRecognition $answer): array
    {
        $timing = ((array) $answer->answer)['detail']['timing'] ?? null;
        if (! is_array($timing)) {
            return [];
        }

        return collect($timing)
            ->filter(fn ($s, $name) => is_string($name) && preg_match('/^[a-z_]{1,32}$/', $name) && is_numeric($s) && $s >= 0 && $s <= 86400)
            ->map(fn ($s) => round((float) $s, 1))
            ->all();
    }

    private function items(mixed $items): ?array
    {
        if ($items === [] || $items === null) {
            return $items === null ? null : [];
        }
        if (! is_array($items) || array_is_list($items)) {
            return null;                           // [2, 1] is not {product: units}: it would be stored as products 0 and 1
        }
        $out = [];
        foreach ($items as $product => $units) {
            if (filter_var($product, FILTER_VALIDATE_INT) === false || (int) $product <= 0 || filter_var($units, FILTER_VALIDATE_INT) === false
                || (int) $units < 0 || (int) $units > 50) {
                return null;
            }
            if ((int) $units > 0) {
                $out[(string) (int) $product] = (int) $units;
            }
        }

        return $out;
    }

    /** @return array{0: string, 1: string} */
    private function ack(string $worker, mixed $session, bool $ok, ?string $error = null, ?string $doneBy = null): array
    {
        return [$this->prefix().'/ack/'.$worker, json_encode(array_filter(
            ['session' => is_numeric($session) ? (int) $session : null, 'ok' => $ok, 'error' => $error, 'done_by' => $doneBy],
            fn ($v) => $v !== null,
        ))];
    }

    private function seen(string $worker, array $data): void
    {
        $state = ($data['state'] ?? 'online') === 'offline' ? 'offline' : 'online';
        Cache::put('freezer-ai:worker:'.$worker, [
            'state' => $state,
            'model' => isset($data['model']) ? mb_substr((string) $data['model'], 0, 64) : null,
            'host' => isset($data['host']) ? mb_substr((string) $data['host'], 0, 64) : null,
            'role' => in_array($data['role'] ?? null, ['active', 'standby'], true) ? $data['role'] : null,
            'seen_at' => Carbon::now()->toIso8601String(),
        ], now()->addDays(30));
        $workers = (array) Cache::get(self::WORKERS_KEY, []);
        if (! in_array($worker, $workers, true)) {
            $workers[] = $worker;
            Cache::forever(self::WORKERS_KEY, array_slice($workers, -20));
        }
    }

    /**
     * The answer to read for a session: the first worker in `worker_priority` that answered (the mini before the M1),
     * else the most recent answer. Since 2026-11-27 a session keeps only its first Mac's answer, so this settles the
     * sessions both Macs answered before that.
     */
    public function preferredAnswer(int $recognitionId): ?SmartFreezerOwnAiRecognition
    {
        $rows = SmartFreezerOwnAiRecognition::where('smart_freezer_recognition_id', $recognitionId)->get();
        $order = array_flip((array) config('smart_freezer.own_ai.worker_priority', []));

        return $rows->sortBy([
            fn ($a, $b) => ($order[$a->worker] ?? PHP_INT_MAX) <=> ($order[$b->worker] ?? PHP_INT_MAX),
            fn ($a, $b) => $b->received_at <=> $a->received_at,
        ])->first();
    }

    /** The sale this session paid for: the one the recognition is linked to, else found by its session ref. */
    public function saleOf(SmartFreezerRecognition $recognition): ?VendTransaction
    {
        if ($recognition->vendTransaction !== null) {
            return $recognition->vendTransaction;
        }
        if ($recognition->vend === null || ! $recognition->session_ref) {
            return null;
        }

        return $this->sales->find($recognition->vend, (string) $recognition->session_ref, $recognition->created_at);
    }

    /**
     * Our answer held against the paid order with Zijia's own rule (RecognitionVerdict): match / took_more / took_less /
     * mixed, or null while the sale is not in. Shadow only.
     */
    public function verdict(SmartFreezerOwnAiRecognition $answer, ?VendTransaction $sale): ?string
    {
        if ($sale === null) {
            return null;
        }
        $paid = $this->sales->paidUnits($sale);
        $taken = $byCode = [];
        foreach ((array) $answer->items as $productId => $units) {
            $taken['p'.$productId] = (int) $units;
            $byCode['p'.$productId] = (int) $productId;
        }
        foreach (array_keys($paid) as $productId) {
            $byCode['p'.$productId] = (int) $productId;
        }

        return RecognitionVerdict::compare($paid, $taken, $byCode)->outcome;
    }

    /**
     * Every worker heard from: name => [state online|offline|silent, model, seen_at]. "silent" = said online but nothing
     * for `worker_offline_after_seconds` (the Mac sends a status every minute).
     *
     * @return array<string, array<string, mixed>>
     */
    public function workers(): array
    {
        $out = [];
        $limit = (int) config('smart_freezer.own_ai.worker_offline_after_seconds', 180);
        foreach ((array) Cache::get(self::WORKERS_KEY, []) as $w) {
            $s = Cache::get('freezer-ai:worker:'.$w);
            if (! is_array($s)) {
                continue;
            }
            if ($s['state'] === 'online' && Carbon::parse($s['seen_at'])->lt(Carbon::now()->subSeconds($limit))) {
                $s['state'] = 'silent';
            }
            $out[$w] = $s;
        }

        return $out;
    }
}
