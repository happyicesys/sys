<?php

namespace App\Console\Commands;

use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Services\HappyHour\HappyHourPricing;
use App\Support\DispenseVerdict;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Failed lines of a multiple purchase keep their price (Brian, 2026-10-21: "we already receive the
 * payment, so payment will not reduced because of error"). Until then the ingest stored them at 0. This
 * prices the lines stored before the fix the way the ingest now does — the channel's price, discounted
 * when the line was a Happy Hour unit — over the last --days (default 31): the window refund claims are
 * made in, and one where today's channel price is still what was charged. Revenue, GP and sold qty do not
 * move: every one of them leaves a failed line out by its error code, not by its price. Dry run unless
 * --apply; a line with no channel price is left at 0 and counted. Idempotent: it only touches lines at 0.
 */
class BackfillFailedItemPrices extends Command
{
    protected $signature = 'sales:backfill-failed-item-prices
        {--days=31 : how far back, by sale time}
        {--apply : write the prices (default: report only)}';

    protected $description = 'Give failed lines of multiple purchases their price instead of 0';

    public function handle(): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')))->startOfDay();
        $lines = DB::table('vend_transaction_items as i')
            ->join('vend_transactions as t', 't.id', '=', 'i.vend_transaction_id')
            ->leftJoin('vend_channels as vc', 'vc.id', '=', 'i.vend_channel_id')
            ->where('t.is_multiple', 1)
            ->where('t.transaction_datetime', '>=', $since)
            ->where('i.unit_price_amount', 0)
            ->whereRaw(DispenseVerdict::sqlFault('i.vend_channel_error_code'))
            ->get(['i.id', 'i.happy_hour_slot_id', 'vc.amount', 'vc.amount2']);

        $slots = HappyHourSlot::query()->whereIn('id', $lines->pluck('happy_hour_slot_id')->filter()->unique())
            ->get(['id', 'happy_hour_campaign_id', 'discount_pct'])->keyBy('id');
        $steps = HappyHourCampaign::withTrashed()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)
            ->whereIn('id', $slots->pluck('happy_hour_campaign_id')->unique())->pluck('price_step_cents', 'id');

        $prices = [];
        $noPrice = 0;
        foreach ($lines as $line) {
            $list = (int) ($line->amount ?: $line->amount2 ?: 0);
            if ($list <= 0) {
                $noPrice++;

                continue;
            }
            $slot = $line->happy_hour_slot_id ? $slots->get($line->happy_hour_slot_id) : null;
            $prices[(int) $line->id] = $slot
                ? (HappyHourPricing::promoPrice($list, (int) $slot->discount_pct, (int) ($steps[$slot->happy_hour_campaign_id] ?? 1)) ?? $list)
                : $list;
        }

        if ($this->option('apply')) {
            foreach (array_chunk($prices, 500, true) as $chunk) {
                DB::transaction(function () use ($chunk) {
                    foreach ($chunk as $id => $price) {
                        DB::table('vend_transaction_items')->where('id', $id)->where('unit_price_amount', 0)->update(['unit_price_amount' => $price]);
                    }
                });
            }
        }

        $verb = $this->option('apply') ? 'Priced' : 'Would price';
        $this->info(sprintf('%s %d failed line(s) since %s, $%s in all; %d left at 0 (no channel price).',
            $verb, count($prices), $since->toDateString(), number_format(array_sum($prices) / 100, 2), $noPrice));
        if (! $this->option('apply')) {
            $this->comment('Dry run — nothing written. Run again with --apply.');
        }

        return self::SUCCESS;
    }
}
