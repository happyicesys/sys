<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Matches a settlement report's rows to vend_transactions.
 *
 * A card sale carries no acquirer reference (the TRADE frame has no
 * RRN/STAN/TID), so the join is: terminal binding (TID → vend, effective on
 * the row's date) + exact amount in cents + a time window. The terminal
 * stamps card-approval time; our TRADE frame lands 10–25 s later, so the
 * report time normally precedes transaction_datetime.
 *
 * Rows from Excel-damaged files carry only mm:ss (hour lost) and match
 * circularly within the hour; when plausible sales exist in more than one
 * hour the row is marked AMBIGUOUS for the user instead of guessing.
 *
 * Claims are unique both ways: fingerprint dedupe stops the same report line
 * being ingested twice, and the UNIQUE index on matched_vend_transaction_id
 * stops two lines claiming one sale.
 *
 * Runs in a queued job (no auth), so vend_transactions is queried
 * withoutGlobalScopes — operator/product visibility scopes are viewer
 * boundaries and must not shape a system-level reconciliation.
 */
class CardSettlementMatcher
{
    /** Observed TRADE-frame lag behind terminal approval time, seconds. */
    const EXPECTED_LAG_SECONDS = 15;

    public function match(CardSettlementReport $report): void
    {
        $rows = $report->rows()
            ->whereIn('status', [
                CardSettlementRow::STATUS_PENDING,
                CardSettlementRow::STATUS_UNMATCHED,
                CardSettlementRow::STATUS_AMBIGUOUS,
            ])
            ->orderBy('row_no')
            ->get();

        if ($rows->isEmpty()) {
            $report->refreshCounts();

            return;
        }

        // 1. Non-purchase events (Logon etc.) never match a sale.
        [$purchases, $nonPurchases] = $rows->partition(
            fn (CardSettlementRow $row) => strcasecmp($row->txn_type, 'Purchase') === 0
        );
        foreach ($nonPurchases as $row) {
            $row->update([
                'status' => CardSettlementRow::STATUS_IGNORED,
                'resolution_note' => 'Non-purchase event',
            ]);
        }

        // 2. Resolve terminal → vend per row (binding effective on the row's date).
        $bindings = CardTerminalBinding::query()
            ->where('provider', $report->provider)
            ->whereIn('terminal_id', $purchases->pluck('terminal_id')->unique())
            ->get()
            ->groupBy('terminal_id');

        $bindingFor = function (CardSettlementRow $row) use ($bindings): ?CardTerminalBinding {
            $date = $row->transaction_date->toDateString();

            return ($bindings->get($row->terminal_id) ?? collect())
                ->first(fn (CardTerminalBinding $b) => ($b->bound_from === null || $b->bound_from->toDateString() <= $date)
                    && ($b->bound_until === null || $b->bound_until->toDateString() >= $date));
        };

        // A reversal is its own report line (negative amount); it never
        // matches a sale directly — it is paired with the purchase line it
        // undoes, after the purchases have been matched.
        [$reversals, $purchases] = $purchases->partition(
            fn (CardSettlementRow $row) => $row->is_reversal || $row->amount_cents < 0
        );

        $resolvable = collect();
        foreach ($purchases as $row) {
            $binding = $bindingFor($row);

            if (! $binding) {
                $row->update([
                    'status' => CardSettlementRow::STATUS_UNMATCHED,
                    'vend_id' => null,
                    'matched_vend_transaction_id' => null,
                    'match_time_delta' => null,
                    'candidates_json' => null,
                    'resolution_note' => 'No terminal binding',
                ]);

                continue;
            }

            $row->vend_id = $binding->vend_id;
            $resolvable->push($row);
        }

        $this->assign($resolvable);

        foreach ($reversals as $row) {
            $row->vend_id = $bindingFor($row)?->vend_id;
            $this->pairReversal($report, $row);
        }

        $report->forceFill(['matched_at' => now(), 'status' => CardSettlementReport::STATUS_REVIEW])->save();
        $report->refreshCounts();
    }

    /**
     * How long after a purchase the reader's reversal line may be stamped.
     * Both lines are stamped by the same terminal clock and the MDB reader
     * reverses within the failed vend cycle — seconds, not minutes — so a wide
     * window only adds risk: with the true purchase line missing, the nearest
     * earlier same-amount sale on that terminal (a different customer who got
     * their goods) would be paired and marked refunded. 5 min covers a slow
     * vend cycle with margin (Brian, 2026-09-02).
     */
    const REVERSAL_WINDOW_SECONDS = 300;

    /**
     * Pair a reversal line with the purchase line it undoes: same terminal,
     * same absolute amount, the latest purchase at or before the reversal
     * time within REVERSAL_WINDOW_SECONDS (the MDB reader reverses within
     * seconds of the failed vend). The purchase may sit in an earlier report
     * (reversal after the cutover) and may itself be unmatched — the pairing
     * is between report lines, so the sale is marked refunded at Sync only
     * when the purchase line has a matched sale.
     */
    protected function pairReversal(CardSettlementReport $report, CardSettlementRow $reversal): void
    {
        $date = $reversal->transaction_date->toDateString();

        $candidates = CardSettlementRow::query()
            ->whereHas('report', fn ($q) => $q->where('provider', $report->provider))
            ->where('terminal_id', $reversal->terminal_id)
            ->where('is_reversal', false)
            ->where('amount_cents', abs($reversal->amount_cents))
            ->whereNull('reversed_by_row_id')
            // A DUPLICATE copy of the purchase (overlapping-cutover re-ingest) has the
            // same time and amount as the original but never a matched sale — pairing
            // with it would leave the real purchase unreversed. Ignored rows are not
            // purchases either.
            ->whereNotIn('status', [CardSettlementRow::STATUS_DUPLICATE, CardSettlementRow::STATUS_IGNORED])
            ->whereBetween('transaction_date', [
                Carbon::parse($date)->subDay()->toDateString(),
                $date,
            ])
            ->where('id', '!=', $reversal->id)
            // Deterministic tie-break: on an equal delta the earliest-ingested line wins.
            ->orderBy('id')
            ->get();

        $best = null;
        $bestDelta = null;
        foreach ($candidates as $purchase) {
            $delta = $this->reversalDelta($purchase, $reversal);
            if ($delta === null) {
                continue;
            }
            if ($bestDelta === null || $delta < $bestDelta) {
                $best = $purchase;
                $bestDelta = $delta;
            }
        }

        if (! $best) {
            $reversal->update([
                'status' => CardSettlementRow::STATUS_UNMATCHED,
                'reverses_row_id' => null,
                'matched_vend_transaction_id' => null,
                'match_time_delta' => null,
                'candidates_json' => null,
                'resolution_note' => 'No original purchase found for reversal',
            ]);

            return;
        }

        $reversal->update([
            'status' => CardSettlementRow::STATUS_MATCHED,
            'reverses_row_id' => $best->id,
            'matched_vend_transaction_id' => null,
            'match_time_delta' => $bestDelta,
            'candidates_json' => null,
            'resolution_note' => 'Reverses row #'.$best->row_no.($best->card_settlement_report_id !== $report->id ? ' of an earlier report' : ''),
        ]);
        $best->update(['reversed_by_row_id' => $reversal->id]);
    }

    /**
     * Seconds from purchase to reversal (≥ 0, ≤ REVERSAL_WINDOW_SECONDS), or
     * null. When either side lost its hour, compare circularly on the same
     * date and cap at 15 minutes — a reversal an hour later is not plausible.
     */
    protected function reversalDelta(CardSettlementRow $purchase, CardSettlementRow $reversal): ?int
    {
        if ($purchase->transaction_time === null || $reversal->transaction_time === null) {
            return null;
        }

        if (! $purchase->time_is_partial && ! $reversal->time_is_partial) {
            $purchaseAt = Carbon::parse($purchase->transaction_date->toDateString().' '.$purchase->transaction_time);
            $reversalAt = Carbon::parse($reversal->transaction_date->toDateString().' '.$reversal->transaction_time);
            $delta = (int) $purchaseAt->diffInSeconds($reversalAt, false);

            return ($delta >= 0 && $delta <= self::REVERSAL_WINDOW_SECONDS) ? $delta : null;
        }

        if ($purchase->transaction_date->toDateString() !== $reversal->transaction_date->toDateString()) {
            return null;
        }

        $delta = ($this->secondOfHour($reversal->transaction_time) - $this->secondOfHour($purchase->transaction_time)) % 3600;
        if ($delta < 0) {
            $delta += 3600;
        }

        return $delta <= 900 ? $delta : null;
    }

    protected function secondOfHour(string $time): int
    {
        [, $i, $s] = explode(':', $time);

        return ((int) $i) * 60 + (int) $s;
    }

    /** @param  Collection<int, CardSettlementRow>  $rows  rows with vend_id resolved */
    protected function assign(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $candidatesByVend = $this->loadCandidates($rows);

        $earlySlack = (int) config('card_settlement.match_early_slack_seconds', 60);
        $lateSlack = (int) config('card_settlement.match_late_slack_seconds', 300);

        // Rows that lost their hour (Excel re-save) are matched by ORDER, not
        // independently: see assignOrdered(). The independent pass below then
        // only sees full-time rows, so its "ambiguous" branch no longer fires
        // in practice (kept for completeness).
        [$partial, $rows] = $rows->partition(fn (CardSettlementRow $row) => $row->time_is_partial);
        if ($partial->isNotEmpty()) {
            $this->assignOrdered($partial, $candidatesByVend, $earlySlack, $lateSlack);
        }
        if ($rows->isEmpty()) {
            return;
        }

        // Build every eligible (row, candidate) pair with its time delta.
        $pairs = [];
        $eligibleByRow = [];
        foreach ($rows as $row) {
            $eligibleByRow[$row->id] = [];
            foreach ($candidatesByVend->get($row->vend_id) ?? [] as $candidate) {
                if ($candidate->amount !== $row->amount_cents) {
                    continue;
                }
                $delta = $this->timeDelta($row, $candidate, $earlySlack, $lateSlack);
                if ($delta === null) {
                    continue;
                }
                $pair = ['row' => $row, 'candidate' => $candidate, 'delta' => $delta];
                $pairs[] = $pair;
                $eligibleByRow[$row->id][] = $pair;
            }
        }

        // Partial-time rows whose plausible sales sit in more than one hour:
        // the circular match cannot tell which hour is right — hand to the user.
        $ambiguousRowIds = [];
        foreach ($eligibleByRow as $rowId => $rowPairs) {
            $row = collect($rowPairs)->first()['row'] ?? null;
            if (! $row || ! $row->time_is_partial) {
                continue;
            }
            $hours = collect($rowPairs)
                ->map(fn ($p) => Carbon::parse($p['candidate']->transaction_datetime)->format('Y-m-d H'))
                ->unique();
            if ($hours->count() > 1) {
                $ambiguousRowIds[$rowId] = true;
                $row->update([
                    'status' => CardSettlementRow::STATUS_AMBIGUOUS,
                    'matched_vend_transaction_id' => null,
                    'match_time_delta' => null,
                    'candidates_json' => $this->candidatesPayload($rowPairs),
                    'resolution_note' => 'Multiple sales fit (hour unknown in report)',
                ]);
            }
        }

        // Greedy global assignment, best (closest to the expected lag) first,
        // so when two report lines compete for two same-amount sales each gets
        // its nearest rather than first-come-first-served.
        usort($pairs, fn ($a, $b) => abs($a['delta'] - self::EXPECTED_LAG_SECONDS) <=> abs($b['delta'] - self::EXPECTED_LAG_SECONDS));

        $assignedRows = [];
        $claimedTxns = [];
        $noSaleOnBoundVend = [];
        foreach ($pairs as $pair) {
            $rowId = $pair['row']->id;
            $txnId = $pair['candidate']->id;
            if (isset($ambiguousRowIds[$rowId]) || isset($assignedRows[$rowId]) || isset($claimedTxns[$txnId])) {
                continue;
            }

            try {
                $pair['row']->update([
                    'status' => CardSettlementRow::STATUS_MATCHED,
                    'matched_vend_transaction_id' => $txnId,
                    'match_time_delta' => $pair['delta'],
                    'candidates_json' => null,
                    'resolution_note' => null,
                ]);
            } catch (QueryException) {
                // Unique index: the sale was claimed by another report between
                // our candidate load and now. Leave the row for the fallback below.
                continue;
            }

            $assignedRows[$rowId] = true;
            $claimedTxns[$txnId] = true;
        }

        foreach ($rows as $row) {
            if (isset($assignedRows[$row->id]) || isset($ambiguousRowIds[$row->id])) {
                continue;
            }
            $hadCandidates = ! empty($eligibleByRow[$row->id]);
            if (! $hadCandidates) {
                $noSaleOnBoundVend[] = $row;

                continue;
            }
            $row->update([
                'status' => CardSettlementRow::STATUS_UNMATCHED,
                'matched_vend_transaction_id' => null,
                'match_time_delta' => null,
                'candidates_json' => $this->candidatesPayload($eligibleByRow[$row->id]),
                // Every fitting sale was taken by another line: NETS charged
                // more times than mark1 recorded here (double tap?).
                'resolution_note' => 'All matching sales already claimed',
            ]);
        }

        if (! empty($noSaleOnBoundVend)) {
            $this->flagSalesOnOtherMachines(collect($noSaleOnBoundVend), $earlySlack, $lateSlack);
        }
    }

    /**
     * Lines with no fitting sale on the machine the terminal is bound to.
     * Before calling that "no sale", look fleet-wide: the same amount at the
     * same moment on ANOTHER machine means the terminal is physically there
     * and the binding sheet is wrong (live case 2026-08-02: TID 23082812
     * bound to 2787, which recorded nothing all day, while its lines fit
     * 2696 to the second). The line stays UNMATCHED — a human moves the
     * binding and rematches — but the note and candidates now say where.
     *
     * @param  Collection<int, CardSettlementRow>  $rows  full-time rows only
     */
    protected function flagSalesOnOtherMachines(Collection $rows, int $earlySlack, int $lateSlack): void
    {
        $from = Carbon::parse($rows->min('transaction_date'))->startOfDay()->subDay();
        $until = Carbon::parse($rows->max('transaction_date'))->endOfDay()->addDay();

        $fleet = VendTransaction::query()
            ->withoutGlobalScopes()
            ->join('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$from, $until])
            ->whereIn('vend_transactions.amount', $rows->pluck('amount_cents')->unique())
            ->whereNotIn('vend_transactions.vend_id', $rows->pluck('vend_id')->unique())
            ->whereNull('payment_methods.payment_gateway_id')
            ->where('payment_methods.code', '>', 0)
            ->where('vend_transactions.is_retained_credit_settlement', false)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('card_settlement_rows')
                    ->whereColumn('card_settlement_rows.matched_vend_transaction_id', 'vend_transactions.id');
            })
            ->get([
                'vend_transactions.id',
                'vend_transactions.vend_id',
                'vend_transactions.transaction_datetime',
                'vend_transactions.amount',
                'vend_transactions.is_refunded',
            ]);

        $vendCodes = \App\Models\Vend::withoutGlobalScopes()
            ->whereIn('id', $fleet->pluck('vend_id')->unique())
            ->pluck('code', 'id');

        foreach ($rows as $row) {
            $hits = [];
            foreach ($fleet as $sale) {
                if ($sale->amount !== $row->amount_cents) {
                    continue;
                }
                $delta = $this->timeDelta($row, $sale, $earlySlack, $lateSlack);
                if ($delta !== null) {
                    $hits[] = ['row' => $row, 'candidate' => $sale, 'delta' => $delta];
                }
            }

            $vendsHit = collect($hits)->pluck('candidate.vend_id')->unique();
            if ($vendsHit->count() === 1) {
                $code = $vendCodes->get($vendsHit->first()) ?? ('#'.$vendsHit->first());
                $row->update([
                    'status' => CardSettlementRow::STATUS_UNMATCHED,
                    'matched_vend_transaction_id' => null,
                    'match_time_delta' => null,
                    'candidates_json' => collect($this->candidatesPayload($hits))
                        ->map(fn ($c) => $c + ['vend_code' => $code, 'other_vend' => true])
                        ->all(),
                    'resolution_note' => 'No matching sale on bound machine — found on machine '.$code,
                ]);

                continue;
            }

            $row->update([
                'status' => CardSettlementRow::STATUS_UNMATCHED,
                'matched_vend_transaction_id' => null,
                'match_time_delta' => null,
                'candidates_json' => null,
                'resolution_note' => 'No matching sale in window',
            ]);
        }
    }

    /**
     * Hour-less rows, matched in sequence.
     *
     * A row that lost its hour still has two things: its mm:ss and its
     * POSITION in the file. The NETS report is written newest-first (verified
     * on a raw file: 0 order violations in 2,872 lines) and an Excel re-save
     * keeps row order, so within one terminal, descending row_no IS the
     * chronological order. Walking a terminal's purchase lines oldest-first,
     * each line's sale must sit at or after the previous line's sale; the
     * EARLIEST unclaimed sale that fits the mm:ss window and that floor is the
     * right one. Offline check on a raw day with the hours stripped: 82/83
     * lines placed, 0 in the wrong hour; the independent rule left 24 of them
     * ambiguous (2026-09-02, terminals 23104097 + 23100721).
     *
     * Lines already matched in an earlier run of this report act as anchors
     * (their sale time becomes the floor) so a Rematch after adding a binding
     * stays consistent with what is already settled.
     *
     * @param  Collection<int, CardSettlementRow>  $rows  unresolved partial-time rows with vend_id set
     */
    protected function assignOrdered(Collection $rows, Collection $candidatesByVend, int $earlySlack, int $lateSlack): void
    {
        $reportId = $rows->first()->card_settlement_report_id;
        $pending = $rows->keyBy('id');

        foreach ($rows->groupBy('terminal_id') as $terminalId => $terminalRows) {
            $vendId = $terminalRows->first()->vend_id;
            $pool = $candidatesByVend->get($vendId) ?? collect();

            $sequence = CardSettlementRow::query()
                ->where('card_settlement_report_id', $reportId)
                ->where('terminal_id', $terminalId)
                ->where('is_reversal', false)
                ->where('txn_type', 'Purchase')
                ->orderByDesc('row_no')
                ->get(['id', 'row_no', 'status', 'matched_vend_transaction_id']);

            $anchorTimes = VendTransaction::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $sequence->pluck('matched_vend_transaction_id')->filter())
                ->pluck('transaction_datetime', 'id');

            $floor = null;
            $claimed = [];
            foreach ($sequence as $line) {
                if ($line->status === CardSettlementRow::STATUS_MATCHED && $line->matched_vend_transaction_id) {
                    $anchor = $anchorTimes->get($line->matched_vend_transaction_id);
                    if ($anchor) {
                        $anchor = Carbon::parse($anchor);
                        $floor = $floor && $floor->gt($anchor) ? $floor : $anchor;
                    }

                    continue;
                }

                $row = $pending->get($line->id);
                if (! $row) {
                    continue;
                }

                $best = null;
                $bestAt = null;
                $bestDelta = null;
                foreach ($pool as $sale) {
                    if (isset($claimed[$sale->id]) || $sale->amount !== $row->amount_cents) {
                        continue;
                    }
                    $delta = $this->timeDelta($row, $sale, $earlySlack, $lateSlack);
                    if ($delta === null) {
                        continue;
                    }
                    $at = Carbon::parse($sale->transaction_datetime);
                    if ($floor && $at->lt($floor->copy()->subSeconds($earlySlack))) {
                        continue;
                    }
                    if ($bestAt === null || $at->lt($bestAt)) {
                        $best = $sale;
                        $bestAt = $at;
                        $bestDelta = $delta;
                    }
                }

                if (! $best) {
                    $row->update([
                        'status' => CardSettlementRow::STATUS_UNMATCHED,
                        'matched_vend_transaction_id' => null,
                        'match_time_delta' => null,
                        'candidates_json' => null,
                        'resolution_note' => 'No matching sale in sequence (hour unknown in report)',
                    ]);

                    continue;
                }

                try {
                    $row->update([
                        'status' => CardSettlementRow::STATUS_MATCHED,
                        'matched_vend_transaction_id' => $best->id,
                        'match_time_delta' => $bestDelta,
                        'candidates_json' => null,
                        'resolution_note' => null,
                    ]);
                } catch (QueryException) {
                    // Claimed by another report between candidate load and now.
                    $claimed[$best->id] = true;
                    $row->update([
                        'status' => CardSettlementRow::STATUS_UNMATCHED,
                        'matched_vend_transaction_id' => null,
                        'match_time_delta' => null,
                        'candidates_json' => null,
                        'resolution_note' => 'All matching sales already claimed',
                    ]);

                    continue;
                }

                $claimed[$best->id] = true;
                $floor = $bestAt;
            }
        }
    }

    /**
     * Card-terminal sales per vend across the report's date span, minus rows
     * that can never settle and sales already claimed by any report.
     *
     * @return Collection<int, Collection<int, object>> vend_id → candidates
     */
    protected function loadCandidates(Collection $rows): Collection
    {
        $vendIds = $rows->pluck('vend_id')->unique()->values();
        $from = Carbon::parse($rows->min('transaction_date'))->startOfDay()->subDay();
        $until = Carbon::parse($rows->max('transaction_date'))->endOfDay()->addDay();

        $candidates = VendTransaction::query()
            ->withoutGlobalScopes()
            ->join('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->whereIn('vend_transactions.vend_id', $vendIds)
            ->whereBetween('vend_transactions.transaction_datetime', [$from, $until])
            // Cashless-terminal rule used across the app: a terminal payment
            // method has no gateway and a non-zero code (cash is code 0).
            ->whereNull('payment_methods.payment_gateway_id')
            ->where('payment_methods.code', '>', 0)
            // Approved from VMC-retained credit — no card presented, no
            // terminal settlement will ever exist for it.
            ->where('vend_transactions.is_retained_credit_settlement', false)
            ->get([
                'vend_transactions.id',
                'vend_transactions.vend_id',
                'vend_transactions.transaction_datetime',
                'vend_transactions.amount',
                'vend_transactions.is_refunded',
                'vend_transactions.card_settlement_synced_at',
            ]);

        $claimed = CardSettlementRow::query()
            ->whereIn('matched_vend_transaction_id', $candidates->pluck('id'))
            ->pluck('matched_vend_transaction_id')
            ->flip();

        return $candidates
            ->reject(fn ($txn) => $claimed->has($txn->id))
            ->groupBy('vend_id');
    }

    /**
     * Seconds between the sale and the report row (txn − report), or null when
     * outside the window. Partial rows compare circularly within the hour.
     */
    protected function timeDelta(CardSettlementRow $row, object $candidate, int $earlySlack, int $lateSlack): ?int
    {
        if ($row->transaction_time === null) {
            return null;
        }

        $txnAt = Carbon::parse($candidate->transaction_datetime);

        if (! $row->time_is_partial) {
            $reportAt = Carbon::parse($row->transaction_date->toDateString().' '.$row->transaction_time);
            $delta = $reportAt->diffInSeconds($txnAt, false);

            return ($delta >= -$earlySlack && $delta <= $lateSlack) ? (int) $delta : null;
        }

        // Hour unknown: the sale must still be on the row's date (± the slack
        // across midnight), and mm:ss must line up modulo the hour.
        $dayStart = $row->transaction_date->copy()->startOfDay();
        if ($txnAt->lt($dayStart->copy()->subSeconds($earlySlack))
            || $txnAt->gt($dayStart->copy()->addDay()->addSeconds($lateSlack))) {
            return null;
        }

        [, $i, $s] = explode(':', $row->transaction_time); // stored as 00:i:s
        $rowSecOfHour = ((int) $i) * 60 + (int) $s;
        $txnSecOfHour = $txnAt->minute * 60 + $txnAt->second;

        $delta = ($txnSecOfHour - $rowSecOfHour) % 3600;
        if ($delta < 0) {
            $delta += 3600;
        }
        if ($delta <= $lateSlack) {
            return $delta;
        }
        if ($delta - 3600 >= -$earlySlack) {
            return $delta - 3600;
        }

        return null;
    }

    protected function candidatesPayload(array $pairs): array
    {
        return collect($pairs)
            ->sortBy(fn ($p) => abs($p['delta'] - self::EXPECTED_LAG_SECONDS))
            ->take(5)
            ->map(fn ($p) => [
                'vend_transaction_id' => $p['candidate']->id,
                'transaction_datetime' => (string) $p['candidate']->transaction_datetime,
                'amount' => $p['candidate']->amount,
                'is_refunded' => (bool) $p['candidate']->is_refunded,
                'delta' => $p['delta'],
            ])
            ->values()
            ->all();
    }
}
