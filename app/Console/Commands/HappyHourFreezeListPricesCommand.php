<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Services\HappyHour\HappyHourPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill (audit 2026-10-30): `happy_hour_list_price` on sale lines tagged before
 * HappyHourSales::tag() froze it. Until then the discount was read from today's `vend_channels.amount`,
 * so a later board-price change or channel re-map rewrote past Happy Hour discounts.
 *
 * Freezers and server-price vending machines: the slot's price (what the discount always used). A
 * board-price vending machine priced each line from its channel's price AT THE TIME, which nothing kept;
 * of the slot's price (the SKU's board price when it was picked, minutes before) and the channel's price
 * now, the one whose promo is what the line was charged wins, the slot's first. A line stored at full
 * price (a basket the reprice could not prove) keeps the candidate equal to it; otherwise the slot's.
 */
class HappyHourFreezeListPricesCommand extends Command
{
    protected $signature = 'happy-hour:freeze-list-prices {--apply : write; without it, only count}';

    protected $description = 'Backfill the pre-discount price on Happy Hour sale lines tagged before it was frozen';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $summary = ['rows' => 0, 'slot' => 0, 'channel' => 0, 'before' => 0, 'after' => 0];

        foreach (['vend_transactions', 'vend_transaction_items'] as $table) {
            $rows = $this->rows($table);
            $updates = [];
            foreach ($rows as $row) {
                $qty = $table === 'vend_transactions' ? max(1, (int) $row->qty) : 1;
                $charged = $table === 'vend_transactions' ? intdiv((int) $row->amount, $qty) : (int) $row->unit_price_amount;
                $list = $this->listPrice($row, $charged);
                $updates[(int) $row->id] = $list;
                $summary['rows']++;
                $summary[$list === (int) $row->original_price ? 'slot' : 'channel']++;
                $live = $row->board_price && (int) $row->channel_amount > 0 ? (int) $row->channel_amount : (int) $row->original_price;
                $summary['before'] += max(0, ($live - $charged) * $qty);
                $summary['after'] += max(0, ($list - $charged) * $qty);
            }
            if ($apply) {
                foreach (collect($updates)->groupBy(fn ($list) => $list, preserveKeys: true) as $list => $ids) {
                    foreach (array_chunk($ids->keys()->all(), 500) as $chunk) {
                        DB::table($table)->whereIn('id', $chunk)->whereNull('happy_hour_list_price')->update(['happy_hour_list_price' => (int) $list]);
                    }
                }
            }
        }

        $this->info(sprintf('%s %d line(s): %d at the slot price, %d at the channel price now. Discount on them: $%.2f read live, $%.2f frozen.',
            $apply ? 'Froze' : 'Would freeze', $summary['rows'], $summary['slot'], $summary['channel'], $summary['before'] / 100, $summary['after'] / 100));

        return self::SUCCESS;
    }

    /** Tagged lines with no list price yet: a single sale's header, or a basket's item. */
    private function rows(string $table)
    {
        $items = $table === 'vend_transaction_items';

        return DB::table($table.' as l')
            ->when($items, fn ($q) => $q->join('vend_transactions as t', 't.id', '=', 'l.vend_transaction_id'))
            ->join('happy_hour_slots as s', 's.id', '=', 'l.happy_hour_slot_id')
            ->join('happy_hour_campaigns as c', 'c.id', '=', 's.happy_hour_campaign_id')
            ->join('vends as v', 'v.id', '=', $items ? 't.vend_id' : 'l.vend_id')
            ->leftJoin('vend_channels as vc', fn ($j) => $j->on('vc.vend_id', '=', 'v.id')->on('vc.code', '=', 'l.vend_channel_code'))
            ->whereNotNull('l.happy_hour_slot_id')
            ->whereNull('l.happy_hour_list_price')
            ->when(! $items, fn ($q) => $q->where('l.is_multiple', 0))
            ->selectRaw('l.id, s.original_price, s.discount_pct, c.price_step_cents, vc.amount AS channel_amount,
                (v.machine_type = ? AND COALESCE(v.is_using_server_price, 0) = 0) AS board_price', [Vend::MACHINE_TYPE_VENDING_MACHINE])
            ->when($items, fn ($q) => $q->addSelect('l.unit_price_amount'), fn ($q) => $q->addSelect('l.amount', 'l.qty'))
            ->orderBy('l.id')
            ->get();
    }

    private function listPrice(object $row, int $charged): int
    {
        $slot = (int) $row->original_price;
        $channel = (int) $row->channel_amount;
        if (! $row->board_price || $channel <= 0 || $channel === $slot) {
            return $slot;
        }
        $promo = fn (int $list) => HappyHourPricing::promoPrice($list, (int) $row->discount_pct, (int) ($row->price_step_cents ?: 1));
        foreach ([$slot, $channel] as $candidate) {
            if ($promo($candidate) === $charged) {
                return $candidate;
            }
        }

        return $charged === $channel ? $channel : $slot;
    }
}
