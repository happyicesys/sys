<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Drill into one customer's gp_metrics + vend_transactions to figure out
 * why a sales/gross number looks wrong on the Customer Summary page.
 *
 * Examples:
 *   php artisan debug:customer-gp 25196
 *   php artisan debug:customer-gp 25196 --from=2023-05-01 --to=2026-04-30
 *
 * What it shows:
 *   1. The customer record (vends bound, begin/termination, contract)
 *   2. customer_period_summaries rows in the window
 *   3. gp_metrics totals + per-month breakdown
 *   4. Top 10 single biggest transactions in vend_transactions
 *   5. Any transaction with abnormally high amount (> $1000)
 *   6. Vend_transaction_items (multi-basket) sanity totals
 */
class DebugCustomerGp extends Command
{
    protected $signature = 'debug:customer-gp
        {ref_id : The Customer Ref ID shown on the Summary page (e.g. 25196)}
        {--from= : Start date (YYYY-MM-DD); default = customer.begin_date}
        {--to= : End date (YYYY-MM-DD); default = today}';

    protected $description = 'Drill into one customer\'s sales/gross to debug anomalies';

    public function handle(): int
    {
        $refId = (int) $this->argument('ref_id');
        $internalId = $refId - Customer::RUNNING_NUMBER_INIT;

        $customer = Customer::query()
            ->withoutGlobalScopes()
            ->with(['vends:id,customer_id,code,begin_date,created_at', 'operator:id,code,name'])
            ->find($internalId);

        if (!$customer) {
            $this->error("Customer ref_id={$refId} (internal id={$internalId}) not found.");
            return self::FAILURE;
        }

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : ($customer->begin_date ? Carbon::parse($customer->begin_date)->startOfDay() : Carbon::today()->subYear());
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        $this->section('Customer');
        $this->table(['Field', 'Value'], [
            ['Internal id', $customer->id],
            ['Ref id (UI)', $refId],
            ['Name', $customer->name],
            ['Operator', $customer->operator?->code . ' / ' . $customer->operator?->name],
            ['Begin date', $customer->begin_date?->toDateString()],
            ['Termination date', $customer->termination_date?->toDateString()],
            ['Contract type', $customer->contract_commission_type ?? '(none)'],
            ['Vends bound (lifetime)', $customer->vends->count()],
            ['Vend codes', $customer->vends->pluck('code')->implode(', ')],
            ['Window', $from->toDateString() . ' → ' . $to->toDateString()],
        ]);

        $this->section('customer_period_summaries rows in window');
        $cps = CustomerPeriodSummary::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('year_month', [$from->toDateString(), $to->toDateString()])
            ->orderBy('year_month')
            ->get(['year_month', 'period_start', 'period_end', 'sales_cents', 'gross_earning_cents', 'transaction_count', 'vend_count']);
        $cpsRows = $cps->map(fn ($r) => [
            $r->year_month?->format('Y-m'),
            number_format($r->sales_cents / 100, 2),
            number_format($r->gross_earning_cents / 100, 2),
            $r->transaction_count,
            $r->vend_count,
        ])->all();
        $this->table(['year_month', 'sales $', 'gross $', 'txn count', 'vend count'], $cpsRows);
        $this->line(sprintf(
            ' totals: sales = %s | gross = %s | txns = %s',
            number_format($cps->sum('sales_cents') / 100, 2),
            number_format($cps->sum('gross_earning_cents') / 100, 2),
            number_format($cps->sum('transaction_count'))
        ));

        $this->section('gp_metrics totals (raw, before SUM into customer_period_summaries)');
        $gpTotals = DB::table('gp_metrics')
            ->where('customer_id', $customer->id)
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('SUM(revenue_cents) AS sales_cents')
            ->selectRaw('SUM(gross_profit_cents) AS gross_earning_cents')
            ->selectRaw('SUM(transaction_count) AS txn_count')
            ->selectRaw('SUM(sale_count) AS sale_count')
            ->selectRaw('COUNT(*) AS gp_metrics_rows')
            ->selectRaw('COUNT(DISTINCT vend_id) AS distinct_vends')
            ->selectRaw('COUNT(DISTINCT product_id) AS distinct_products')
            ->first();
        $this->table(['Metric', 'Value'], [
            ['Sales (sum of revenue_cents)', $gpTotals ? number_format(($gpTotals->sales_cents ?? 0) / 100, 2) : 0],
            ['Gross (sum of gross_profit_cents)', $gpTotals ? number_format(($gpTotals->gross_earning_cents ?? 0) / 100, 2) : 0],
            ['Transactions', $gpTotals?->txn_count ?? 0],
            ['Sales count', $gpTotals?->sale_count ?? 0],
            ['gp_metrics rows', $gpTotals?->gp_metrics_rows ?? 0],
            ['Distinct vends', $gpTotals?->distinct_vends ?? 0],
            ['Distinct products', $gpTotals?->distinct_products ?? 0],
        ]);

        $this->section('gp_metrics per-month breakdown');
        $perMonth = DB::table('gp_metrics')
            ->where('customer_id', $customer->id)
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(txn_date, '%Y-%m') AS ym")
            ->selectRaw('SUM(revenue_cents) AS sales_cents')
            ->selectRaw('SUM(gross_profit_cents) AS gross_cents')
            ->selectRaw('SUM(transaction_count) AS txns')
            ->selectRaw('COUNT(*) AS rows')
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();
        $rows = $perMonth->map(fn ($r) => [
            $r->ym,
            number_format($r->sales_cents / 100, 2),
            number_format($r->gross_cents / 100, 2),
            $r->txns,
            $r->rows,
        ])->all();
        $this->table(['Month', 'Sales $', 'Gross $', 'Txns', 'gp_metrics rows'], $rows);

        $this->section('Top 20 single vend_transactions by amount');
        $topTxns = DB::table('vend_transactions')
            ->where('customer_id', $customer->id)
            ->whereBetween('transaction_datetime', [$from, $to])
            ->orderByDesc('amount')
            ->limit(20)
            ->get(['id', 'vend_id', 'transaction_datetime', 'amount', 'revenue', 'unit_cost', 'qty', 'is_multiple']);
        $this->table(
            ['id', 'vend_id', 'when', 'amount $', 'revenue $', 'unit_cost $', 'qty', 'multi?'],
            $topTxns->map(fn ($t) => [
                $t->id,
                $t->vend_id,
                $t->transaction_datetime,
                number_format(($t->amount ?? 0) / 100, 2),
                number_format(($t->revenue ?? 0) / 100, 2),
                number_format(($t->unit_cost ?? 0) / 100, 2),
                $t->qty,
                $t->is_multiple ? 'yes' : 'no',
            ])->all()
        );

        $this->section('Suspicious vend_transactions (amount > $1000 — i.e. > 100,000 cents)');
        $suspicious = DB::table('vend_transactions')
            ->where('customer_id', $customer->id)
            ->where('amount', '>', 100_000)
            ->whereBetween('transaction_datetime', [$from, $to])
            ->orderByDesc('amount')
            ->limit(50)
            ->get(['id', 'vend_id', 'transaction_datetime', 'amount']);
        if ($suspicious->isEmpty()) {
            $this->line(' (none — every transaction is under $1000)');
        } else {
            $this->table(
                ['id', 'vend_id', 'when', 'amount $'],
                $suspicious->map(fn ($t) => [
                    $t->id,
                    $t->vend_id,
                    $t->transaction_datetime,
                    number_format(($t->amount ?? 0) / 100, 2),
                ])->all()
            );
            $this->warn(sprintf(' Found %d txns with amount > $1000 — these are likely the cause.', $suspicious->count()));
        }

        $this->section('vend_transaction_items totals (multi-basket)');
        $items = DB::table('vend_transaction_items as vti')
            ->join('vend_transactions as vt', 'vt.id', '=', 'vti.vend_transaction_id')
            ->where('vt.customer_id', $customer->id)
            ->whereBetween('vt.transaction_datetime', [$from, $to])
            ->selectRaw('COUNT(*) AS item_count')
            ->selectRaw('SUM(COALESCE(vti.unit_price_amount, 0)) AS items_amount_cents')
            ->selectRaw('SUM(COALESCE(vti.unit_cost, 0)) AS items_cost_cents')
            ->first();
        $this->table(['Metric', 'Value'], [
            ['Item count', $items?->item_count ?? 0],
            ['SUM(unit_price_amount) $', $items ? number_format(($items->items_amount_cents ?? 0) / 100, 2) : 0],
            ['SUM(unit_cost) $', $items ? number_format(($items->items_cost_cents ?? 0) / 100, 2) : 0],
        ]);

        $this->newLine();
        $this->info('Done. Compare the gp_metrics totals to vend_transactions totals — if they\'re miles apart, there\'s a duplication or join issue.');

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('━━━ ' . $title . ' ━━━');
    }
}
