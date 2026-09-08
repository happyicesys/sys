<?php

namespace App\Services\Sales;

use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Called by VendTransactionService once a TRADE has been written — a fresh
 * row, or a gateway row it just filled. Two duties:
 *
 *  1. If the row's day is already over, register it as dirty so tonight's
 *     `reconcile:sales-rollups --dirty` rebuilds that day (DirtyDayRegistry).
 *  2. If the row had been marked 99 by the nightly marker and the TRADE has
 *     now replaced that code, stamp meta_json.missing_trade.cleared_at so the
 *     mark → clear history stays auditable. The code itself was overwritten by
 *     applyTradeToPreCreatedRow(); nothing else moves.
 */
class LateTradeTracker
{
    public function __construct(private readonly DirtyDayRegistry $dirtyDays) {}

    public function noteLanded(VendTransaction $row, ?int $previousErrorId, ?CarbonInterface $now = null): void
    {
        $now ??= Carbon::now();

        $at = $row->transaction_datetime instanceof CarbonInterface
            ? $row->transaction_datetime
            : Carbon::parse($row->transaction_datetime);

        if ($at->toDateString() < $now->toDateString()) {
            $this->dirtyDays->mark($at, $now);
        }

        if ($previousErrorId !== null && $previousErrorId === self::notFoundId()) {
            $meta = (array) ($row->meta_json ?? []);
            $meta['missing_trade'] = array_merge((array) ($meta['missing_trade'] ?? []), [
                'cleared_at' => $now->toDateTimeString(),
            ]);
            $row->forceFill(['meta_json' => $meta])->saveQuietly();
        }
    }

    private static ?int $notFoundId = null;

    public static function notFoundId(): ?int
    {
        return self::$notFoundId ??= \App\Models\VendChannelError::query()
            ->where('code', DispenseVerdict::NOT_FOUND_CODE)
            ->value('id');
    }

    /** Tests only — the id differs per fresh database. */
    public static function forgetNotFoundId(): void
    {
        self::$notFoundId = null;
    }
}
