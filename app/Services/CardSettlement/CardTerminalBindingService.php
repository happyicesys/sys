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
 *  - Close and open share the same date. effectiveOn() is inclusive at both
 *    ends, so a same-day swap leaves no gap; the two rows carry different
 *    terminal_ids, so nothing is ambiguous for a given report line.
 */
class CardTerminalBindingService
{
    /**
     * Make `$unit` the machine's current terminal (or clear it when null).
     *
     * @param  string|null  $boundFrom  Y-m-d; blank/null = today.
     * @return bool whether anything changed
     */
    public function assignToVend(Vend $vend, ?CardTerminalUnit $unit, ?string $boundFrom = null): bool
    {
        $date = $this->resolveDate($boundFrom);

        $currentOnVend = $this->openBindingsForVend($vend)->get();

        // Already the machine's open terminal — leave the row (and its
        // bound_from) alone rather than rewriting settled history.
        if ($unit && $currentOnVend->count() === 1 && $currentOnVend->first()->terminal_id === $unit->terminal_id) {
            return false;
        }

        $this->guardBackdating($currentOnVend, $date);

        return DB::transaction(function () use ($vend, $unit, $date, $currentOnVend) {
            foreach ($currentOnVend as $binding) {
                $binding->update(['bound_until' => $date]);
            }

            if (! $unit) {
                return $currentOnVend->isNotEmpty();
            }

            // The terminal may currently be open on a DIFFERENT machine (it was
            // physically moved). Close that too, or the new row would be the
            // second open binding for this TID.
            CardTerminalBinding::query()
                ->where('terminal_id', $unit->terminal_id)
                ->whereNull('bound_until')
                ->where('vend_id', '!=', $vend->id)
                ->get()
                ->each(fn (CardTerminalBinding $b) => $b->update(['bound_until' => $date]));

            CardTerminalBinding::create([
                'provider' => $unit->settlementProvider(),
                'terminal_id' => $unit->terminal_id,
                'vend_id' => $vend->id,
                'bound_from' => $date,
                'bound_until' => null,
                'remarks' => null,
            ]);

            return true;
        });
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
            ->whereNull('bound_until')
            ->orderBy('id');
    }

    private function resolveDate(?string $boundFrom): string
    {
        $boundFrom = trim((string) $boundFrom);

        if ($boundFrom === '' || $boundFrom === 'Invalid date') {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($boundFrom)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'card_terminal_bound_from' => 'Bound From is not a valid date.',
            ]);
        }
    }

    /**
     * Refuse a bound_from that lands before the current binding started: it
     * would close the old row with bound_until < bound_from, an inverted range
     * that effectiveOn() can never satisfy, silently orphaning every report
     * line for that terminal in the gap.
     */
    private function guardBackdating($currentOnVend, string $date): void
    {
        foreach ($currentOnVend as $binding) {
            if ($binding->bound_from && $binding->bound_from->toDateString() > $date) {
                throw ValidationException::withMessages([
                    'card_terminal_bound_from' => 'Bound From cannot be earlier than the current terminal\'s start date ('
                        .$binding->bound_from->toDateString().').',
                ]);
            }
        }
    }
}
