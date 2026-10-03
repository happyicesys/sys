<?php

namespace App\Services\CardTerminal;

use App\Models\CardTerminal;
use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use Illuminate\Validation\ValidationException;

/**
 * The one place a remote card terminal (Payrallel T05) is bound to, or taken off, a
 * machine — used by Setting > Edit and by `php artisan payrallel:bind-terminal`.
 *
 * One terminal, one machine, unless the Payrallel (T05) company allows a terminal to
 * serve several (Data Management > Card Terminal Company > "One terminal can serve
 * several machines", off by default): binding a token that is active on another machine
 * takes it off that machine. Tokens are encrypted at rest, so the comparison decrypts the
 * other active rows; there are only a handful.
 */
class RemoteCardTerminalBinder
{
    public function __construct(private readonly CardTerminalEventLog $events) {}

    /**
     * @param  string|null  $token  blank keeps the stored token (required for a first bind)
     * @return array{terminal: RemoteCardTerminal, released_from: list<string>, event: string}
     */
    public function save(Vend $vend, bool $active, ?string $label, ?string $token, string $by, string $via): array
    {
        // Taking a terminal OFF is always allowed; only binding needs a machine whose app can use it.
        if ($active && ! $vend->isSmartFreezer()) {
            throw ValidationException::withMessages(['vend' => 'Remote card terminals are for smart freezers only.']);
        }

        $existing = RemoteCardTerminal::query()->where('vend_id', $vend->id)->first();
        $token = trim((string) $token);
        if ($active && $token === '' && ! filled($existing?->getRawOriginal('access_token'))) {
            throw ValidationException::withMessages(['access_token' => "Paste the terminal's Payrallel access token to bind it."]);
        }

        $wasActive = (bool) $existing?->is_active;
        $attributes = ['provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL, 'label' => $label, 'is_active' => $active];
        if ($token !== '') {
            $attributes['access_token'] = $token;
        }
        $terminal = RemoteCardTerminal::query()->updateOrCreate(['vend_id' => $vend->id], $attributes);

        $released = $terminal->is_active ? $this->releaseFromOtherMachines($terminal, $by, $via) : [];

        // A freezer selling through a T05 names it as its card reader company, unless
        // someone already set one by hand.
        if ($terminal->is_active && ! $vend->card_terminal_id) {
            $companyId = CardTerminal::query()->where('name', CardTerminal::NAME_PAYRALLEL)->value('id');
            if ($companyId) {
                $vend->update(['card_terminal_id' => $companyId]);
            }
        }

        $event = match (true) {
            $terminal->is_active && ! $wasActive => 'terminal.bound',
            ! $terminal->is_active && $wasActive => 'terminal.deactivated',
            default => 'terminal.updated',
        };
        $this->events->record($event, array_filter([
            'label' => $terminal->label,
            'token_replaced' => $event === 'terminal.updated' && $token !== '' ? true : null,
            'released_from' => $released ?: null,
            'by' => $by,
            'via' => $via,
        ], fn ($v) => $v !== null), $terminal);

        return ['terminal' => $terminal, 'released_from' => $released, 'event' => $event];
    }

    /** @return list<string> machine IDs the terminal was taken off */
    private function releaseFromOtherMachines(RemoteCardTerminal $terminal, string $by, string $via): array
    {
        $multi = (bool) CardTerminal::query()->where('name', CardTerminal::NAME_PAYRALLEL)->value('can_bind_multiple_vends');
        if ($multi) {
            return [];
        }

        $released = [];
        RemoteCardTerminal::query()
            ->with('vend')
            ->where('provider', $terminal->provider)
            ->where('is_active', true)
            ->where('id', '!=', $terminal->id)
            ->get()
            ->filter(fn (RemoteCardTerminal $other) => hash_equals((string) $other->access_token, (string) $terminal->access_token))
            ->each(function (RemoteCardTerminal $other) use ($terminal, $by, $via, &$released) {
                $other->update(['is_active' => false]);
                $to = $terminal->vend?->codeLabel() ?? (string) $terminal->vend_id;
                $this->events->record('terminal.deactivated', ['by' => $by, 'via' => $via, 'reason' => "terminal moved to {$to}"], $other);
                $released[] = $other->vend?->codeLabel() ?? (string) $other->vend_id;
            });

        return $released;
    }
}
