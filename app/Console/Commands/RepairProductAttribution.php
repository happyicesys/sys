<?php

namespace App\Console\Commands;

use App\Models\ProductMapping;
use App\Models\Vend;
use App\Services\Sales\ProductAttributionRepair;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Re-attribute sales a machine booked while the WRONG product mapping was bound.
 *
 * The machine, the window and the planogram it really held are all named by the
 * caller — nothing is inferred. See ProductAttributionRepair for the rule and
 * UNATTRIBUTED_SALES_AUDIT_2026-09-23.md for the incident this came from.
 *
 *   # dry run (default): prints every row it would change
 *   php artisan sales:repair-attribution --vend=2487 --mapping=635 \
 *       --from="2026-09-17 19:27" --to="2026-09-23 10:09"
 *
 *   # write
 *   php artisan sales:repair-attribution --vend=2487 --mapping=635 \
 *       --from="2026-09-17 19:27" --to="2026-09-23 10:09" --apply
 */
class RepairProductAttribution extends Command
{
    protected $signature = 'sales:repair-attribution
        {--vend= : machine code (required)}
        {--mapping= : id of the product mapping the machine REALLY held (required)}
        {--from= : window start, "Y-m-d H:i" (required)}
        {--to= : window end, "Y-m-d H:i" (required)}
        {--apply : write (default is a dry run)}';

    protected $description = 'Re-attribute product/COGS/GST on sales booked against the wrong product mapping (dry-run by default)';

    public function handle(ProductAttributionRepair $repair): int
    {
        foreach (['vend', 'mapping', 'from', 'to'] as $required) {
            if (! $this->option($required)) {
                $this->error("--{$required} is required.");

                return self::FAILURE;
            }
        }

        // bareCode(), not where('code') — a CityBox chiller can hold the same
        // bare number behind a prefix (see App\Support\VendCode).
        $vend = Vend::withoutGlobalScopes()->bareCode($this->option('vend'))->first();
        if (! $vend) {
            $this->error('No machine with code '.$this->option('vend').'.');

            return self::FAILURE;
        }

        $mapping = ProductMapping::withoutGlobalScopes()->find($this->option('mapping'));
        if (! $mapping) {
            $this->error('No product mapping with id '.$this->option('mapping').'.');

            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('from'));
        $to = Carbon::parse($this->option('to'));

        $this->line("Machine {$vend->code} · attributing as <info>{$mapping->name}</info> (#{$mapping->id})");
        $this->line("Window {$from->toDateTimeString()} → {$to->toDateTimeString()}");

        $plan = $repair->plan($vend, $mapping, $from, $to);

        if ($plan->isEmpty()) {
            $this->info('Nothing to repair — every sale in range already matches that planogram.');

            return self::SUCCESS;
        }

        $this->table(
            ['Txn', 'When', 'Ch', 'Amount', 'Product was → now', 'Cost was → now', 'GP was → now'],
            $plan->map(fn ($e) => [
                $e['txn']->id,
                Carbon::parse($e['txn']->transaction_datetime)->format('m-d H:i'),
                $e['channel_code'],
                number_format($e['txn']->amount / 100, 2),
                ($e['txn']->product_id ?: '—').' → '.$e['product_id'],
                number_format($e['txn']->unit_cost / 100, 2).' → '.number_format($e['unit_cost'] / 100, 2),
                number_format($e['txn']->gross_profit / 100, 2).' → '.number_format($e['gross_profit'] / 100, 2),
            ])->all()
        );

        $gpBefore = $plan->sum(fn ($e) => (int) $e['txn']->gross_profit);
        $gpAfter = $plan->sum(fn ($e) => $e['gross_profit']);
        $revBefore = $plan->sum(fn ($e) => (int) $e['txn']->revenue);
        $revAfter = $plan->sum(fn ($e) => $e['revenue']);

        $this->newLine();
        $this->line(sprintf('%d sale(s). Revenue %s → %s. Gross profit %s → %s (%s).',
            $plan->count(),
            number_format($revBefore / 100, 2),
            number_format($revAfter / 100, 2),
            number_format($gpBefore / 100, 2),
            number_format($gpAfter / 100, 2),
            number_format(($gpAfter - $gpBefore) / 100, 2),
        ));

        if (! $this->option('apply')) {
            $this->warn('Dry run — nothing written. Re-run with --apply.');

            return self::SUCCESS;
        }

        $result = $repair->apply($plan);

        $this->info("Repaired {$result['repaired']} sale(s).");
        $this->line('Days marked dirty (rebuilt by reconcile:sales-rollups --dirty at 02:00, or run it now): '
            .implode(', ', $result['days']));

        return self::SUCCESS;
    }
}
