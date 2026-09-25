<?php

namespace App\Console\Commands;

use App\Models\CardSettlementRow;
use App\Models\CardTerminalBinding;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair (2026-09-25) for the Setting/Edit "Bound From" pre-fill bug:
 * the field carried the CURRENT terminal's start date, so a technician who
 * swapped terminals and left it alone back-dated the new terminal over the
 * old one's whole history — 13 of 18 human bindings, 44–470 days — and closed
 * the old binding at that date.
 *
 * Each damaged pair is exact: the back-dated manual row M, and the row P it
 * closed in the same save (P.updated_at = M.created_at to the second, P ending
 * where M begins). The repair puts the change where the evidence says it
 * happened: after the old terminal's last matched sale on the machine, at the
 * new terminal's first matched sale — or, with no such evidence, the moment
 * the person saved. P runs until then, M starts then. Nothing else moves;
 * report rows written since that disagree with the person's change are
 * listed as conflicts for a human.
 *
 *   php artisan card-settlement:repair-backdated-bindings           # dry run
 *   php artisan card-settlement:repair-backdated-bindings --apply
 */
class RepairBackdatedTerminalBindings extends Command
{
    protected $signature = 'card-settlement:repair-backdated-bindings
        {--min-days=2 : a manual binding counts as back-dated when it starts this many days before it was saved}
        {--apply : write (default is a dry run)}';

    protected $description = 'Undo the Setting/Edit Bound From pre-fill back-dating of terminal bindings (dry-run by default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $minDays = (int) $this->option('min-days');

        $manual = CardTerminalBinding::query()
            ->where('source', CardTerminalBinding::SOURCE_MANUAL)
            ->whereNotNull('from_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (CardTerminalBinding $m) => $m->from_at->copy()->addDays($minDays)->lt($m->created_at));

        if ($manual->isEmpty()) {
            $this->info('No back-dated manual binding.');

            return self::SUCCESS;
        }

        $codes = Vend::withoutGlobalScopes()->pluck('code', 'id');
        $plan = [];
        foreach ($manual as $m) {
            $closedBySave = CardTerminalBinding::query()
                ->where('id', '<', $m->id)
                ->where(fn ($q) => $q->where('vend_id', $m->vend_id)->orWhere('terminal_id', $m->terminal_id))
                ->whereBetween('updated_at', [$m->created_at->copy()->subSeconds(5), $m->created_at->copy()->addSeconds(5)])
                // Closed on the day M claims to start (the old inclusive-date
                // close; after the time migration its until_at is the next day).
                ->whereDate('bound_until', $m->from_at->toDateString())
                ->get();
            $old = $closedBySave->firstWhere('vend_id', $m->vend_id);

            $lastOld = $old ? $this->lastMatchedOn($old->terminal_id, $m->vend_id, $m->from_at, $m->created_at) : null;
            $firstNew = $this->firstMatchedOn($m->terminal_id, $m->vend_id, $lastOld ?? $m->from_at, $m->created_at);
            $start = $firstNew ?? $m->created_at->copy();

            // Rows the report fixes wrote AFTER this save, putting another
            // terminal on the machine past the moment the person recorded.
            $later = CardTerminalBinding::query()
                ->where('id', '>', $m->id)
                ->where('source', '!=', CardTerminalBinding::SOURCE_MANUAL)
                ->where('vend_id', $m->vend_id)
                ->where('terminal_id', '!=', $m->terminal_id)
                ->where(fn ($q) => $q->whereNull('until_at')->orWhere('until_at', '>', $start))
                ->get();
            $conflicts = collect();   // NETS shows the other terminal selling there after the change — a human decides
            $overran = collect();     // no such evidence — the report row simply ran past the change
            foreach ($later as $c) {
                $sold = $this->matchedOn($c->terminal_id, $m->vend_id, $start, now())->min();
                $sold ? $conflicts->push([$c, $sold]) : $overran->push($c);
            }
            // The same terminal opened here by a report fix from the swap day's
            // midnight: it claims the morning the old terminal still sold in.
            $dups = CardTerminalBinding::query()
                ->where('id', '>', $m->id)
                ->where('source', CardTerminalBinding::SOURCE_REPORT)
                ->where('vend_id', $m->vend_id)
                ->where('terminal_id', $m->terminal_id)
                ->where('from_at', '<', $start)
                ->where(fn ($q) => $q->whereNull('until_at')->orWhere('until_at', '>', $start))
                ->get();
            // Reopen the person's row only when a later report row closed it
            // and nothing disputes it.
            $reopen = $conflicts->isEmpty() && $m->until_at !== null && $m->until_at->lte($m->created_at)
                && $overran->concat($dups)->contains(fn ($c) => $c->from_at && $c->from_at->lte($m->until_at));
            $mUntil = $reopen
                ? CardTerminalBinding::query()->where('id', '!=', $m->id)
                    ->where(fn ($q) => $q->where('vend_id', $m->vend_id)->orWhere('terminal_id', $m->terminal_id))
                    ->where('from_at', '>', $start)->whereNotIn('id', $overran->concat($dups)->pluck('id'))->min('from_at')
                : $m->until_at;
            $mUntil = $mUntil ? Carbon::parse($mUntil) : null;
            $inverted = $mUntil !== null && $mUntil->lte($start);

            $plan[] = compact('m', 'closedBySave', 'old', 'lastOld', 'firstNew', 'start', 'conflicts', 'overran', 'dups', 'reopen', 'mUntil', 'inverted');
        }

        $this->table(
            ['Machine', 'Manual row', 'Terminal', 'Saved at', 'Claimed from', 'Old terminal', 'Old last sale', 'New first sale', 'Repaired', 'Report rows cut', 'Disputed (review)'],
            collect($plan)->map(fn ($p) => [
                $codes->get($p['m']->vend_id),
                $p['m']->id,
                $p['m']->terminal_id,
                $p['m']->created_at->format('Y-m-d H:i:s'),
                $p['m']->from_at->format('Y-m-d H:i'),
                $p['old'] ? $p['old']->terminal_id.' (#'.$p['old']->id.')' : '—',
                $p['lastOld']?->format('Y-m-d H:i:s') ?? '—',
                $p['firstNew']?->format('Y-m-d H:i:s') ?? '—',
                $p['inverted'] ? 'history before the change only (later rows cover it — review)' : $p['start']->format('Y-m-d H:i:s').' → '.($p['mUntil']?->format('Y-m-d H:i') ?? 'open'),
                $p['overran']->map(fn ($c) => $c->terminal_id.' #'.$c->id)->implode('; ') ?: '—',
                $p['conflicts']->map(fn ($x) => $x[0]->terminal_id.' #'.$x[0]->id.' sold '.$x[1]->format('m-d H:i'))->implode('; ') ?: '—',
            ])->all()
        );

        if (! $apply) {
            $this->comment('Dry run — re-run with --apply to write.');

            return self::SUCCESS;
        }

        foreach ($plan as $p) {
            if ($p['inverted']) {
                // Later rows (disputed by NETS) already cover the time after the
                // change: repair only the history BEFORE it — the old terminal
                // runs to the change, and the person's row stops claiming the
                // months before they saved it (collapsed, kept for the audit).
                DB::transaction(function () use ($p) {
                    foreach ($p['closedBySave'] as $closed) {
                        $closed->update(['until_at' => $p['start']]);
                    }
                    $p['m']->update(['from_at' => $p['m']->until_at]);
                });
                $this->warn("#{$p['m']->id}: history before the change repaired; after it, later rows are disputed — review by hand");

                continue;
            }
            DB::transaction(function () use ($p) {
                foreach ($p['closedBySave'] as $closed) {
                    $closed->update(['until_at' => $p['start']]);
                }
                if ($p['conflicts']->isEmpty()) {
                    foreach ($p['overran'] as $c) {
                        $c->from_at && $c->from_at->gte($p['start'])
                            ? $c->delete()
                            : $c->update(['until_at' => $p['start']]);
                    }
                }
                $p['m']->update(['from_at' => $p['start'], 'until_at' => $p['mUntil']]);
                // The person's row covers this terminal from the recorded
                // moment: a report row that opened it from midnight collapses.
                $p['dups']->each(fn (CardTerminalBinding $dup) => $dup->update(['until_at' => $dup->from_at]));
            });
            $this->line("repaired #{$p['m']->id}: {$p['m']->terminal_id} from {$p['start']->format('Y-m-d H:i:s')}"
                .($p['conflicts']->isNotEmpty() ? ' (disputed report rows left for review)' : ''));
        }
        $this->comment('Rematch the reports covering these machines so their unmatched lines resolve on the repaired history.');

        return self::SUCCESS;
    }

    /** Latest line of `$terminal` matched to a real sale on `$vendId` inside the window. */
    private function lastMatchedOn(string $terminal, int $vendId, Carbon $from, Carbon $until): ?Carbon
    {
        return $this->matchedOn($terminal, $vendId, $from, $until)->max();
    }

    /** Earliest line of `$terminal` matched to a real sale on `$vendId` after `$after`, up to `$until`. */
    private function firstMatchedOn(string $terminal, int $vendId, Carbon $after, Carbon $until): ?Carbon
    {
        return $this->matchedOn($terminal, $vendId, $after, $until)->filter(fn (Carbon $t) => $t->gt($after))->min();
    }

    /** @return Collection<int, Carbon> */
    private function matchedOn(string $terminal, int $vendId, Carbon $from, Carbon $until): Collection
    {
        return CardSettlementRow::query()
            ->join('vend_transactions as vt', 'vt.id', '=', 'card_settlement_rows.matched_vend_transaction_id')
            ->where('card_settlement_rows.terminal_id', $terminal)
            ->where('vt.vend_id', $vendId)
            ->where('vt.is_found_in_transaction', true)
            ->where('card_settlement_rows.status', CardSettlementRow::STATUS_MATCHED)
            ->where('card_settlement_rows.time_is_partial', false)
            ->whereBetween('card_settlement_rows.transaction_date', [$from->toDateString(), $until->toDateString()])
            ->get(['card_settlement_rows.transaction_date', 'card_settlement_rows.transaction_time'])
            ->map(fn ($r) => Carbon::parse(Carbon::parse($r->transaction_date)->toDateString().' '.$r->transaction_time))
            ->filter(fn (Carbon $t) => $t->gte($from) && $t->lte($until))
            ->values();
    }
}
