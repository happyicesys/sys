<?php

namespace App\Services\SmartFreezer\Claude;

use App\Jobs\RunClaudeFreezerRecognition;
use App\Models\Product;
use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use App\Models\SmartFreezerRecognition;
use App\Models\VendChannel;
use App\Services\SmartFreezer\FreezerSaleLocator;
use App\Services\SmartFreezer\PersonCount;
use App\Services\SmartFreezer\RecognitionVerdict;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Imagick;
use Throwable;

/**
 * Claude's count of a freezer door session, beside Zijia's (Brian, 2026-11-13). The only writer of
 * `smart_freezer_claude_recognitions`.
 *
 *  1. schedule() — FreezerRecognitionService::open() names a door session; one Claude row per Zijia row, run
 *                  `delay_seconds` later so every camera and our own copy are in.
 *  2. run()      — claim (pending → running: a billed call is made once), frames, planogram, Claude, cost.
 *  3. evaluate() — Claude's count held against the paid sale with Zijia's own rule (RecognitionVerdict).
 *                  Re-run by `smart-freezer:claude-recognition --evaluate` for sales that land late.
 *
 * SHADOW ONLY. Nothing here charges, releases, refunds or moves stock, and nothing else reads these rows to
 * do so. Every stop is recorded in `status_reason`.
 */
class ClaudeShadowRecognitionService
{
    public function __construct(
        private readonly FreezerCounter $counter,
        private readonly DoorFrameSource $frames,
        private readonly FreezerSaleLocator $sales,
        private readonly ClaudeWorkedExamples $examples,
    ) {}

    public function enabledFor(SmartFreezerRecognition $recognition): bool
    {
        if (! config('smart_freezer.claude_recognition.enabled') || ! $this->counter->isConfigured() || $recognition->vend === null) {
            return false;
        }
        $vends = (array) config('smart_freezer.claude_recognition.vends');

        return $vends === [] || in_array((string) $recognition->vend->code, $vends, true);
    }

    /** Step 1. Never throws: a Claude hiccup must never stop Zijia's own check. */
    public function schedule(SmartFreezerRecognition $recognition): void
    {
        try {
            if (! $this->enabledFor($recognition)) {
                return;
            }
            $row = ClaudeRow::createOrFirst(
                ['smart_freezer_recognition_id' => $recognition->id],
                ['vend_id' => $recognition->vend_id, 'session_ref' => $recognition->session_ref, 'status' => ClaudeRow::STATUS_PENDING],
            );
            if ($row->wasRecentlyCreated) {
                RunClaudeFreezerRecognition::dispatch($row->id)
                    ->delay(now()->addSeconds(max(0, (int) config('smart_freezer.claude_recognition.delay_seconds'))));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** A row for a past session, made by a person (`--backfill`); null when it already has one. */
    public function openForBackfill(SmartFreezerRecognition $recognition): ?ClaudeRow
    {
        $row = ClaudeRow::createOrFirst(
            ['smart_freezer_recognition_id' => $recognition->id],
            ['vend_id' => $recognition->vend_id, 'session_ref' => $recognition->session_ref, 'status' => ClaudeRow::STATUS_PENDING],
        );

        return $row->wasRecentlyCreated ? $row : null;
    }

    /**
     * Step 2. Only a `pending` row is ever sent. $byPerson: run from the command, so the daily limit (a guard
     * against a flood of automatic calls) does not apply.
     */
    public function run(ClaudeRow $row, bool $byPerson = false): ClaudeRow
    {
        if ($row->status !== ClaudeRow::STATUS_PENDING) {
            return $row;
        }
        $recognition = $row->recognition()->with(['vend', 'videos'])->first();

        $stop = match (true) {
            $recognition === null => [ClaudeRow::STATUS_FAILED, 'its Zijia recognition is gone'],
            ! $this->counter->isConfigured() => [ClaudeRow::STATUS_FAILED, 'ANTHROPIC_API_KEY not set'],
            $recognition->status === SmartFreezerRecognition::STATUS_SKIPPED => [ClaudeRow::STATUS_SKIPPED, 'a door try that did not open (not sent to Zijia either)'],
            $recognition->vend === null => [ClaudeRow::STATUS_FAILED, 'freezer not recognised from the push'],
            ! $byPerson && $this->callsToday() >= (int) config('smart_freezer.claude_recognition.daily_limit') => [ClaudeRow::STATUS_SKIPPED, 'daily limit of Claude calls reached'],
            $this->overMonthlyBudget() => [ClaudeRow::STATUS_SKIPPED, sprintf('monthly Claude budget of US$%s reached', rtrim(rtrim(number_format((float) config('smart_freezer.claude_recognition.monthly_budget_usd'), 2, '.', ''), '0'), '.'))],
            default => null,
        };
        if ($stop !== null) {
            ClaudeRow::whereKey($row->id)->where('status', ClaudeRow::STATUS_PENDING)->update(['status' => $stop[0], 'status_reason' => $stop[1]]);

            return $row->refresh();
        }

        // Claim: only one worker may spend this call.
        $claimed = ClaudeRow::whereKey($row->id)->where('status', ClaudeRow::STATUS_PENDING)
            ->update(['status' => ClaudeRow::STATUS_RUNNING, 'status_reason' => null, 'started_at' => Carbon::now()]);
        if ($claimed !== 1) {
            return $row->refresh();
        }
        $row->refresh();
        $started = microtime(true);

        try {
            ['frames' => $frames, 'notes' => $notes] = $this->frames->framesFor($recognition);
            $catalog = $this->catalog($recognition);
            $examples = $frames === [] ? [] : $this->examples->for($recognition);
            $row->update([
                'vend_id' => $recognition->vend_id,
                'session_ref' => $recognition->session_ref,
                'model' => $this->counter->model(),
                'effort' => (string) config('smart_freezer.claude_recognition.effort'),
                'frames' => ['sent' => array_map(fn (DoorFrame $f) => $f->meta(), $frames), 'notes' => $notes, 'catalog' => array_map(fn (CatalogProduct $p) => $p->id, $catalog),
                    'examples' => array_map(fn (WorkedExample $e) => $e->recognitionId, $examples)],
            ]);
            if ($frames === []) {
                return $this->finish($row, ClaudeRow::STATUS_FAILED, 'no camera 3 or 4 video could be read'.($notes ? ': '.implode('; ', $notes) : ''), $started);
            }
            if ($catalog === []) {
                return $this->finish($row, ClaudeRow::STATUS_FAILED, 'the freezer has no product on its planogram', $started);
            }

            $count = $this->counter->count($frames, $catalog, (string) ($recognition->session_ref ?? $recognition->trade_id).' on machine '.$recognition->vend->code, $examples);

            $row->update($count->usage->columns() + [
                'items' => self::itemsOf($count),
                'answer' => $count->answer,
                'confidence' => $count->confidence,
                'needs_review' => $count->needsReview,
            ]);
            $this->finish($row, ClaudeRow::STATUS_DONE, null, $started);
        } catch (CountFailed $e) {
            if ($e->usage !== null) {
                $row->update($e->usage->columns());
            }

            return $this->finish($row, ClaudeRow::STATUS_FAILED, $e->getMessage(), $started);
        } catch (Throwable $e) {
            report($e);

            return $this->finish($row, ClaudeRow::STATUS_FAILED, 'error: '.$e->getMessage(), $started);
        }

        return $this->evaluate($row);
    }

    /**
     * Step 3, safe to re-run. Claude's count goes through Zijia's own verdict rule, so the two verdicts mean the
     * same thing. Every product it saw was on the list it was shown, so nothing is "incomplete"; something it
     * could not name makes the verdict `unrecognised`.
     */
    public function evaluate(ClaudeRow $row): ClaudeRow
    {
        if ($row->status !== ClaudeRow::STATUS_DONE) {
            return $row;
        }
        $recognition = $row->recognition()->with(['vend', 'vendTransaction'])->first();
        if ($recognition === null || $recognition->vend === null) {
            return $row;
        }
        if ($recognition->status === SmartFreezerRecognition::STATUS_SKIPPED) {
            $row->update(['status_reason' => 'not judged: '.($recognition->status_reason ?? 'a door try that did not open')]);

            return $row;
        }

        $sale = $recognition->vendTransaction
            ?? ($recognition->session_ref ? $this->sales->find($recognition->vend, $recognition->session_ref, $recognition->created_at) : null);
        if ($sale === null) {
            $row->update(['status_reason' => $recognition->session_ref ? 'sale not found yet for '.$recognition->session_ref : 'no session ref to find the sale by']);

            return $row;
        }

        $paid = $this->sales->paidUnits($sale);
        $taken = [];
        $byCode = [];
        foreach ((array) $row->items as $productId => $units) {
            $taken['p'.$productId] = (int) $units;
            $byCode['p'.$productId] = (int) $productId;
        }
        foreach (array_keys($paid) as $productId) {
            $byCode['p'.$productId] = (int) $productId;
        }
        $unknown = array_sum(array_column((array) (($row->answer ?? [])['unknown_items'] ?? []), 'taken'));
        if ($unknown > 0) {
            $taken['not on this freezer'] = (int) $unknown;
        }

        $verdict = RecognitionVerdict::compare($paid, $taken, $byCode);
        $row->update([
            'vend_transaction_id' => $sale->id,
            'verdict' => $verdict->outcome,
            'verdict_lines' => $verdict->lines,
            'status_reason' => null,
        ]);

        return $row;
    }

    /** Rows Claude answered whose sale was not found yet; run every 10 minutes. */
    public function evaluateAwaitingSale(int $days = 7): int
    {
        $judged = 0;
        ClaudeRow::where('status', ClaudeRow::STATUS_DONE)->whereNull('verdict')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('id')
            ->each(function (ClaudeRow $row) use (&$judged) {
                $judged += $this->evaluate($row)->verdict !== null ? 1 : 0;
            });

        return $judged;
    }

    /** What this calendar month's calls cost, in micro-US$. */
    public function spentThisMonthUsdMicros(): int
    {
        return (int) ClaudeRow::where('started_at', '>=', Carbon::now()->startOfMonth())->sum('cost_usd_micros');
    }

    /**
     * The hard stop (`monthly_budget_usd`), for a person's command too. A call in flight may finish past it by one
     * call's cost; 0 or less means no budget at all.
     */
    public function overMonthlyBudget(): bool
    {
        $budget = (float) config('smart_freezer.claude_recognition.monthly_budget_usd');

        return $budget > 0 && $this->spentThisMonthUsdMicros() >= (int) round($budget * 1_000_000);
    }

    /** Calls started today (billed or at least sent), against the daily limit. */
    public function callsToday(): int
    {
        return ClaudeRow::where('started_at', '>=', Carbon::today())->count();
    }

    /**
     * Claude, Zijia and a person on one session, as product id => units out of the cabinet. Zijia's is from its
     * verdict lines (null until it answered), the person's from Adjust Qty (null when nobody adjusted).
     *
     * @return array{claude: array<int, int>|null, zijia: array<int, int>|null, person: array<int|string, int>|null}
     */
    public static function counts(ClaudeRow $row, ?SmartFreezerRecognition $zijia, ?array $meta): array
    {
        $zijiaCount = null;
        if ($zijia?->verdict !== null) {
            $zijiaCount = [];
            foreach ((array) $zijia->verdict_lines as $line) {
                $key = PersonCount::key($line);
                $zijiaCount[$key] = ($zijiaCount[$key] ?? 0) + (int) ($line['taken'] ?? 0);
            }
            $zijiaCount = array_filter($zijiaCount);
        }
        $correction = $meta['correction'] ?? null;

        return [
            'claude' => $row->status === ClaudeRow::STATUS_DONE ? array_filter(array_map('intval', (array) $row->items)) : null,
            'zijia' => $zijiaCount,
            'person' => is_array($correction) ? array_filter(array_map('intval', (array) ($correction['basket'] ?? []))) : null,
        ];
    }

    /** @return array<int, int> */
    private static function itemsOf(FreezerCount $count): array
    {
        $items = $count->netByProduct();
        ksort($items);

        return $items;
    }

    private function finish(ClaudeRow $row, string $status, ?string $reason, float $started): ClaudeRow
    {
        $row->update([
            'status' => $status,
            'status_reason' => $reason === null ? null : mb_substr($reason, 0, 255),
            'completed_at' => Carbon::now(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $row;
    }

    /**
     * The freezer's live planogram: every active channel's product (a freezer channel row is its SKU), with the
     * channel codes it sits in and a small photo.
     *
     * @return list<CatalogProduct>
     */
    private function catalog(SmartFreezerRecognition $recognition): array
    {
        $sections = [];
        foreach (VendChannel::where('vend_id', $recognition->vend_id)->where('is_active', true)->whereNotNull('product_id')
            ->orderBy('code')->get(['product_id', 'code']) as $channel) {
            $sections[(int) $channel->product_id][] = (string) $channel->code;
        }
        if ($sections === []) {
            return [];
        }

        $px = (int) config('smart_freezer.claude_recognition.product_photo_px');

        return Product::withoutGlobalScopes()->with('thumbnail')->whereIn('id', array_keys($sections))->orderBy('name')->get()
            ->map(fn (Product $p) => new CatalogProduct(
                id: $p->id,
                name: (string) $p->name,
                code: $p->code ? (string) $p->code : null,
                sections: array_values(array_unique($sections[$p->id])),
                photoJpeg: self::photo($p->thumbnail?->full_url, $px),
            ))->values()->all();
    }

    private static function photo(?string $url, int $px): ?string
    {
        if (! is_string($url) || ! str_starts_with($url, 'http')) {
            return null;
        }
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful() || $response->body() === '') {
                return null;
            }
            $image = new Imagick;
            $image->readImageBlob($response->body());
            $image->setIteratorIndex(0);
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $image->thumbnailImage($px, $px, true);
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(85);
            $jpeg = $image->getImageBlob();
            $image->clear();

            return $jpeg;
        } catch (Throwable) {
            return null;
        }
    }
}
