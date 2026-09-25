<?php

namespace App\Services\CardSettlement;

use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Vend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puts a card terminal on a machine, from the machine Setting/Edit page — the
 * only place a binding is created since the standalone Card Terminal Bindings
 * page was removed (2026-09-05).
 *
 * Everything here exists to keep CardSettlementMatcher working. That matcher
 * resolves a report line to a machine by (provider, terminal_id) **effective on
 * the line's transaction date**, so:
 *
 *  - Bindings are never edited or deleted when a terminal moves. The old row is
 *    CLOSED (bound_until) and a new one opened, or a report for last month
 *    would suddenly resolve to this month's machine.
 *  - A terminal may hold at most ONE open-ended binding. Two would make the
 *    matcher pick a machine arbitrarily — the same invariant the old page
 *    enforced as a validation error.
 *  - Close and open share the same INSTANT (2026-09-25: bindings are
 *    [from_at, until_at) to the second), so a 14:30 swap splits the day
 *    exactly — morning lines resolve to the old terminal, afternoon to the new.
 *  - A person's change (source manual) is authoritative from the moment they
 *    recorded it (created_at). NETS evidence (moveToVend) may fill history
 *    BEFORE that moment, never after it, and refuses — for a human to decide —
 *    when it contradicts a change recorded before the evidence.
 */
class CardTerminalBindingService
{
    /**
     * Make `$unit` the machine's current terminal (or clear it when null).
     *
     * @param  string|null  $boundFrom  blank = now (the save moment); a date =
     *                                  that day 00:00 (today = now); a date+time
     *                                  = that instant.
     * @param  int|null  $createdBy  the person fitting it on Setting/Edit; null
     *                               for every unattended path, shown as "sys".
     * @return bool whether anything changed
     */
    public function assignToVend(Vend $vend, ?CardTerminalUnit $unit, ?string $boundFrom = null, ?int $createdBy = null): bool
    {
        $at = $this->resolveAt($boundFrom);

        $currentOnVend = $this->openBindingsForVend($vend)->get();

        // Already the machine's open terminal — leave the row alone rather
        // than rewriting settled history.
        if ($unit && $currentOnVend->count() === 1 && $currentOnVend->first()->terminal_id === $unit->terminal_id) {
            return false;
        }

        $this->guardBackdating($currentOnVend, $at);

        return DB::transaction(function () use ($vend, $unit, $at, $currentOnVend, $createdBy) {
            foreach ($currentOnVend as $binding) {
                $binding->update(['until_at' => $at]);
            }

            if (! $unit) {
                return $currentOnVend->isNotEmpty();
            }

            // The terminal may currently be open on a DIFFERENT machine (it was
            // physically moved). Close that too, or the new row would be the
            // second open binding for this TID.
            CardTerminalBinding::query()
                ->where('terminal_id', $unit->terminal_id)
                ->whereNull('until_at')
                ->where('vend_id', '!=', $vend->id)
                ->get()
                ->each(fn (CardTerminalBinding $b) => $b->update(['until_at' => $at]));

            CardTerminalBinding::create([
                'provider' => $unit->settlementProvider(),
                'terminal_id' => $unit->terminal_id,
                'vend_id' => $vend->id,
                'from_at' => $at,
                'until_at' => null,
                'source' => $createdBy ? CardTerminalBinding::SOURCE_MANUAL : CardTerminalBinding::SOURCE_REPORT,
                'remarks' => null,
                'created_by' => $createdBy,
            ]);

            return true;
        });
    }

    /**
     * NETS evidence says `$unit` was on `$vend` from `$from` (the first report
     * line that fits a sale there): record that as a segment of history — the
     * Card Settlement page's binding repair buttons.
     *
     * The segment runs from `$from` to the first thing already recorded after
     * it — a later binding of this terminal or of this machine, or the moment a
     * PERSON recorded a change (Brian, 2026-09-25: a technician who fits a new
     * terminal at 11:43 must not be overwritten by yesterday's report). What
     * covered `$from` is closed there; a person's back-dated claim yields to
     * the evidence only for the part before they recorded it. When the
     * evidence contradicts a change a person recorded BEFORE `$from`, nothing
     * is moved — the note says so and a human decides.
     *
     * @param  string  $from  Y-m-d (that day 00:00) or a date-time.
     * @return array{moved: bool, note: string}
     */
    public function moveToVend(CardTerminalUnit $unit, Vend $vend, string $from): array
    {
        try {
            $at = strlen(trim($from)) <= 10 ? Carbon::parse($from)->startOfDay() : Carbon::parse($from);
        } catch (\Throwable) {
            return ['moved' => false, 'note' => 'not a valid date'];
        }
        $tid = $unit->terminal_id;

        // Never put a terminal on a machine whose reader belongs to another
        // company/provider (2700 is MLS: a NETS TID was bound there from one
        // same-amount coincidence, 2026-09-22).
        $company = $vend->card_terminal_id ? \App\Models\CardTerminal::whereKey($vend->card_terminal_id)->value('name') : null;
        if ($company && in_array((int) $vend->card_terminal_id, CardSettlementMatcher::foreignCompanyIds($unit->settlementProvider()), true)) {
            return ['moved' => false, 'note' => $vend->codeLabel().'\'s card reader is '.$company.', not '.strtoupper($unit->settlementProvider())];
        }

        $isThis = fn (CardTerminalBinding $b) => $b->terminal_id === $tid && (int) $b->vend_id === (int) $vend->id;

        $open = CardTerminalBinding::query()->where('terminal_id', $tid)->whereNull('until_at')->get();
        if ($open->count() > 1) {
            return ['moved' => false, 'note' => 'has '.$open->count().' open bindings — fix by hand'];
        }

        $rows = CardTerminalBinding::query()
            ->with('creator:id,name')
            ->where(fn ($q) => $q->where('terminal_id', $tid)->orWhere('vend_id', $vend->id))
            ->orderBy('id')
            ->get();
        $covering = $rows->filter(fn (CardTerminalBinding $b) => $b->coversAt($at));

        if ($covering->contains($isThis)) {
            return ['moved' => false, 'note' => 'already on '.$vend->codeLabel()];
        }

        foreach ($covering as $b) {
            // Another terminal's FINISHED stay on this machine covers $from:
            // the period would have two owners — a human decides. (An OPEN
            // binding there is simply displaced, as any move displaces it.)
            if ($b->terminal_id !== $tid && (int) $b->vend_id === (int) $vend->id && $b->until_at !== null && ! $b->isManual()) {
                return ['moved' => false, 'note' => 'on '.$vend->codeLabel().' from '.$at->format('Y-m-d H:i')
                    .' by the report, but '.$b->terminal_id.' held it then — an earlier binding covers '.$at->toDateString()];
            }
            if ($b->isManual() && $b->created_at && $b->created_at->lte($at)) {
                $code = Vend::withoutGlobalScopes()->whereKey($b->vend_id)->value('code');

                return ['moved' => false, 'note' => 'NETS shows it on '.$vend->codeLabel().' at '.$at->format('Y-m-d H:i')
                    .', but '.$b->boundByLabel().' recorded '.$b->terminal_id.' on '.($code ?? '#'.$b->vend_id)
                    .' at '.$b->created_at->format('Y-m-d H:i').' — check which is right'];
            }
        }

        // Where the segment stops: the next recorded thing after $from.
        $end = null;
        foreach ($rows as $b) {
            $candidates = [];
            if ($b->from_at && $b->from_at->gt($at)) {
                $candidates[] = $b->from_at;
            }
            if ($b->isManual() && $b->created_at && $b->created_at->gt($at) && $b->coversAt($at)) {
                $candidates[] = $b->created_at; // a person's back-dated claim: theirs from when they said it
            }
            foreach ($candidates as $c) {
                $end = $end === null || $c->lt($end) ? $c->copy() : $end;
            }
        }

        $closed = [];
        $merged = false;
        DB::transaction(function () use ($covering, $rows, $at, $end, $isThis, $unit, $vend, &$closed, &$merged) {
            foreach ($covering as $b) {
                if ($b->isManual() && ($b->until_at === null || $b->until_at->gt($b->created_at))) {
                    // A back-dated manual row (recorded after $at): theirs from
                    // the moment they recorded it; the evidence takes the rest.
                    $b->update(['from_at' => $b->created_at]);
                } elseif ($b->from_at && $b->from_at->equalTo($at)) {
                    $b->delete();
                } else {
                    $b->update(['until_at' => $at]);
                }
                $closed[] = $b->terminal_id.' on '.(Vend::withoutGlobalScopes()->whereKey($b->vend_id)->value('code') ?? '#'.$b->vend_id);
            }

            $next = $end ? $rows->first(fn ($b) => $isThis($b) && $b->from_at && $b->from_at->equalTo($end)) : null;
            if ($next) {
                $next->update(['from_at' => $at]); // the terminal's own later stay reaches back
                $merged = true;
            } else {
                CardTerminalBinding::create([
                    'provider' => $unit->settlementProvider(),
                    'terminal_id' => $unit->terminal_id,
                    'vend_id' => $vend->id,
                    'from_at' => $at,
                    'until_at' => $end,
                    'source' => CardTerminalBinding::SOURCE_REPORT,
                ]);
            }
        });

        $note = $merged
            ? 'back-dated on '.$vend->codeLabel().' to '.$at->format('Y-m-d H:i')
            : 'from '.$at->format('Y-m-d H:i').($end ? ' until '.$end->format('Y-m-d H:i') : '');
        if ($closed) {
            $note .= ', closed '.implode(', ', array_unique($closed)).' at '.$at->format('Y-m-d H:i');
        }

        return ['moved' => true, 'note' => $note];
    }

    /** The terminal currently on this machine, if any. */
    public function currentUnitFor(Vend $vend): ?CardTerminalUnit
    {
        $terminalId = $this->openBindingsForVend($vend)->value('terminal_id');

        return $terminalId
            ? CardTerminalUnit::where('terminal_id', $terminalId)->first()
            : null;
    }

    /** The open binding row for this machine, for showing bound_from on the form. */
    public function currentBindingFor(Vend $vend): ?CardTerminalBinding
    {
        return $this->openBindingsForVend($vend)->first();
    }

    private function openBindingsForVend(Vend $vend)
    {
        return CardTerminalBinding::query()
            ->where('vend_id', $vend->id)
            ->whereNull('until_at')
            ->orderBy('id');
    }

    /**
     * Blank → now (the moment the person saved). A bare date → that day 00:00,
     * except today → now: "today" means "just now", not "since midnight",
     * which would hand the old terminal's morning sales to the new one.
     */
    private function resolveAt(?string $boundFrom): Carbon
    {
        $boundFrom = trim((string) $boundFrom);

        if ($boundFrom === '' || $boundFrom === 'Invalid date') {
            return now()->startOfSecond();
        }

        try {
            $at = Carbon::parse($boundFrom);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'card_terminal_bound_from' => 'Bound From is not a valid date.',
            ]);
        }

        if (strlen($boundFrom) <= 10) {
            return $at->isToday() ? now()->startOfSecond() : $at->startOfDay();
        }

        return $at->startOfSecond();
    }

    /**
     * Refuse a start that lands before the current binding started: it would
     * close the old row before it opened, an inverted range nothing resolves
     * to, silently orphaning every report line for that terminal in the gap.
     */
    private function guardBackdating($currentOnVend, Carbon $at): void
    {
        foreach ($currentOnVend as $binding) {
            if ($binding->from_at && $binding->from_at->gt($at)) {
                throw ValidationException::withMessages([
                    'card_terminal_bound_from' => 'Bound From cannot be earlier than the current terminal\'s start ('
                        .$binding->from_at->format('Y-m-d H:i').').',
                ]);
            }
        }
    }
}
