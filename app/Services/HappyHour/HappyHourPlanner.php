<?php

namespace App\Services\HappyHour;

use App\Jobs\NudgeFreezerMenu;
use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\Vend;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only writer of `happy_hour_slots`. Run every minute (`happy-hour:run`), and right after a
 * campaign is saved, it keeps each bound freezer's schedule current:
 *
 *  1. ends a live slot early when its SKU has sold out (the screen must not advertise an empty basket),
 *     and takes back slots that no longer belong (campaign paused or deleted, machine left or ineligible);
 *  2. picks each slot's SKU just in time — `lead_minutes` before the slot starts, from that machine's
 *     stock at that moment (Brian, 2026-10-11: "derived in real time ... based on each of the vends
 *     during that moment"), taking turns among the top `sku_count` of the live ranking — and freezes
 *     the slot's product and prices;
 *  3. as each slot starts, re-checks its SKU and swaps in the next one in turn if it no longer
 *     qualifies (a new slot row: the board that missed the nudge still names the old one);
 *  4. closes slots whose time is over and records what they sold.
 *
 * Every change nudges the machine to re-read its menu (a freezer's `/menu`, a vending board's
 * `/thumbnails` — NudgeFreezerMenu picks the frame), which carries the live and the next slot; the
 * machine switches between them on its own clock.
 *
 * Failsafe (HappyHourPause): a machine bound to any UI Setting gets no slot (Happy Hour is ignored
 * there, Brian 2026-10-15), and a slot already live there ends at the next minute run; Happy Hour
 * resumes by itself once the machine is unbound from its UI Setting. The pause is read once per run for every bound machine (one query).
 *
 * One writer at a time (audit 2026-10-30): the minute run and the work after a campaign save
 * (FollowHappyHourCampaignSave) each hold the `happy-hour-plan` lock (exclusively()). Before it, a save
 * re-planning while the run was half-way through gave three machines two slot groups at once (2026-10-15
 * 21:57), and a campaign deleted mid-run could still get new slots. Each step of the run is isolated, so
 * one bad slot never stops the rest; finished slots are totalled last, a few hundred a run.
 */
class HappyHourPlanner
{
    /** Seconds a freezer waits after a nudge, so a burst of slot writes costs one menu fetch. */
    private const NUDGE_DELAY_SECONDS = 5;

    public function __construct(private readonly HappyHourRanker $ranker) {}

    /** The lock every slot writer holds: the minute run and the work after a campaign save. */
    public const LOCK = 'happy-hour-plan';

    /** Longest a holder may keep the lock if its process dies mid-way. */
    private const LOCK_SECONDS = 300;

    /** Finished slots totalled per run at most (each is 2–3 queries); the rest wait for the next minute. */
    private const CLOSE_PER_RUN = 300;

    /**
     * Runs $work holding the planner lock, waiting up to $waitSeconds for it.
     *
     * @throws LockTimeoutException when the lock stays taken
     */
    public function exclusively(\Closure $work, int $waitSeconds): mixed
    {
        return Cache::lock(self::LOCK, self::LOCK_SECONDS)->block($waitSeconds, $work);
    }

    /** @return array{closed: int, sold_out: int, paused: int, orphaned: int, group_joined: int, group_left: int, paused_machines: int, planned: int, swapped: int} */
    public function run(CarbonInterface $now, int $waitSeconds = 45): array
    {
        try {
            // A save's re-plan holds the lock for up to ~30 s on the whole fleet; slots are picked 2 min
            // ahead, so a skipped minute is caught up by the next one.
            // The clock moves on while the run waits for the lock: the run works at the moment it got it.
            $asked = microtime(true);

            return $this->exclusively(fn () => $this->runLocked(
                CarbonImmutable::instance($now)->addSeconds((int) floor(microtime(true) - $asked))
            ), $waitSeconds);
        } catch (LockTimeoutException) {
            Log::warning('happy hour run skipped: the planner lock is taken');

            return ['closed' => 0, 'sold_out' => 0, 'paused' => 0, 'orphaned' => 0, 'group_joined' => 0,
                'group_left' => 0, 'paused_machines' => 0, 'planned' => 0, 'swapped' => 0];
        }
    }

    /** @return array{closed: int, sold_out: int, paused: int, orphaned: int, group_joined: int, group_left: int, paused_machines: int, planned: int, swapped: int} */
    private function runLocked(CarbonImmutable $now): array
    {
        $stats = ['closed' => 0, 'sold_out' => 0, 'paused' => 0, 'orphaned' => 0, 'group_joined' => 0,
            'group_left' => 0, 'paused_machines' => 0, 'planned' => 0, 'swapped' => 0];
        try {
            [$stats['group_joined'], $stats['group_left']] = $this->followGroups($now);
        } catch (\Throwable $e) {
            report($e);
        }

        $campaigns = HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->active()->with('vends')->get();
        $paused = HappyHourPause::forVends($campaigns->flatMap(fn ($c) => $c->vends->pluck('id')), $now);
        $stats['paused_machines'] = count($paused);

        $stats['paused'] = $this->safely(fn () => $this->endPaused($now));
        $stats['orphaned'] = $this->safely(fn () => $this->endOrphaned($campaigns, $now));
        $stats['sold_out'] = $this->safely(fn () => $this->endSoldOut($now));

        $campaigns->each(function (HappyHourCampaign $campaign) use ($now, $paused, &$stats) {
            foreach ($campaign->vends as $vend) {
                try {
                    [$planned, $swapped] = $this->planVend($campaign, $vend, $now, $paused);
                    $stats['planned'] += $planned;
                    $stats['swapped'] += $swapped;
                } catch (\Throwable $e) {
                    // One machine's bad data must never stop the other machines' schedules.
                    report($e);
                }
            }
        });

        // Last: totals are bookkeeping, nothing a screen waits for.
        $stats['closed'] = $this->safely(fn () => $this->closeEnded($now));

        return $stats;
    }

    /** One step of the run; a failure is reported and the run goes on. */
    private function safely(\Closure $step): int
    {
        try {
            return (int) $step();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Live groups ("All VM - Big Board", "All Smart Freezers") follow their members before planning: a
     * machine that now qualifies joins, one that stopped leaves with its slots taken back. Never stops
     * the run. Also called right after a campaign save or delete, so a machine picked by name leaves
     * (or rejoins) a group campaign at once.
     *
     * @return array{0: int, 1: int} machines joined, machines left
     */
    public function followGroups(CarbonInterface $now): array
    {
        $now = CarbonImmutable::instance($now);
        try {
            $changes = app(HappyHourMachineGroups::class)->syncActive();
        } catch (\Throwable $e) {
            report($e);

            return [0, 0];
        }
        $joined = $left = 0;
        foreach ($changes as $campaignId => $change) {
            if ($change['removed'] !== []) {
                $campaign = HappyHourCampaign::query()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->find($campaignId);
                $campaign && $this->cancel($campaign, $now, $change['removed']);
            }
            // The bear comes on / goes off on a machine with no UI Setting.
            HappyHourWelcomeFallback::nudgeUnbound([...$change['added'], ...$change['removed']]);
            $joined += count($change['added']);
            $left += count($change['removed']);
        }
        if ($joined || $left) {
            Log::info('happy hour groups followed', ['changes' => $changes]);
        }

        return [$joined, $left];
    }

    /**
     * Plans every bound machine of one campaign at once (after a save), instead of waiting a minute.
     * Callers hold the planner lock (FollowHappyHourCampaignSave).
     */
    public function planCampaign(HappyHourCampaign $campaign, CarbonInterface $now): void
    {
        if (! $campaign->is_active || $campaign->trashed()) {
            return;
        }
        $now = CarbonImmutable::instance($now);
        $vends = $campaign->vends()->get();
        $paused = HappyHourPause::forVends($vends->pluck('id'), $now);
        foreach ($vends as $vend) {
            try {
                $this->planVend($campaign, $vend, $now, $paused);
            } catch (\Throwable $e) {
                report($e);
            }
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

    /**
     * @param  array<int, mixed>  $paused  HappyHourPause::forVends() of this run: such a machine gets no slot
     * @return array{0: int, 1: int} slots planned, slots swapped
     */
    private function planVend(HappyHourCampaign $campaign, Vend $vend, CarbonImmutable $now, array $paused = []): array
    {
        if (! HappyHourPricing::supports($vend)) {
            return [0, 0];
        }
        // Every ranking of this run for this machine, ranked at most once.
        $eligible = null;
        $eligibleOnce = function () use (&$eligible, $campaign, $vend, $now) {
            return $eligible ??= $this->ranker->lineup($campaign, $vend, $now);
        };

        // Today, and tomorrow once the lead reaches past midnight: the 00:00 slot is picked at 23:58 like
        // any other, instead of a minute late (audit 2026-10-30: every day began at 00:01).
        $planned = 0;
        $inWindow = false;
        $horizon = $now->addMinutes($this->leadMinutes());
        foreach (array_unique([$now->toDateString(), $horizon->toDateString()]) as $date) {
            $day = $this->planDay($campaign, $vend, $now, CarbonImmutable::parse($date), $paused, $eligibleOnce);
            $inWindow = $inWindow || $day !== null;
            $planned += (int) $day;
        }
        if (! $inWindow) {
            return [0, 0];
        }

        $swapped = $this->checkStartingSlot($campaign, $vend, $now, $eligibleOnce);

        if ($planned > 0 || $swapped > 0) {
            $this->nudge([$vend->id]);
        }

        return [$planned, $swapped];
    }

    /**
     * Picks the slot times of one day that come up within the lead.
     *
     * @param  array<int, mixed>  $paused  HappyHourPause::forVends() of this run
     * @return int|null slots planned; null when the day's window is not open (or the machine is paused)
     */
    private function planDay(HappyHourCampaign $campaign, Vend $vend, CarbonImmutable $now, CarbonImmutable $day, array $paused, \Closure $eligibleOnce): ?int
    {
        if (! $campaign->runsOn($day)) {
            return null;
        }
        [$windowStart, $windowEnd] = $campaign->windowOn($day);
        if ($now->lt($windowStart->subMinutes($this->leadMinutes())) || $now->gte($windowEnd)) {
            return null;
        }
        if (isset($paused[$vend->id])) {
            $this->rememberPause([$vend->id], $now);

            return null;
        }
        // A slot time that began while the machine was paused is not resumed part-way: Happy Hour
        // comes back at the next slot (Brian, 2026-10-12).
        $pausedAt = Cache::get(self::pausedAtKey($vend->id));

        // That day's slots in any state: a time is planned only while a slot that still offers (or offered)
        // the price covers it. Matching by time, not position, keeps an edited window from mistaking
        // this morning's finished slots for its own (2026-10-09, 2009 test).
        $existing = HappyHourSlot::query()
            ->where('happy_hour_campaign_id', $campaign->id)
            ->where('vend_id', $vend->id)
            ->whereDate('slot_date', $day->toDateString())
            ->get();

        $planned = 0;
        $perSlot = $this->perSlot($campaign);
        $horizon = $now->addMinutes($this->leadMinutes());
        foreach ($campaign->slotTimesOn($day) as $time) {
            if ($time['ends_at']->lte($now)) {
                continue;
            }
            $covering = $this->covering($existing, $time, $now);
            if ($covering->count() >= $perSlot) {
                continue;
            }
            if ($pausedAt !== null && $time['starts_at']->lt($now) && $time['starts_at']->timestamp <= (int) $pausedAt) {
                continue;
            }
            // Just in time: a later slot is picked when it comes up, from the stock it will see then.
            if ($time['starts_at']->gt($horizon)) {
                break;
            }
            $picks = $this->picksFor($campaign, $eligibleOnce(), $existing, $time['position'], $time['starts_at'],
                $perSlot - $covering->count(), $covering->pluck('product_id')->all());
            if ($picks === []) {
                break;
            }
            // A slot time already under way (a mid-slot edit, a sold-out SKU) gets the rest of its time.
            $time['starts_at'] = $time['starts_at']->max($now->startOfMinute());
            foreach ($picks as [$pick, $isPriority]) {
                if ($slot = $this->createSlot($campaign, $vend, $time, $pick, $isPriority)) {
                    $planned++;
                    $existing = $existing->push($slot);
                }
            }
        }

        return $planned;
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
     * Prioritize (Brian, 2026-10-16: "sku will be selected for HH on every other hour"): while the campaign lists
     * prioritized SKUs, every other slot time of the day — positions 0, 2, 4 … from the window's start, so
     * 00:00, 02:00 … on a 24 h window of 60 min slots — offers them first.
     */
    private function isPriorityTime(HappyHourCampaign $campaign, int $position): bool
    {
        return $campaign->priorityProductIds() !== [] && $position % 2 === 0;
    }

    /**
     * The SKUs for one slot time, each with whether it was picked as a priority SKU. On a priority time the
     * machine's prioritized SKUs come first, taking turns among themselves, and the places left are filled from
     * the normal ranking; on the other times only the normal ranking runs, so a prioritized SKU is on offer every
     * other slot, not every slot. A machine holding none of them runs the normal ranking at every time.
     *
     * @param  list<HappyHourCandidate>  $eligible  best first (prioritized SKUs first, HappyHourRanker)
     * @param  \Illuminate\Support\Collection<int, HappyHourSlot|null>  $existing  today's slots on this machine
     * @param  list<int>  $taken  products already on offer at this time (never picked twice)
     * @return list<array{0: HappyHourCandidate, 1: bool}>
     */
    private function picksFor(HappyHourCampaign $campaign, array $eligible, $existing, int $position, CarbonImmutable $startsAt, int $count, array $taken = [], ?int $skipSlotId = null): array
    {
        $priority = array_values(array_filter($eligible, fn (HappyHourCandidate $c) => $c->priority));
        $normal = array_values(array_filter($eligible, fn (HappyHourCandidate $c) => ! $c->priority));
        $perSlot = $this->perSlot($campaign);

        $picks = [];
        if ($count > 0 && $priority !== [] && $this->isPriorityTime($campaign, $position)) {
            $history = $existing->filter(fn (?HappyHourSlot $s) => $s !== null && $s->is_priority);
            foreach ($this->inTurn($priority, count($priority), min(count($priority), $perSlot), $history, $startsAt, $count, $taken, $skipSlotId) as $pick) {
                $picks[] = [$pick, true];
                $taken[] = $pick->productId;
            }
        }
        if ($count - count($picks) > 0) {
            $history = $existing->filter(fn (?HappyHourSlot $s) => $s !== null && ! $s->is_priority);
            $top = array_slice($normal, 0, max(1, $campaign->sku_count));
            foreach ($this->inTurn($top, min($campaign->sku_count, count($normal)), $perSlot, $history, $startsAt, $count - count($picks), $taken, $skipSlotId) as $pick) {
                $picks[] = [$pick, false];
            }
        }

        return $picks;
    }

    /**
     * The SKUs whose turn it is in one pool. Of the pool's `$top` (the live top `sku_count` for the normal
     * ranking, every qualifying prioritized SKU for the priority one), the best ones that were not in that
     * pool's last `$turnSize - $perSlot` SKU-slots before this time — so with 4 SKUs, 2 at a time, ranks 1+2
     * run, then 3+4, then 1+2 again, while each turn still follows the stock of the moment. When too few are
     * left (1 SKU, or few qualifying) the best ones not already on offer repeat.
     *
     * @param  list<HappyHourCandidate>  $top  best first
     * @param  \Illuminate\Support\Collection<int, HappyHourSlot>  $history  today's slots of this pool on this machine
     * @param  list<int>  $taken  products already on offer at this time (never picked twice)
     * @return list<HappyHourCandidate>
     */
    private function inTurn(array $top, int $turnSize, int $perSlot, $history, CarbonImmutable $startsAt, int $count, array $taken = [], ?int $skipSlotId = null): array
    {
        $top = array_values(array_filter($top, fn (HappyHourCandidate $c) => ! in_array($c->productId, $taken, true)));
        if ($top === [] || $count < 1) {
            return [];
        }
        $recent = $history
            ->filter(fn (HappyHourSlot $s) => $s->id !== $skipSlotId
                && $s->status !== HappyHourSlot::STATUS_CANCELLED && $s->starts_at->lt($startsAt))
            ->sortBy([fn ($a, $b) => $b->starts_at <=> $a->starts_at, fn ($a, $b) => $a->rank <=> $b->rank])
            ->take(max(0, $turnSize - $perSlot))
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
    private function createSlot(HappyHourCampaign $campaign, Vend $vend, array $time, HappyHourCandidate $pick, bool $isPriority = false, ?CarbonImmutable $checkedAt = null): ?HappyHourSlot
    {
        try {
            return HappyHourSlot::create([
                'happy_hour_campaign_id' => $campaign->id,
                'vend_id' => $vend->id,
                'slot_date' => $time['slot_date'] ?? $time['starts_at']->toDateString(),
                'position' => $time['position'],
                'starts_at' => $time['starts_at'],
                'ends_at' => $time['ends_at'],
                'planned_ends_at' => $time['ends_at'],
                'status' => HappyHourSlot::STATUS_SCHEDULED,
                'checked_at' => $checkedAt,
                ...$this->pickColumns($campaign, $pick, $isPriority),
            ]);
        } catch (UniqueConstraintViolationException) {
            // This SKU already has a slot on this machine at this start time. One this campaign cancelled (a
            // swap, a save) is taken up again — same SKU, so a board's sales under its id stay right; it
            // would otherwise block the pick for a minute or two (audit review 2026-10-30).
            $revived = HappyHourSlot::query()
                ->where('vend_id', $vend->id)->where('starts_at', $time['starts_at'])->where('product_id', $pick->productId)
                ->where('happy_hour_campaign_id', $campaign->id)->where('status', HappyHourSlot::STATUS_CANCELLED)
                ->first();
            if ($revived !== null) {
                $revived->update([
                    'slot_date' => $time['slot_date'] ?? $time['starts_at']->toDateString(),
                    'position' => $time['position'],
                    'ends_at' => $time['ends_at'],
                    'planned_ends_at' => $time['ends_at'],
                    'status' => HappyHourSlot::STATUS_SCHEDULED,
                    'checked_at' => $checkedAt,
                    'units_sold' => null,
                    ...$this->pickColumns($campaign, $pick, $isPriority),
                ]);

                return $revived;
            }
            // Otherwise first come keeps it.
            Log::warning('happy hour slot clash', ['campaign' => $campaign->id, 'vend' => $vend->id, 'product' => $pick->productId, 'starts_at' => (string) $time['starts_at']]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function pickColumns(HappyHourCampaign $campaign, HappyHourCandidate $pick, bool $isPriority = false): array
    {
        return [
            'product_id' => $pick->productId,
            'is_priority' => $isPriority,
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
     * that is not already on offer then takes its place (see picksFor: a priority slot tries the prioritized SKUs first), else that slot is cancelled.
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
            // A priority slot's SKU must still be a qualifying prioritized SKU, a normal slot's a normal one
            // (a prioritized SKU runs every other slot only).
            $still = collect($eligible)->first(fn (HappyHourCandidate $c) => $c->productId === $slot->product_id
                && $c->priority === (bool) $slot->is_priority);
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
            [$replacement, $isPriority] = $this->picksFor($campaign, $eligible, $today, (int) $slot->position,
                CarbonImmutable::instance($slot->starts_at), 1, $taken, $slot->id)[0] ?? [null, false];
            // The old SKU's slot ends (cancelled, keeping its planned end) and a replacement is a NEW row:
            // a board that missed the nudge still sells the old SKU under the old slot id, and that sale is
            // still tagged to it (audit 2026-10-30; the row used to be rewritten in place).
            $plannedEnd = CarbonImmutable::instance($slot->taggableUntil());
            $slot->update(['status' => HappyHourSlot::STATUS_CANCELLED, 'checked_at' => $now, 'ends_at' => $now->max($slot->starts_at)]);
            $today = $today->map(fn (HappyHourSlot $s) => $s->id === $slot->id ? $slot : $s);
            if ($replacement !== null) {
                $new = $this->createSlot($campaign, $vend, [
                    'slot_date' => $slot->slot_date->toDateString(),
                    'position' => (int) $slot->position,
                    'starts_at' => CarbonImmutable::instance($slot->starts_at)->max($now->startOfMinute()),
                    'ends_at' => $plannedEnd,
                ], $replacement, $isPriority, $now);
                $new && $today->push($new);
            }
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
        if ($live->isEmpty()) {
            return 0;
        }
        // One stock read for every live slot (a fleet of vending machines runs ~one slot each).
        $stock = DB::table('vend_channels')
            ->whereIn('vend_id', $live->pluck('vend_id')->unique()->values())
            ->whereIn('product_id', $live->pluck('product_id')->unique()->values())
            ->where('is_active', 1)
            ->groupBy('vend_id', 'product_id')
            ->selectRaw('vend_id, product_id, SUM(GREATEST(qty, 0)) AS qty')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->vend_id}:{$row->product_id}" => (int) $row->qty]);
        $ended = 0;
        foreach ($live as $slot) {
            $qty = $stock["{$slot->vend_id}:{$slot->product_id}"] ?? 0;
            if ($qty < 1) {
                HappyHourSlot::whereKey($slot->id)->update(['ends_at' => $now, 'status' => HappyHourSlot::STATUS_SOLD_OUT]);
                $this->nudge([$slot->vend_id]);
                $ended++;
            }
        }

        return $ended;
    }

    /**
     * The failsafe, every minute: a machine newly bound to a UI Setting has its Happy Hour taken
     * back at once — a live slot ends now (cancelled; its sales are still recorded when it closes), one
     * picked but not started is removed — and the machine is nudged, so it drops the promo within about
     * a minute. Only machines with an open slot are checked (one query).
     */
    private function endPaused(CarbonImmutable $now): int
    {
        $open = HappyHourSlot::query()->open()->where('ends_at', '>', $now)->get(['id', 'vend_id', 'starts_at']);
        if ($open->isEmpty()) {
            return 0;
        }
        $paused = array_keys(HappyHourPause::forVends($open->pluck('vend_id'), $now));
        if ($paused === []) {
            return 0;
        }
        $slots = $open->whereIn('vend_id', $paused);
        $notStarted = $slots->filter(fn (HappyHourSlot $s) => $s->starts_at->gt($now))->pluck('id')->all();
        $live = $slots->filter(fn (HappyHourSlot $s) => $s->starts_at->lte($now))->pluck('id')->all();
        if ($notStarted !== []) {
            HappyHourSlot::whereIn('id', $notStarted)->delete();
        }
        if ($live !== []) {
            HappyHourSlot::whereIn('id', $live)->update(['ends_at' => $now, 'status' => HappyHourSlot::STATUS_CANCELLED]);
        }
        $this->rememberPause($paused, $now);
        Log::info('happy hour ignored: machine bound to a UI Setting', ['vends' => $paused, 'slots_ended' => count($live), 'slots_removed' => count($notStarted)]);
        $this->nudge($paused);

        return $slots->count();
    }

    /**
     * Records each finished slot's units, takings and discount from the settled sales tagged with
     * it (`HappyHourSales`). A TRADE tagged after this clears `units_sold` again, so it is recounted.
     */
    private function closeEnded(CarbonImmutable $now): int
    {
        // Oldest first, a few hundred a run: a bulk re-open (happy-hour:reprice-vending-baskets) is worked
        // off over a few minutes instead of holding up one run.
        $slots = HappyHourSlot::query()
            ->whereNull('units_sold')
            ->where('ends_at', '<=', $now)
            ->orderBy('ends_at')
            ->limit(self::CLOSE_PER_RUN)
            ->get();
        $sales = app(HappyHourSales::class);
        $closed = 0;
        foreach ($slots as $slot) {
            try {
                $result = $sales->results($slot);
                $slot->update([
                    'status' => $slot->status === HappyHourSlot::STATUS_SCHEDULED ? HappyHourSlot::STATUS_ENDED : $slot->status,
                    'units_sold' => $result['units'],
                    'revenue_cents' => $result['revenue'],
                    'discount_cents' => $result['discount'],
                ]);
                $closed++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $closed;
    }

    /**
     * Takes back open slots that no longer belong: their campaign is paused or deleted, or the machine left
     * it or can no longer run Happy Hour (deactivated, app downgraded). Saves and group changes cancel their
     * own slots, but a slot written by a run that was already under way, or a hand-picked machine that
     * stopped qualifying, had nothing to end it (audit 2026-10-30). Not started: removed; live: ends now.
     *
     * @param  \Illuminate\Support\Collection<int, HappyHourCampaign>  $campaigns  the active campaigns of this run, with their machines
     */
    private function endOrphaned($campaigns, CarbonImmutable $now): int
    {
        $open = HappyHourSlot::query()->open()->where('ends_at', '>', $now)
            ->get(['id', 'happy_hour_campaign_id', 'vend_id', 'starts_at']);
        if ($open->isEmpty()) {
            return 0;
        }
        $members = [];
        foreach ($campaigns as $campaign) {
            foreach ($campaign->vends as $vend) {
                $members["{$campaign->id}:{$vend->id}"] = HappyHourPricing::supports($vend);
            }
        }
        $orphans = $open->reject(fn (HappyHourSlot $s) => $members["{$s->happy_hour_campaign_id}:{$s->vend_id}"] ?? false);
        if ($orphans->isEmpty()) {
            return 0;
        }

        $notStarted = $orphans->filter(fn (HappyHourSlot $s) => $s->starts_at->gt($now))->pluck('id')->all();
        $live = $orphans->filter(fn (HappyHourSlot $s) => $s->starts_at->lte($now))->pluck('id')->all();
        if ($notStarted !== []) {
            HappyHourSlot::whereIn('id', $notStarted)->delete();
        }
        if ($live !== []) {
            HappyHourSlot::whereIn('id', $live)->update(['ends_at' => $now, 'status' => HappyHourSlot::STATUS_CANCELLED]);
        }
        Log::info('happy hour slots taken back: campaign or machine no longer runs them', [
            'slots_ended' => $live, 'slots_removed' => $notStarted,
        ]);
        $this->nudge($orphans->pluck('vend_id')->unique()->values()->all());

        return $orphans->count();
    }

    private static function pausedAtKey(int $vendId): string
    {
        return "happy-hour-paused-at:{$vendId}";
    }

    /** @param  list<int>  $vendIds */
    private function rememberPause(array $vendIds, CarbonImmutable $now): void
    {
        foreach ($vendIds as $vendId) {
            Cache::put(self::pausedAtKey((int) $vendId), $now->timestamp, $now->addDay());
        }
    }

    /** @param  list<int>  $vendIds */
    private function nudge(array $vendIds): void
    {
        foreach (array_unique($vendIds) as $vendId) {
            // The key lives exactly as long as the delay: a change after the job has read the menu sends a
            // new nudge (it used to live 4× the delay, so a change 5–20 s later sent none).
            if (Cache::add("happy-hour-nudge:{$vendId}", true, self::NUDGE_DELAY_SECONDS)) {
                NudgeFreezerMenu::dispatch((int) $vendId)->delay(now()->addSeconds(self::NUDGE_DELAY_SECONDS))->afterCommit();
            }
        }
    }
}
