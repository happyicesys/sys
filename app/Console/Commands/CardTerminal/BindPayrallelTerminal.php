<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use App\Models\RemoteCardTerminal;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\CardSettlement\CardTerminalBindingService;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\RemoteCardTerminalBinder;
use Illuminate\Console\Command;

/**
 * Binds a registered Payrallel (T05) terminal to a smart freezer, or takes it off — the
 * same as picking it in Setting/Edit's Card Terminal field (both go through
 * CardTerminalBindingService, so the binding history is recorded too). The T05 itself
 * (SN + access token) is registered under Data Management > Card Terminal.
 *
 *   php artisan payrallel:bind-terminal 50001 --sn=T05SN12345
 *   php artisan payrallel:bind-terminal 50001 --deactivate
 */
class BindPayrallelTerminal extends Command
{
    protected $signature = 'payrallel:bind-terminal
        {vend : bare machine code, e.g. 50001}
        {--sn= : the T05\'s SN, as registered under Data Management > Card Terminal}
        {--deactivate : stop selling through this machine\'s remote terminal}';

    protected $description = 'Bind a registered Payrallel (T05) terminal to a smart freezer, or take it off';

    public function handle(CardPaymentService $payments, CardTerminalBindingService $bindings, RemoteCardTerminalBinder $binder): int
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->bareCode($this->argument('vend'))->first();
        if (! $vend) {
            $this->error('No machine with that code.');

            return self::FAILURE;
        }

        if ($this->option('deactivate')) {
            if ($bindings->currentUnitFor($vend)?->isRemoteTerminal()) {
                $bindings->assignToVend($vend, null); // closes the binding and switches the rail back
            }
            // A row made before T05s were units has no binding to close.
            $binder->unbind($vend, null, 'artisan', includeCommandRows: true);
            $this->info("Remote terminal on {$vend->code} deactivated.");

            return self::SUCCESS;
        }

        if (! $vend->isSmartFreezer()) {
            $this->error("{$vend->code} is not a smart freezer.");

            return self::FAILURE;
        }
        $sn = trim((string) $this->option('sn'));
        $unit = $sn === '' ? null : CardTerminalUnit::query()->with('company')->where('terminal_id', $sn)->first();
        if (! $unit || ! $unit->isRemoteTerminal()) {
            $this->error('Give --sn= of a T05 registered under Data Management > Card Terminal (company '.CardTerminal::NAME_PAYRALLEL.').');

            return self::FAILURE;
        }
        if (! $unit->hasAccessToken()) {
            $this->error("T05 {$sn} has no access token yet — add it under Data Management > Card Terminal.");

            return self::FAILURE;
        }

        $bindings->assignToVend($vend, $unit);
        $terminal = RemoteCardTerminal::query()->where('vend_id', $vend->id)->first();
        $this->info("T05 {$sn} bound to {$vend->code} (remote terminal #{$terminal?->id}).");

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
