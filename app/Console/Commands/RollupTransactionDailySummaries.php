<?php

namespace App\Console\Commands;

use App\Models\PaymentGatewayLog;
use App\Models\VendTransaction;
use App\Models\VendTransactionDailySummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate vend_transaction_daily_summaries — the per-operator-per-day rollup of
 * the transactions-index headline aggregates.
 *
 * Recomputes a moving window (default last 14 completed days, EXCLUDING today,
 * to absorb late settlements — same rationale as reconcile:sales-rollups) from
 * vend_transactions (source of truth). Idempotent: INSERT ON DUPLICATE KEY
 * UPDATE, so re-running heals any drift. Stores RAW additive fields only; rates
 * and the unreported merge are derived on read.
 *
 * Processed in MONTHLY chunks with a progress bar, so a wide backfill scans one
 * month at a time (bounded resource use) instead of three giant queries. Each
 * day belongs to exactly one chunk, so chunking never double-counts.
 *
 * The aggregate CASE expressions come from VendTransaction::salesRawTotalsSelect()
 * / salesItemTotalsSelect() — the SAME definitions the live query uses.
 *
 *   php artisan transactions:rollup-daily                  # last 14 completed days
 *   php artisan transactions:rollup-daily --days=45
 *   php artisan transactions:rollup-daily --from=2023-01-01   # backfill to yesterday
 */
class RollupTransactionDailySummaries extends Command
{
    protected $signature = 'transactions:rollup-daily {--days=14} {--from=} {--to=}';

    protected $description = 'Roll up per-operator-per-day transaction headline totals (excludes today).';

    public function handle(): int
    {
        // Window: [from, to]. Never include today (still mutating).
        $yesterdayEnd = Carbon::today()->subDay()->endOfDay();
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : $yesterdayEnd;
        if ($to->gt($yesterdayEnd)) {
            $to = $yesterdayEnd;
        }
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::today()->subDays((int) $this->option('days'))->startOfDay();

        if ($from->gt($to)) {
            $this->info('Nothing to roll up (from > to).');
            return self::SUCCESS;
        }

        $testingVendIds = DB::table('vends')->where('is_testing', true)->pluck('id')->all();

        // Build monthly chunks: [chunkFrom, chunkTo] clamped to [from, to].
        $periods = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $chunkFrom = $cursor->copy();
            $chunkTo = $cursor->copy()->endOfMonth();
            if ($chunkTo->gt($to)) {
                $chunkTo = $to->copy();
            }
            $periods[] = [$chunkFrom, $chunkTo];
            $cursor = $cursor->copy()->startOfMonth()->addMonth();
        }

        $this->info("Rolling up {$from->toDateString()} .. {$to->toDateString()} in " . count($periods) . " monthly chunk(s) ...");

        $written = 0;
        $this->withProgressBar($periods, function ($period) use (&$written, $testingVendIds) {
            [$chunkFrom, $chunkTo] = $period;
            $written += $this->rollupPeriod(
                $chunkFrom->toDateTimeString(),
                $chunkTo->toDateTimeString(),
                $testingVendIds
            );
        });

        $this->newLine(2);
        $this->info("Wrote {$written} operator-day summary rows.");

        return self::SUCCESS;
    }

    /**
     * Roll up a single [fromStr, toStr] window into the summary table.
     * Returns the number of operator-day rows written. Same 3-query + merge +
     * upsert logic for every chunk.
     */
    private function rollupPeriod(string $fromStr, string $toStr, array $testingVendIds): int
    {
        // --- 1. Main raw totals per (operator_id, date) --------------------------
        $mainRows = VendTransaction::query()
            ->withoutGlobalScopes()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            // The live page filters operator_id IN (...), so null-operator rows
            // (old data) are never counted — exclude them here too (and it avoids
            // a null group violating the summary's NOT NULL operator_id).
            ->whereNotNull('vend_transactions.operator_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$fromStr, $toStr])
            ->select(array_merge(
                [
                    'vend_transactions.operator_id',
                    DB::raw('DATE(vend_transactions.transaction_datetime) as d'),
                ],
                VendTransaction::salesRawTotalsSelect()
            ))
            ->groupBy('vend_transactions.operator_id', DB::raw('DATE(vend_transactions.transaction_datetime)'))
            ->get();

        // --- 2. Item totals per (operator_id, date) ------------------------------
        $itemRows = VendTransaction::query()
            ->withoutGlobalScopes()
            ->where('is_multiple', true)
            ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
            ->whereNotNull('vend_transactions.operator_id')
            ->whereBetween('vend_transactions.transaction_datetime', [$fromStr, $toStr])
            ->leftJoin('vend_transaction_items', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
            ->select(array_merge(
                [
                    'vend_transactions.operator_id',
                    DB::raw('DATE(vend_transactions.transaction_datetime) as d'),
                ],
                VendTransaction::salesItemTotalsSelect()
            ))
            ->groupBy('vend_transactions.operator_id', DB::raw('DATE(vend_transactions.transaction_datetime)'))
            ->get()
            ->keyBy(fn($r) => $r->operator_id . '|' . $r->d);

        // --- 3. Unreported dispensed gateway revenue per (operator_id, date) -----
        $unreportedRows = PaymentGatewayLog::query()
            ->join('operator_payment_gateways', 'payment_gateway_logs.operator_payment_gateway_id', '=', 'operator_payment_gateways.id')
            ->where('payment_gateway_logs.status', PaymentGatewayLog::STATUS_APPROVE)
            ->where('payment_gateway_logs.is_dispensed', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('vend_transactions')
                    ->whereColumn('vend_transactions.payment_gateway_log_id', 'payment_gateway_logs.id');
            })
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('payment_gateway_logs.vend_id', $testingVendIds))
            ->where('payment_gateway_logs.approved_at', '>=', PaymentGatewayLog::UNREPORTED_GATEWAY_CUTOFF)
            ->whereBetween('payment_gateway_logs.approved_at', [$fromStr, $toStr])
            ->select(
                'operator_payment_gateways.operator_id',
                DB::raw('DATE(payment_gateway_logs.approved_at) as d'),
                DB::raw('COALESCE(SUM(payment_gateway_logs.amount), 0) as unreported_major')
            )
            ->groupBy('operator_payment_gateways.operator_id', DB::raw('DATE(payment_gateway_logs.approved_at)'))
            ->get()
            ->keyBy(fn($r) => $r->operator_id . '|' . $r->d);

        // --- 4. Merge + upsert ---------------------------------------------------
        $now = now();
        $written = 0;

        foreach ($mainRows as $m) {
            $key = $m->operator_id . '|' . $m->d;
            $item = $itemRows->get($key);
            $unrep = $unreportedRows->get($key);

            VendTransactionDailySummary::updateOrCreate(
                ['operator_id' => $m->operator_id, 'date' => $m->d],
                [
                    'total_count'                      => (int) $m->total_count,
                    'success_count'                    => (int) $m->success_count,
                    'cash_count'                       => (int) $m->cash_count,
                    'cashless_terminal_count'          => (int) $m->cashless_terminal_count,
                    'qr_payment_count'                 => (int) $m->qr_payment_count,
                    'delivery_platform_success_count'  => (int) $m->delivery_platform_success_count,
                    'single_qty'                       => (int) $m->single_qty,
                    'success_single_qty'               => (int) $m->success_single_qty,
                    'multiple_count_delivery_platform' => (int) $m->multiple_count_delivery_platform,
                    'multiple_count_machine'           => (int) $m->multiple_count_machine,
                    'success_amount'                   => (int) round((float) $m->success_amount),
                    'cash_amount'                      => (int) round((float) $m->cash_amount),
                    'cashless_terminal_amount'         => (int) round((float) $m->cashless_terminal_amount),
                    'qr_payment_amount'                => (int) round((float) $m->qr_payment_amount),
                    'delivery_platform_success_amount' => (int) round((float) $m->delivery_platform_success_amount),
                    'total_items'                      => (int) ($item->total_items ?? 0),
                    'success_items'                    => (int) ($item->success_items ?? 0),
                    'unreported_gateway_amount_major'  => (float) ($unrep->unreported_major ?? 0),
                    'computed_at'                      => $now,
                ]
            );
            $written++;
        }

        // Days that have ONLY unreported gateway rows (no settled vend_transaction
        // that day) still need a summary row so the read path sees the unreported
        // revenue. Rare, but handled for correctness.
        foreach ($unreportedRows as $key => $unrep) {
            if ($mainRows->first(fn($m) => ($m->operator_id . '|' . $m->d) === $key)) {
                continue;
            }
            [$opId, $d] = explode('|', $key);
            VendTransactionDailySummary::updateOrCreate(
                ['operator_id' => (int) $opId, 'date' => $d],
                [
                    'unreported_gateway_amount_major' => (float) $unrep->unreported_major,
                    'computed_at' => $now,
                ]
            );
            $written++;
        }

        return $written;
    }
}
