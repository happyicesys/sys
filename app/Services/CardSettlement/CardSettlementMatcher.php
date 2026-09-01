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

        $resolvable = collect();
        foreach ($purchases as $row) {
            $date = $row->transaction_date->toDateString();
            $binding = ($bindings->get($row->terminal_id) ?? collect())
                ->first(fn (CardTerminalBinding $b) => ($b->bound_from === null || $b->bound_from->toDateString() <= $date)
                    && ($b->bound_until === null || $b->bound_until->toDateString() >= $date));

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

        $report->forceFill(['matched_at' => now(), 'status' => CardSettlementReport::STATUS_REVIEW])->save();
        $report->refreshCounts();
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
            $row->update([
                'status' => CardSettlementRow::STATUS_UNMATCHED,
                'matched_vend_transaction_id' => null,
                'match_time_delta' => null,
                'candidates_json' => $hadCandidates ? $this->candidatesPayload($eligibleByRow[$row->id]) : null,
                'resolution_note' => $hadCandidates
                    ? 'All matching sales already claimed'
                    : 'No matching sale in window',
            ]);
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
