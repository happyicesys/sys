<?php

namespace App\Services\HappyHour;

use App\Jobs\NudgeFreezerMenu;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
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
 *  3. about `lead_minutes` before a day's window, picks the lineup — rank 1 takes the first slot,
 *     rank 2 the next, cycling when the window holds more slots than `sku_count` — and freezes each
 *     slot's product and prices;
 *  4. as each slot starts, re-checks its SKU and swaps in the next-ranked one if it no longer
 *     qualifies.
 *
 * Every change nudges the freezer to re-read its menu, which carries the slots ahead of time; the
 * freezer switches between them on its own clock.
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

        HappyHourCampaign::query()->active()->with('vends')->get()
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
     * Takes a campaign's schedule back: slots not yet started are deleted, a live one ends now
     * (status cancelled; its sales are still recorded when it closes).
     *
     * @param  list<int>|null  $vendIds  null = every machine
     */
    public function cancel(HappyHourCampaign $campaign, CarbonInterface $now, ?array $vendIds = null): void
    {
        $scope = fn () => HappyHourSlot::query()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->when($vendIds !== null, fn ($q) => $q->whereIn('vend_id', $vendIds));

        $touched = $scope()->open()->where('ends_at', '>', $now)->distinct()->pluck('vend_id')->all();
        $scope()->open()->where('starts_at', '>', $now)->delete();
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
        if ($now->lt($windowStart->subMinutes((int) config('happy_hour.lead_minutes', 10))) || $now->gte($windowEnd)) {
            return [0, 0];
        }

        $existing = HappyHourSlot::query()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->where('vend_id', $vend->id)
            ->whereDate('slot_date', $now->toDateString())
            ->get()
            ->keyBy('position');

        // Every SKU that qualifies now, best first; ranked at most once per machine per run.
        $eligible = null;
        $eligibleOnce = function () use (&$eligible, $campaign, $vend, $now) {
            return $eligible ??= $this->ranker->lineup($campaign, $vend, $now);
        };

        $planned = 0;
        foreach ($campaign->slotTimesOn($now) as $time) {
            if ($time['ends_at']->lte($now) || $existing->has($time['position'])) {
                continue;
            }
            $picks = array_slice($eligibleOnce(), 0, max(1, $campaign->sku_count));
            if ($picks === []) {
                break;
            }
            $planned += (int) $this->createSlot($campaign, $vend, $time, $picks[$time['position'] % count($picks)]);
        }

        $swapped = $this->checkStartingSlot($campaign, $vend, $now, $eligibleOnce);

        if ($planned > 0 || $swapped > 0) {
            $this->nudge([$vend->id]);
        }

        return [$planned, $swapped];
    }

    /** @param  array{position: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable}  $time */
    private function createSlot(HappyHourCampaign $campaign, Vend $vend, array $time, HappyHourCandidate $pick): bool
    {
        try {
            HappyHourSlot::create([
                'happy_hour_campaign_id' => $campaign->id,
                'vend_id' => $vend->id,
                'slot_date' => $time['starts_at']->toDateString(),
                'position' => $time['position'],
                'starts_at' => $time['starts_at'],
                'ends_at' => $time['ends_at'],
                'status' => HappyHourSlot::STATUS_SCHEDULED,
                ...$this->pickColumns($campaign, $pick),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Another campaign already holds this machine at this start time: first come keeps it.
            Log::warning('happy hour slot clash', ['campaign' => $campaign->id, 'vend' => $vend->id, 'starts_at' => (string) $time['starts_at']]);

            return false;
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
     * As a slot starts (within the minute), its SKU must still qualify; otherwise the best-ranked SKU
     * not already on today's later slots takes its place, else any qualifying one, else it is cancelled.
     */
    private function checkStartingSlot(HappyHourCampaign $campaign, Vend $vend, CarbonImmutable $now, \Closure $eligibleOnce): int
    {
        $slot = HappyHourSlot::query()->open()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->where('vend_id', $vend->id)
            ->whereNull('checked_at')
            ->where('starts_at', '<=', $now->addMinute())
            ->where('ends_at', '>', $now)
            ->orderBy('starts_at')
            ->first();
        if ($slot === null) {
            return 0;
        }

        $eligible = $eligibleOnce();
        $still = collect($eligible)->first(fn (HappyHourCandidate $c) => $c->productId === $slot->product_id);
        if ($still !== null) {
            $slot->update(['checked_at' => $now]);
            // A slot starting is also a moment to refresh the freezer's copy of the schedule.
            $this->nudge([$vend->id]);

            return 0;
        }

        $taken = HappyHourSlot::query()->open()
            ->where('vend_id', $vend->id)
            ->where('id', '!=', $slot->id)
            ->where('ends_at', '>', $now)
            ->pluck('product_id')
            ->all();
        $picks = array_slice($eligible, 0, max(1, $campaign->sku_count));
        $replacement = collect($picks)->first(fn ($c) => ! in_array($c->productId, $taken, true)) ?? ($picks[0] ?? null);
        if ($replacement === null) {
            $slot->update(['status' => HappyHourSlot::STATUS_CANCELLED, 'checked_at' => $now, 'ends_at' => $now->max($slot->starts_at)]);

            return 1;
        }
        $slot->update(['checked_at' => $now, ...$this->pickColumns($campaign, $replacement)]);

        return 1;
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

    /** Records each finished slot's units, takings and discount given, from the settled sales in it. */
    private function closeEnded(CarbonImmutable $now): int
    {
        $slots = HappyHourSlot::query()
            ->whereNull('units_sold')
            ->where('ends_at', '<=', $now)
            ->get();
        foreach ($slots as $slot) {
            $sales = DB::table('vend_transaction_items as i')
                ->join('vend_transactions as t', 't.id', '=', 'i.vend_transaction_id')
                ->where('t.vend_id', $slot->vend_id)
                ->where('t.transaction_datetime', '>=', $slot->starts_at)
                ->where('t.transaction_datetime', '<', $slot->ends_at)
                ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
                ->where('i.product_id', $slot->product_id)
                ->where(fn ($q) => $q->whereNull('i.vend_channel_error_code')->orWhereIn('i.vend_channel_error_code', DispenseVerdict::SALE_CODES))
                ->where(fn ($q) => $q->whereNull('i.is_refunded')->orWhere('i.is_refunded', 0))
                ->selectRaw('COUNT(*) AS units, COALESCE(SUM(i.unit_price_amount), 0) AS revenue')
                ->first();
            $units = (int) ($sales->units ?? 0);
            $revenue = (int) ($sales->revenue ?? 0);
            $slot->update([
                'status' => $slot->status === HappyHourSlot::STATUS_SCHEDULED ? HappyHourSlot::STATUS_ENDED : $slot->status,
                'units_sold' => $units,
                'revenue_cents' => $revenue,
                'discount_cents' => max(0, $units * $slot->original_price - $revenue),
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
