<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\RemoteCardTerminal;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\RemoteCardTerminalBinder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Binds a Payrallel terminal (its Sales Channel access token) to a machine, or
 * deactivates the binding. Same rules as Setting > Edit > "Remote card terminal (T05)"
 * (both go through RemoteCardTerminalBinder). The token is asked for as a hidden prompt so it
 * never lands in shell history, and is stored encrypted.
 *
 *   php artisan payrallel:bind-terminal 2009 --label="Bench UPT"
 *   php artisan payrallel:bind-terminal 2009 --deactivate
 */
class BindPayrallelTerminal extends Command
{
    protected $signature = 'payrallel:bind-terminal
        {vend : bare machine code, e.g. 2009}
        {--label= : a name for the terminal, e.g. its serial}
        {--deactivate : stop selling through this machine\'s remote terminal}';

    protected $description = 'Bind a Payrallel remote terminal token to a machine (smart-freezer card rail)';

    public function handle(CardPaymentService $payments, RemoteCardTerminalBinder $binder): int
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->bareCode($this->argument('vend'))->first();
        if (! $vend) {
            $this->error('No machine with that code.');

            return self::FAILURE;
        }

        if ($this->option('deactivate')) {
            if (! RemoteCardTerminal::query()->where('vend_id', $vend->id)->exists()) {
                $this->info("No remote terminal on {$vend->code}.");

                return self::SUCCESS;
            }
            $existing = RemoteCardTerminal::query()->where('vend_id', $vend->id)->first();
            $this->runBinder(fn () => $binder->save($vend, false, $existing->label, null, 'payrallel:bind-terminal', 'artisan'));
            $this->info("Remote terminal on {$vend->code} deactivated.");

            return self::SUCCESS;
        }

        $token = (string) $this->secret('Payrallel access token for this terminal');
        if (trim($token) === '') {
            $this->error('No token given.');

            return self::FAILURE;
        }

        $result = $this->runBinder(fn () => $binder->save($vend, true, $this->option('label'), $token, 'payrallel:bind-terminal', 'artisan'));
        if ($result === null) {
            return self::FAILURE;
        }
        $this->info("Terminal #{$result['terminal']->id} bound to {$vend->code}.");
        if ($result['released_from']) {
            $this->warn('Taken off: '.implode(', ', $result['released_from']).' (one terminal, one machine — see Card Terminal Company).');
        }

        $status = $payments->terminalStatus($vend);
        $this->line(sprintf(
            'Status: %s / %s%s',
            $status?->online ? 'online' : 'offline or unreachable',
            $status?->state ?? '-',
            isset($status?->raw['error']) ? ' ('.$status->raw['error'].')' : '',
        ));

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null the binder's result, or null after printing why it refused */
    private function runBinder(callable $save): ?array
    {
        try {
            return $save();
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return null;
        }
    }
}
