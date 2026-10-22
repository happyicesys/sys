<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourSlot;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
 * transaction holding a Happy Hour unit, counted whole (HappyHourSales::dailyRelatedTotals). `non_hh_*_pct`
 * is the share of those transactions that is NOT the Happy Hour units — what Happy Hour pulled along.
 *
 * Only machines the viewer may see are counted ($operatorId = OperatorVendFilterScope::viewerOperatorId()):
 * a HIPL campaign may span operators.
 */
final class HappyHourCampaignStats
{
    public function __construct(private readonly HappyHourSales $sales) {}

    /**
     * Totals for one window. $oneDay: the window is a single day (the last 24 h), so a machine counts
     * once even when the window spans two dates.
     *
     * @return array<string, int|float|null> revenue, discount, units, machines, machine_days, avg_daily_units_per_machine + the related figures
     */
    public function window(int $campaignId, CarbonInterface $from, CarbonInterface $to, ?int $operatorId, bool $oneDay = false): array
    {
        $ran = $this->ranOn($campaignId, $from, $to, $operatorId);
        $rows = $this->rows($campaignId, $ran, $from, $to);
        $machines = $ran->pluck('vend_id')->unique()->count();
        $machineDays = $oneDay ? $machines : $ran->count();

        return $this->figures($rows, $this->relatedRows($campaignId, $ran, $from, $to), $machineDays) + [
            'machines' => $machines,
            'machine_days' => $machineDays,
        ];
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
        $ran = $this->ranOn($campaignId, $from, $to, $operatorId);
        $machinesByDay = $ran->countBy('day');
        $byDay = $this->rows($campaignId, $ran, $from, $to)->groupBy('day');
        $relatedByDay = $this->relatedRows($campaignId, $ran, $from, $to)->groupBy('day');

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $date = $d->toDateString();
            $machines = (int) ($machinesByDay[$date] ?? 0);
            $out[] = ['date' => $date, 'machines' => $machines]
                + $this->figures($byDay->get($date, collect()), $relatedByDay->get($date, collect()), $machines);
        }

        return $out;
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
            'related_multiple' => (int) $related->sum('multiple'),
            'related_revenue' => $relatedRevenue,
            'related_units' => $relatedUnits,
            'related_units_per_machine' => $machineDays > 0 ? round($relatedUnits / $machineDays, 2) : null,
            'non_hh_revenue_pct' => $pct($revenue, $relatedRevenue),
            'non_hh_units_pct' => $pct($units, $relatedUnits),
        ];
    }

    /**
     * The (machine, date) pairs on which this campaign ran a slot overlapping [$from, $to].
     *
     * @return Collection<int, object{vend_id: int, day: string}>
     */
    private function ranOn(int $campaignId, CarbonInterface $from, CarbonInterface $to, ?int $operatorId): Collection
    {
        return DB::table('happy_hour_slots as s')
            ->join('vends as v', 'v.id', '=', 's.vend_id')
            ->where('s.happy_hour_campaign_id', $campaignId)
            ->where('s.status', '!=', HappyHourSlot::STATUS_CANCELLED)
            ->where('s.starts_at', '<', $to)
            ->where('s.ends_at', '>', $from)
            ->when($operatorId, fn ($q) => $q->where('v.operator_id', $operatorId))
            ->distinct()
            ->get(['s.vend_id', DB::raw('DATE(s.starts_at) AS day')])
            ->map(fn ($r) => (object) ['vend_id' => (int) $r->vend_id, 'day' => (string) $r->day]);
    }

    /** @return Collection<int, array<string, mixed>> this campaign's related transactions, per machine and day */
    private function relatedRows(int $campaignId, Collection $ran, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return collect($this->sales->dailyRelatedTotals($ran->pluck('vend_id')->unique(), $from, $to))
            ->where('campaign_id', $campaignId)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> this campaign's daily rows on the machines that ran it */
    private function rows(int $campaignId, Collection $ran, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return collect($this->sales->dailyTotals($ran->pluck('vend_id')->unique(), $from, $to))
            ->where('campaign_id', $campaignId)
            ->values();
    }
}
