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
 * not yet refunded, and per frame shape: VMC-keypad frames (TXN_SRC 0) need
 * header error ∉ {0,6} + ISOK = 0; Android soft-keyboard frames (TXN_SRC ≥ 1,
 * ISOK hard-coded 1) need transf_info[0].SErr ∉ {0,6,7} — and marks them
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

        // Card payment methods only (code 1) — the live predicate requires
        // paymentClassification 'card'. cashless_mfg alone is not that guarantee:
        // pre-2026-05 rows had it stamped from VMC telemetry on ALL transactions.
        $cardMethodIds = \App\Models\PaymentMethod::query()->where('code', 1)->pluck('id')->all();

        $query = VendTransaction::withoutGlobalScopes()
            ->where('transaction_datetime', '>=', $from)
            ->where('transaction_datetime', '<', $to)
            ->whereIn('cashless_mfg', $terminals)
            ->whereIn('payment_method_id', $cardMethodIds)
            ->where('is_multiple', false)
            ->where('is_refunded', false)
            ->where('success_qty', 0)
            // The TRADE arrives in two shapes (mirrors isCardTerminalReversal):
            ->where(function ($q) use ($failureCodes) {
                // VMC-keypad frames (TXN_SRC 0): header SErr is normalized into
                // error_code_normalized (index-friendly) and ISOK = 0 is a veto.
                $q->where(function ($q) use ($failureCodes) {
                    $q->whereRaw('COALESCE(interface_type, 0) = 0')
                        ->whereIn('error_code_normalized', $failureCodes)
                        ->whereRaw("JSON_EXTRACT(vend_transaction_json, '$.ISOK') = 0");
                })
                    // Android-built soft-keyboard frames (TXN_SRC >= 1): ISOK is
                    // hard-coded 1 and the header SErr is absent, so
                    // error_code_normalized is NULL — the error lives in
                    // transf_info[0].SErr. Err 7 is excluded UNCONDITIONALLY
                    // here (stricter than the live predicate, which admits it on
                    // machines reporting APK v303+): on <= v301 NETS can RETAIN
                    // the credit for a free re-vend instead of reversing, and a
                    // machine's version AT TRADE TIME is unknowable historically
                    // — its current version proves nothing about old rows.
                    ->orWhere(function ($q) {
                        $q->whereRaw('COALESCE(interface_type, 0) >= 1')
                            ->whereRaw("CAST(JSON_EXTRACT(vend_transaction_json, '$.transf_info[0].SErr') AS SIGNED) NOT IN (0, 6, 7)");
                    });
            })
            ->orderBy('id');

        // Frame-shape-aware error for the summary: header SErr where normalized,
        // else the Android frame's transf_info error.
        $errExpr = "COALESCE(error_code_normalized, CAST(JSON_EXTRACT(vend_transaction_json, '$.transf_info[0].SErr') AS SIGNED))";

        $total = (clone $query)->count();
        $byTerminal = (clone $query)
            ->select('cashless_mfg', DB::raw("{$errExpr} err"), DB::raw('COUNT(*) n'), DB::raw('SUM(amount) cents'))
            ->groupBy('cashless_mfg', DB::raw($errExpr))
            ->get();

        $this->info(($apply ? 'APPLY' : 'DRY-RUN')." — {$total} card rows with the reversal footprint, {$from} ≤ t < {$to}");
        $this->table(['terminal', 'err', 'rows', 'amount'], $byTerminal->map(fn ($r) => [
            $r->cashless_mfg, $r->err, $r->n, number_format($r->cents / 100, 2),
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
