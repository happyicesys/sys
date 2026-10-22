<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourSlot;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * How well a Happy Hour campaign is doing (Brian, 2026-10-16: "so that we can monitor the
 * effectiveness of this feature"): the campaign page's "Last 24h / Last 30d" figures and its daily graph.
 *
 * Sales, discount and units are the campaign's tagged sales (HappyHourSales::dailyTotals — the slot
 * results' rules: settled, not refunded, a sale code). "Avg daily qty / VM" is units per machine-day:
 * a machine counts for each day it ran at least one slot of the campaign (a cancelled slot never ran),
 * so a campaign that started 8 days ago is not divided by 30, and a machine that joined yesterday is
 * not counted for the days before.
 *
 * Beside them, the "Related" figures (Daniel, 2026-10-22: HH is the bridge to the rest of the basket): every
 * transaction holding a Happy Hour unit, counted whole: each single HH sale (already a whole transaction in
 * dailyTotals) plus each tagged basket (HappyHourSales::dailyBasketTotals). `non_hh_*_pct` is the share of
 * those transactions that is NOT the Happy Hour units — what Happy Hour pulled along.
 *
 * A window or a graph range costs ~2 s on prod (2026-10-22: the sales queries walk every sale of the
 * campaign's ~220 machines, since `happy_hour_slot_id` has no index), so both are kept CACHE_SECONDS per
 * (campaign, viewer operator, range rounded to CACHE_SECONDS): the page shows figures up to 5 minutes old.
 *
 * Only machines the viewer may see are counted ($operatorId = OperatorVendFilterScope::viewerOperatorId()):
 * a HIPL campaign may span operators.
 */
final class HappyHourCampaignStats
{
    public const CACHE_SECONDS = 300;

    public function __construct(private readonly HappyHourSales $sales) {}

    /**
     * Totals for one window. $oneDay: the window is a single day (the last 24 h), so a machine counts
     * once even when the window spans two dates.
     *
     * @return array<string, int|float|null> revenue, discount, units, machines, machine_days, avg_daily_units_per_machine + the related figures
     */
    public function window(int $campaignId, CarbonInterface $from, CarbonInterface $to, ?int $operatorId, bool $oneDay = false): array
    {
        return $this->remember(['window', $campaignId, $operatorId, $from, $to, (int) $oneDay], function () use ($campaignId, $from, $to, $operatorId, $oneDay) {
            $ran = $this->ranOn($campaignId, $from, $to, $operatorId);
            [$rows, $related] = $this->rowsAndRelated($campaignId, $ran, $from, $to);
            $machines = $ran->pluck('vend_id')->unique()->count();
            $machineDays = $oneDay ? $machines : $ran->count();

            return $this->figures($rows, $related, $machineDays) + [
                'machines' => $machines,
                'machine_days' => $machineDays,
            ];
        });
    }

    /**
     * One row per date in [$fromDay, $toDay], every date present (zeros where nothing ran or sold).
     *
     * @return list<array<string, int|float|string|null>> date, machines + figures()
     */
    public function daily(int $campaignId, CarbonInterface $fromDay, CarbonInterface $toDay, ?int $operatorId): array
    {
        $from = Carbon::parse($fromDay)->startOfDay();
        $to = Carbon::parse($toDay)->endOfDay();

        return $this->remember(['daily', $campaignId, $operatorId, $from, $to], function () use ($campaignId, $from, $to, $operatorId) {
            $ran = $this->ranOn($campaignId, $from, $to, $operatorId);
            $machinesByDay = $ran->countBy('day');
            [$rows, $related] = $this->rowsAndRelated($campaignId, $ran, $from, $to);
            $byDay = $rows->groupBy('day');
            $relatedByDay = $related->groupBy('day');

            $out = [];
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $date = $d->toDateString();
                $machines = (int) ($machinesByDay[$date] ?? 0);
                $out[] = ['date' => $date, 'machines' => $machines]
                    + $this->figures($byDay->get($date, collect()), $relatedByDay->get($date, collect()), $machines);
            }

            return $out;
        });
    }

    /**
     * Cached CACHE_SECONDS; the key rounds each time down to CACHE_SECONDS, so "now" in a window does not
     * make every page load a miss.
     *
     * @param  list<int|string|CarbonInterface|null>  $parts
     */
    private function remember(array $parts, \Closure $compute): array
    {
        $key = 'hh-campaign-stats:v1:'.implode(':', array_map(fn ($p) => $p instanceof CarbonInterface
            ? intdiv($p->getTimestamp(), self::CACHE_SECONDS)
            : (string) ($p ?? 'all'), $parts));

        return Cache::remember($key, self::CACHE_SECONDS, $compute);
    }

    /**
     * The figures of one window or day. The related total never reads below the Happy Hour part (a basket
     * refunded in full leaves related but its promo lines may still count), so "other" is never negative.
     *
     * @return array<string, int|float|null>
     */
    private function figures(Collection $rows, Collection $related, int $machineDays): array
    {
        $units = (int) $rows->sum('units');
        $revenue = (int) $rows->sum('revenue');
        $relatedRevenue = max($revenue, (int) $related->sum('revenue'));
        $relatedUnits = max($units, (int) $related->sum('units'));
        $pct = fn (int $part, int $whole) => $whole > 0 ? round(($whole - $part) * 100 / $whole, 1) : null;

        return [
            'revenue' => $revenue,
            'discount' => (int) $rows->sum('discount'),
            'units' => $units,
            'avg_daily_units_per_machine' => $machineDays > 0 ? round($units / $machineDays, 2) : null,
            'related_transactions' => (int) $related->sum('transactions'),
            'related_multiple' => (int) $related->where('multiple', true)->sum('transactions'),
            'related_revenue' => $relatedRevenue,
            'related_units' => $relatedUnits,
            'related_units_per_machine' => $machineDays > 0 ? round($relatedUnits / $machineDays, 2) : null,
            'non_hh_revenue_pct' => $pct($revenue, $relatedRevenue),
            'non_hh_units_pct' => $pct($units, $relatedUnits),
        ];
    }

    /**
     * The (machine, date) pairs on which this campaign ran a slot overlapping [$from, $to]. The slot_date
     * bound (a slot is under a day long, so it starts on the day before $from at the earliest) lets the
     * (campaign, slot_date) index range-scan instead of reading every slot the campaign ever had.
     *
     * @return Collection<int, object{vend_id: int, day: string}>
     */
    private function ranOn(int $campaignId, CarbonInterface $from, CarbonInterface $to, ?int $operatorId): Collection
    {
        return DB::table('happy_hour_slots as s')
            ->when($operatorId, fn ($q) => $q->join('vends as v', 'v.id', '=', 's.vend_id')->where('v.operator_id', $operatorId))
            ->where('s.happy_hour_campaign_id', $campaignId)
            ->whereBetween('s.slot_date', [$from->copy()->subDay()->toDateString(), $to->toDateString()])
            ->where('s.status', '!=', HappyHourSlot::STATUS_CANCELLED)
            ->where('s.starts_at', '<', $to)
            ->where('s.ends_at', '>', $from)
            ->distinct()
            ->get(['s.vend_id', DB::raw('DATE(s.starts_at) AS day')])
            ->map(fn ($r) => (object) ['vend_id' => (int) $r->vend_id, 'day' => (string) $r->day]);
    }

    /**
     * This campaign's Happy Hour rows (dailyTotals) and its related transactions on the machines that ran it.
     * Related = every single HH sale (a whole transaction on its own, read from the same rows) + every tagged
     * basket (dailyBasketTotals) — three queries in all.
     *
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function rowsAndRelated(int $campaignId, Collection $ran, CarbonInterface $from, CarbonInterface $to): array
    {
        $vendIds = $ran->pluck('vend_id')->unique();
        $rows = collect($this->sales->dailyTotals($vendIds, $from, $to))->where('campaign_id', $campaignId)->values();
        $singles = $rows->where('multiple', false)
            ->map(fn (array $r) => ['day' => $r['day'], 'multiple' => false, 'transactions' => $r['transactions'], 'revenue' => $r['revenue'], 'units' => $r['units']]);
        $baskets = collect($this->sales->dailyBasketTotals($vendIds, $from, $to))->where('campaign_id', $campaignId)
            ->map(fn (array $r) => ['day' => $r['day'], 'multiple' => true, 'transactions' => $r['transactions'], 'revenue' => $r['revenue'], 'units' => $r['units']]);

        return [$rows, $singles->concat($baskets)->values()];
    }
}
