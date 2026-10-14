<?php

namespace App\Services\Sales;

use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Models\VendTransaction;

/**
 * The sales a machine made between two completed ops-job stops — the "Transaction
 * S$ (Qty)" line of an ops job item (`ops_job_items.acc_total_*`), shown on the
 * ops job pages and the Operation Dashboard's Last Job column.
 *
 * Window = previous completed stop on the same vend → this stop's completed_at.
 * A vending machine's sale is placed in it by `created_at`, as it always was: a
 * TRADE lands within seconds of the sale. A Smart Chiller's sale is placed by
 * `transaction_datetime` (when the money came in) instead, because its rows are
 * CityBox orders PULLED by CityboxOrderProjector: `created_at` is the pull, which
 * can be days after the sale (pay-later up to 14 days; the 2026-10-13 backfill
 * created every earlier order that day), so `created_at` put every chiller sale
 * in the wrong window or none.
 *
 * Written once when the stop completes (OpsJobController); a chiller's past
 * windows keep changing as late orders arrive, so `ops-jobs:recompute-sales-window`
 * re-runs this nightly for chiller stops.
 */
class OpsJobItemSalesWindow
{
    /**
     * Recompute and store the item's acc_total_* figures. Returns false (and
     * writes nothing) when the item has no earlier completed stop to measure from.
     */
    public function apply(OpsJobItem $item): bool
    {
        if (! $item->completed_at || ! $item->vend_id) {
            return false;
        }

        $previous = OpsJobItem::where('vend_id', $item->vend_id)
            ->where('id', '<', $item->id)
            ->where('status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('status', '<>', OpsJob::STATUS_CANCELLED)
            ->orderBy('id', 'desc')
            ->first();

        if (! $previous || ! $previous->completed_at) {
            return false;
        }

        $timeColumn = self::timeColumnFor($item->vend);

        $totals = VendTransaction::query()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
            ->where('vend_transactions.'.$timeColumn, '>=', $previous->completed_at)
            ->where('vend_transactions.'.$timeColumn, '<=', $item->completed_at)
            ->where('vend_transactions.vend_id', $item->vend_id)
            ->isSuccessful()
            ->selectRaw('SUM(vend_transactions.amount) as total_amount')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN payment_methods.code = 0 THEN vend_transactions.amount ELSE 0 END) as total_cash_amount')
            ->selectRaw('SUM(CASE WHEN payment_methods.code <> 0 THEN vend_transactions.amount ELSE 0 END) as total_cashless_amount')
            ->selectRaw('SUM(vend_channels.amount - vend_transactions.amount) as total_promo_amount')
            ->first();

        $item->update([
            'previous_ops_job_item_id' => $previous->id,
            'acc_total_amount' => $totals->total_amount,
            'acc_total_cash_amount' => $totals->total_cash_amount,
            'acc_total_cashless_amount' => $totals->total_cashless_amount,
            'acc_total_promo_amount' => $totals->total_promo_amount,
            'acc_total_count' => $totals->total_count,
        ]);

        return true;
    }

    public static function timeColumnFor(?Vend $vend): string
    {
        return $vend && $vend->isSmartChiller() ? 'transaction_datetime' : 'created_at';
    }
}
