<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair `vend_transaction_items.unit_cost` for rows written before the unit
 * fix landed on 2026-02-28.
 *
 * THE BUG. The column is CENTS (same unit as `unit_price_amount`), and the
 * current write path stores `$unitCost->cost * 100` — where the UnitCost `cost`
 * accessor has already divided the raw DB cents by 100 — so what lands in the
 * column is exactly `unit_costs.cost` as stored. Before the fix the multiply was
 * missing, so a $1.36 item was written as `1` into an INT column: roughly 100×
 * too small, and truncated on top. Across ~14k items a month the whole fleet
 * booked S$155–168 of product cost instead of ~S$16k.
 *
 * WHY IT MATTERS. GpMetricsAggregator's multi-basket branch derives
 * gross_profit as `revenue − unit_cost`, so every multi-item basket before the
 * cutover carries ~100% margin. That flows into gp_metrics.gross_profit_cents →
 * customer_period_summaries.gross_earning_cents → the Dashboard's "Sales by
 * Months" breakdown, where Product Cost is the residual
 * `sales(excl GST) − Gross Earning`. Multi-heavy sites (the Zoo estate is
 * almost entirely multi-purchase) show a product cost of ~17% of ex-GST sales
 * against ~35% for single transactions, and a correspondingly inflated Vend
 * Earning and VE ratio.
 *
 * THE REPAIR. `unit_cost_id` was always written correctly, so the true value is
 * recoverable exactly: `vend_transaction_items.unit_cost = unit_costs.cost`
 * (the raw DB value, in cents), which is byte-for-byte what the post-fix write
 * path produces — verified against 29,725/29,725 post-cutover rows that carry a
 * unit_cost_id.
 *
 * CUTOVER. Verified from the data: match rate against `unit_costs.cost` is
 * ~0.4–1.7% daily through 2026-02-26, 11.4% on 2026-02-27 (the deploy day), and
 * exactly 1.000 every day from 2026-02-28. Hence the default `--before` bound
 * of 2026-02-28, which re-repairs the partial deploy day.
 *
 * NOT REPAIRABLE. ~9,400 pre-cutover rows (~4.4%) have a NULL `unit_cost_id`
 * and no recoverable cost; they keep whatever they hold. Post-cutover rows have
 * the same gap at a similar rate, so this is pre-existing behaviour, not a
 * regression introduced here.
 *
 * ONE JUDGEMENT CALL. `unit_costs` rows are mutable — all 294 carry an
 * `updated_at` later than `created_at`, though that is equally consistent with
 * `date_to`/`is_current` being flipped as the cost version rolled over, which is
 * the far more likely cause. Where a cost value itself was edited after the
 * sale, this backfill restores the row's CURRENT value rather than the value in
 * force at TRADE time. That is the only recoverable source, and it is the same
 * source the corrected write path reads — but it means the result is an
 * accurate reconstruction, not a byte-exact replay of history.
 *
 * DOWNSTREAM — this command fixes the source column ONLY. Nothing that has
 * already been aggregated moves until you rebuild, in this order:
 *
 *   php artisan gp:compute-metrics --from=2023-12-11 --to=2026-02-28
 *   php artisan customer-summary:compute --from=2023-12 --to=2026-02
 *
 * That restates Gross Earning and Vend Earning DOWNWARD for every affected
 * month. Locked customer_period_summaries rows are preserved by the aggregator;
 * months already locked will keep their frozen figures and stay inconsistent
 * with the repaired source until they are deliberately unlocked and recomputed.
 */
class BackfillVendTransactionItemUnitCost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backfill:vti-unit-cost
                            {--before=2026-02-28 : Only repair items created before this date (exclusive). Default = the verified fix cutover.}
                            {--from= : Optional lower bound (YYYY-MM-DD) to repair one slice at a time}
                            {--chunk=5000 : Rows per UPDATE batch}
                            {--dry-run : Report the impact per month and change nothing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair vend_transaction_items.unit_cost (cents) from unit_costs.cost for rows written before the 2026-02-28 unit fix.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $before = Carbon::parse($this->option('before'))->startOfDay();
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $chunk = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($from && $from->gte($before)) {
            $this->error('--from must be earlier than --before.');

            return 1;
        }

        $this->info('Repairing vend_transaction_items.unit_cost from unit_costs.cost');
        $this->comment('  window : '.($from ? $from->toDateString() : 'beginning of time').' → '.$before->toDateString().' (exclusive)');
        $this->comment('  mode   : '.($dryRun ? 'DRY RUN — nothing will be written' : 'APPLY'));
        $this->newLine();

        // ── Impact preview ────────────────────────────────────────────────────
        // Grouped by month so the restatement can be sanity-checked against the
        // dashboard before anything is written.
        $preview = $this->affectedQuery($before, $from)
            ->selectRaw("DATE_FORMAT(vti.created_at, '%Y-%m') AS ym")
            ->selectRaw('COUNT(*) AS items')
            ->selectRaw('SUM(COALESCE(vti.unit_cost, 0)) AS booked_cents')
            ->selectRaw('SUM(uc.cost) AS true_cents')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        if ($preview->isEmpty()) {
            $this->info('Nothing to repair — every item in the window already matches unit_costs.cost.');

            return 0;
        }

        $totalItems = (int) $preview->sum('items');
        $totalBooked = (int) $preview->sum('booked_cents');
        $totalTrue = (int) $preview->sum('true_cents');

        $this->table(
            ['Month', 'Items', 'Cost booked (S$)', 'True cost (S$)', 'Understated by (S$)'],
            $preview->map(fn ($r) => [
                $r->ym,
                number_format((int) $r->items),
                number_format($r->booked_cents / 100, 2),
                number_format($r->true_cents / 100, 2),
                number_format(($r->true_cents - $r->booked_cents) / 100, 2),
            ])->all()
        );

        $this->newLine();
        $this->warn(sprintf(
            '%s items · product cost S$%s → S$%s · Gross Earning falls by S$%s',
            number_format($totalItems),
            number_format($totalBooked / 100, 2),
            number_format($totalTrue / 100, 2),
            number_format(($totalTrue - $totalBooked) / 100, 2)
        ));
        $this->newLine();

        if ($dryRun) {
            $this->comment('Dry run — no rows written. Re-run without --dry-run to apply.');

            return 0;
        }

        if (! $this->confirm('Apply this correction to vend_transaction_items?', false)) {
            $this->comment('Aborted. Nothing was written.');

            return 0;
        }

        // ── Apply ─────────────────────────────────────────────────────────────
        // Batched by primary key so each UPDATE holds a short, bounded set of
        // row locks rather than one lock over the whole table.
        $bounds = $this->affectedQuery($before, $from)
            ->selectRaw('MIN(vti.id) AS min_id, MAX(vti.id) AS max_id')
            ->first();

        $cursor = (int) $bounds->min_id;
        $maxId = (int) $bounds->max_id;
        $updated = 0;

        $bar = $this->output->createProgressBar((int) ceil(($maxId - $cursor + 1) / $chunk));
        $bar->start();

        while ($cursor <= $maxId) {
            $ceiling = $cursor + $chunk - 1;

            $updated += DB::table('vend_transaction_items as vti')
                ->join('unit_costs as uc', 'uc.id', '=', 'vti.unit_cost_id')
                ->whereBetween('vti.id', [$cursor, $ceiling])
                ->where('vti.created_at', '<', $before)
                ->when($from, fn ($q) => $q->where('vti.created_at', '>=', $from))
                ->where(function ($q) {
                    $q->whereColumn('vti.unit_cost', '<>', 'uc.cost')
                        ->orWhereNull('vti.unit_cost');
                })
                ->update([
                    'vti.unit_cost' => DB::raw('uc.cost'),
                    'vti.updated_at' => now(),
                ]);

            $cursor = $ceiling + 1;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Repaired {$updated} item rows.");
        $this->newLine();

        $this->line('<fg=cyan>Now rebuild the aggregates that were derived from the old values:</>');
        $this->comment('  php artisan gp:compute-metrics --from=<earliest> --to='.$before->toDateString());
        $this->comment('  php artisan customer-summary:compute --from=<earliest month> --to='.$before->format('Y-m'));
        $this->warn('Both restate Gross Earning / Vend Earning downward. Locked summary rows keep their frozen figures.');

        return 0;
    }

    /**
     * Items in the window whose stored unit_cost disagrees with the unit_costs
     * row they were priced against. NULL unit_cost is included explicitly —
     * `NULL <> x` is NULL in SQL, so it would not survive the comparison alone.
     */
    private function affectedQuery(Carbon $before, ?Carbon $from)
    {
        return DB::table('vend_transaction_items as vti')
            ->join('unit_costs as uc', 'uc.id', '=', 'vti.unit_cost_id')
            ->where('vti.created_at', '<', $before)
            ->when($from, fn ($q) => $q->where('vti.created_at', '>=', $from))
            ->where(function ($q) {
                $q->whereColumn('vti.unit_cost', '<>', 'uc.cost')
                    ->orWhereNull('vti.unit_cost');
            });
    }
}
