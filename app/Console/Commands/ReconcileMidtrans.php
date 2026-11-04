<?php

namespace App\Console\Commands;

use App\Services\Payment\MidtransReconciler;
use Illuminate\Console\Command;

/**
 * php artisan payment:reconcile-midtrans                   # asks Midtrans, writes nothing
 * php artisan payment:reconcile-midtrans --apply
 * php artisan payment:reconcile-midtrans --order=<id>      # one order, whatever its age
 */
class ReconcileMidtrans extends Command
{
    protected $signature = 'payment:reconcile-midtrans
        {--apply : Write what Midtrans says. Without this the command only reports}
        {--days= : Look back this many days (default config payment.midtrans_reconcile.days)}
        {--limit= : Most orders to ask about in one run (default config payment.midtrans_reconcile.limit)}
        {--order= : Only this order id}';

    protected $description = 'Ask Midtrans about expired QRs with no final notification. Records the answer; never dispenses.';

    public function handle(MidtransReconciler $reconciler): int
    {
        $apply = (bool) $this->option('apply');
        $result = $reconciler->run(
            $apply,
            (int) ($this->option('days') ?: config('payment.midtrans_reconcile.days', 90)),
            (int) ($this->option('limit') ?: config('payment.midtrans_reconcile.limit', 300)),
            $this->option('order') ?: null,
        );

        if ($result['rows'] !== []) {
            $this->table(
                ['Order', 'Machine', 'Amount', 'Midtrans', 'Action', 'Note'],
                array_map(fn ($r) => [$r['order_id'], $r['vend_code'], $r['amount'], $r['midtrans'], $r['action'], $r['note']], $result['rows']),
            );
        }
        $this->info(($apply ? 'Applied: ' : 'Dry run: ').collect($result['counts'])->map(fn ($n, $k) => "{$k} {$n}")->implode(', '));

        return self::SUCCESS;
    }
}
