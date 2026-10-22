<?php

namespace App\Services\Sales;

use App\Models\VendTransaction;
use App\Services\HappyHour\HappyHourSales;
use App\Support\DispenseVerdict;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Units each machine sold under each campaign over the last N days — the "L7d: n pcs sold" line in
 * the campaign boxes on the Operation Dashboard (Brian, 2026-10-17).
 *
 * - Happy Hour: the campaign's TAGGED sales (HappyHourSales::dailyTotals, the campaign figures' rules).
 * - UI Setting campaign: sales whose `label_json` carries the campaign as "slug(id)" (the TRADE
 *   ingest writes it from `campaign_label_pivot`); units = the sale's qty. Settled, dispensed and
 *   not refunded, like the Happy Hour count.
 *
 * Three queries over (vend_id, transaction_datetime), whatever the page size (~280 ms for 100 machines on
 * prod, 2026-10-22), kept CACHE_SECONDS per set of machines: the Dashboard re-renders often.
 */
class MachineCampaignSales
{
    public const CACHE_SECONDS = 300;

    public function __construct(private readonly HappyHourSales $happyHourSales) {}

    /**
     * @param  iterable<int|string|null>  $vendIds
     * @return array{happy_hour: array<int, array<int, int>>, campaigns: array<int, array<int, int>>} [vend id][campaign id] => units
     */
    public function lastDays(iterable $vendIds, int $days = 7): array
    {
        $ids = collect($vendIds)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();
        if ($ids->isEmpty()) {
            return ['happy_hour' => [], 'campaigns' => []];
        }

        return Cache::remember('machine-campaign-sales:v1:'.$days.':'.md5($ids->implode(',')), self::CACHE_SECONDS,
            fn () => $this->compute($ids, $days));
    }

    /** @return array{happy_hour: array<int, array<int, int>>, campaigns: array<int, array<int, int>>} */
    private function compute(Collection $ids, int $days): array
    {
        $result = ['happy_hour' => [], 'campaigns' => []];
        $to = Carbon::now();
        $from = $to->copy()->subDays($days);

        foreach ($this->happyHourSales->dailyTotals($ids, $from, $to) as $row) {
            $result['happy_hour'][$row['vend_id']][$row['campaign_id']] = ($result['happy_hour'][$row['vend_id']][$row['campaign_id']] ?? 0) + $row['units'];
        }

        $labelled = DB::table('vend_transactions as t')
            ->leftJoin('vend_channel_errors as e', 'e.id', '=', 't.vend_channel_error_id')
            ->whereIn('t.vend_id', $ids)
            ->whereBetween('t.transaction_datetime', [$from, $to])
            ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where('t.is_refunded', 0)
            ->whereRaw('JSON_LENGTH(COALESCE(t.label_json, JSON_ARRAY())) > 0')
            ->where(fn ($q) => $q->whereNull('e.code')->orWhereIn('e.code', DispenseVerdict::SALE_CODES))
            ->get(['t.vend_id', 't.qty', 't.label_json']);
        foreach ($labelled as $sale) {
            $labels = json_decode((string) $sale->label_json, true);
            $campaignIds = collect(is_array($labels) ? $labels : [])
                ->map(fn ($label) => is_string($label) && preg_match('/\((\d+)\)$/', $label, $m) ? (int) $m[1] : null)
                ->filter()
                ->unique();
            foreach ($campaignIds as $campaignId) {
                $result['campaigns'][(int) $sale->vend_id][$campaignId] = ($result['campaigns'][(int) $sale->vend_id][$campaignId] ?? 0) + max(1, (int) $sale->qty);
            }
        }

        return $result;
    }
}
