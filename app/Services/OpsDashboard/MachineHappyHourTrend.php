<?php

namespace App\Services\OpsDashboard;

use App\Models\HappyHourSlot;
use App\Services\HappyHour\HappyHourSales;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One machine's Happy Hour trend for the Operation Dashboard's campaign box (Brian, 2026-10-23): the daily HH
 * sold qty — L30d and L7d as a daily average over the days it ran Happy Hour, yesterday and today as they are —
 * and its L7d Non-HH %.
 *
 * The same rules as the campaign page (HappyHourCampaignStats), for one machine and every campaign it ran:
 *  - HH qty / sales = the TAGGED sales (HappyHourSales::dailyTotals).
 *  - Related sales = every single HH sale + every tagged basket counted whole (dailyBasketTotals), never below
 *    the HH part; Non-HH % = (related − HH) ÷ related, in $ — how much other spending Happy Hour brought along.
 *
 * Windows start at midnight like TransactionMix: today, yesterday, today + 6, today + 29. A day it ran Happy Hour
 * = a slot that was not cancelled started that day. Three indexed queries, run by SyncVendTransactionTotalsJson
 * after each sale and stored as `happy_hour` in the totals; null for a machine with no slot in 30 days.
 */
final class MachineHappyHourTrend
{
    public function __construct(private readonly HappyHourSales $sales) {}

    /**
     * @return array{daily_units: array<string, int|float|null>, ran_days: array<string, int>, units: array<string, int>, non_hh_pct_7d: ?float, hh_revenue_7d: int, related_revenue_7d: int}|null
     */
    public function forVend(int $vendId, ?CarbonInterface $now = null): ?array
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();
        $today = $now->copy()->startOfDay();
        $from = [
            'today' => $today,
            'yesterday' => $today->copy()->subDay(),
            'seven_days' => $today->copy()->subDays(6),
            'thirty_days' => $today->copy()->subDays(29),
        ];

        $ranOn = DB::table('happy_hour_slots')
            ->where('vend_id', $vendId)
            ->where('status', '!=', HappyHourSlot::STATUS_CANCELLED)
            ->whereBetween('starts_at', [$from['thirty_days'], $now])
            ->distinct()
            ->pluck(DB::raw('DATE(starts_at)'))
            ->map(fn ($day) => (string) $day);
        if ($ranOn->isEmpty()) {
            return null;
        }

        $rows = collect($this->sales->dailyTotals([$vendId], $from['thirty_days'], $now));
        $inWindow = fn (Collection $items, string $window) => $items->filter(fn ($item) => $this->inWindow(
            is_array($item) ? $item['day'] : $item, $window, $from
        ));

        $units = [];
        $ranDays = [];
        $daily = [];
        foreach ($from as $window => $start) {
            $units[$window] = (int) $inWindow($rows, $window)->sum('units');
            $ranDays[$window] = $inWindow($ranOn, $window)->count();
            // A day it did not run Happy Hour has no figure (the line breaks), not a zero.
            $daily[$window] = $ranDays[$window] === 0 ? null
                : (in_array($window, ['today', 'yesterday'], true) ? $units[$window] : round($units[$window] / $ranDays[$window], 1));
        }

        $week = $inWindow($rows, 'seven_days');
        $hhRevenue = (int) $week->sum('revenue');
        $baskets = collect($this->sales->dailyBasketTotals([$vendId], $from['seven_days'], $now));
        $related = max($hhRevenue, (int) $week->where('multiple', false)->sum('revenue') + (int) $baskets->sum('revenue'));

        return [
            'daily_units' => $daily,
            'ran_days' => $ranDays,
            'units' => $units,
            'non_hh_pct_7d' => $related > 0 ? round(($related - $hhRevenue) * 100 / $related, 1) : null,
            'hh_revenue_7d' => $hhRevenue,
            'related_revenue_7d' => $related,
        ];
    }

    /** @param  array<string, Carbon>  $from */
    private function inWindow(string $day, string $window, array $from): bool
    {
        return $window === 'yesterday'
            ? $day === $from['yesterday']->toDateString()
            : $day >= $from[$window]->toDateString();
    }
}
