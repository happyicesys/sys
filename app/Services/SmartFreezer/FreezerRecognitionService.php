<?php

namespace App\Services\SmartFreezer;

use App\Jobs\SubmitFreezerRecognition;
use App\Models\FreezerFailedSession;
use App\Models\Product;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\SmartFreezer\Zijia\RecognitionRequest;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use App\Services\SmartFreezer\Zijia\ZijiaAlgorithmClient;
use App\Services\SmartFreezer\Zijia\ZijiaVideoPush;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The AI check of a freezer door session, end to end:
 *
 *  1. open()     — a Zijia video push arrives; ONE recognition row per door session.
 *  2. submit()   — ask the algorithm, with the videos and the cabinet's candidate SKUs.
 *  3. complete() — its answer arrives on the notify URL; stored as received.
 *  4. evaluate() — held against the paid sale: RecognitionVerdict. Re-run by the
 *                  `smart-freezer:zijia-evaluate-pending` sweep for sales that arrive late.
 *
 * Every stop is recorded on the row with its reason, because each depends on something outside
 * mark1 (their contract, their product modelling, the TRADE arriving) and "nothing happened"
 * must always be explainable from the row alone. A recognition is a metered call on their side:
 * only a `pending` row is ever sent, submit() claims it first, and sending one again is a person's
 * decision (retry()).
 */
class FreezerRecognitionService
{
    public function __construct(
        private readonly ZijiaAlgorithmClient $client,
        private readonly FreezerSaleLocator $sales,
    ) {}

    /**
     * Step 1: the recognition for this push's door session — one row however many pushes the session
     * arrives as (one per camera is possible), even when they arrive at the same instant: `trade_id`
     * is unique, so a concurrent second insert fails and createOrFirst reads the winner back. Each
     * push is linked to it, and fills in what the row does not know yet — the push carrying the IMEI
     * may not be the first. Submission waits the settle window so every camera is in before the
     * metered call.
     */
    public function open(SmartFreezerVideo $video, ZijiaVideoPush $push, ?Vend $vend): ?SmartFreezerRecognition
    {
        if ($push->tradeId === null) {
            Log::warning('zijia video push without a trade id; no recognition opened', ['video' => $video->id]);

            return null;
        }

        $recognition = SmartFreezerRecognition::createOrFirst(
            ['trade_id' => $push->tradeId],
            [
                'vend_id' => $vend?->id,
                'session_ref' => $push->sessionRef,
                'device_id' => $push->deviceIdentifier(),
                'status' => SmartFreezerRecognition::STATUS_PENDING,
            ],
        );

        if ($vend !== null && $recognition->vend_id !== null && (int) $recognition->vend_id !== $vend->id) {
            // Two pushes of one session naming two freezers: keep the first, say so.
            Log::warning('zijia pushes of one door session name different freezers', [
                'recognition' => $recognition->id, 'kept' => $recognition->vend_id, 'pushed' => $vend->id,
            ]);
        }
        $recognition->vend_id ??= $vend?->id;
        $recognition->session_ref ??= $push->sessionRef;
        $recognition->device_id ??= $push->deviceIdentifier();
        $video->update(['smart_freezer_recognition_id' => $recognition->id]);

        if ($recognition->status !== SmartFreezerRecognition::STATUS_PENDING) {
            // Already sent: a late camera cannot join a call that is in flight. Kept, and said.
            $this->noteLatePush($video, $recognition);
            $recognition->save();

            return $recognition;
        }

        if (($skip = $this->doorTryNotOpened($recognition)) !== null) {
            $recognition->status = SmartFreezerRecognition::STATUS_SKIPPED;
            $recognition->status_reason = $skip;
            $recognition->save();

            return $recognition;
        }

        $recognition->status_reason = $this->blocker($recognition);
        $recognition->save();

        if ($recognition->status_reason === null && config('smart_freezer.zijia.algorithm.auto_submit')) {
            // One job per push; the job waits for the session to go quiet, and the claim in
            // submit() lets only the first through.
            SubmitFreezerRecognition::dispatch($recognition->id)->delay(now()->addSeconds($this->settleSeconds()));
        }

        return $recognition;
    }

    /**
     * Why this recognition cannot be submitted yet, or null when it can. The order is the order
     * a person would fix things in.
     */
    public function blocker(SmartFreezerRecognition $recognition): ?string
    {
        return match (true) {
            ! $this->client->isConfigured() => 'algorithm credentials not configured',
            $recognition->vend_id === null => 'freezer not recognised from the push (no IMEI / device no / session ref match)',
            $this->videoUrls($recognition) === [] => 'the push carried no video URL',
            $this->candidateCodes($recognition->vend) === [] => 'none of this freezer\'s products has a barcode (Zijia 商品条形码)',
            default => null,
        };
    }

    /**
     * Seconds the session has been quiet for too short a time to send, or 0 when it may go. The
     * window counts from the LAST push, so a slow second camera still makes it into the call.
     */
    public function settleRemaining(SmartFreezerRecognition $recognition): int
    {
        $last = $recognition->videos()->max('created_at');
        if ($last === null) {
            return 0;
        }

        return max(0, $this->settleSeconds() - (int) Carbon::parse($last)->diffInSeconds(Carbon::now(), true));
    }

    /**
     * Step 2. Only a `pending` row is ever sent; any other row is returned untouched, its reason
     * included. A blocked pending row records what it is waiting for.
     */
    public function submit(SmartFreezerRecognition $recognition): SmartFreezerRecognition
    {
        if ($recognition->status !== SmartFreezerRecognition::STATUS_PENDING) {
            return $recognition;
        }
        // Asked again at send time: the TRADE naming the try that opened usually lands after the push.
        if (($skip = $this->doorTryNotOpened($recognition)) !== null) {
            SmartFreezerRecognition::whereKey($recognition->id)
                ->where('status', SmartFreezerRecognition::STATUS_PENDING)
                ->update(['status' => SmartFreezerRecognition::STATUS_SKIPPED, 'status_reason' => $skip]);

            return $recognition->refresh();
        }
        if (($reason = $this->blocker($recognition)) !== null) {
            $recognition->update(['status_reason' => $reason]);

            return $recognition;
        }

        // Claim: only one worker may spend a recognition.
        $claimed = SmartFreezerRecognition::whereKey($recognition->id)
            ->where('status', SmartFreezerRecognition::STATUS_PENDING)
            ->update(['status' => SmartFreezerRecognition::STATUS_SUBMITTING, 'status_reason' => null]);
        if ($claimed !== 1) {
            return $recognition->refresh();
        }

        try {
            $request = $this->buildRequest($recognition->load('videos'));
            $response = $this->client->submitRecognition($request);

            // What proves the billed call happened goes in first, in plain columns, so nothing
            // that follows can lose it.
            $recognition->update([
                'status' => $response->ok() ? SmartFreezerRecognition::STATUS_SUBMITTED : SmartFreezerRecognition::STATUS_FAILED,
                'status_reason' => $response->ok() ? null : mb_substr('submit refused: '.($response->message ?? "code {$response->code}"), 0, 255),
                'request_id' => $response->requestId,
                'submitted_at' => Carbon::now(),
            ]);
            $recognition->update([
                'request_payload' => $request->bizContent(),
                'response' => $response->raw ?: ['code' => $response->code, 'msg' => $response->message],
            ]);
        } catch (Throwable $e) {
            // Never leave a row stuck in `submitting`: it would never be sent, and never be retried.
            $recognition->update([
                'status' => SmartFreezerRecognition::STATUS_FAILED,
                'status_reason' => mb_substr('error while submitting: '.$e->getMessage(), 0, 255),
            ]);
            report($e);
        }

        return $recognition;
    }

    /**
     * Put a failed recognition back to `pending` so it can be sent again — a person's decision,
     * since it is billed again. A row stuck in `submitting` (a worker died mid-call) is released
     * only with $force: its first call may or may not have reached them.
     */
    public function retry(SmartFreezerRecognition $recognition, bool $force = false): bool
    {
        $from = $force
            ? [SmartFreezerRecognition::STATUS_FAILED, SmartFreezerRecognition::STATUS_SUBMITTING]
            : [SmartFreezerRecognition::STATUS_FAILED];

        $released = SmartFreezerRecognition::whereKey($recognition->id)
            ->whereIn('status', $from)
            ->update(['status' => SmartFreezerRecognition::STATUS_PENDING, 'status_reason' => 'resubmission requested']) === 1;
        $recognition->refresh();

        return $released;
    }

    /**
     * Step 3: the algorithm's answer. An UNVERIFIED answer (callback_verification = log) may update a
     * recognition mark1 sent, but can neither overwrite one already holding a verified answer nor
     * create a row for a trade mark1 never asked about — the envelope is logged instead. A VERIFIED
     * answer for an unknown trade is kept as its own row, so a genuine result is never lost.
     */
    public function complete(RecognitionResult $result, bool $verified, array $callback): ?SmartFreezerRecognition
    {
        $recognition = SmartFreezerRecognition::where('trade_id', $result->tradeId)->first();

        if ($recognition === null && ! $verified) {
            Log::warning('unverified zijia result for an unknown trade: not stored', ['trade_id' => $result->tradeId, 'callback' => $callback]);

            return null;
        }
        if ($recognition !== null && ! $verified && $recognition->callback_verified === true) {
            Log::warning('unverified zijia result ignored: the recognition already holds a verified one', [
                'recognition' => $recognition->id, 'callback' => $callback,
            ]);

            return $recognition;
        }

        $recognition ??= SmartFreezerRecognition::create([
            'trade_id' => $result->tradeId,
            'status' => SmartFreezerRecognition::STATUS_SUBMITTED,
        ]);

        $recognition->update([
            'order_status' => $result->orderStatus,
            'items' => $result->items,
            'error_message' => $result->errorMessage ? mb_substr($result->errorMessage, 0, 255) : null,
            'callback_payload' => $callback,
            'callback_verified' => $verified,
            'completed_at' => Carbon::now(),
            'status' => $result->isNormal() ? SmartFreezerRecognition::STATUS_COMPLETED : SmartFreezerRecognition::STATUS_FAILED,
            'status_reason' => match (true) {
                ! $result->isNormal() => 'algorithm: '.$result->statusLabel(),
                $recognition->vend_id === null => 'result for a trade mark1 has no record of submitting',
                default => null,
            },
        ]);

        if ($result->isNormal()) {
            $this->evaluate($recognition);
        }

        return $recognition;
    }

    /**
     * Step 4: the verdict against the paid sale. Safe to re-run, and re-run by the sweep
     * (`evaluateAwaitingSale`): the TRADE of an offline board can land long after the result, and
     * products may be modelled later.
     */
    public function evaluate(SmartFreezerRecognition $recognition): SmartFreezerRecognition
    {
        if ($recognition->status !== SmartFreezerRecognition::STATUS_COMPLETED || $recognition->vend === null) {
            return $recognition;
        }

        $sale = $recognition->session_ref
            ? $this->sales->find($recognition->vend, $recognition->session_ref, $recognition->created_at)
            : null;
        if ($sale === null) {
            $recognition->update(['status_reason' => $recognition->session_ref
                ? 'sale not found yet for '.$recognition->session_ref
                : 'no session ref to find the sale by']);

            return $recognition;
        }
        // A try whose door did not open was sent anyway (its push came before anything said so): its
        // answer is about a shut lid, never about this sale, so it must not judge it — a "nothing
        // taken" here would release the sale's card hold.
        if (($other = $this->notTheOpenedTry($recognition, $sale)) !== null) {
            $recognition->update(['status' => SmartFreezerRecognition::STATUS_SKIPPED, 'status_reason' => $other]);

            return $recognition;
        }

        $verdict = RecognitionVerdict::compare(
            $this->sales->paidUnits($sale),
            (array) $recognition->items,
            $this->productByCode($recognition->vend, array_keys((array) $recognition->items)),
        );

        $recognition->update([
            'vend_transaction_id' => $sale->id,
            'verdict' => $verdict->outcome,
            'verdict_lines' => $verdict->lines,
            'status_reason' => $verdict->outcome === RecognitionVerdict::INCOMPLETE
                ? 'paid for a product the algorithm could not name (no barcode): '.implode(', ', $verdict->unnameable)
                : null,
        ]);

        return $recognition;
    }

    /**
     * Why this recognition is a door try whose door never opened, so it is kept but never sent to the
     * AI (Brian, 2026-11-08), or null when it may be sent. Freezer app 54+ tries a lock that will not
     * open up to five times, each a new host order "<SFREF>R<n>" with a video of a shut lid:
     *
     *  - the sale's TRADE names the try that opened (`SFORD`), and this is another one;
     *  - there is no sale and the freezer reported the session as failed before the door opened;
     *  - a later try of the same sale is already here (each try is made only after the one before failed).
     *
     * Only our own refs are judged; a trade id of theirs cannot be told apart, so it is sent as before.
     */
    public function doorTryNotOpened(SmartFreezerRecognition $recognition): ?string
    {
        $try = self::doorTryOf($recognition->trade_id);
        if ($try === null || $recognition->vend === null || $recognition->session_ref === null) {
            return null;
        }

        $sale = $this->sales->find($recognition->vend, $recognition->session_ref, $recognition->created_at);
        if ($sale !== null) {
            return $this->notTheOpenedTry($recognition, $sale);
        }

        $failed = FreezerFailedSession::query()
            ->where('vend_id', $recognition->vend_id)
            ->where('session_ref', $recognition->session_ref)
            ->whereIn('reason', (array) config('smart_freezer.unfulfilled_refund.reasons'))
            ->exists();
        if ($failed) {
            return "door try {$try}: the door never opened (the freezer reported the session failed) — not sent to the AI";
        }

        $later = SmartFreezerRecognition::query()
            ->where('vend_id', $recognition->vend_id)
            ->where('session_ref', $recognition->session_ref)
            ->whereKeyNot($recognition->id)
            ->pluck('trade_id')
            ->map(fn ($id) => self::doorTryOf($id))
            ->filter(fn ($n) => $n !== null && $n > $try)
            ->max();

        return $later !== null
            ? "door try {$try}: the door did not open, try {$later} followed — not sent to the AI"
            : null;
    }

    /**
     * Why $recognition is not the door try that opened for $sale, or null when it is (or cannot be
     * told). The TRADE names the opened try's host order as `SFORD` (app 54+); before that every
     * sale had one try, its own ref.
     */
    private function notTheOpenedTry(SmartFreezerRecognition $recognition, $sale): ?string
    {
        $try = self::doorTryOf($recognition->trade_id);
        if ($try === null) {
            return null;
        }
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }
        $opened = (string) (((array) $frame)['SFORD'] ?? ((array) $frame)['SFREF'] ?? '');
        if ($opened === '' || $opened === $recognition->trade_id) {
            return null;
        }
        $openedTry = self::doorTryOf($opened) ?? 1;

        return "door try {$try}: the door did not open, it opened on try {$openedTry} — not sent to the AI";
    }

    /** The door try an order ref of ours names (1 for the plain ref), or null when it is not ours. */
    public static function doorTryOf(?string $orderRef): ?int
    {
        if ($orderRef === null || preg_match('/^SF-\d+-\d+-\d+(?:R(\d+))?$/', $orderRef, $m) !== 1) {
            return null;
        }

        return isset($m[1]) && $m[1] !== '' ? max(1, (int) $m[1]) : 1;
    }

    /**
     * The sweep: completed results still waiting for their sale, from the last $days.
     *
     * @return int how many now carry a verdict
     */
    public function evaluateAwaitingSale(int $days = 7): int
    {
        $judged = 0;
        SmartFreezerRecognition::query()
            ->where('status', SmartFreezerRecognition::STATUS_COMPLETED)
            ->whereNull('verdict')
            ->whereNotNull('vend_id')
            ->whereNotNull('session_ref')
            ->where('completed_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('id')
            ->each(function (SmartFreezerRecognition $recognition) use (&$judged) {
                if ($this->evaluate($recognition)->verdict !== null) {
                    $judged++;
                }
            });

        return $judged;
    }

    /**
     * Every video URL the session's pushes carried, first-seen order.
     *
     * @return list<string>
     */
    public function videoUrls(SmartFreezerRecognition $recognition): array
    {
        return self::sendableVideos($recognition->videos)->flatMap(fn (SmartFreezerVideo $v) => (array) $v->video_urls)->unique()->values()->all();
    }

    /**
     * The videos a recognition is sent with: Zijia's own pushes once any has arrived, else the ones
     * mark1 found early on their storage (EarlyOrderVideo). The push is their word on which files
     * belong to the session, so it wins whenever it is in before the send.
     *
     * @param  Collection<int, SmartFreezerVideo>  $videos
     * @return Collection<int, SmartFreezerVideo>
     */
    public static function sendableVideos(Collection $videos): Collection
    {
        $pushed = $videos->reject(fn (SmartFreezerVideo $v) => $v->isEarlyFetch())->values();

        return $pushed->isNotEmpty() ? $pushed : $videos->values();
    }

    /**
     * A push for a session already sent. When mark1 sent it early, the push is the check that the
     * early links were the right ones: a video it names that was not sent means their naming moved
     * (a plugin or camera change), and is logged as a warning.
     */
    private function noteLatePush(SmartFreezerVideo $video, SmartFreezerRecognition $recognition): void
    {
        $early = $recognition->videos()->get()->filter(fn (SmartFreezerVideo $v) => $v->isEarlyFetch());
        if ($video->isEarlyFetch() || $early->isEmpty()) {
            Log::info('zijia video push after its recognition was sent', ['video' => $video->id, 'recognition' => $recognition->id]);

            return;
        }

        $bare = fn ($url) => strtok((string) $url, '?');
        $sent = $early->flatMap(fn (SmartFreezerVideo $v) => (array) $v->video_urls)->map($bare)->unique()->all();
        $missed = collect((array) $video->video_urls)->map($bare)->unique()->diff($sent)->values()->all();
        if ($missed !== []) {
            Log::warning('zijia push names videos the early fetch did not send', [
                'video' => $video->id, 'recognition' => $recognition->id, 'missed' => $missed, 'sent' => array_values($sent),
            ]);

            return;
        }
        Log::info('zijia push confirmed an early-fetched session', ['video' => $video->id, 'recognition' => $recognition->id]);
    }

    /**
     * The cabinet's candidate SKUs by barcode — every product on the freezer's LIVE planogram that
     * has one. `goodsList.sn` is the `productCode` of their algorithm library, which is the
     * 商品条形码 (barcode) a product was modelled with in their portal — NOT its 商品编码: proven
     * 2026-09-30 on 50001, where "CC-01" was refused ("存在sku商品为null") and the Basque
     * Cheesecake's barcode 9726436016148 was accepted and recognised. Put that barcode on the
     * mark1 product.
     * A freezer's channel row is its SKU (mark1 CLAUDE.md, "SKU-stocked machines"); a SKU
     * that left the planogram keeps a retired, inactive row, and is no candidate. Keyed by product id.
     *
     * @return array<int, string>
     */
    public function candidateCodes(?Vend $vend): array
    {
        if ($vend === null) {
            return [];
        }

        return Product::query()
            ->whereIn('id', VendChannel::where('vend_id', $vend->id)->where('is_active', true)->whereNotNull('product_id')->select('product_id'))
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->pluck('barcode', 'id')
            ->map(fn ($code) => trim((string) $code))
            ->all();
    }

    /**
     * Every session push, as one request: videos from all of them, the longest duration, and the
     * first device number / door any of them named.
     */
    private function buildRequest(SmartFreezerRecognition $recognition): RecognitionRequest
    {
        $pushes = self::sendableVideos($recognition->videos)->map(fn (SmartFreezerVideo $v) => ZijiaVideoPush::fromPayload((array) $v->payload));
        $identity = FreezerDeviceResolver::identityOf($recognition->vend);

        return new RecognitionRequest(
            deviceId: (string) ($pushes->pluck('deviceNo')->filter()->first() ?? $identity['deviceNo'] ?? $pushes->pluck('imei')->filter()->first() ?? $recognition->vend->code),
            tradeId: $recognition->trade_id,
            videoUrls: $this->videoUrls($recognition),
            goodsCodes: array_values($this->candidateCodes($recognition->vend)),
            modelIds: $this->client->modelIds(),
            notifyUrl: $this->client->notifyUrl(),
            videoDuration: (int) $pushes->max('videoDuration'),
            doorId: (int) ($pushes->pluck('doorId')->first() ?? 1),
        );
    }

    /**
     * barcode => product id: every candidate the algorithm was offered, plus any product carrying a
     * code it answered with that was not offered (goods can be put in the wrong freezer). The
     * verdict reads "nameable" from this map, so it must be the whole candidate set, not only the
     * codes that came back.
     *
     * @param  list<string>  $answered
     * @return array<string, int>
     */
    private function productByCode(Vend $vend, array $answered): array
    {
        $map = array_flip($this->candidateCodes($vend));
        $missing = array_values(array_diff($answered, array_keys($map)));
        if ($missing !== []) {
            foreach (Product::whereIn('barcode', $missing)->orderBy('id')->get(['id', 'barcode']) as $product) {
                $map[$product->barcode] ??= $product->id;
            }
        }

        return $map;
    }

    private function settleSeconds(): int
    {
        return max(0, (int) config('smart_freezer.zijia.algorithm.submit_delay_seconds'));
    }
}
