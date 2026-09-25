<?php

namespace App\Services\CardSettlement;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalUnit;
use App\Models\VendTransaction;
use App\Services\UserLogger;
use App\Support\VendCode;
use Carbon\Carbon;

/**
 * Terminal-move suggestions for a report, and applying them — shared by the
 * Card Settlement page's buttons ("Move N terminals & rematch", "Bind") and
 * the unattended pass after matching (MatchCardSettlementReport::autoMove,
 * 2026-09-26: strong evidence moves itself; the rest goes to the nightly
 * alert email). Moved here unchanged from CardSettlementController.
 */
class TerminalMoveSuggestions
{
    const MIN_LINES_TO_MOVE_TERMINAL = 2;

    public function __construct(protected CardTerminalBindingService $bindings) {}

    /**
     * TIDs in this report with no binding on the lines' dates, split by whether
     * the terminal exists in Data Management at all, each with the machine the
     * matcher suggests (the ONE machine every fitting sale of its lines sits on
     * — see CardSettlementMatcher::suggestMachineForUnbound) and how many
     * lines back that suggestion.
     */
    public function unbound(CardSettlementReport $report)
    {
        $lines = $report->rows()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('resolution_note', 'No terminal binding')
            ->get(['terminal_id', 'transaction_date', 'candidates_json']);

        $knownUnits = CardTerminalUnit::query()
            ->whereIn('terminal_id', $lines->pluck('terminal_id')->unique())
            ->pluck('terminal_id')
            ->flip();

        return $lines->groupBy('terminal_id')
            ->map(function ($group, $terminalId) use ($knownUnits) {
                // Evidence over the day before and these days, every report
                // (Brian, 2026-09-25): a terminal fitted at 21:00 has one line
                // in each file, and neither file alone would reach two.
                $evidence = $this->evidenceWindow((string) $terminalId, $group, 'No terminal binding');
                [$code, $fitting, $clear] = $this->bestMachine($evidence);
                $strong = $clear && $fitting->count() >= self::MIN_LINES_TO_MOVE_TERMINAL;

                return [
                    'terminal_id' => (string) $terminalId,
                    'row_count' => $group->count(),
                    'unit_exists' => $knownUnits->has($terminalId),
                    'suggested_vend_code' => $strong ? $code : null,
                    'suggested_hits' => $strong ? $fitting->count() : 0,
                    'from_date' => $strong ? $this->lineAt($fitting->first()) : $group->min('transaction_date')?->toDateString(),
                ];
            })
            ->sortByDesc('row_count')
            ->values();
    }

    public function suspects(CardSettlementReport $report)
    {
        $lines = $report->rows()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('resolution_note', 'like', 'No matching sale on bound machine%')
            ->get(['terminal_id', 'vend_id', 'transaction_date', 'candidates_json']);

        $vendCodes = \App\Models\Vend::withoutGlobalScopes()
            ->whereIn('id', $lines->pluck('vend_id')->filter()->unique())
            ->selectRaw('id, '.VendCode::sqlLabel().' AS label')
            ->pluck('label', 'id');

        return $lines
            ->groupBy('terminal_id')
            ->map(function ($lines, $terminalId) use ($vendCodes) {
                // A move is a change point (Brian, 2026-09-25): the matcher
                // flags a line only when the terminal never matched at home
                // after it, and here those flagged lines are gathered over the
                // day before and these days, from every report. Two or more on
                // ONE other machine, clearly ahead of any other, or no
                // suggestion at all — the move then dates from the FIRST such
                // line, to the second.
                $evidence = $this->evidenceWindow((string) $terminalId, $lines, 'No matching sale on bound machine%');
                [$suggestedCode, $fitting, $clear] = $this->bestMachine($evidence);
                $fromDate = $fitting->isNotEmpty() ? $this->lineAt($fitting->first()) : null;

                return [
                    'terminal_id' => (string) $terminalId,
                    'bound_vend_code' => $vendCodes->get($lines->first()->vend_id),
                    'suggested_vend_code' => $suggestedCode,
                    'row_count' => $lines->count(),
                    'suggested_hits' => $fitting->count(),
                    'from_date' => $fromDate,
                    'weak' => ! $clear || $fitting->count() < self::MIN_LINES_TO_MOVE_TERMINAL,
                ] + $this->bindingMoveImpact((string) $terminalId, $suggestedCode, $fromDate ? substr($fromDate, 0, 10) : null);
            })
            // Below the bar it is not a suggestion at all (Brian, 2026-09-25):
            // the lines stay queries with their candidates.
            ->reject(fn ($s) => $s['weak'])
            ->sortByDesc('row_count')
            ->values();
    }

    /**
     * This terminal's lines carrying `$note` from the day before the given
     * lines to their last day, across every report, oldest first.
     */
    private function evidenceWindow(string $terminalId, $lines, string $note)
    {
        $from = $lines->min(fn ($l) => $l->transaction_date->toDateString());
        $to = $lines->max(fn ($l) => $l->transaction_date->toDateString());

        return CardSettlementRow::query()
            ->where('terminal_id', $terminalId)
            ->whereBetween('transaction_date', [Carbon::parse($from)->subDay()->toDateString(), $to])
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('resolution_note', 'like', $note)
            ->where('time_is_partial', false)
            ->whereNotNull('transaction_time')
            ->get(['id', 'transaction_date', 'transaction_time', 'candidates_json'])
            ->sortBy(fn ($l) => $this->lineAt($l))
            ->values();
    }

    /**
     * The machine most of the evidence fits, the lines that fit it (oldest
     * first) and whether it is clearly ahead of the runner-up.
     *
     * @return array{0: ?string, 1: \Illuminate\Support\Collection, 2: bool}
     */
    private function bestMachine($evidence): array
    {
        $votes = $evidence
            ->flatMap(fn ($l) => collect($l->candidates_json ?? [])->where('other_vend', true)->pluck('vend_code')->unique())
            ->countBy()
            ->sortDesc();
        $code = $votes->keys()->first();
        if ($code === null) {
            return [null, collect(), false];
        }
        $fitting = $evidence->filter(fn ($l) => collect($l->candidates_json ?? [])
            ->contains(fn ($c) => ($c['other_vend'] ?? false) && (string) ($c['vend_code'] ?? '') === (string) $code))->values();
        $runnerUp = (int) ($votes->values()[1] ?? 0);

        return [(string) $code, $fitting, $votes->first() > $runnerUp];
    }

    private function lineAt($line): string
    {
        return $line->transaction_date->toDateString().' '.Carbon::parse($line->transaction_time)->format('H:i:s');
    }

    /**
     * What moving this terminal would cost as well as fix, counted BEFORE the
     * move so the trade-off is visible on the button rather than discovered in
     * the rematch afterwards.
     *
     * A binding is global, so the blast radius is EVERY report from `$fromDate`
     * on, not just the one on screen:
     *  - `would_fix`   unmatched lines that have a fitting sale on the machine
     *                  we would move to — the point of the exercise.
     *  - `would_break` lines matched TODAY whose sale sits on some other
     *                  machine; re-resolving the terminal takes their binding
     *                  away and they fall back to unmatched.
     *  - `would_break_synced` the subset of those already stamped onto a sale
     *                  by Sync. Those are settled finance data, so a move that
     *                  breaks any of them wants a human, not a bulk button.
     *
     * Counted from `card_settlement_rows.vend_id`, which a matched row carries
     * as the machine its sale was found on — no join to 5M vend_transactions.
     *
     * @return array{would_fix: int, would_break: int, would_break_synced: int}
     */
    private function bindingMoveImpact(string $terminalId, $suggestedVendCode, ?string $fromDate): array
    {
        $empty = ['would_fix' => 0, 'would_break' => 0, 'would_break_synced' => 0];

        if (! $fromDate || $suggestedVendCode === null) {
            return $empty;
        }

        $target = \App\Models\Vend::withoutGlobalScopes()->bareCode($suggestedVendCode)->get(['id']);
        if ($target->count() !== 1) {
            return $empty;
        }
        $targetId = (int) $target->first()->id;

        $affected = CardSettlementRow::query()
            ->where('terminal_id', $terminalId)
            ->whereDate('transaction_date', '>=', $fromDate)
            ->whereIn('status', [CardSettlementRow::STATUS_MATCHED, CardSettlementRow::STATUS_UNMATCHED])
            ->get(['id', 'status', 'vend_id', 'matched_vend_transaction_id', 'candidates_json']);

        [$matched, $unmatched] = $affected->partition(
            fn (CardSettlementRow $r) => $r->status === CardSettlementRow::STATUS_MATCHED
        );

        $breaking = $matched->reject(fn (CardSettlementRow $r) => (int) $r->vend_id === $targetId);

        $syncedTxnIds = $breaking->pluck('matched_vend_transaction_id')->filter();
        $syncedCount = $syncedTxnIds->isEmpty() ? 0 : VendTransaction::withoutGlobalScopes()
            ->whereIn('id', $syncedTxnIds)
            ->whereNotNull('card_settlement_synced_at')
            ->count();

        return [
            'would_fix' => $unmatched->filter(fn (CardSettlementRow $r) => collect($r->candidates_json ?? [])
                ->contains(fn ($c) => ($c['other_vend'] ?? false)
                    && (string) ($c['vend_code'] ?? '') === (string) $suggestedVendCode))->count(),
            'would_break' => $breaking->count(),
            'would_break_synced' => $syncedCount,
        ];
    }

    /**
     * Move each suspect terminal onto the machine its lines prove it is on.
     *
     * @return array{0: string[], 1: string[]} [moved, skipped]
     */
    public function applySuspects($suspects): array
    {
        $moved = [];
        $skipped = [];

        foreach ($suspects as $suspect) {
            $terminalId = $suspect['terminal_id'];

            if (! empty($suspect['weak'])) {
                $skipped[] = "{$terminalId}: only {$suspect['suggested_hits']} line fits {$suspect['suggested_vend_code']} — not enough to move it; bind by hand if you know it moved";

                continue;
            }

            $unit = CardTerminalUnit::where('terminal_id', $terminalId)->first();
            if (! $unit) {
                $skipped[] = "{$terminalId}: no terminal record — add it under Data Management → Card Terminal";

                continue;
            }

            // withoutGlobalScopes to match the matcher's own fleet lookup: an
            // operator-scoped read would silently skip another operator's
            // machine and leave those lines unmatched forever.
            $candidates = \App\Models\Vend::withoutGlobalScopes()
                ->bareCode($suspect['suggested_vend_code'])->get();
            if ($candidates->count() !== 1) {
                $skipped[] = "{$terminalId}: machine {$suspect['suggested_vend_code']} "
                    .($candidates->isEmpty() ? 'not found' : 'is not unique');

                continue;
            }
            $vend = $candidates->first();

            // Breaking a line Sync already stamped onto a sale is un-settling
            // finance data. The bulk button never does that unattended — the
            // Settings page is still there for a human who means it.
            if ($suspect['would_break_synced'] > 0) {
                $skipped[] = "{$terminalId}: would break {$suspect['would_break_synced']} already-synced line(s) — move it by hand from machine {$vend->codeLabel()}'s Settings page";

                continue;
            }

            $before = $this->bindings->currentUnitFor($vend)?->terminal_id;
            $result = $this->bindings->moveToVend($unit, $vend, $suspect['from_date']);

            if (! $result['moved']) {
                $skipped[] = "{$terminalId}: {$result['note']}";

                continue;
            }

            // Bindings live on their own table, so the app-wide audit never
            // sees this as a vend change — record it by hand under the same
            // synthetic column VendController uses for the Settings page.
            UserLogger::recordChanges($vend, [
                'card_terminal_unit_id' => [$before, $unit->terminal_id],
            ]);

            $moved[] = "{$terminalId} → {$vend->codeLabel()} ({$result['note']})";
        }

        return [$moved, $skipped];
    }

    /**
     * Bind each unbound TID to its suggested machine (creating the Data
     * Management row when new).
     *
     * @return array{0: string[], 1: string[]} [bound, skipped]
     */
    public function applyUnbound(CardSettlementReport $report, $targets): array
    {
        $bound = [];
        $skipped = [];

        foreach ($targets as $t) {
            $terminalId = $t['terminal_id'];

            $vends = \App\Models\Vend::withoutGlobalScopes()->bareCode($t['suggested_vend_code'])->get();
            if ($vends->count() !== 1) {
                $skipped[] = "{$terminalId}: machine {$t['suggested_vend_code']} ".($vends->isEmpty() ? 'not found' : 'is not unique');

                continue;
            }
            $vend = $vends->first();

            // A new TID gets its Data Management row here, under the same
            // supplying company as the machine it lands on (mirrors the
            // import command), so it is editable from Settings afterwards.
            $unit = CardTerminalUnit::firstOrCreate(
                ['terminal_id' => $terminalId],
                ['card_terminal_id' => $vend->card_terminal_id, 'remarks' => "Created from settlement report #{$report->id}"]
            );

            // moveToVend, not assignToVend: when the terminal is ALREADY on this
            // machine but the binding starts after these lines (the live
            // pattern — bound_from was set to the date the first report got
            // reviewed, leaving Aug 5–20 unbound), it pulls bound_from back
            // over the gap instead of answering "already current".
            $before = $this->bindings->currentUnitFor($vend)?->terminal_id;
            $result = $this->bindings->moveToVend($unit, $vend, $t['from_date']);
            if (! $result['moved']) {
                $skipped[] = "{$terminalId}: {$result['note']}";

                continue;
            }

            UserLogger::recordChanges($vend, [
                'card_terminal_unit_id' => [$before, $unit->terminal_id],
            ]);
            $bound[] = "{$terminalId} → {$vend->codeLabel()} ({$result['note']})";
        }

        return [$bound, $skipped];
    }
}
