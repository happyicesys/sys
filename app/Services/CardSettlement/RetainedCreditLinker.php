<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementRow;
use App\Models\VendTransaction;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retained credit, recognised from the NETS report (2026-09-26).
 *
 * A vend fails, the card is charged anyway (a NETS line, no reversal), and
 * the reader keeps the credit. The customer's next selection on that machine
 * is served from it:
 *
 *  - RE-VEND: same amount — a dispensed card sale with NO line of its own
 *    (4177, 2026-09-21: $4.60 failed + charged 22:00, $4.60 dispensed 22:01,
 *    one NETS line). 79 in 08-31 → 09-22; three of the failed sales had
 *    already been refunded as "not dispensed" although the customer got it.
 *  - TOP-UP: dearer item — the reader charges only the difference, and that
 *    smaller line is the sale's (5073: $1.70 failed + charged 22:02, $2.40
 *    dispensed 22:06, a $0.70 line at 22:06). Paired by LateTradePairer
 *    (topUpPairs); left alone it became an NA sale and overstated revenue.
 *
 * Either way the dispensed sale is recorded here as what it is — a
 * retained-credit settlement of the failed sale (is_retained_credit_settlement,
 * retained_credit_settles_txn_id), exactly as RetainedCreditSettlementRecorder
 * records the ones the APK can see (TXN_SRC 1 with CSHL_ARMED_MS). The keypad
 * boards (TXN_SRC 0) carry no such field; the NETS report is the evidence.
 * Nothing is written on the failed sale: is_refunded is the report's alone.
 */
class RetainedCreditLinker
{
    /** How long after the failed charge the retained credit is still used. */
    const WINDOW_SECONDS = 900;

    /**
     * Failed card sales on these machines that NETS charged (a matched,
     * unreversed purchase line) and no retained-credit sale consumes yet,
     * dated inside [$from, $until].
     *
     * @param  int[]  $vendIds
     * @return Collection<int, Collection<int, object>> vend_id → failed sales, oldest first
     */
    public function chargedFailures(array $vendIds, CarbonInterface $from, CarbonInterface $until): Collection
    {
        if (! $vendIds) {
            return collect();
        }

        return DB::table('vend_transactions as p')
            ->join('card_settlement_rows as r', function ($j) {
                $j->on('r.matched_vend_transaction_id', '=', 'p.id')
                    ->where('r.is_reversal', false)
                    ->whereNull('r.reversed_by_row_id')
                    ->where('r.status', CardSettlementRow::STATUS_MATCHED);
            })
            ->leftJoin('vend_channel_errors as e', 'e.id', '=', 'p.vend_channel_error_id')
            ->whereIn('p.vend_id', $vendIds)
            ->whereBetween('p.transaction_datetime', [$from, $until])
            ->where('p.is_found_in_transaction', true)
            ->where('p.is_multiple', false)
            ->whereRaw(DispenseVerdict::sqlFaultById('p.vend_channel_error_id', 'e.code'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('vend_transactions as c')->whereColumn('c.retained_credit_settles_txn_id', 'p.id'))
            ->orderBy('p.transaction_datetime')
            ->get(['p.id', 'p.vend_id', 'p.amount', 'p.transaction_datetime'])
            ->groupBy('vend_id');
    }

    /** Record `$sale` as served from `$failed`'s retained credit. */
    public function link(int $saleId, int $failedId, string $how): void
    {
        VendTransaction::withoutGlobalScopes()->whereKey($saleId)->update([
            'is_retained_credit_settlement' => true,
            'retained_credit_settles_txn_id' => $failedId,
            // The reconciler never classifies a retained-credit row; a stale
            // "not_captured" would keep its "No line in NETS" badge.
            'card_settlement_state' => null,
        ]);
        Log::info('RetainedCreditLinker: '.$how, ['sale_id' => $saleId, 'failed_sale_id' => $failedId]);
    }

    /**
     * RE-VENDS on days NETS has finalised (a later file can still carry the
     * sale's own line before then): a dispensed single card sale with no
     * line, on a machine whose failed sale of the SAME amount was charged
     * within WINDOW_SECONDS before it. Nearest failure first, one sale each.
     *
     * @param  callable(CarbonInterface): bool  $isFinal
     * @return array<int, int> sale id → failed sale id
     */
    public function linkRevends(CarbonInterface $from, CarbonInterface $until, callable $isFinal, bool $apply = true): array
    {
        $sales = DB::table('vend_transactions as s')
            ->leftJoin('vend_channel_errors as e', 'e.id', '=', 's.vend_channel_error_id')
            ->join('payment_methods as pm', 'pm.id', '=', 's.payment_method_id')
            ->where('pm.code', \App\Models\PaymentMethod::CODE_CARD_TERMINAL)
            ->whereNull('pm.payment_gateway_id')
            ->whereBetween('s.transaction_datetime', [$from, $until])
            ->where('s.is_found_in_transaction', true)
            ->where('s.is_multiple', false)
            ->where('s.is_retained_credit_settlement', false)
            ->where('s.success_qty', '>', 0)
            ->where(fn ($q) => $q->whereNull('s.vend_channel_error_id')->orWhereIn('e.code', DispenseVerdict::DISPENSED_CODES))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('card_settlement_rows as r')->whereColumn('r.matched_vend_transaction_id', 's.id'))
            ->orderBy('s.transaction_datetime')
            ->get(['s.id', 's.vend_id', 's.amount', 's.transaction_datetime']);

        $sales = $sales->filter(fn ($s) => $isFinal(Carbon::parse($s->transaction_datetime)->startOfDay()));
        if ($sales->isEmpty()) {
            return [];
        }

        $failures = $this->chargedFailures(
            $sales->pluck('vend_id')->unique()->values()->all(),
            Carbon::parse($from)->subSeconds(self::WINDOW_SECONDS),
            $until
        );

        $linked = [];
        $used = [];
        foreach ($sales as $s) {
            $at = Carbon::parse($s->transaction_datetime);
            $failed = collect($failures->get($s->vend_id) ?? [])
                ->filter(fn ($p) => ! isset($used[$p->id]) && (int) $p->amount === (int) $s->amount)
                ->filter(function ($p) use ($at) {
                    $lag = $at->getTimestamp() - Carbon::parse($p->transaction_datetime)->getTimestamp();

                    return $lag > 0 && $lag <= self::WINDOW_SECONDS;
                })
                ->sortByDesc('transaction_datetime')
                ->first();
            if (! $failed) {
                continue;
            }
            $used[$failed->id] = true;
            $linked[$s->id] = $failed->id;
            if ($apply) {
                $this->link($s->id, $failed->id, 're-vend');
            }
        }

        return $linked;
    }

    /** Sales that consumed each of these failed sales' retained credit (failed id → sale). */
    public static function consumersOf(array $failedIds): Collection
    {
        if (! $failedIds) {
            return collect();
        }

        return VendTransaction::withoutGlobalScopes()
            ->whereIn('retained_credit_settles_txn_id', $failedIds)
            ->where('is_retained_credit_settlement', true)
            ->get(['id', 'order_id', 'transaction_datetime', 'amount', 'retained_credit_settles_txn_id'])
            ->keyBy('retained_credit_settles_txn_id');
    }
}
