<?php

namespace App\Services\HappyHour;

use App\Jobs\NudgeFreezerMenu;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\Vend;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only writer of `happy_hour_slots`. Run every minute (`happy-hour:run`), and right after a
 * campaign is saved, it keeps each bound freezer's schedule current:
 *
 *  1. closes slots whose time is over and records what they sold;
 *  2. ends a live slot early when its SKU has sold out (the screen must not advertise an empty basket);
 *  3. picks each slot's SKU just in time — `lead_minutes` before the slot starts, from that machine's
 *     stock at that moment (Brian, 2026-10-11: "derived in real time ... based on each of the vends
 *     during that moment"), taking turns among the top `sku_count` of the live ranking — and freezes
 *     the slot's product and prices;
 *  4. as each slot starts, re-checks its SKU and swaps in the next one in turn if it no longer
 *     qualifies.
 *
 * Every change nudges the freezer to re-read its menu, which carries the live and the next slot;
 * the freezer switches between them on its own clock.
 */
class HappyHourPlanner
{
    /** Seconds a freezer waits after a nudge, so a burst of slot writes costs one menu fetch. */
    private const NUDGE_DELAY_SECONDS = 5;

    public function __construct(private readonly HappyHourRanker $ranker) {}

    /** @return array{closed: int, sold_out: int, planned: int, swapped: int} */
    public function run(CarbonInterface $now): array
    {
        $now = CarbonImmutable::instance($now);
        $stats = ['closed' => $this->closeEnded($now), 'sold_out' => $this->endSoldOut($now), 'planned' => 0, 'swapped' => 0];

        HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->active()->with('vends')->get()
            ->each(function (HappyHourCampaign $campaign) use ($now, &$stats) {
                foreach ($campaign->vends as $vend) {
                    try {
                        [$planned, $swapped] = $this->planVend($campaign, $vend, $now);
                        $stats['planned'] += $planned;
                        $stats['swapped'] += $swapped;
                    } catch (\Throwable $e) {
                        // One machine's bad data must never stop the other machines' schedules.
                        report($e);
                    }
                }
            });

        return $stats;
    }

    /** Plans every bound machine of one campaign at once (after a save), instead of waiting a minute. */
    public function planCampaign(HappyHourCampaign $campaign, CarbonInterface $now): void
    {
        if (! $campaign->is_active) {
            return;
        }
        $now = CarbonImmutable::instance($now);
        foreach ($campaign->vends()->get() as $vend) {
            $this->planVend($campaign, $vend, $now);
        }
    }

    /**
     * Takes a campaign's schedule back: slots not yet started (or started under a minute ago) are
     * deleted, a live one ends now (status cancelled; its sales are still recorded when it closes).
     *
     * @param  list<int>|null  $vendIds  null = every machine
     */
    public function cancel(HappyHourCampaign $campaign, CarbonInterface $now, ?array $vendIds = null): void
    {
        $scope = fn () => HappyHourSlot::query()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->when($vendIds !== null, fn ($q) => $q->whereIn('vend_id', $vendIds));

        $touched = $scope()->open()->where('ends_at', '>', $now)->distinct()->pluck('vend_id')->all();
        // Not started, or started under a minute ago: removed, so the replacement can take that start
        // time (unique per machine) — two saves in one minute otherwise left the machine with no slot.
        $scope()->open()->where('starts_at', '>', $now->copy()->subMinute())->delete();
        $scope()->open()->where('starts_at', '<=', $now)->where('ends_at', '>', $now)
            ->update(['ends_at' => $now, 'status' => HappyHourSlot::STATUS_CANCELLED]);

        $this->nudge($touched);
    }

    /** @return array{0: int, 1: int} slots planned, slots swapped */
    private function planVend(HappyHourCampaign $campaign, Vend $vend, CarbonImmutable $now): array
    {
        if (! HappyHourPricing::supports($vend) || ! $campaign->runsOn($now)) {
            return [0, 0];
        }
        [$windowStart, $windowEnd] = $campaign->windowOn($now);
        if ($now->lt($windowStart->subMinutes($this->leadMinutes())) || $now->gte($windowEnd)) {
            return [0, 0];
        }

        // Today's slots in any state: a time is planned only while a slot that still offers (or offered)
        // the price covers it. Matching by time, not position, keeps an edited window from mistaking
        // this morning's finished slots for its own (2026-10-09, 2009 test).
        $existing = HappyHourSlot::query()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->where('vend_id', $vend->id)
            ->whereDate('slot_date', $now->toDateString())
            ->get();

        // Every SKU that qualifies now, best first; ranked at most once per machine per run.
        $eligible = null;
        $eligibleOnce = function () use (&$eligible, $campaign, $vend, $now) {
            return $eligible ??= $this->ranker->lineup($campaign, $vend, $now);
        };

        $planned = 0;
        $perSlot = $this->perSlot($campaign);
        $horizon = $now->addMinutes($this->leadMinutes());
        foreach ($campaign->slotTimesOn($now) as $time) {
            if ($time['ends_at']->lte($now)) {
                continue;
            }
            $covering = $this->covering($existing, $time, $now);
            if ($covering->count() >= $perSlot) {
                continue;
            }
            // Just in time: a later slot is picked when it comes up, from the stock it will see then.
            if ($time['starts_at']->gt($horizon)) {
                break;
            }
            $picks = $this->nextInTurn($campaign, $eligibleOnce(), $existing, $time['starts_at'],
                $perSlot - $covering->count(), $covering->pluck('product_id')->all());
            if ($picks === []) {
                break;
            }
            // A slot time already under way (a mid-slot edit, a sold-out SKU) gets the rest of its time.
            $time['starts_at'] = $time['starts_at']->max($now->startOfMinute());
            foreach ($picks as $pick) {
                if ($slot = $this->createSlot($campaign, $vend, $time, $pick)) {
                    $planned++;
                    $existing = $existing->push($slot);
                }
            }
        }

        $swapped = $this->checkStartingSlot($campaign, $vend, $now, $eligibleOnce);

        if ($planned > 0 || $swapped > 0) {
            $this->nudge([$vend->id]);
        }

        return [$planned, $swapped];
    }

    private function leadMinutes(): int
    {
        return max(1, (int) config('happy_hour.lead_minutes', 2));
    }

    /** How many SKUs are on offer together in one slot: `skus_per_slot`, never more than `sku_count`. */
    private function perSlot(HappyHourCampaign $campaign): int
    {
        return max(1, min((int) $campaign->skus_per_slot, (int) $campaign->sku_count));
    }

    /**
     * The SKUs whose turn it is. Of the live top `sku_count`, the best ones that were not in today's
     * last `sku_count - skus_per_slot` SKU-slots before this time — so with 4 SKUs, 2 at a time, ranks
     * 1+2 run, then 3+4, then 1+2 again, while each turn still follows the stock of the moment. When
     * too few are left (1 SKU, or few qualifying) the best ones not already on offer repeat.
     *
     * @param  list<HappyHourCandidate>  $eligible  best first
     * @param  \Illuminate\Support\Collection<int, HappyHourSlot|null>  $existing  today's slots on this machine
     * @param  list<int>  $taken  products already on offer at this time (never picked twice)
     * @return list<HappyHourCandidate>
     */
    private function nextInTurn(HappyHourCampaign $campaign, array $eligible, $existing, CarbonImmutable $startsAt, int $count, array $taken = [], ?int $skipSlotId = null): array
    {
        $top = array_values(array_filter(
            array_slice($eligible, 0, max(1, $campaign->sku_count)),
            fn (HappyHourCandidate $c) => ! in_array($c->productId, $taken, true),
        ));
        if ($top === [] || $count < 1) {
            return [];
        }
        $recent = $existing
            ->filter(fn (?HappyHourSlot $s) => $s !== null && $s->id !== $skipSlotId
                && $s->status !== HappyHourSlot::STATUS_CANCELLED && $s->starts_at->lt($startsAt))
            ->sortBy([fn ($a, $b) => $b->starts_at <=> $a->starts_at, fn ($a, $b) => $a->rank <=> $b->rank])
            ->take(max(0, min($campaign->sku_count, count($eligible)) - $this->perSlot($campaign)))
            ->pluck('product_id')
            ->all();

        $fresh = array_filter($top, fn (HappyHourCandidate $c) => ! in_array($c->productId, $recent, true));
        $rest = array_filter($top, fn (HappyHourCandidate $c) => in_array($c->productId, $recent, true));

        return array_slice([...$fresh, ...$rest], 0, $count);
    }

    /**
     * The slots, not cancelled or sold out, that cover the rest of this slot time. An ended slot counts
     * only for the time it ran, so a cancelled or sold-out SKU leaves its place free for another.
     *
     * @param  \Illuminate\Support\Collection<int, HappyHourSlot|null>  $existing
     * @param  array{position: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $time
     * @return \Illuminate\Support\Collection<int, HappyHourSlot>
     */
    private function covering($existing, array $time, CarbonImmutable $now)
    {
        $from = $time['starts_at']->max($now);

        return $existing->filter(fn (?HappyHourSlot $s) => $s !== null
            && $s->status !== HappyHourSlot::STATUS_CANCELLED
            && $s->status !== HappyHourSlot::STATUS_SOLD_OUT
            && $s->starts_at->lt($time['ends_at'])
            && $s->ends_at->gt($from));
    }

    /** @param  array{position: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $time */
    private function createSlot(HappyHourCampaign $campaign, Vend $vend, array $time, HappyHourCandidate $pick): ?HappyHourSlot
    {
        try {
            return HappyHourSlot::create([
                'happy_hour_campaign_id' => $campaign->id,
                'vend_id' => $vend->id,
                'slot_date' => $time['starts_at']->toDateString(),
                'position' => $time['position'],
                'starts_at' => $time['starts_at'],
                'ends_at' => $time['ends_at'],
                'status' => HappyHourSlot::STATUS_SCHEDULED,
                ...$this->pickColumns($campaign, $pick),
            ]);
        } catch (UniqueConstraintViolationException) {
            // This SKU already has a slot on this machine at this start time: first come keeps it.
            Log::warning('happy hour slot clash', ['campaign' => $campaign->id, 'vend' => $vend->id, 'product' => $pick->productId, 'starts_at' => (string) $time['starts_at']]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function pickColumns(HappyHourCampaign $campaign, HappyHourCandidate $pick): array
    {
        return [
            'product_id' => $pick->productId,
            'original_price' => $pick->originalPrice,
            'promo_price' => $pick->promoPrice,
            'discount_pct' => $campaign->discount_pct,
            'rank' => $pick->rank,
            'qty_at_pick' => $pick->qty,
            'capacity_at_pick' => $pick->capacity,
            'avg_daily_sales' => round($pick->avgDailySales(), 3),
            'days_of_cover' => $pick->daysOfCover(),
        ];
    }

    /**
     * As slots start (within the minute), each SKU must still qualify; otherwise the next SKU in turn
     * that is not already on offer then takes its place (see nextInTurn), else that slot is cancelled.
     */
    private function checkStartingSlot(HappyHourCampaign $campaign, Vend $vend, CarbonImmutable $now, \Closure $eligibleOnce): int
    {
        $starting = HappyHourSlot::query()->open()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->where('vend_id', $vend->id)
            ->whereNull('checked_at')
            ->where('starts_at', '<=', $now->addMinute())
            ->where('ends_at', '>', $now)
            ->orderBy('starts_at')->orderBy('rank')
            ->get();
        if ($starting->isEmpty()) {
            return 0;
        }

        $eligible = $eligibleOnce();
        $swapped = 0;
        $today = null;
        foreach ($starting as $slot) {
            $still = collect($eligible)->first(fn (HappyHourCandidate $c) => $c->productId === $slot->product_id);
            if ($still !== null) {
                $slot->update(['checked_at' => $now]);

                continue;
            }

            $today ??= HappyHourSlot::query()
                ->where('happy_hour_campaign_id', $campaign->id)
                ->where('vend_id', $vend->id)
                ->whereDate('slot_date', $slot->slot_date)
                ->get();
            // Products the other slots of this time already offer.
            $taken = $today->filter(fn (HappyHourSlot $s) => $s->id !== $slot->id
                && $s->status === HappyHourSlot::STATUS_SCHEDULED
                && $s->starts_at->lt($slot->ends_at) && $s->ends_at->gt($slot->starts_at))
                ->pluck('product_id')->all();
            $replacement = $this->nextInTurn($campaign, $eligible, $today, CarbonImmutable::instance($slot->starts_at), 1, $taken, $slot->id)[0] ?? null;
            if ($replacement === null) {
                $slot->update(['status' => HappyHourSlot::STATUS_CANCELLED, 'checked_at' => $now, 'ends_at' => $now->max($slot->starts_at)]);
            } else {
                $slot->update(['checked_at' => $now, ...$this->pickColumns($campaign, $replacement)]);
            }
            $today = $today->map(fn (HappyHourSlot $s) => $s->id === $slot->id ? $slot : $s);
            $swapped++;
        }
        // Slots starting are also a moment to refresh the freezer's copy of the schedule.
        $this->nudge([$vend->id]);

        return $swapped;
    }

    /** A live slot whose SKU has run out stops advertising it now. */
    private function endSoldOut(CarbonImmutable $now): int
    {
        $live = HappyHourSlot::query()->open()
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->get(['id', 'vend_id', 'product_id']);
        $ended = 0;
        foreach ($live as $slot) {
            $qty = (int) DB::table('vend_channels')
                ->where('vend_id', $slot->vend_id)
                ->where('product_id', $slot->product_id)
                ->where('is_active', 1)
                ->sum(DB::raw('GREATEST(qty, 0)'));
            if ($qty < 1) {
                HappyHourSlot::whereKey($slot->id)->update(['ends_at' => $now, 'status' => HappyHourSlot::STATUS_SOLD_OUT]);
                $this->nudge([$slot->vend_id]);
                $ended++;
            }
        }

        return $ended;
    }

    /**
     * Records each finished slot's units, takings and discount from the settled sales tagged with
     * it (`HappyHourSales`). A TRADE tagged after this clears `units_sold` again, so it is recounted.
     */
    private function closeEnded(CarbonImmutable $now): int
    {
        $slots = HappyHourSlot::query()
            ->whereNull('units_sold')
            ->where('ends_at', '<=', $now)
            ->get();
        $sales = app(HappyHourSales::class);
        foreach ($slots as $slot) {
            $result = $sales->results($slot);
            $slot->update([
                'status' => $slot->status === HappyHourSlot::STATUS_SCHEDULED ? HappyHourSlot::STATUS_ENDED : $slot->status,
                'units_sold' => $result['units'],
                'revenue_cents' => $result['revenue'],
                'discount_cents' => $result['discount'],
            ]);
        }

        return $slots->count();
    }

    /** @param  list<int>  $vendIds */
    private function nudge(array $vendIds): void
    {
        foreach (array_unique($vendIds) as $vendId) {
            if (Cache::add("happy-hour-nudge:{$vendId}", true, self::NUDGE_DELAY_SECONDS * 4)) {
                NudgeFreezerMenu::dispatch((int) $vendId)->delay(now()->addSeconds(self::NUDGE_DELAY_SECONDS))->afterCommit();
            }
        }
    }
}
