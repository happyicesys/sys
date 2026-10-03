<?php

namespace App\Services\CardTerminal;

use App\Models\CardTerminalUnit;
use App\Models\RemoteCardTerminal;
use App\Models\User;
use App\Models\Vend;

/**
 * The one writer of `remote_card_terminals` — the row a smart freezer's card rail reads
 * (`RemoteCardTerminal::activeForVend`, freezer app v25+).
 *
 * A Payrallel (T05) terminal is a Data Management > Card Terminal unit (SN = terminal_id,
 * access token on the unit). Binding that unit to a freezer on Setting/Edit
 * (`CardTerminalBindingService::assignToVend`) calls {@see bindUnit}; binding anything else
 * or nothing calls {@see unbind}; editing the unit's token calls {@see syncUnit}.
 *
 * One terminal, one machine, unless the unit's company (Payrallel (T05)) has "One terminal
 * can serve several machines" on: binding a unit that is active on another freezer takes
 * it off there. Rows made by the old command (no unit) are matched by token.
 */
class RemoteCardTerminalBinder
{
    public function __construct(private readonly CardTerminalEventLog $events) {}

    /**
     * @return list<string> machine IDs the terminal was taken off
     */
    public function bindUnit(Vend $vend, CardTerminalUnit $unit, ?int $userId, string $via): array
    {
        if (! $vend->isSmartFreezer() || ! $unit->isRemoteTerminal() || ! $unit->hasAccessToken()) {
            return [];
        }
        $by = $this->who($userId);

        $existing = RemoteCardTerminal::query()->where('vend_id', $vend->id)->first();
        $wasSameActive = $existing?->is_active && (int) $existing->card_terminal_unit_id === (int) $unit->id;

        $terminal = RemoteCardTerminal::query()->updateOrCreate(['vend_id' => $vend->id], [
            'card_terminal_unit_id' => $unit->id,
            'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL,
            'label' => $unit->terminal_id,
            'access_token' => $unit->access_token,
            'is_active' => true,
        ]);

        $released = $this->releaseFromOtherMachines($terminal, $unit, $by, $via);

        // A freezer selling through a T05 names it as its card reader company, unless set by hand.
        if (! $vend->card_terminal_id && $unit->card_terminal_id) {
            $vend->update(['card_terminal_id' => $unit->card_terminal_id]);
        }

        if (! $wasSameActive) {
            $this->events->record('terminal.bound', array_filter([
                'sn' => $unit->terminal_id,
                'released_from' => $released ?: null,
                'by' => $by,
                'via' => $via,
            ]), $terminal);
        }

        return $released;
    }

    /**
     * Takes the T05 off this freezer (its card rail goes back to the wired reader).
     *
     * From Setting/Edit only a row bound FROM a unit is taken off: that form posts the
     * card terminal on every save, and a row made by the command (no unit) must not be
     * switched off by someone saving an unrelated field. The command passes
     * $includeCommandRows to take off either kind.
     */
    public function unbind(Vend $vend, ?int $userId, string $via, bool $includeCommandRows = false): bool
    {
        $terminal = RemoteCardTerminal::query()
            ->where('vend_id', $vend->id)
            ->where('is_active', true)
            ->when(! $includeCommandRows, fn ($q) => $q->whereNotNull('card_terminal_unit_id'))
            ->first();
        if (! $terminal) {
            return false;
        }
        $terminal->update(['is_active' => false]);
        $this->events->record('terminal.deactivated', ['by' => $this->who($userId), 'via' => $via], $terminal);

        return true;
    }

    /** The unit was deleted from Data Management: no freezer may keep selling through it. */
    public function releaseUnit(CardTerminalUnit $unit, ?int $userId): void
    {
        RemoteCardTerminal::query()->where('card_terminal_unit_id', $unit->id)->where('is_active', true)->get()
            ->each(function (RemoteCardTerminal $row) use ($unit, $userId) {
                $row->update(['is_active' => false]);
                $this->events->record('terminal.deactivated', [
                    'by' => $this->who($userId), 'via' => 'card-terminal-units', 'reason' => "T05 {$unit->terminal_id} deleted",
                ], $row);
            });
    }

    /** The unit's SN or token changed in Data Management: every freezer row made from it follows. */
    public function syncUnit(CardTerminalUnit $unit, ?int $userId): void
    {
        $rows = RemoteCardTerminal::query()->where('card_terminal_unit_id', $unit->id)->get();
        foreach ($rows as $row) {
            $changes = ['label' => $unit->terminal_id];
            if ($unit->hasAccessToken() && $row->access_token !== $unit->access_token) {
                $changes['access_token'] = $unit->access_token;
            }
            $row->fill($changes);
            if ($row->isDirty()) {
                $tokenChanged = $row->isDirty('access_token');
                $row->save();
                $this->events->record('terminal.updated', array_filter([
                    'sn' => $unit->terminal_id,
                    'token_replaced' => $tokenChanged ?: null,
                    'by' => $this->who($userId),
                    'via' => 'card-terminal-units',
                ]), $row);
            }
        }
    }

    /** @return list<string> */
    private function releaseFromOtherMachines(RemoteCardTerminal $terminal, CardTerminalUnit $unit, string $by, string $via): array
    {
        if ($unit->company?->can_bind_multiple_vends) {
            return [];
        }

        $released = [];
        RemoteCardTerminal::query()
            ->with('vend')
            ->where('is_active', true)
            ->where('id', '!=', $terminal->id)
            ->get()
            ->filter(fn (RemoteCardTerminal $other) => (int) $other->card_terminal_unit_id === (int) $unit->id
                || ($other->card_terminal_unit_id === null && hash_equals((string) $other->access_token, (string) $terminal->access_token)))
            ->each(function (RemoteCardTerminal $other) use ($terminal, $by, $via, &$released) {
                $other->update(['is_active' => false]);
                $to = $terminal->vend?->codeLabel() ?? (string) $terminal->vend_id;
                $this->events->record('terminal.deactivated', ['by' => $by, 'via' => $via, 'reason' => "terminal moved to {$to}"], $other);
                $released[] = $other->vend?->codeLabel() ?? (string) $other->vend_id;
            });

        return $released;
    }

    private function who(?int $userId): string
    {
        return $userId ? (User::query()->whereKey($userId)->value('name') ?? "user #{$userId}") : 'sys';
    }
}
