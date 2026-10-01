<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\RemoteCardTerminal;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\CardTerminalEventLog;
use Illuminate\Console\Command;

/**
 * Binds a Payrallel terminal (its Sales Channel access token) to a machine, or
 * deactivates the binding. The token is asked for as a hidden prompt so it
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

    public function handle(CardPaymentService $payments, CardTerminalEventLog $events): int
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->bareCode($this->argument('vend'))->first();
        if (! $vend) {
            $this->error('No machine with that code.');

            return self::FAILURE;
        }

        if ($this->option('deactivate')) {
            $n = RemoteCardTerminal::query()->where('vend_id', $vend->id)->update(['is_active' => false]);
            if ($n) {
                $events->record('terminal.deactivated', ['by' => 'payrallel:bind-terminal'], RemoteCardTerminal::where('vend_id', $vend->id)->first());
            }
            $this->info($n ? "Remote terminal on {$vend->code} deactivated." : "No remote terminal on {$vend->code}.");

            return self::SUCCESS;
        }

        $token = (string) $this->secret('Payrallel access token for this terminal');
        if (trim($token) === '') {
            $this->error('No token given.');

            return self::FAILURE;
        }

        $terminal = RemoteCardTerminal::query()->updateOrCreate(
            ['vend_id' => $vend->id],
            [
                'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL,
                'label' => $this->option('label'),
                'access_token' => trim($token),
                'is_active' => true,
            ],
        );
        $this->info("Terminal #{$terminal->id} bound to {$vend->code}.");
        $events->record('terminal.bound', ['label' => $terminal->label, 'by' => 'payrallel:bind-terminal'], $terminal);

        $status = $payments->terminalStatus($vend);
        $this->line(sprintf(
            'Status: %s / %s%s',
            $status?->online ? 'online' : 'offline or unreachable',
            $status?->state ?? '-',
            isset($status?->raw['error']) ? ' ('.$status->raw['error'].')' : '',
        ));

        return self::SUCCESS;
    }
}
