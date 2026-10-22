<?php

namespace App\Services\HappyHour;

use App\Models\HappyHourSlot;
use App\Models\Scopes\OperatorHappyHourCampaignScope;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Which sales a Happy Hour slot priced — the only writer of `happy_hour_slot_id` on
 * `vend_transactions` / `vend_transaction_items`, and the reader the slot results and the
 * Sales Transactions badge use.
 *
 * The freezer's TRADE carries the price it charged but not the slot, so a line is the slot's when
 * the same machine sold the slot's product at exactly its promo price within the slot's time
 * (± `sale_grace_seconds`). The promo price is below the normal price, so the price is the proof;
 * the time only picks between slots. Tagged once at ingest, so a later campaign edit never
 * re-attributes a sale.
 *
 * A vending machine (big board, app 315+) is never tagged by price: its own discounts (labels,
 * bundles, P2) can land on the same figure. Its touchscreen TRADE names the slot of each promo line
 * in a top-level `"HH": [{"SId": <channel>, "HHId": <slot id>}]`, and a line is tagged only when
 * its channel is named there, the slot exists on THIS machine (for the line's product, when known)
 * and the sale time is inside the slot ± grace. Keypad / VMC-built TRADEs carry no `HH` and are never
 * tagged (Brian, 2026-10-12: Happy Hour is a touchscreen offer on vending).
 */
final class HappyHourSales
{
    /**
     * Tags the promo lines of a sale that has just been written (its items included). Never
     * throws: the sale is already committed and must not fail over a label.
     */
    public function tag(Vend $vend, VendTransaction $sale, ?array $frame = null): int
    {
        if ($vend->machine_type === Vend::MACHINE_TYPE_VENDING_MACHINE) {
            // The MQTT hot path: a TRADE with no `HH` (every keypad sale, every pre-315 board) costs
            // no query at all.
            $hh = ($frame ?? (is_array($sale->vend_transaction_json) ? $sale->vend_transaction_json : []))['HH'] ?? null;
            if (! is_array($hh) || $hh === []) {
                return 0;
            }
        } elseif ($vend->machine_type !== Vend::MACHINE_TYPE_SMART_FREEZER) {
            return 0;
        }

        try {
            return $vend->machine_type === Vend::MACHINE_TYPE_SMART_FREEZER
                ? $this->tagLines($vend, $sale)
                : $this->tagVendingLines($vend, $sale, $hh);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * The settled units, takings and discount a slot sold, from the sales tagged with it.
     *
     * @return array{units: int, revenue: int, discount: int}
     */
    public function results(HappyHourSlot $slot): array
    {
        [$from, $to] = $this->searchWindow($slot->starts_at, $slot->ends_at);
        // A vending machine on the board price prices each channel from its own board price, and two
        // channels of one SKU may differ: its discount is each line's channel price less what it
        // took (the slot's original price where the channel has none), not units × the slot's.
        $perChannel = DB::table('vends')->where('id', $slot->vend_id)
            ->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where(fn ($q) => $q->whereNull('is_using_server_price')->orWhere('is_using_server_price', 0))
            ->exists();
        $original = (int) $slot->original_price;

        // A single sale is its header row; qty is the units it sold at the promo price.
        $single = DB::table('vend_transactions as t')
            ->leftJoin('vend_channel_errors as e', 'e.id', '=', 't.vend_channel_error_id')
            ->when($perChannel, fn ($q) => $q->leftJoin('vend_channels as vc', fn ($j) => $j
                ->on('vc.vend_id', '=', 't.vend_id')->on('vc.code', '=', 't.vend_channel_code')))
            ->where('t.vend_id', $slot->vend_id)
            ->whereBetween('t.transaction_datetime', [$from, $to])
            ->where('t.happy_hour_slot_id', $slot->id)
            ->where('t.is_multiple', 0)
            ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where('t.is_refunded', 0)
            ->where(fn ($q) => $q->whereNull('e.code')->orWhereIn('e.code', DispenseVerdict::SALE_CODES))
            ->selectRaw('COALESCE(SUM(GREATEST(t.qty, 1)), 0) AS units, COALESCE(SUM(t.amount), 0) AS revenue')
            ->when($perChannel, fn ($q) => $q->selectRaw(
                'COALESCE(SUM(GREATEST(GREATEST(t.qty, 1) * COALESCE(NULLIF(vc.amount, 0), ?) - t.amount, 0)), 0) AS discount', [$original]
            ))
            ->first();

        // A multiple purchase: one item row per unit, each with its own verdict and refund flag.
        $multi = DB::table('vend_transaction_items as i')
            ->join('vend_transactions as t', 't.id', '=', 'i.vend_transaction_id')
            ->when($perChannel, fn ($q) => $q->leftJoin('vend_channels as vc', fn ($j) => $j
                ->on('vc.vend_id', '=', 't.vend_id')->on('vc.code', '=', 'i.vend_channel_code')))
            ->where('t.vend_id', $slot->vend_id)
            ->whereBetween('t.transaction_datetime', [$from, $to])
            ->where('t.is_multiple', 1)
            ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where('i.happy_hour_slot_id', $slot->id)
            ->where(fn ($q) => $q->whereNull('i.vend_channel_error_code')->orWhereIn('i.vend_channel_error_code', DispenseVerdict::SALE_CODES))
            ->where(fn ($q) => $q->whereNull('i.is_refunded')->orWhere('i.is_refunded', 0))
            ->selectRaw('COUNT(*) AS units, COALESCE(SUM(i.unit_price_amount), 0) AS revenue')
            ->when($perChannel, fn ($q) => $q->selectRaw(
                'COALESCE(SUM(GREATEST(COALESCE(NULLIF(vc.amount, 0), ?) - i.unit_price_amount, 0)), 0) AS discount', [$original]
            ))
            ->first();

        $units = (int) $single->units + (int) $multi->units;
        $revenue = (int) $single->revenue + (int) $multi->revenue;
        $discount = $perChannel
            ? (int) $single->discount + (int) $multi->discount
            : max(0, $units * $slot->original_price - $revenue);

        return ['units' => $units, 'revenue' => $revenue, 'discount' => $discount];
    }

    /**
     * What each slot sold on these machines, for sales made in [$from, $to] — the same rules as
     * results() (settled, not refunded, a sale code; a single sale = its header, a basket = its
     * items; a board-price vending machine's discount per channel), summed over dailyTotals(). The
     * campaign page reads it for "HH: n" (the slot running now) and each machine's last 24 h
     * (Brian, 2026-10-15). Units are live: a closed slot's `units_sold` is not used.
     *
     * @param  iterable<int>  $vendIds
     * @return array<int, array{vend_id: int, units: int, revenue: int, discount: int}> slot id => totals
     */
    public function slotTotals(iterable $vendIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $totals = [];
        foreach ($this->dailyTotals($vendIds, $from, $to) as $row) {
            $t = $totals[$row['slot_id']] ??= ['vend_id' => $row['vend_id'], 'units' => 0, 'revenue' => 0, 'discount' => 0];
            $t['units'] += $row['units'];
            $t['revenue'] += $row['revenue'];
            $t['discount'] += $row['discount'];
            $totals[$row['slot_id']] = $t;
        }

        return $totals;
    }

    /**
     * Happy Hour sales on these machines in [$from, $to], one row per (slot, sale date) — two grouped
     * queries, whatever the range. Read by slotTotals() and the campaign figures and graph
     * (HappyHourCampaignStats, Brian 2026-10-16). Same rules as results().
     *
     * @param  iterable<int>  $vendIds
     * @return list<array{slot_id: int, campaign_id: int, vend_id: int, day: string, units: int, revenue: int, discount: int}>
     */
    public function dailyTotals(iterable $vendIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $vendIds = collect($vendIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($vendIds === []) {
            return [];
        }
        // A machine that sells at its own board price: discount = each line's channel price less what it took.
        $perChannel = array_flip(DB::table('vends')->whereIn('id', $vendIds)
            ->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where(fn ($q) => $q->whereNull('is_using_server_price')->orWhere('is_using_server_price', 0))
            ->pluck('id')->all());

        $single = DB::table('vend_transactions as t')
            ->join('happy_hour_slots as s', 's.id', '=', 't.happy_hour_slot_id')
            ->leftJoin('vend_channel_errors as e', 'e.id', '=', 't.vend_channel_error_id')
            ->leftJoin('vend_channels as vc', fn ($j) => $j->on('vc.vend_id', '=', 't.vend_id')->on('vc.code', '=', 't.vend_channel_code'))
            ->whereIn('t.vend_id', $vendIds)
            ->whereBetween('t.transaction_datetime', [$from, $to])
            ->whereNotNull('t.happy_hour_slot_id')
            ->where('t.is_multiple', 0)
            ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where('t.is_refunded', 0)
            ->where(fn ($q) => $q->whereNull('e.code')->orWhereIn('e.code', DispenseVerdict::SALE_CODES))
            ->groupBy('t.happy_hour_slot_id', 's.happy_hour_campaign_id', 's.vend_id', 's.original_price', DB::raw('DATE(t.transaction_datetime)'))
            ->selectRaw('t.happy_hour_slot_id AS slot_id, s.happy_hour_campaign_id AS campaign_id, s.vend_id, s.original_price,
                DATE(t.transaction_datetime) AS day,
                SUM(GREATEST(t.qty, 1)) AS units, SUM(t.amount) AS revenue,
                SUM(GREATEST(GREATEST(t.qty, 1) * COALESCE(NULLIF(vc.amount, 0), s.original_price) - t.amount, 0)) AS channel_discount')
            ->get();

        $multi = DB::table('vend_transaction_items as i')
            ->join('vend_transactions as t', 't.id', '=', 'i.vend_transaction_id')
            ->join('happy_hour_slots as s', 's.id', '=', 'i.happy_hour_slot_id')
            ->leftJoin('vend_channels as vc', fn ($j) => $j->on('vc.vend_id', '=', 't.vend_id')->on('vc.code', '=', 'i.vend_channel_code'))
            ->whereIn('t.vend_id', $vendIds)
            ->whereBetween('t.transaction_datetime', [$from, $to])
            ->where('t.is_multiple', 1)
            ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->whereNotNull('i.happy_hour_slot_id')
            ->where(fn ($q) => $q->whereNull('i.vend_channel_error_code')->orWhereIn('i.vend_channel_error_code', DispenseVerdict::SALE_CODES))
            ->where(fn ($q) => $q->whereNull('i.is_refunded')->orWhere('i.is_refunded', 0))
            ->groupBy('i.happy_hour_slot_id', 's.happy_hour_campaign_id', 's.vend_id', 's.original_price', DB::raw('DATE(t.transaction_datetime)'))
            ->selectRaw('i.happy_hour_slot_id AS slot_id, s.happy_hour_campaign_id AS campaign_id, s.vend_id, s.original_price,
                DATE(t.transaction_datetime) AS day,
                COUNT(*) AS units, SUM(i.unit_price_amount) AS revenue,
                SUM(GREATEST(COALESCE(NULLIF(vc.amount, 0), s.original_price) - i.unit_price_amount, 0)) AS channel_discount')
            ->get();

        return $single->concat($multi)->map(function ($row) use ($perChannel) {
            $units = (int) $row->units;
            $revenue = (int) $row->revenue;

            return [
                'slot_id' => (int) $row->slot_id,
                'campaign_id' => (int) $row->campaign_id,
                'vend_id' => (int) $row->vend_id,
                'day' => (string) $row->day,
                'units' => $units,
                'revenue' => $revenue,
                'discount' => isset($perChannel[(int) $row->vend_id])
                    ? (int) $row->channel_discount
                    : max(0, $units * (int) $row->original_price - $revenue),
            ];
        })->values()->all();
    }

    /**
     * "Related Total Sales" (Daniel, 2026-10-22: HH is the bridge — "如果说 5 支里面有一支是 Happy Hour 的也算"):
     * every settled, not-refunded transaction holding at least one Happy Hour unit, counted WHOLE — the
     * header is tagged on a single sale and on a basket that includes a promo unit, so this is the set
     * Sales Transactions lists under that campaign's label. One row per (campaign, machine, sale date):
     * transactions, multiple purchases, what the customer paid (header amount) and the dispensed units
     * (a single sale's own verdict; a basket's lines with a sale code, not refunded). Two grouped queries
     * over (vend_id, transaction_datetime). Read by HappyHourCampaignStats beside dailyTotals().
     *
     * @param  iterable<int>  $vendIds
     * @return list<array{campaign_id: int, vend_id: int, day: string, transactions: int, multiple: int, revenue: int, units: int}>
     */
    public function dailyRelatedTotals(iterable $vendIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $vendIds = collect($vendIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($vendIds === []) {
            return [];
        }
        $base = fn () => DB::table('vend_transactions as t')
            ->join('happy_hour_slots as s', 's.id', '=', 't.happy_hour_slot_id')
            ->whereIn('t.vend_id', $vendIds)
            ->whereBetween('t.transaction_datetime', [$from, $to])
            ->whereNotNull('t.happy_hour_slot_id')
            ->where('t.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->where('t.is_refunded', 0)
            ->groupBy('s.happy_hour_campaign_id', 't.vend_id', DB::raw('DATE(t.transaction_datetime)'));

        $single = $base()
            ->leftJoin('vend_channel_errors as e', 'e.id', '=', 't.vend_channel_error_id')
            ->where('t.is_multiple', 0)
            ->where(fn ($q) => $q->whereNull('e.code')->orWhereIn('e.code', DispenseVerdict::SALE_CODES))
            ->selectRaw('s.happy_hour_campaign_id AS campaign_id, t.vend_id, DATE(t.transaction_datetime) AS day,
                COUNT(*) AS transactions, 0 AS multiple, SUM(t.amount) AS revenue, SUM(GREATEST(t.qty, 1)) AS units')
            ->get();

        $codes = implode(', ', array_map('intval', DispenseVerdict::SALE_CODES));
        $multi = $base()
            ->where('t.is_multiple', 1)
            ->selectRaw("s.happy_hour_campaign_id AS campaign_id, t.vend_id, DATE(t.transaction_datetime) AS day,
                COUNT(*) AS transactions, COUNT(*) AS multiple, SUM(t.amount) AS revenue,
                SUM((SELECT COUNT(*) FROM vend_transaction_items i WHERE i.vend_transaction_id = t.id
                    AND (i.vend_channel_error_code IS NULL OR i.vend_channel_error_code IN ({$codes}))
                    AND (i.is_refunded IS NULL OR i.is_refunded = 0))) AS units")
            ->get();

        return $single->concat($multi)->map(fn ($row) => [
            'campaign_id' => (int) $row->campaign_id,
            'vend_id' => (int) $row->vend_id,
            'day' => (string) $row->day,
            'transactions' => (int) $row->transactions,
            'multiple' => (int) $row->multiple,
            'revenue' => (int) $row->revenue,
            'units' => (int) $row->units,
        ])->values()->all();
    }

    /**
     * The "Happy Hour" badge for each slot id, for one page of Sales Transactions.
     *
     * @param  iterable<int|string|null>  $slotIds
     * @return array<int, array<string, mixed>>
     */
    public function badges(iterable $slotIds): array
    {
        $ids = collect($slotIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return HappyHourSlot::query()
            // The name of whichever campaign priced the sale, even another operator's or a deleted one.
            ->with(['campaign' => fn ($q) => $q->withTrashed()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->select('id', 'name')])
            ->whereIn('id', $ids)
            ->get(['id', 'happy_hour_campaign_id', 'starts_at', 'ends_at', 'original_price', 'promo_price', 'discount_pct'])
            ->mapWithKeys(fn (HappyHourSlot $s) => [$s->id => [
                'slot_id' => $s->id,
                'campaign' => $s->campaign?->name,
                'discount_pct' => $s->discount_pct,
                // The Campaign Label(s) chip: "HH 30%" (CampaignLabelFilter owns the wording).
                'label' => \App\Support\Sales\CampaignLabelFilter::happyHourLabel((int) $s->discount_pct),
                'original_price' => $s->original_price,
                'promo_price' => $s->promo_price,
                'slot' => $s->starts_at->format('H:i').'–'.$s->ends_at->format('H:i'),
            ]])
            ->all();
    }

    private function tagLines(Vend $vend, VendTransaction $sale): int
    {
        if ((int) $sale->amount <= 0 || ! $sale->transaction_datetime) {
            return 0;
        }
        $at = Carbon::parse($sale->transaction_datetime);

        // [line key => [product id, unit price cents]]; key 0 = the header of a single sale.
        $lines = [];
        if ($sale->is_multiple) {
            foreach (DB::table('vend_transaction_items')->where('vend_transaction_id', $sale->id)
                ->get(['id', 'product_id', 'unit_price_amount']) as $item) {
                if ($item->product_id && $item->unit_price_amount > 0) {
                    $lines[(int) $item->id] = [(int) $item->product_id, (int) $item->unit_price_amount];
                }
            }
        } elseif ($sale->product_id) {
            $lines[0] = [(int) $sale->product_id, intdiv((int) $sale->amount, max(1, (int) $sale->qty))];
        }
        if ($lines === []) {
            return 0;
        }

        $grace = (int) config('happy_hour.sale_grace_seconds', 300);
        $slots = HappyHourSlot::query()
            ->where('vend_id', $vend->id)
            ->whereIn('product_id', array_unique(array_column($lines, 0)))
            ->where('starts_at', '<=', $at->copy()->addSeconds($grace))
            ->where('ends_at', '>', $at->copy()->subSeconds($grace))
            ->get(['id', 'product_id', 'promo_price', 'starts_at', 'ends_at', 'units_sold']);
        if ($slots->isEmpty()) {
            return 0;
        }

        $tagged = []; // slot id => item ids
        foreach ($lines as $key => [$productId, $unit]) {
            $slot = $slots
                ->filter(fn (HappyHourSlot $s) => $s->product_id === $productId && $s->promo_price === $unit)
                ->sortBy(fn (HappyHourSlot $s) => $this->secondsOutside($s, $at))
                ->first();
            if ($slot) {
                $tagged[$slot->id][] = $key;
            }
        }

        return $this->write($sale, $tagged, $slots);
    }

    /**
     * A vending TRADE's `HH` list names each promo line's slot: tag exactly those lines. Malformed
     * entries are skipped (never thrown — the MQTT ingest path).
     *
     * @param  array<int|string, mixed>  $hh
     */
    private function tagVendingLines(Vend $vend, VendTransaction $sale, array $hh): int
    {
        if ((int) $sale->amount <= 0 || ! $sale->transaction_datetime) {
            return 0;
        }
        // channel code => slot id; one entry per promo cart line (a cart holds a handful).
        $slotBySid = [];
        foreach (array_slice($hh, 0, 50) as $entry) {
            $sid = is_array($entry) ? ($entry['SId'] ?? null) : null;
            $slotId = is_array($entry) ? ($entry['HHId'] ?? null) : null;
            if (! self::positiveInt($sid) || ! self::positiveInt($slotId)) {
                continue;
            }
            $slotBySid[(int) $sid] = (int) $slotId;
        }
        if ($slotBySid === []) {
            return 0;
        }

        // [line key => [product id|null, channel code]]; key 0 = the header of a single sale.
        $lines = [];
        if ($sale->is_multiple) {
            foreach (DB::table('vend_transaction_items')->where('vend_transaction_id', $sale->id)
                ->get(['id', 'product_id', 'vend_channel_code']) as $item) {
                $lines[(int) $item->id] = [$item->product_id ? (int) $item->product_id : null, (int) $item->vend_channel_code];
            }
        } else {
            $lines[0] = [$sale->product_id ? (int) $sale->product_id : null, (int) $sale->vend_channel_code];
        }

        $at = Carbon::parse($sale->transaction_datetime);
        $grace = (int) config('happy_hour.sale_grace_seconds', 300);
        $slots = HappyHourSlot::query()
            ->whereIn('id', array_values(array_unique($slotBySid)))
            ->where('vend_id', $vend->id)
            ->where('starts_at', '<=', $at->copy()->addSeconds($grace))
            ->where('ends_at', '>', $at->copy()->subSeconds($grace))
            ->get(['id', 'product_id', 'promo_price', 'starts_at', 'ends_at', 'units_sold'])
            ->keyBy('id');
        if ($slots->isEmpty()) {
            return 0;
        }

        $tagged = []; // slot id => line keys
        foreach ($lines as $key => [$productId, $channel]) {
            $slot = $slots->get($slotBySid[$channel] ?? 0);
            if ($slot && ($productId === null || $slot->product_id === $productId)) {
                $tagged[$slot->id][] = $key;
            }
        }

        $count = $this->write($sale, $tagged, $slots->values());
        if ($count > 0 && $sale->is_multiple) {
            $this->repriceVendingBasket($sale->id);
        }

        return $count;
    }

    /**
     * A vending TRADE carries ONE price for a basket (`Price`) and none per line, so the ingest stores
     * each item at its channel's board price — a Happy Hour line too, though the screen charged its
     * promo price (Brian, 2026-10-16: "L24h $0.00 · 5" on 2378, whose 5 units were all in baskets).
     * This rewrites each tagged item to the price the screen charged — the slot's discount on that
     * channel's price with the campaign's rounding, exactly as `/thumbnails` priced it
     * (HappyHourPricing::channelSlots) — and only when the items then add up to the header's amount,
     * which proves it. Anything else (another discount in the basket, a price changed mid-slot) is left
     * alone and logged. Idempotent: a basket whose items already add up is untouched.
     *
     * A failed line was charged too: the header's `Price` is the whole cart (2301, 2026-10-16: 2 × $2.40
     * = $4.80 with one unit failed). Since 2026-10-21 the ingest stores it at its channel price like any
     * line, so it is repriced like any tagged line. Lines stored before that sit at 0: the proof counts
     * them at what the screen charged — the channel price, discounted when tagged — and a header that
     * leaves them out still proves with them at 0.
     *
     * Every reader of item prices follows: slot results, the campaign page, product revenue / GP.
     *
     * @return bool whether the items were rewritten
     */
    public function repriceVendingBasket(int $saleId): bool
    {
        $plan = $this->planVendingBasketReprice($saleId);
        if ($plan['state'] !== 'reprice') {
            if ($plan['state'] === 'unprovable') {
                Log::info('happy hour basket not repriced: items do not add up to the sale', [
                    'vend_transaction_id' => $saleId, 'amount' => $plan['amount'], 'repriced_total' => $plan['total'],
                ]);
            }

            return false;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan['prices'] as $itemId => $price) {
                DB::table('vend_transaction_items')->where('id', $itemId)->update(['unit_price_amount' => $price]);
            }
        });

        return true;
    }

    /**
     * What repriceVendingBasket would do, without writing: `adds_up` (nothing to do), `unprovable` (the
     * repriced items still do not add up — left alone), `reprice` (with the new `prices` by item id), or
     * `none` (not a basket, or no tagged line to reprice).
     *
     * @return array{state: string, prices: array<int, int>, amount: int, total: int}
     */
    public function planVendingBasketReprice(int $saleId): array
    {
        $plan = fn (string $state, array $prices = [], int $amount = 0, int $total = 0) => compact('state', 'prices', 'amount', 'total');
        $sale = DB::table('vend_transactions')->where('id', $saleId)->first(['id', 'amount', 'is_multiple']);
        if (! $sale || ! $sale->is_multiple) {
            return $plan('none');
        }
        $items = DB::table('vend_transaction_items as i')
            ->leftJoin('vend_channels as vc', 'vc.id', '=', 'i.vend_channel_id')
            ->where('i.vend_transaction_id', $saleId)
            ->get(['i.id', 'i.unit_price_amount', 'i.happy_hour_slot_id', 'i.vend_channel_error_code', 'vc.amount as channel_amount']);
        $header = (int) $sale->amount;
        if ($items->isEmpty()) {
            return $plan('none', [], $header);
        }

        $slots = HappyHourSlot::query()
            ->with(['campaign' => fn ($q) => $q->withTrashed()->withoutGlobalScope(OperatorHappyHourCampaignScope::class)->select('id', 'price_step_cents')])
            ->whereIn('id', $items->pluck('happy_hour_slot_id')->filter()->unique()->values())
            ->get(['id', 'happy_hour_campaign_id', 'discount_pct'])
            ->keyBy('id');
        $promo = function (object $item, int $price) use ($slots): ?int {
            $slot = $item->happy_hour_slot_id ? $slots->get($item->happy_hour_slot_id) : null;

            return $slot && $price > 0
                ? HappyHourPricing::promoPrice($price, (int) $slot->discount_pct, (int) ($slot->campaign?->price_step_cents ?? 1))
                : null;
        };

        // What the customer was charged for failed lines stored at 0 before 2026-10-21: the channel price,
        // discounted when the line is tagged.
        $failedCharged = 0;
        foreach ($items as $item) {
            if ((int) $item->unit_price_amount === 0 && ! DispenseVerdict::isSaleCode($item->vend_channel_error_code)) {
                $list = (int) ($item->channel_amount ?? 0);
                $failedCharged += $promo($item, $list) ?? $list;
            }
        }
        $proves = fn (int $stored) => $stored === $header || $stored + $failedCharged === $header;

        $stored = (int) $items->sum('unit_price_amount');
        if ($proves($stored)) {
            return $plan('adds_up', [], $header, $stored);
        }

        $new = [];
        $total = 0;
        foreach ($items as $item) {
            $price = (int) $item->unit_price_amount;
            $repriced = $promo($item, $price);
            if ($repriced !== null) {
                $new[(int) $item->id] = $repriced;
                $price = $repriced;
            }
            $total += $price;
        }
        if ($new === []) {
            return $plan('none', [], $header, $total);
        }

        return $proves($total) ? $plan('reprice', $new, $header, $total) : $plan('unprovable', [], $header, $total);
    }

    private static function positiveInt(mixed $value): bool
    {
        return (is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0;
    }

    /**
     * @param  array<int, list<int>>  $tagged  slot id => line keys (0 = the header of a single sale)
     * @param  \Illuminate\Support\Collection<int, HappyHourSlot>  $slots
     */
    private function write(VendTransaction $sale, array $tagged, $slots): int
    {
        if ($tagged === []) {
            return 0;
        }

        DB::transaction(function () use ($sale, $tagged, $slots) {
            foreach ($tagged as $slotId => $keys) {
                $itemIds = array_values(array_filter($keys));
                if ($itemIds !== []) {
                    DB::table('vend_transaction_items')->whereIn('id', $itemIds)->update(['happy_hour_slot_id' => $slotId]);
                }
            }
            // The header names the slot too (for a multiple purchase: "includes a promo unit").
            DB::table('vend_transactions')->where('id', $sale->id)->update(['happy_hour_slot_id' => array_key_first($tagged)]);

            // A TRADE that lands after its slot was totalled re-opens the totals; the minute run recounts.
            $closed = $slots->whereIn('id', array_keys($tagged))->whereNotNull('units_sold')->pluck('id')->all();
            if ($closed !== []) {
                HappyHourSlot::whereIn('id', $closed)->update(['units_sold' => null]);
            }
        });

        return array_sum(array_map('count', $tagged));
    }

    /** 0 when the sale is inside the slot, else how far outside it. */
    private function secondsOutside(HappyHourSlot $slot, CarbonInterface $at): int
    {
        if ($at->lt($slot->starts_at)) {
            return (int) $at->diffInSeconds($slot->starts_at, true);
        }
        if ($at->gte($slot->ends_at)) {
            return (int) $slot->ends_at->diffInSeconds($at, true) + 1;
        }

        return 0;
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    private function searchWindow(CarbonInterface $starts, CarbonInterface $ends): array
    {
        $grace = (int) config('happy_hour.sale_grace_seconds', 300);

        return [$starts->copy()->subSeconds($grace), $ends->copy()->addSeconds($grace)];
    }
}
