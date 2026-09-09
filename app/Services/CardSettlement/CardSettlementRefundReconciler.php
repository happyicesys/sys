<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\PaymentMethod;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\Refund\RefundTicketService;
use App\Support\AutoRefundSource;
use App\Support\DispenseVerdict;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
 * For each calendar day D this classifies EVERY card-terminal sale dated D
 * against the synced reports and applies:
 *
 *   1. purchase line paired with a "Reversal Code = Y" line (synced report)
 *        → is_refunded = 1, source = settlement_report_reversal
 *          (an inference-era card_terminal_reversal is relabelled); a Part 2
 *          orphan (sale created from a line, no TRADE) is also set REFUNDED
 *          so it leaves revenue — its TRADE-less row has no other writer;
 *   2. purchase line, no reversal (synced report)
 *        → the customer WAS charged and NOT refunded: owned tick cleared;
 *   3. no line at all, terminal bound on D and fully covered by the report,
 *      day final
 *        → "not captured": no money was taken on this sale.
 *          3a. FAILED single-item sale on a terminal flagged
 *              `is_will_auto_refund` → the terminal voided the approval
 *              before batch upload (the only way a Visa/MasterCard failure is
 *              ever made good): is_refunded = 1, source
 *              settlement_report_not_captured — "NA in NETS" (Brian,
 *              2026-09-09). Never for a dispensed sale, a multiple, or a
 *              terminal flagged No / Unknown (those go to the verify list).
 *          3b. otherwise an owned tick is cleared;
 *   4. no line, terminal bound but its company's sales are only PARTLY in
 *      the file (Nets-Auresys, config card_settlement.report_coverage_gap_companies)
 *        → `uncovered`: the report cannot say anything, tick left alone;
 *   5. no line, terminal NOT bound on D
 *        → `unbound`: left alone.
 *
 * The verdict is PERSISTED in vend_transactions.card_settlement_state
 * (reversed as soon as its report is synced; captured / not_captured /
 * uncovered / unbound once the day is final; NULL until then) so per-terminal
 * statistics and the liabilities list are a GROUP BY, not a 4-table join.
 * One UPDATE per distinct state per day, only for rows whose state changed.
 *
 * "Day final" — rules 2–5 CLEAR or persist, so they wait until the report
 * cannot change any more: a sale on day D lands in the file cut on D or,
 * when NETS captured it late (post-22:15 cutover, store-and-forward), in the
 * file cut on D+1, so both reports D and D+1 must be synced. Rule 1 is
 * positive evidence and applies as soon as the report holding the line is
 * synced.
 *
 * Only ticks this mechanism owns are ever cleared: sources
 * card_terminal_reversal (inference), settlement_report_reversal and
 * settlement_report_not_captured. Omise / Midtrans sources never appear on
 * card-terminal sales; retained_credit_revend is the documented "made whole
 * by goods" exception and is not touched here.
 *
 * Every tick set also crosses the open refund ticket
 * (RefundTicketService::markAutoRefundedByCharge) and every clear releases
 * it (clearAutoRefundByCharge).
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
        AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED,
    ];

    public const STATE_REVERSED = 'reversed';          // captured, then reversed → refunded

    public const STATE_CAPTURED = 'captured';          // captured, no reversal → charged

    public const STATE_NOT_CAPTURED = 'not_captured';  // bound + covered terminal, day final, no line

    public const STATE_UNCOVERED = 'uncovered';        // bound terminal the report only partly carries

    public const STATE_PENDING_REVIEW = 'pending_review'; // line exists, report not synced yet

    public const STATE_NO_REPORT = 'no_report';        // day not final yet

    public const STATE_UNBOUND = 'unbound';            // no terminal binding on that day

    /** States persisted on the sale (the rest are transient). */
    public const PERSISTED_STATES = [
        self::STATE_REVERSED, self::STATE_CAPTURED, self::STATE_NOT_CAPTURED, self::STATE_UNCOVERED, self::STATE_UNBOUND,
    ];

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
     * @return array<string, int|string|bool>
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
            'confirmed' => 0,            // rule 1, tick newly set
            'relabelled' => 0,           // rule 1, inference tick relabelled
            'orphans_refunded' => 0,     // rule 1, orphan row set REFUNDED
            'cleared_captured' => 0,     // rule 2
            'ticked_not_captured' => 0,  // rule 3a, "NA in NETS"
            'cleared_not_captured' => 0, // rule 3b
            'skipped_uncovered' => 0,    // rule 4, tick carried
            'skipped_unbound' => 0,      // rule 5, tick carried
            'skipped_not_final' => 0,    // rules 2–5 deferred
            'states_written' => 0,
            'tickets_crossed' => 0,
            'tickets_released' => 0,
        ];

        $sales = $this->salesOn($from, $to);
        $stats['candidates'] = $sales->count();
        if ($sales->isEmpty()) {
            return $stats;
        }

        $lines = $this->linesFor($sales->pluck('id')->all());
        $terminals = $this->terminalsByVend($from);
        $codes = VendChannelError::query()->pluck('code', 'id');

        $stateChanges = []; // state → [ids]

        foreach ($sales as $sale) {
            $line = $lines->get($sale->id);
            $unit = $terminals->get((int) $sale->vend_id);
            $state = $this->classify($line, $final, $unit !== null, $unit ? ! $unit->hasReportCoverageGap() : false);

            switch ($state) {
                case self::STATE_REVERSED:
                    $this->applyReversed($sale, $apply, $stats);
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
                    $this->applyNotCaptured($sale, $unit, $codes, $apply, $stats);
                    break;

                case self::STATE_UNCOVERED:
                    if ($this->ownsTick($sale)) {
                        $stats['skipped_uncovered']++;
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

            // Reversed is positive evidence and lands at once; captured / not
            // captured / uncovered / unbound only once the day is final (the
            // next day's file may still carry the reversal or the late capture).
            $persist = $state === self::STATE_REVERSED || ($final && in_array($state, self::PERSISTED_STATES, true));
            if ($persist && $sale->card_settlement_state !== $state) {
                $stateChanges[$state][] = $sale->id;
            }
        }

        foreach ($stateChanges as $state => $ids) {
            $stats['states_written'] += count($ids);
            if ($apply) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    VendTransaction::query()->withoutGlobalScopes()->whereIn('id', $chunk)->update(['card_settlement_state' => $state]);
                }
            }
        }

        return $stats;
    }

    /** Rule 1. */
    protected function applyReversed(VendTransaction $sale, bool $apply, array &$stats): void
    {
        // An orphan (no TRADE) whose line is reversed leaves revenue: nothing
        // else will ever write its settlement_status.
        $orphanToRefund = $sale->isSettlementOrphan() && (int) $sale->settlement_status !== VendTransaction::SETTLEMENT_REFUNDED;
        if ($orphanToRefund) {
            $stats['orphans_refunded']++;
            if ($apply) {
                $sale->forceFill(['settlement_status' => VendTransaction::SETTLEMENT_REFUNDED])->save();
            }
        }

        if ($sale->is_refunded && $sale->auto_refund_source === AutoRefundSource::SETTLEMENT_REPORT_REVERSAL) {
            return; // already what the report says
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
    }

    /** Rule 3: the day is final, the terminal is bound and covered, and no line carries this sale. */
    protected function applyNotCaptured(VendTransaction $sale, CardTerminalUnit $unit, Collection $codes, bool $apply, array &$stats): void
    {
        if ($this->isVoidableFailure($sale, $codes) && $unit->willAutoRefund() === true) {
            if ($sale->is_refunded && $sale->auto_refund_source === AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED) {
                return; // already ticked by this rule
            }
            $stats['ticked_not_captured']++;
            if ($apply) {
                $sale->forceFill([
                    'is_refunded' => true,
                    'auto_refund_source' => AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED,
                ])->save();
                $stats['tickets_crossed'] += $this->crossTickets($sale);
            }

            return;
        }

        if ($this->ownsTick($sale)) {
            $stats['cleared_not_captured']++;
            if ($apply) {
                $stats['tickets_released'] += $this->clear($sale, 'NETS report has no capture for this sale');
            }
        }
    }

    /**
     * A single-item sale the machine reported as a FAULT (DispenseVerdict) —
     * the only shape a terminal voids by itself. Multiples never void; a sale
     * with no TRADE has no verdict; a dispensed sale with no line is a loss to
     * list, not a refund.
     */
    protected function isVoidableFailure(VendTransaction $sale, Collection $codes): bool
    {
        if ($sale->vend_channel_error_id === null) {
            return false;
        }

        return self::isVoidableShape(
            (bool) $sale->is_multiple,
            (bool) $sale->is_found_in_transaction,
            $codes->get((int) $sale->vend_channel_error_id)
        );
    }

    /**
     * The shape of a sale a terminal can void by itself, without needing the
     * row: a single item the machine reported as a FAULT. Pure, so the Sales
     * Transactions grid can ask the same question of a row it has already
     * selected ("NA in NETS" badge) instead of spelling the rule again.
     */
    public static function isVoidableShape(bool $isMultiple, bool $isFoundInTransaction, int|string|null $errorCode): bool
    {
        return ! $isMultiple && $isFoundInTransaction && DispenseVerdict::isMachineFault($errorCode);
    }

    /**
     * What the NETS report says about one sale — the same classification the
     * reconciler acts on, for the refund-ticket page, plus the bound
     * terminal's auto-refund flag so ops know whether to wait for the report.
     *
     * @return array{state:string,label:string,detail:string,report_id:int|null,final:bool,terminal:array|null}
     */
    public function verdictFor(VendTransaction $sale): array
    {
        $day = Carbon::parse($sale->transaction_datetime)->startOfDay();
        $final = $this->isDayFinal($day);
        $line = $this->linesFor([$sale->id])->get($sale->id);
        $unit = $this->terminalsByVend($day)->get((int) $sale->vend_id);
        $state = $this->classify($line, $final, $unit !== null, $unit ? ! $unit->hasReportCoverageGap() : false);

        $labels = [
            self::STATE_REVERSED => ['Reversed', 'The NETS report carries a Reversal Code = Y line for this sale: the terminal returned the money.'],
            self::STATE_CAPTURED => ['Captured, not reversed', 'The NETS report captured this charge and carries no reversal for it: the customer was charged and has not been refunded.'],
            self::STATE_NOT_CAPTURED => ['Not captured', 'Both NETS files that could carry this sale are synced and neither has a line for it: no money was taken on this sale.'],
            self::STATE_UNCOVERED => ['Not in report (partial coverage)', 'This terminal\'s company settles only part of its sales through the NETS file, so a missing line proves nothing.'],
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
            'terminal' => $unit ? [
                'terminal_id' => $unit->terminal_id,
                'batch' => $unit->batch,
                'will_auto_refund' => $unit->willAutoRefund(),
            ] : null,
        ];
    }

    /**
     * @param  object|null  $line  matched purchase line (+ report_status) or null
     * @param  bool  $covered  the bound terminal's sales are fully in the report
     */
    protected function classify(?object $line, bool $final, bool $bound, bool $covered): string
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
        if (! $final) {
            return self::STATE_NO_REPORT;
        }

        return $covered ? self::STATE_NOT_CAPTURED : self::STATE_UNCOVERED;
    }

    /**
     * The card-terminal sales dated [$from, $to): every Card Terminal sale
     * that is not a retained-credit settlement (no card was presented, no
     * line can ever exist), plus any sale still carrying a tick this
     * reconciler owns (legacy rows on other payment methods).
     */
    protected function salesOn(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $cardMethodIds = PaymentMethod::query()->where('code', PaymentMethod::CODE_CARD_TERMINAL)->pluck('id')->all();

        return VendTransaction::query()
            ->withoutGlobalScopes()
            ->where('transaction_datetime', '>=', $from)
            ->where('transaction_datetime', '<', $to)
            ->where('is_retained_credit_settlement', false)
            ->where(fn ($q) => $q
                ->whereIn('payment_method_id', $cardMethodIds)
                ->orWhere(fn ($qq) => $qq->where('is_refunded', true)->whereIn('auto_refund_source', self::OWNED_SOURCES)))
            ->get([
                'id', 'order_id', 'vend_id', 'amount', 'is_multiple', 'is_found_in_transaction', 'vend_channel_error_id',
                'is_refunded', 'auto_refund_source', 'settlement_status', 'card_settlement_row_id', 'card_settlement_state',
                'payment_gateway_log_id',
            ]);
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

    /**
     * vend_id → the CardTerminalUnit bound to it on that day (company loaded).
     * A binding whose terminal has no unit row still counts as bound: it gets
     * a unit shell with no company and no flag (unknown), never "unbound".
     *
     * @return Collection<int, CardTerminalUnit>
     */
    protected function terminalsByVend(CarbonInterface $day): Collection
    {
        $bindings = CardTerminalBinding::query()
            ->effectiveOn($day->toDateString())
            ->orderBy('id')
            ->get(['vend_id', 'terminal_id'])
            ->unique('vend_id');

        $units = CardTerminalUnit::query()
            ->with('company')
            ->whereIn('terminal_id', $bindings->pluck('terminal_id')->unique())
            ->get()
            ->keyBy('terminal_id');

        return $bindings->mapWithKeys(function ($b) use ($units) {
            $unit = $units->get($b->terminal_id) ?? (new CardTerminalUnit)->forceFill(['terminal_id' => $b->terminal_id]);

            return [(int) $b->vend_id => $unit];
        });
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
