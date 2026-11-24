<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Services\Sales\DirtyDayRegistry;
use App\Support\Sales\ChannelChargedPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Basket lines on machines that follow the Site's pricing were stored at the VMC board price (Brian, 2026-11-24,
 * IDN 10027: Watermelon Berry stored at 32,000 in a 60,000 basket where the machine charged 28,000). This re-prices
 * them the way the ingest now does (ChannelChargedPrice: the server price the machine holds, else the board price)
 * — but ONLY where the re-priced lines then add up exactly to what the customer paid and the stored ones did not.
 * That sum is the proof the prices are the ones charged; a basket whose prices moved since, or whose lines were
 * stored at 0 or repriced for Happy Hour, is left alone and counted. Vending machines only (freezer and chiller lines
 * carry their own prices). Dry run unless --apply; the days touched are rebuilt by the 02:00 dirty-day pass.
 */
class BackfillServerPriceBasketLines extends Command
{
    protected $signature = 'sales:backfill-server-price-basket-lines
        {--days=31 : how far back, by sale time}
        {--vend= : one machine code only}
        {--apply : write the prices (default: report only)}';

    protected $description = 'Re-price basket lines on server-price machines to what the machine charged';

    public function handle(DirtyDayRegistry $dirtyDays): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')))->startOfDay();

        $vends = Vend::query()->withoutGlobalScopes()
            ->where('is_using_server_price', true)
            ->where(fn ($q) => $q->whereNull('machine_type')->orWhere('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE))
            ->when($this->option('vend'), fn ($q, $code) => $q->bareCode($code))
            ->with('vendChannels', 'productMapping.productMappingItems')
            ->get();

        $rows = [];
        $totals = ['fixed' => 0, 'unprovable' => 0, 'happy_hour' => 0, 'lines' => 0];
        $days = [];

        foreach ($vends as $vend) {
            $served = ChannelChargedPrice::serverPrices($vend);
            if ($served === []) {
                continue;
            }

            $baskets = DB::table('vend_transactions')
                ->where('vend_id', $vend->id)
                ->where('is_multiple', 1)
                ->where('transaction_datetime', '>=', $since)
                ->get(['id', 'amount', 'transaction_datetime']);
            if ($baskets->isEmpty()) {
                continue;
            }

            $items = DB::table('vend_transaction_items')
                ->whereIn('vend_transaction_id', $baskets->pluck('id'))
                ->get(['id', 'vend_transaction_id', 'vend_channel_code', 'unit_price_amount', 'happy_hour_slot_id'])
                ->groupBy('vend_transaction_id');

            $row = ['code' => $vend->code, 'fixed' => 0, 'unprovable' => 0, 'happy_hour' => 0, 'lines' => 0];
            foreach ($baskets as $basket) {
                $lines = $items->get($basket->id, collect());
                if ($lines->isEmpty()) {
                    continue;
                }
                if ($lines->contains(fn ($l) => $l->happy_hour_slot_id)) {
                    $row['happy_hour']++; // HappyHourSales::repriceVendingBasket owns these

                    continue;
                }

                $stored = (int) $lines->sum('unit_price_amount');
                if ($stored === (int) $basket->amount) {
                    continue; // already adds up
                }

                $changes = [];
                $proposed = 0;
                foreach ($lines as $line) {
                    $price = $served[(string) (int) $line->vend_channel_code] ?? (int) $line->unit_price_amount;
                    $proposed += $price;
                    if ($price !== (int) $line->unit_price_amount) {
                        $changes[(int) $line->id] = [(int) $line->unit_price_amount, $price];
                    }
                }

                if ($changes === [] || $proposed !== (int) $basket->amount) {
                    $row['unprovable']++;

                    continue;
                }

                $row['fixed']++;
                $row['lines'] += count($changes);
                $days[Carbon::parse($basket->transaction_datetime)->toDateString()] = true;

                if ($this->option('apply')) {
                    DB::transaction(function () use ($changes) {
                        foreach ($changes as $id => [$from, $to]) {
                            DB::table('vend_transaction_items')->where('id', $id)->where('unit_price_amount', $from)
                                ->update(['unit_price_amount' => $to, 'updated_at' => now()]);
                        }
                    });
                }
            }

            if ($row['fixed'] + $row['unprovable'] + $row['happy_hour'] > 0) {
                $rows[] = $row;
                foreach (['fixed', 'unprovable', 'happy_hour', 'lines'] as $k) {
                    $totals[$k] += $row[$k];
                }
            }
        }

        if ($this->option('apply')) {
            foreach (array_keys($days) as $day) {
                $dirtyDays->mark($day);
            }
        }

        $this->table(['Machine', 'Baskets re-priced', 'Lines changed', 'Left: prices do not add up', 'Left: Happy Hour'],
            array_map(fn ($r) => [$r['code'], $r['fixed'], $r['lines'], $r['unprovable'], $r['happy_hour']], $rows));

        $verb = $this->option('apply') ? 'Re-priced' : 'Would re-price';
        $this->info(sprintf('%s %d basket(s), %d line(s) since %s; %d left (prices do not add up), %d Happy Hour basket(s) left; %d day(s) to rebuild.',
            $verb, $totals['fixed'], $totals['lines'], $since->toDateString(), $totals['unprovable'], $totals['happy_hour'], count($days)));
        if (! $this->option('apply')) {
            $this->comment('Dry run — nothing written. Run again with --apply.');
        }

        return self::SUCCESS;
    }
}
