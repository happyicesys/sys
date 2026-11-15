<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\OpsJob;
use App\Models\SmartFreezerRecognition;
use App\Models\VendTransaction;
use App\Services\Freezer\FreezerStockLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * About how many units of each product the freezer held when this session's door opened, from our own stock records
 * (Brian, 2026-11-15), worked BACK from today's count: today's units (`vend_channels.qty`, every section of the
 * product), less what Stock Ins added since the door opened, plus every unit that left since — this session's own,
 * the AI's corrections included (FreezerStockLedger::unitsOut). Working back covers a freezer stocked without an ops
 * job too (50002 has none).
 *
 * Why: on #154 Claude and Fable both read section 32 as "almost empty" after six cones left, while our records had
 * about 27 there: the camera sees only the front layer, so a gap is no proof of a count. It is a hint only: it says
 * nothing about what was paid for (what is left after the session is never sent), and a count can be a few out.
 */
final class StockOnHand
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, int> product id => units, only for products the freezer carries now
     */
    public static function before(SmartFreezerRecognition $recognition, array $productIds): array
    {
        $vend = $recognition->vend;
        $at = self::doorOpened($recognition);
        if ($vend === null || $at === null || $productIds === []) {
            return [];
        }

        $units = [];
        foreach (DB::table('vend_channels')->where('vend_id', $vend->id)->where('is_active', true)->whereIn('product_id', $productIds)
            ->get(['product_id', 'qty']) as $channel) {
            $units[(int) $channel->product_id] = ($units[(int) $channel->product_id] ?? 0) + max(0, (int) $channel->qty);
        }
        if ($units === []) {
            return [];
        }

        // Stock In since the door opened: what each one actually moved (ledger_applied_qty, else its refill).
        foreach (DB::table('ops_job_item_channels as c')
            ->join('ops_job_items as oji', 'oji.id', '=', 'c.ops_job_item_id')
            ->where('oji.vend_id', $vend->id)
            ->whereIn('c.product_id', array_keys($units))
            ->where('oji.status', '>=', OpsJob::STATUS_DELIVERED)
            ->where('oji.status', '<>', OpsJob::STATUS_CANCELLED)
            ->where('oji.completed_at', '>', $at)
            ->get(['c.product_id', 'c.actual_qty', 'c.ledger_applied_qty']) as $line) {
            $units[(int) $line->product_id] -= (int) ($line->ledger_applied_qty ?? $line->actual_qty);
        }

        // Every unit that left since, this session's own included (its TRADE lands when the lid closes).
        $ledger = app(FreezerStockLedger::class);
        VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $vend->id)
            ->where('created_at', '>=', $at)
            ->orderBy('id')
            ->each(function (VendTransaction $sale) use ($ledger, &$units) {
                foreach ($ledger->unitsOut($sale) as $productId => $out) {
                    if (isset($units[$productId])) {
                        $units[$productId] += $out;
                    }
                }
            });

        return array_map(fn (int $n) => max(0, $n), $units);
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
