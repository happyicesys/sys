<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\VendTransaction;
use App\Services\Refund\RefundTicketService;
use App\Support\AutoRefundSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The NETS settlement report is the ONLY source of truth for the auto-refund
 * tick (vend_transactions.is_refunded) on card-terminal sales (Brian,
 * 2026-09-08). Nothing mark1 deduces from a TRADE frame or a machine signal
 * may set it any more — the TRADE-time inference (card_terminal_reversal)
 * was removed the same day — and whatever the report says overrides what an
 * earlier writer left behind.
 *
 * For each calendar day D this applies four rules to the card sales dated D:
 *
 *   1. purchase line paired with a "Reversal Code = Y" line (synced report)
 *        → is_refunded = 1, source = settlement_report_reversal
 *          (an inference-era card_terminal_reversal is relabelled: the
 *          report confirmed it, so the row now says who really proved it);
 *   2. purchase line, no reversal (synced report)
 *        → the customer WAS charged and NOT refunded: is_refunded cleared;
 *   3. no line at all, terminal bound on D, day final
 *        → no money was taken on this sale: is_refunded cleared;
 *   4. no line, terminal NOT bound on D
 *        → the report cannot say anything: left alone, counted as skipped.
 *
 * "Day final" — rules 2 and 3 CLEAR a tick, so they wait until the report
 * cannot change any more: a sale on day D lands in the file cut on D or,
 * when NETS captured it late (post-22:15 cutover, store-and-forward), in the
 * file cut on D+1, so both reports D and D+1 must be synced. Rule 1 is
 * positive evidence and applies as soon as the report holding the line is
 * synced. Rule 2 also waits for D+1 because the reversal line can sit in the
 * next day's file while the purchase sits in this one.
 *
 * Only ticks this mechanism owns are ever cleared: sources
 * card_terminal_reversal (inference) and settlement_report_reversal (an
 * earlier sync whose pairing since changed, e.g. the reversal's report was
 * deleted). Omise / Midtrans sources never appear on card-terminal sales;
 * retained_credit_revend is the documented "made whole by goods" exception
 * and is not touched here.
 *
 * Every clear also releases the refund ticket the tick had auto-crossed
 * (RefundTicketService::clearAutoRefundByCharge), the mirror of the
 * markAutoRefundedByCharge call rule 1 makes — a customer whose money the
 * report proves is still with us must be claimable again.
 *
 * Idempotent: running it twice over the same synced reports changes nothing
 * the second time. Runs withoutGlobalScopes (queued / console, no viewer).
 */
class CardSettlementRefundReconciler
{
    /** Sources this reconciler owns and may clear. */
    public const OWNED_SOURCES = [
        AutoRefundSource::CARD_TERMINAL_REVERSAL,
        AutoRefundSource::SETTLEMENT_REPORT_REVERSAL,
    ];

    public const STATE_REVERSED = 'reversed';          // captured, then reversed → refunded

    public const STATE_CAPTURED = 'captured';          // captured, no reversal → charged

    public const STATE_NOT_CAPTURED = 'not_captured';  // bound terminal, day final, no line

    public const STATE_PENDING_REVIEW = 'pending_review'; // line exists, report not synced yet

    public const STATE_NO_REPORT = 'no_report';        // day not final yet

    public const STATE_UNBOUND = 'unbound';            // no terminal binding on that day

    public function __construct(protected RefundTicketService $tickets) {}

    /**
     * The calendar days a report can finalise: its own cutover day and the one
     * before (whose late captures it carries). Empty when the report has no
     * cutover date (parser failure / fixture).
     *
     * @return Carbon[]
     */
    public static function daysCoveredBy(CardSettlementReport $report): array
    {
        if (! $report->cutover_date) {
            return [];
        }
        $day = Carbon::parse($report->cutover_date)->startOfDay();

        return [$day->copy()->subDay(), $day];
    }

    /**
     * Day D is final once the reports cut on D and D+1 are both synced.
     */
    public function isDayFinal(CarbonInterface $day): bool
    {
        $d = $day->toDateString();
        $next = $day->copy()->addDay()->toDateString();

        $synced = CardSettlementReport::query()
            ->where('status', CardSettlementReport::STATUS_SYNCED)
            ->whereIn('cutover_date', [$d, $next])
            ->pluck('cutover_date')
            ->map(fn ($c) => Carbon::parse($c)->toDateString())
            ->unique();

        return $synced->contains($d) && $synced->contains($next);
    }

    /**
     * Reconcile every card sale dated $day. Dry-run when $apply is false.
     *
     * @return array{day:string,final:bool,candidates:int,confirmed:int,relabelled:int,cleared_captured:int,cleared_not_captured:int,skipped_unbound:int,skipped_not_final:int,tickets_crossed:int,tickets_released:int}
     */
    public function reconcileDay(CarbonInterface $day, bool $apply = true): array
    {
        $from = $day->copy()->startOfDay();
        $to = $from->copy()->addDay();
        $final = $this->isDayFinal($from);

        $stats = [
            'day' => $from->toDateString(),
            'final' => $final,
            'candidates' => 0,
            'confirmed' => 0,          // rule 1, tick newly set
            'relabelled' => 0,         // rule 1, inference tick relabelled
            'cleared_captured' => 0,   // rule 2
            'cleared_not_captured' => 0, // rule 3
            'skipped_unbound' => 0,    // rule 4
            'skipped_not_final' => 0,  // rules 2/3 deferred
            'tickets_crossed' => 0,
            'tickets_released' => 0,
        ];

        // Candidates: (a) every sale carrying a tick this reconciler owns,
        // (b) every sale a synced report pairs with a reversal. (b) is what
        // sets a tick; (a) is what may lose one.
        $owned = VendTransaction::query()
            ->withoutGlobalScopes()
            ->where('transaction_datetime', '>=', $from)
            ->where('transaction_datetime', '<', $to)
            ->where('is_refunded', true)
            ->whereIn('auto_refund_source', self::OWNED_SOURCES)
            ->pluck('id');

        $reversedIds = CardSettlementRow::query()
            ->join('card_settlement_reports as rep', 'rep.id', '=', 'card_settlement_rows.card_settlement_report_id')
            ->join('vend_transactions as vt', 'vt.id', '=', 'card_settlement_rows.matched_vend_transaction_id')
            ->where('rep.status', CardSettlementReport::STATUS_SYNCED)
            ->where('card_settlement_rows.status', CardSettlementRow::STATUS_MATCHED)
            ->where('card_settlement_rows.is_reversal', false)
            ->whereNotNull('card_settlement_rows.reversed_by_row_id')
            ->where('vt.transaction_datetime', '>=', $from)
            ->where('vt.transaction_datetime', '<', $to)
            ->pluck('card_settlement_rows.matched_vend_transaction_id');

        $ids = $owned->merge($reversedIds)->unique()->values();
        $stats['candidates'] = $ids->count();
        if ($ids->isEmpty()) {
            return $stats;
        }

        $sales = VendTransaction::query()->withoutGlobalScopes()->whereIn('id', $ids)->get();
        $lines = $this->linesFor($ids->all());
        $boundVendIds = $this->boundVendIds($from);

        foreach ($sales as $sale) {
            $line = $lines->get($sale->id);
            $state = $this->classify($line, $final, $boundVendIds->contains((int) $sale->vend_id));

            switch ($state) {
                case self::STATE_REVERSED:
                    if ($sale->is_refunded && $sale->auto_refund_source === AutoRefundSource::SETTLEMENT_REPORT_REVERSAL) {
                        break; // already what the report says
                    }
                    $wasTicked = (bool) $sale->is_refunded;
                    $stats[$wasTicked ? 'relabelled' : 'confirmed']++;
                    if ($apply) {
                        $sale->forceFill([
                            'is_refunded' => true,
                            'auto_refund_source' => AutoRefundSource::SETTLEMENT_REPORT_REVERSAL,
                        ])->save();
                        if (! $wasTicked) {
                            $stats['tickets_crossed'] += $this->crossTickets($sale);
                        }
                    }
                    break;

                case self::STATE_CAPTURED:
                    if (! $final) {
                        $stats['skipped_not_final']++;
                        break;
                    }
                    if ($this->ownsTick($sale)) {
                        $stats['cleared_captured']++;
                        if ($apply) {
                            $stats['tickets_released'] += $this->clear($sale, 'NETS report shows the charge captured and never reversed');
                        }
                    }
                    break;

                case self::STATE_NOT_CAPTURED:
                    if ($this->ownsTick($sale)) {
                        $stats['cleared_not_captured']++;
                        if ($apply) {
                            $stats['tickets_released'] += $this->clear($sale, 'NETS report has no capture for this sale');
                        }
                    }
                    break;

                case self::STATE_UNBOUND:
                    if ($this->ownsTick($sale)) {
                        $stats['skipped_unbound']++;
                    }
                    break;

                default: // pending_review / no_report — nothing final to act on
                    if ($this->ownsTick($sale)) {
                        $stats['skipped_not_final']++;
                    }
                    break;
            }
        }

        return $stats;
    }

    /**
     * What the NETS report says about one sale — the same classification the
     * reconciler acts on, for the refund-ticket page.
     *
     * @return array{state:string,label:string,detail:string,report_id:int|null,final:bool}
     */
    public function verdictFor(VendTransaction $sale): array
    {
        $day = Carbon::parse($sale->transaction_datetime)->startOfDay();
        $final = $this->isDayFinal($day);
        $line = $this->linesFor([$sale->id])->get($sale->id);
        $bound = $this->boundVendIds($day)->contains((int) $sale->vend_id);
        $state = $this->classify($line, $final, $bound);

        $labels = [
            self::STATE_REVERSED => ['Reversed', 'The NETS report carries a Reversal Code = Y line for this sale: the terminal returned the money.'],
            self::STATE_CAPTURED => ['Captured, not reversed', 'The NETS report captured this charge and carries no reversal for it: the customer was charged and has not been refunded.'],
            self::STATE_NOT_CAPTURED => ['Not captured', 'Both NETS files that could carry this sale are synced and neither has a line for it: no money was taken on this sale.'],
            self::STATE_PENDING_REVIEW => ['In review', 'The NETS report has a line for this sale but that report has not been synced yet.'],
            self::STATE_NO_REPORT => ['No report yet', 'The NETS files for this day and the next are not both synced yet, so the report cannot rule on this sale.'],
            self::STATE_UNBOUND => ['Terminal not bound', 'No card terminal was bound to this machine on that day, so its NETS lines cannot be matched.'],
        ];
        [$label, $detail] = $labels[$state];

        return [
            'state' => $state,
            'label' => $label,
            'detail' => $detail,
            'report_id' => $line?->card_settlement_report_id,
            'final' => $final,
        ];
    }

    /**
     * @param  object|null  $line  matched purchase line (+ report_status) or null
     */
    protected function classify(?object $line, bool $final, bool $bound): string
    {
        if ($line) {
            if ($line->report_status !== CardSettlementReport::STATUS_SYNCED) {
                return self::STATE_PENDING_REVIEW;
            }

            return $line->reversed_by_row_id ? self::STATE_REVERSED : self::STATE_CAPTURED;
        }
        if (! $bound) {
            return self::STATE_UNBOUND;
        }

        return $final ? self::STATE_NOT_CAPTURED : self::STATE_NO_REPORT;
    }

    /** Matched purchase lines for these sales, keyed by sale id, with their report's status. */
    protected function linesFor(array $txnIds)
    {
        return CardSettlementRow::query()
            ->join('card_settlement_reports as rep', 'rep.id', '=', 'card_settlement_rows.card_settlement_report_id')
            ->whereIn('card_settlement_rows.matched_vend_transaction_id', $txnIds)
            ->where('card_settlement_rows.status', CardSettlementRow::STATUS_MATCHED)
            ->where('card_settlement_rows.is_reversal', false)
            ->get([
                'card_settlement_rows.id',
                'card_settlement_rows.card_settlement_report_id',
                'card_settlement_rows.matched_vend_transaction_id',
                'card_settlement_rows.reversed_by_row_id',
                'rep.status as report_status',
            ])
            ->keyBy('matched_vend_transaction_id');
    }

    /** Machines with a NETS terminal binding effective on that day. */
    protected function boundVendIds(CarbonInterface $day)
    {
        return CardTerminalBinding::query()
            ->effectiveOn($day->toDateString())
            ->pluck('vend_id')
            ->map(fn ($id) => (int) $id);
    }

    protected function ownsTick(VendTransaction $sale): bool
    {
        return (bool) $sale->is_refunded && in_array($sale->auto_refund_source, self::OWNED_SOURCES, true);
    }

    /** Clear a tick the report contradicts and release the ticket it had crossed. */
    protected function clear(VendTransaction $sale, string $why): int
    {
        $previous = $sale->auto_refund_source;
        $sale->forceFill(['is_refunded' => false, 'auto_refund_source' => null])->save();

        Log::info('Card settlement reconcile: auto-refund tick cleared', [
            'vend_transaction_id' => $sale->id,
            'order_id' => $sale->order_id,
            'previous_source' => $previous,
            'reason' => $why,
        ]);

        try {
            return $this->tickets->clearAutoRefundByCharge($sale->order_id, null, $sale->id, $why);
        } catch (Throwable $e) {
            Log::error('Refund ticket release after settlement reconcile failed', [
                'vend_transaction_id' => $sale->id,
                'order_id' => $sale->order_id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    protected function crossTickets(VendTransaction $sale): int
    {
        try {
            return $this->tickets->markAutoRefundedByCharge($sale->order_id, null, $sale->id);
        } catch (Throwable $e) {
            Log::error('Refund ticket auto-resolve after settlement reconcile failed', [
                'vend_transaction_id' => $sale->id,
                'order_id' => $sale->order_id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
