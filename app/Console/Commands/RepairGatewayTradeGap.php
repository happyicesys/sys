<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\PaymentGatewayLog;
use App\Models\Vend;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Repair the gateway rows stranded by the TRADE pre-check gap.
 *
 * THE BUG. On 2026-08-10 20:02:34 (commit 8070ed3b87) CreateVendTransaction gained an
 * `alreadyRecorded()` pre-check that skips the job whenever a vend_transaction already exists on
 * `(order_id, vend_id)`. Under unified transactions GatewayVendTransactionService pre-creates
 * exactly such a row at gateway paid-time, so from that minute EVERY QR/PayNow sale's TRADE was
 * dropped before reaching VendTransactionService — the only place that applies the machine's
 * ground truth to a pre-created row and flips `is_found_in_transaction`. Symptom on the Payment
 * Gateway Transactions page: "Dispense Attempted? ✓ / Found in Transactions? ✗" on every row.
 *
 * WHAT IS AND IS NOT RECOVERABLE. The TRADE payload itself is gone — the job returned before
 * touching it, the machine was already ACKed at the HTTP layer, and nothing else persists the
 * frame (`dispense_records` records only that the VM got the dispense signal). So `success_qty`,
 * `dispensed_qty`, `vend_transaction_json` and the per-channel error codes cannot be reconstructed
 * for the affected window, and `is_found_in_transaction` stays false — it means "the machine's
 * TRADE was applied to this row", which is still not true. Flipping it would hide a real gap.
 *
 * WHAT THIS REPAIRS. The money. A pre-created row is SETTLED by one of two events: the dispense
 * ACK (GetPurchaseConfirm) or the TRADE. Where the ACK lost the race — it consistently lands 1–3s
 * BEFORE the paid-webhook pre-create, so it finds no row to settle — the TRADE used to settle the
 * row and no longer does. Those rows are stuck PENDING and PENDING is excluded from every sales
 * aggregation (`settledSql()`), so real dispensed revenue is missing from the dashboards.
 *
 * THE RULE APPLIED. `settlement_status = PENDING` + PG log approved + `is_dispensed = 1`
 * → SETTLED. That is not a new judgement: it is the rule GetPurchaseConfirm already applies live
 * ("the item dispensed, so the pre-created row is a sale now even if the TRADE never arrives") and
 * the same rule the dashboards use for dispensed-but-unreported gateway revenue. Rows whose PG log
 * is refunded, or that were never dispensed, are left alone and reported for manual review.
 *
 * Every repaired row is stamped `meta_json.trade_gap_repair` so the change is auditable and the
 * command is idempotent.
 *
 * DOWNSTREAM — this moves rows into the settled population, so the rollups must be rebuilt:
 *
 *   php artisan repair:gateway-trade-gap                 # report only (default)
 *   php artisan repair:gateway-trade-gap --apply
 *   php artisan reconcile:range --from=2026-08-10 --to=<yesterday>
 */
class RepairGatewayTradeGap extends Command
{
    /**
     * Deploy time of the bad pre-check (commit 8070ed3b87), verified against the data: the 20:00
     * hour on 2026-08-10 is 53/57 broken, every hour after it is 100% broken, every hour before it
     * is clean.
     */
    public const GAP_START = '2026-08-10 20:02:34';

    protected $signature = 'repair:gateway-trade-gap
        {--from= : Window start (default: 2026-08-10 20:02:34, when the bad pre-check deployed)}
        {--to= : Window end (default: now)}
        {--apply : Write the repair. Without this the command only reports}
        {--chunk=500 : Rows per chunk}';

    protected $description = 'Settle gateway rows left PENDING by the dropped-TRADE gap (2026-08-10 20:02 onward), and report what is not recoverable.';

    public function handle(): int
    {
        $from = Carbon::parse($this->option('from') ?: self::GAP_START);
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : Carbon::now();
        $apply = (bool) $this->option('apply');

        $this->info('Window: '.$from->toDateTimeString().' → '.$to->toDateTimeString());
        $this->newLine();

        $settled = (clone $this->scope($from, $to))
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->count();

        $refunded = (clone $this->scope($from, $to))
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_REFUNDED)
            ->count();

        $review = (clone $this->scope($from, $to))
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_PENDING)
            ->where('pgl.is_dispensed', false)
            ->count();

        $repairableCount = (clone $this->repairable($from, $to))->count();
        $repairableCents = (int) (clone $this->repairable($from, $to))->sum('vend_transactions.amount');

        $this->table(
            ['Bucket', 'Rows', 'Action'],
            [
                ['Settled, machine data unrecoverable', $settled, 'none — money is already counted'],
                ['PENDING + dispensed', $repairableCount, 'SETTLE ('.$this->money($repairableCents).')'],
                ['PENDING + never dispensed', $review, 'manual review — refund or void'],
                ['Refunded', $refunded, 'none'],
            ]
        );

        if ($repairableCount === 0) {
            $this->info('Nothing to settle in this window.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run. Re-run with --apply to settle '.$repairableCount.' row(s).');

            return self::SUCCESS;
        }

        $stamp = [
            'at' => Carbon::now()->toDateTimeString(),
            'rule' => 'dispensed_no_trade',
            'reason' => 'TRADE dropped by CreateVendTransaction pre-check (2026-08-10 20:02 → fix)',
        ];

        $repaired = 0;
        $vendIds = [];

        (clone $this->repairable($from, $to))
            ->select('vend_transactions.*')
            ->chunkById((int) $this->option('chunk'), function ($transactions) use ($stamp, &$repaired, &$vendIds) {
                foreach ($transactions as $transaction) {
                    $meta = (array) $transaction->meta_json;
                    $meta['trade_gap_repair'] = $stamp;

                    $transaction->forceFill([
                        'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
                        'meta_json' => $meta,
                    ])->save();

                    $repaired++;
                    $vendIds[$transaction->vend_id] = true;
                }

                $this->output->write('.');
            }, 'vend_transactions.id', 'id');

        $this->newLine();
        $this->info('Settled '.$repaired.' row(s) across '.count($vendIds).' machine(s).');

        // Refresh the per-machine cached totals the machine page reads.
        foreach (array_keys($vendIds) as $vendId) {
            if ($vend = Vend::withoutGlobalScopes()->find($vendId)) {
                SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('low');
            }
        }

        $this->newLine();
        $this->warn('Rollups still hold the pre-repair figures. Rebuild them:');
        $this->line('  php artisan reconcile:range --from='.$from->toDateString().' --to='.Carbon::yesterday()->toDateString());

        return self::SUCCESS;
    }

    /**
     * Every gateway row in the window that never received its TRADE. Global scopes are bypassed
     * deliberately: the console has no authenticated user, so the operator scopes would silently
     * narrow the repair to nothing.
     */
    private function scope(Carbon $from, Carbon $to)
    {
        return VendTransaction::withoutGlobalScopes()
            ->join('payment_gateway_logs as pgl', 'pgl.id', '=', 'vend_transactions.payment_gateway_log_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$from, $to])
            ->where('vend_transactions.is_found_in_transaction', false);
    }

    /**
     * PENDING, the gateway payment is approved (not refunded), and the machine was sent the
     * dispense signal — the same "dispensed ⇒ sale" test GetPurchaseConfirm applies live.
     */
    private function repairable(Carbon $from, Carbon $to)
    {
        return $this->scope($from, $to)
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_PENDING)
            ->where('pgl.status', PaymentGatewayLog::STATUS_APPROVE)
            ->where('pgl.is_dispensed', true);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
