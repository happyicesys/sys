<?php

namespace App\Console\Commands;

use App\Models\VendTransaction;
use App\Services\Refund\RefundTicketService;
use App\Support\AutoRefundSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill for card-terminal reversals that pre-date the live rule
 * (VendTransactionService::markCardTerminalReversal, 2026-08-23).
 *
 * Selects card rows with the reversal footprint — terminal in
 * config('refund.card_reversal_terminals'), single item, success_qty = 0,
 * error ∉ {0,6}, ISOK = 0, not yet refunded — and marks them
 * is_refunded = 1 / auto_refund_source = card_terminal_reversal, then runs the
 * same ticket guard the live path runs (markAutoRefundedByCharge: open tickets
 * get the "already refunded" cross, approved/scheduled ones are pulled out of
 * payout; COMPLETED tickets are never touched — those are the reconciliation
 * list in REFUND_INTEGRITY_AUDIT_2026-08-23.md, not a data fix).
 *
 * DRY-RUN BY DEFAULT. Pass --apply to write. Bounded by --from/--to on
 * transaction_datetime (indexed) and chunked so it is safe on the live DB.
 */
class BackfillCardTerminalReversals extends Command
{
    protected $signature = 'refund:backfill-card-reversals
        {--from= : transaction_datetime >= (Y-m-d[ H:i:s]); required}
        {--to= : transaction_datetime < (Y-m-d[ H:i:s]); default now}
        {--apply : actually write; without it only a summary is printed}
        {--chunk=500}';

    protected $description = 'Mark historical single-item card dispense failures on reversing terminals (NETS) as auto-refunded (dry-run unless --apply)';

    public function handle(RefundTicketService $tickets): int
    {
        $from = $this->option('from');
        if (! $from) {
            $this->error('--from is required (e.g. --from=2026-07-01)');

            return self::FAILURE;
        }
        $to = $this->option('to') ?: now()->toDateTimeString();
        $apply = (bool) $this->option('apply');
        $terminals = (array) config('refund.card_reversal_terminals', []);
        if (empty($terminals)) {
            $this->error('config refund.card_reversal_terminals is empty — nothing to do');

            return self::FAILURE;
        }

        // Failure codes = every known channel error except the success set {0,6},
        // plus 8 (the APK's "could not dispense" fallback). Listing them lets
        // MySQL use the error_code_normalized index instead of scanning every
        // card row in the window; the JSON ISOK check then runs on that small set.
        $failureCodes = \App\Models\VendChannelError::query()->pluck('code')
            ->map(fn ($c) => (int) $c)->reject(fn ($c) => in_array($c, [0, 6], true))
            ->push(8)->unique()->values()->all();

        $query = VendTransaction::withoutGlobalScopes()
            ->whereIn('error_code_normalized', $failureCodes)
            ->where('transaction_datetime', '>=', $from)
            ->where('transaction_datetime', '<', $to)
            ->whereIn('cashless_mfg', $terminals)
            ->where('is_multiple', false)
            ->where('is_refunded', false)
            ->where('success_qty', 0)
            ->whereRaw("JSON_EXTRACT(vend_transaction_json, '$.ISOK') = 0")
            ->orderBy('id');

        $total = (clone $query)->count();
        $byTerminal = (clone $query)
            ->select('cashless_mfg', 'error_code_normalized', DB::raw('COUNT(*) n'), DB::raw('SUM(amount) cents'))
            ->groupBy('cashless_mfg', 'error_code_normalized')
            ->get();

        $this->info(($apply ? 'APPLY' : 'DRY-RUN')." — {$total} card rows with the reversal footprint, {$from} ≤ t < {$to}");
        $this->table(['terminal', 'err', 'rows', 'amount'], $byTerminal->map(fn ($r) => [
            $r->cashless_mfg, $r->error_code_normalized, $r->n, number_format($r->cents / 100, 2),
        ])->all());

        if (! $apply || $total === 0) {
            $this->line('No writes made.'.($apply ? '' : ' Re-run with --apply to write.'));

            return self::SUCCESS;
        }

        $marked = 0;
        $ticketsTouched = 0;
        $query->chunkById((int) $this->option('chunk'), function ($rows) use (&$marked, &$ticketsTouched, $tickets) {
            foreach ($rows as $txn) {
                $txn->forceFill([
                    'is_refunded' => true,
                    'auto_refund_source' => AutoRefundSource::CARD_TERMINAL_REVERSAL,
                ])->save();
                $marked++;

                try {
                    $ticketsTouched += $tickets->markAutoRefundedByCharge($txn->order_id, null, $txn->id);
                } catch (\Throwable $e) {
                    $this->warn("ticket guard failed for txn {$txn->id}: ".$e->getMessage());
                }
            }
        });

        $this->info("Marked {$marked} transactions; {$ticketsTouched} open refund ticket(s) flagged already-refunded.");

        return self::SUCCESS;
    }
}
