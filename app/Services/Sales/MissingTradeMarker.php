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
 * The window is walked ONE DAY AT A TIME, each day chunked by id: a single
 * day is a few thousand rows, so `idx_datetime_error (transaction_datetime,
 * vend_channel_error_id)` is always the cheaper plan for the ORDER BY id
 * chunk cursor (a 40-day range would tempt MySQL onto a PK scan). No new
 * index (Part 1, blockage 6).
 *
 * Each chunk re-selects its rows FOR UPDATE inside the write transaction and
 * re-checks `is_found_in_transaction = 0`: the TRADE ingest locks the same
 * row, so a frame that lands between the chunk scan and the write is either
 * seen (row skipped) or waits (row marked, then the TRADE clears the mark).
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
 *
 * Watermark (settings.missing_trade_marked_until) = "everything before this
 * instant has been examined". It advances only when the applied window is
 * contiguous with it — a manual re-run over a later slice must not skip the
 * days in between — and never past today 00:00 (a day is marked once it is
 * over).
 */
class MissingTradeMarker
{
    public function __construct(private ?Setting $setting = null) {}

    /**
     * Nightly window: [watermark, today 00:00). A missed night self-heals
     * because the watermark only moves when a run applied.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function nightlyWindow(): array
    {
        return [$this->watermark(), Carbon::now()->startOfDay()];
    }

    /** Where marking stands: the stored watermark, else the configured floor. */
    public function watermark(): Carbon
    {
        $stored = $this->setting()?->missing_trade_marked_until;

        return $stored
            ? Carbon::instance($stored)
            : Carbon::parse((string) config('sales.missing_trade_floor'))->startOfDay();
    }

    public function mark(CarbonInterface $from, CarbonInterface $until, bool $apply, ?int $chunk = null): MissingTradeMarkResult
    {
        $chunk ??= (int) config('sales.missing_trade_chunk');
        $result = new MissingTradeMarkResult;

        // Never mark a day that is not over yet.
        $until = min(Carbon::instance($until), Carbon::now()->startOfDay());
        if ($until->lte($from)) {
            return $result;
        }

        $naId = VendChannelError::query()->where('code', DispenseVerdict::NOT_FOUND_CODE)->value('id');
        if ($naId === null) {
            throw new \RuntimeException('vend_channel_errors has no code-99 row; run the 2026_09_09 migration first.');
        }

        $stamp = Carbon::now()->toDateTimeString();

        for ($day = Carbon::instance($from); $day->lt($until); $day = $dayEnd) {
            $dayEnd = min($day->copy()->addDay()->startOfDay(), $until);
            $this->candidates($day, $dayEnd)->chunkById($chunk, function ($rows) use ($apply, $naId, $stamp, $result) {
                $this->markChunk($rows, $apply, $naId, $stamp, $result);
            });
        }

        if ($apply) {
            $result->watermarkAdvanced = $this->advanceWatermark($from, $until);
        }

        return $result;
    }

    /** Candidate headers for [from, until), ordered by id for chunkById. Global scopes bypassed: this runs from the scheduler with no user. */
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

    private function markChunk($rows, bool $apply, int $naId, string $stamp, MissingTradeMarkResult $result): void
    {
        $ids = $rows->pluck('id')->all();
        $multiIds = $rows->where('is_multiple', true)->pluck('id')->all();

        if (! $apply) {
            foreach ($rows as $row) {
                $result->countDay($row->transaction_datetime->toDateString());
            }
            $result->items += $this->itemsToMark($multiIds);

            return;
        }

        DB::transaction(function () use ($ids, $multiIds, $naId, $stamp, $result) {
            // Re-read under lock: only rows STILL awaiting their TRADE are marked.
            $locked = VendTransaction::query()
                ->withoutGlobalScopes()
                ->select(['id', 'transaction_datetime'])
                ->whereIn('id', $ids)
                ->whereNull('vend_channel_error_id')
                ->where('is_found_in_transaction', false)
                ->lockForUpdate()
                ->get();
            if ($locked->isEmpty()) {
                return;
            }
            $lockedIds = $locked->pluck('id')->all();
            $lockedMultiIds = array_values(array_intersect($multiIds, $lockedIds));

            foreach ($locked as $row) {
                $result->countDay($row->transaction_datetime->toDateString());
            }

            if ($lockedMultiIds) {
                $ph = implode(',', array_fill(0, count($lockedMultiIds), '?'));
                $result->items += DB::update(
                    "UPDATE vend_transaction_items
                        SET vend_channel_error_id = ?, vend_channel_error_code = ?
                      WHERE vend_transaction_id IN ({$ph}) AND vend_channel_error_id IS NULL",
                    array_merge([$naId, DispenseVerdict::NOT_FOUND_CODE], $lockedMultiIds)
                );
            }

            $ph = implode(',', array_fill(0, count($lockedIds), '?'));
            DB::update(
                "UPDATE vend_transactions
                    SET vend_channel_error_id = ?,
                        meta_json = JSON_SET(COALESCE(meta_json, JSON_OBJECT()), '$.missing_trade', JSON_OBJECT('marked_at', ?))
                  WHERE id IN ({$ph})",
                array_merge([$naId, $stamp], $lockedIds)
            );
        });
    }

    /** Item rows a report-mode run WOULD mark (multiples only; singles carry no items). */
    private function itemsToMark(array $multiIds): int
    {
        if (! $multiIds) {
            return 0;
        }

        return (int) DB::table('vend_transaction_items')
            ->whereIn('vend_transaction_id', $multiIds)
            ->whereNull('vend_channel_error_id')
            ->count();
    }

    /**
     * Move the watermark to $until when the applied window starts at or before
     * the current watermark (nothing in between was skipped). Returns whether
     * it moved.
     */
    private function advanceWatermark(CarbonInterface $from, CarbonInterface $until): bool
    {
        $setting = $this->setting();
        if (! $setting) {
            return false;
        }
        $current = $this->watermark();
        if ($from->gt($current) || $until->lte($current)) {
            return false;
        }
        $setting->forceFill(['missing_trade_marked_until' => $until])->save();

        return true;
    }

    private function setting(): ?Setting
    {
        return $this->setting ??= Setting::singleton();
    }
}
