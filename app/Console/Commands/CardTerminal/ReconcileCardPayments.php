<?php

namespace App\Console\Commands\CardTerminal;

use App\Services\CardTerminal\CardPaymentService;
use Illuminate\Console\Command;

/**
 * Every minute: drives each remote-terminal card attempt the device stopped
 * polling to a final answer, and voids any approval no door will follow
 * (CardPaymentService::reconcile). A no-op while no intent is open.
 */
class ReconcileCardPayments extends Command
{
    protected $signature = 'card-payments:reconcile';

    protected $description = 'Resolve abandoned remote-terminal card attempts and void orphaned approvals';

    public function handle(CardPaymentService $payments): int
    {
        $n = $payments->reconcile();
        if ($n > 0) {
            $this->info("Reconciled {$n} card attempt(s).");
        }

        return self::SUCCESS;
    }
}
