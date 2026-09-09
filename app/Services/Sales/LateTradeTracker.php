<?php

namespace App\Services\Sales;

use App\Models\VendTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Called by VendTransactionService once a TRADE has been written — a fresh
 * row, or a gateway row it just filled. Two duties:
 *
 *  1. noteLanded(): if the row's day is already over, register it as dirty so
 *     tonight's `reconcile:sales-rollups --dirty` rebuilds that day
 *     (DirtyDayRegistry). Deferred to after the ingest transaction commits:
 *     the Redis round trip never runs under the row lock, and a rolled-back
 *     ingest dirties nothing.
 *  2. withClearStamp(): if the row had been marked 99 by the nightly marker
 *     (meta_json.missing_trade.marked_at, written in the same UPDATE as the
 *     code) and a TRADE is now replacing that code, add cleared_at so the
 *     mark → clear history stays auditable. Pure — applyTradeToPreCreatedRow
 *     folds the result into its own write, so there is no second UPDATE.
 */
class LateTradeTracker
{
    public function __construct(private readonly DirtyDayRegistry $dirtyDays) {}

    public function noteLanded(VendTransaction $row): void
    {
        $at = $row->transaction_datetime instanceof CarbonInterface
            ? $row->transaction_datetime->copy()
            : Carbon::parse($row->transaction_datetime);

        if ($at->toDateString() >= Carbon::now()->toDateString()) {
            return; // today is still in flight; the nightly builders own it
        }

        DB::afterCommit(fn () => $this->dirtyDays->mark($at));
    }

    /**
     * meta_json for a pre-created row that a TRADE is now filling: unchanged
     * unless the row carries an open 99 mark, in which case cleared_at is added.
     */
    public static function withClearStamp(?array $meta, CarbonInterface $now): ?array
    {
        $mark = $meta['missing_trade'] ?? null;
        if (! is_array($mark) || ! isset($mark['marked_at']) || isset($mark['cleared_at'])) {
            return $meta;
        }

        $meta['missing_trade'] = $mark + ['cleared_at' => $now->toDateTimeString()];

        return $meta;
    }
}
