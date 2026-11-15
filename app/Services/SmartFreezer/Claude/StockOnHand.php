<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\OpsJob;
use App\Models\SmartFreezerRecognition;
use App\Models\VendTransaction;
use App\Services\Freezer\FreezerStockLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * About how many units of each product the freezer held when this session's door opened, from our own records
 * (Brian, 2026-11-15). Same rule as `freezer:rebuild-stock`: the product's last Stock In before the session (the qty
 * the page showed + the refill), minus every sale since, the AI's corrections included — never this session's own.
 *
 * Why: on #154 Claude and Fable both read section 32 as "almost empty" after six cones left, while our records had
 * about 27 there, so the camera only sees the front layer and a gap is no proof of a count. It is a hint only: it
 * says nothing about what was paid for, and a driver's count can be a few out.
 */
final class StockOnHand
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, int> product id => units, only for products with a Stock In on record
     */
    public static function before(SmartFreezerRecognition $recognition, array $productIds): array
    {
        $vend = $recognition->vend;
        $at = self::doorOpened($recognition);
        if ($vend === null || $at === null || $productIds === []) {
            return [];
        }

        $stocked = [];
        $since = [];
        $lines = DB::table('ops_job_item_channels as c')
            ->join('ops_job_items as oji', 'oji.id', '=', 'c.ops_job_item_id')
            ->where('oji.vend_id', $vend->id)
            ->whereIn('c.product_id', $productIds)
            ->where('oji.status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('oji.status', '<>', OpsJob::STATUS_CANCELLED)
            ->whereNotNull('oji.completed_at')
            ->where('oji.completed_at', '<=', $at)
            ->orderBy('oji.completed_at')
            ->get(['c.product_id', 'c.qty', 'c.actual_qty', 'oji.id as item_id', 'oji.completed_at']);
        // The latest visit that stocked each product; a product on two sections in that visit is the sum of both.
        $latest = [];
        foreach ($lines as $line) {
            $latest[(int) $line->product_id] = (int) $line->item_id;
        }
        foreach ($lines as $line) {
            $productId = (int) $line->product_id;
            if ($latest[$productId] === (int) $line->item_id) {
                $stocked[$productId] = ($stocked[$productId] ?? 0) + max(0, (int) $line->qty + (int) $line->actual_qty);
                $since[$productId] = (string) $line->completed_at;
            }
        }
        if ($stocked === []) {
            return [];
        }

        $ledger = app(FreezerStockLedger::class);
        $out = [];
        VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->where('created_at', '>=', min($since))
            ->where('created_at', '<', $at)
            ->when($recognition->vend_transaction_id, fn ($q, $id) => $q->whereKeyNot($id))
            ->orderBy('id')
            ->each(function (VendTransaction $sale) use ($ledger, $since, &$out) {
                foreach ($ledger->unitsOut($sale) as $productId => $units) {
                    if (isset($since[$productId]) && (string) $sale->created_at >= $since[$productId]) {
                        $out[$productId] = ($out[$productId] ?? 0) + $units;
                    }
                }
            });

        $onHand = [];
        foreach ($stocked as $productId => $units) {
            $onHand[$productId] = max(0, $units - ($out[$productId] ?? 0));
        }

        return $onHand;
    }

    /** The door opened at our ref's epoch ("SF-50002-1794663352-16"), else a few minutes before Zijia's row. */
    private static function doorOpened(SmartFreezerRecognition $recognition): ?Carbon
    {
        if (preg_match('/^SF-[^-]+-(\d{10})-/', (string) $recognition->session_ref, $m)) {
            return Carbon::createFromTimestamp((int) $m[1])->setTimezone(config('app.timezone'));
        }

        return $recognition->created_at?->copy()->subMinutes(5);
    }
}
