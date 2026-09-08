<?php

namespace App\Services\Sales;

use App\Models\Setting;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Nightly: stamp channel error 99 ("Machine transaction not found (NA)") on
 * every gateway sale whose day is over and whose TRADE never arrived.
 *
 * Scope (ONE place — the NETS-report-created rows of Part 2 plug in here):
 *   payment_gateway_log_id IS NOT NULL   — pre-created by a payment rail
 *   is_found_in_transaction = 0          — no TRADE applied
 *   vend_channel_error_id IS NULL        — not marked yet (idempotent)
 *   transaction_datetime in [from, until) — the day is over
 *
 * The window rides `idx_datetime_error (transaction_datetime,
 * vend_channel_error_id)`: the datetime range is the seek, the IS NULL is
 * filtered in the index, and the two unindexed predicates run on the few
 * hundred candidates a day leaves. No new index (Part 1, blockage 6).
 *
 * What changes: the header's vend_channel_error_id, every item row's
 * vend_channel_error_id + vend_channel_error_code (multiples carry their
 * verdict on the items), and meta_json.missing_trade.marked_at. NOTHING
 * else — settlement, is_found_in_transaction, qty stay as they are. Because
 * 99 is a sale code (DispenseVerdict), no rollup moves and no rebuild is
 * queued; the only visible change is Error Code "NA" and a blank Dispense.
 *
 * Refunded rows are marked too (Brian, 2026-09-08): 99 describes the TRADE,
 * the refund describes the money.
 */
class MissingTradeMarker
{
    public function __construct(private readonly ?Setting $setting = null) {}

    /**
     * Nightly window: [watermark, today 00:00). A missed night self-heals
     * because the watermark only moves when a run applied.
     */
    public function nightlyWindow(?CarbonInterface $now = null): array
    {
        $now ??= Carbon::now();
        $until = $now->copy()->startOfDay();
        $from = $this->setting()?->missing_trade_marked_until
            ? Carbon::parse($this->setting()->missing_trade_marked_until)
            : Carbon::parse((string) config('sales.missing_trade_floor', '2026-08-01'))->startOfDay();

        return [$from, $until];
    }

    public function mark(CarbonInterface $from, CarbonInterface $until, bool $apply, ?int $chunk = null): MissingTradeMarkResult
    {
        $chunk ??= (int) config('sales.missing_trade_chunk', 500);
        $result = new MissingTradeMarkResult($from, $until, $apply);

        if ($until->lte($from)) {
            return $result;
        }

        $naId = VendChannelError::query()->where('code', DispenseVerdict::NOT_FOUND_CODE)->value('id');
        if ($naId === null) {
            throw new \RuntimeException('vend_channel_errors has no code-99 row; run the 2026_09_09 migration first.');
        }

        $stamp = Carbon::now()->toDateTimeString();

        $this->candidates($from, $until)->chunkById($chunk, function ($rows) use ($apply, $naId, $stamp, $result) {
            $ids = $rows->pluck('id')->all();
            $result->headers += count($ids);
            foreach ($rows as $row) {
                $result->countDay(Carbon::parse($row->transaction_datetime)->toDateString());
            }

            // Item rows of multiples: count first (report mode), then update.
            $itemCount = (int) DB::table('vend_transaction_items')
                ->whereIn('vend_transaction_id', $ids)
                ->whereNull('vend_channel_error_id')
                ->count();
            $result->items += $itemCount;

            if (! $apply) {
                return;
            }

            DB::transaction(function () use ($ids, $naId, $stamp) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                DB::update(
                    "UPDATE vend_transactions
                        SET vend_channel_error_id = ?,
                            meta_json = JSON_SET(COALESCE(meta_json, JSON_OBJECT()), '$.missing_trade', JSON_OBJECT('marked_at', ?))
                      WHERE id IN ({$placeholders}) AND vend_channel_error_id IS NULL",
                    array_merge([$naId, $stamp], $ids)
                );

                DB::update(
                    "UPDATE vend_transaction_items
                        SET vend_channel_error_id = ?, vend_channel_error_code = ?
                      WHERE vend_transaction_id IN ({$placeholders}) AND vend_channel_error_id IS NULL",
                    array_merge([$naId, DispenseVerdict::NOT_FOUND_CODE], $ids)
                );
            });
        });

        if ($apply) {
            $this->advanceWatermark($until);
        }

        return $result;
    }

    /** Candidate headers, ordered by id for chunkById. Global scopes bypassed: this runs from the scheduler with no user. */
    public function candidates(CarbonInterface $from, CarbonInterface $until)
    {
        return VendTransaction::query()
            ->withoutGlobalScopes()
            ->select(['id', 'transaction_datetime', 'is_multiple'])
            ->where('transaction_datetime', '>=', $from)
            ->where('transaction_datetime', '<', $until)
            ->whereNull('vend_channel_error_id')
            ->whereNotNull('payment_gateway_log_id')
            ->where('is_found_in_transaction', false);
    }

    private function advanceWatermark(CarbonInterface $until): void
    {
        $setting = $this->setting();
        if (! $setting) {
            return;
        }
        $current = $setting->missing_trade_marked_until ? Carbon::parse($setting->missing_trade_marked_until) : null;
        if ($current === null || $until->gt($current)) {
            $setting->forceFill(['missing_trade_marked_until' => $until])->save();
        }
    }

    private function setting(): ?Setting
    {
        return $this->setting ?? Setting::query()->first();
    }
}
