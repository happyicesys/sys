<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * READ-ONLY what-if: recompute the Customer Summary header totals as they WOULD
 * read if every customer's PS Term were set to a flat value (default 50%),
 * except customers under a given operator code (default UL-ST → 100%).
 *
 * Nothing is written. It re-derives each current-month summary row's location
 * fee with the overridden PS Term using the SAME formula the page uses
 * (CustomerSummaryAggregator::computeLocationFeeCents), then sums.
 *
 * Only PS-family contracts (PS, PS+U, PSORU) are affected by PS Term — all
 * other contract types pass through unchanged, so:
 *   - Total Sales            : unchanged (independent of PS Term)
 *   - Total Gross Earning    : unchanged (independent of PS Term)
 *   - Total Location Fees    : CHANGES (PS-family fee re-scaled)
 *   - Total Vend Earnings    : CHANGES (= Gross − (LocFee − ExtSubsidize))
 *   - # without Contract Att. : unchanged
 *   - # To Be Expired in 30d  : unchanged
 *
 * Scope mirrors the Summary page defaults: current month, Active customers,
 * all operators. Pass --status / --operators / --month to match a specific
 * on-screen view. (--operators accepts operator IDs or codes.)
 *
 *   php artisan summary:simulate-ps-term
 *   php artisan summary:simulate-ps-term --status=all
 *   php artisan summary:simulate-ps-term --operators=HIPL,HIMD --month=2026-06
 *   php artisan summary:simulate-ps-term --ps=50 --ul-st-ps=100
 */
class SimulatePsTermTotals extends Command
{
    protected $signature = 'summary:simulate-ps-term
        {--month= : Month to evaluate (YYYY-MM or YYYY-MM-DD). Default: current month.}
        {--ps=50 : Flat PS Term % to apply to everyone outside the excluded operator.}
        {--ul-st-ps=100 : PS Term % to apply to the excluded operator.}
        {--ul-st-code=UL-ST : Operator code that gets --ul-st-ps instead of --ps.}
        {--status=2 : Customer status_id to include (2=Active). Use "all" for every status.}
        {--operators=HIPL,HIMD,LEA,HIESG,UL-ST : Restrict to these operators (comma-separated IDs or codes). Default mirrors the Summary page''s default operator chips. Pass "all" for every operator.}';

    protected $description = 'What-if: recompute Summary header totals with PS Term flattened (read-only, no writes)';

    public function handle(): int
    {
        $flatPs = (float) $this->option('ps');
        $ulStPs = (float) $this->option('ul-st-ps');
        $ulStCode = (string) $this->option('ul-st-code');

        $monthStart = $this->option('month')
            ? Carbon::parse($this->option('month'))->startOfMonth()
            : Carbon::today()->startOfMonth();

        $this->info(sprintf(
            'What-if for %s — PS Term → %s%% (operator "%s" → %s%%). Read-only.',
            $monthStart->format('Y-m'),
            rtrim(rtrim(number_format($flatPs, 2), '0'), '.'),
            $ulStCode,
            rtrim(rtrim(number_format($ulStPs, 2), '0'), '.')
        ));

        // ---- Build the customer scope (mirrors Summary page defaults) -------
        $statusOpt = $this->option('status');
        $operatorsOpt = $this->option('operators');

        $customerScope = Customer::query()->withoutGlobalScopes();

        if ($statusOpt !== null && strtolower((string) $statusOpt) !== 'all') {
            $customerScope->where('status_id', (int) $statusOpt);
        }

        $operatorLabel = 'all operators';
        if (!empty($operatorsOpt) && strtolower((string) $operatorsOpt) !== 'all') {
            $tokens = collect(explode(',', $operatorsOpt))->map(fn ($t) => trim($t))->filter()->values();
            $ids = $tokens->filter(fn ($t) => is_numeric($t))->map(fn ($t) => (int) $t);
            $codes = $tokens->reject(fn ($t) => is_numeric($t));
            $resolvedIds = DB::table('operators')
                ->when($codes->isNotEmpty(), fn ($q) => $q->orWhereIn('code', $codes->all()))
                ->when($ids->isNotEmpty(), fn ($q) => $q->orWhereIn('id', $ids->all()))
                ->pluck('id')
                ->all();
            $customerScope->whereIn('operator_id', $resolvedIds);
            $operatorLabel = 'operators [' . implode(',', $resolvedIds) . ']';
        }

        $customerIds = $customerScope->pluck('id');

        $this->line(sprintf('Scope: %s, status=%s, %d customer(s) in scope.',
            $operatorLabel,
            $statusOpt === null ? '2' : $statusOpt,
            $customerIds->count()
        ));

        // Operator GST rate + code lookups (id => value).
        $operators = DB::table('operators')->get(['id', 'code', 'gst_vat_rate']);
        $gstById = $operators->pluck('gst_vat_rate', 'id');
        $codeById = $operators->pluck('code', 'id');

        // ---- Walk the current-month summary rows --------------------------
        // One row per (customer, segment); summing rows = the header behaviour.
        $actual = ['sales' => 0, 'gross' => 0, 'fees' => 0, 'earn' => 0];
        $sim = ['fees' => 0, 'earn' => 0];
        $rowCount = 0;

        CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', $monthStart->toDateString())
            ->select([
                'id', 'customer_id', 'operator_id',
                'sales_cents', 'gross_earning_cents', 'location_fees_cents',
                'location_earning_cents', 'external_subsidize_cents',
                'contract_commission_type', 'contract_commission_value',
                'contract_commission_value2', 'contract_ps_term',
            ])
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$actual, &$sim, &$rowCount, $gstById, $codeById, $flatPs, $ulStPs, $ulStCode) {
                foreach ($rows as $r) {
                    $rowCount++;
                    $salesCents = (int) $r->sales_cents;
                    $grossCents = (int) $r->gross_earning_cents;
                    $extSubCents = (int) $r->external_subsidize_cents;
                    $gstRatePct = (float) ($gstById[$r->operator_id] ?? 0);

                    // Actual (stored) totals — sum the snapshot exactly as the
                    // header does.
                    $actual['sales'] += $salesCents;
                    $actual['gross'] += $grossCents;
                    $actual['fees']  += (int) $r->location_fees_cents;
                    $actual['earn']  += (int) $r->location_earning_cents;

                    // Simulated PS Term for this row's operator.
                    $simPs = ($codeById[$r->operator_id] ?? null) === $ulStCode ? $ulStPs : $flatPs;

                    // Re-derive the location fee with the overridden PS Term,
                    // keeping every other contract field from the snapshot.
                    // Non-PS contract types ignore PS Term, so they pass through.
                    $simFee = CustomerSummaryAggregator::computeLocationFeeCents(
                        $r->contract_commission_type,
                        $r->contract_commission_value !== null ? (float) $r->contract_commission_value : null,
                        $r->contract_commission_value2 !== null ? (float) $r->contract_commission_value2 : null,
                        $simPs,
                        $salesCents,
                        $grossCents,
                        $gstRatePct
                    );

                    // Vend Earning = Gross − (LocFee − ExtSubsidize) — same as
                    // the aggregator's net-of-subsidize definition.
                    $simEarn = $grossCents - ($simFee - $extSubCents);

                    $sim['fees'] += $simFee;
                    $sim['earn'] += $simEarn;
                }
            });

        // ---- Counts (invariant to PS Term) — computed for completeness -----
        $displayedCustomerIds = CustomerPeriodSummary::query()
            ->whereIn('customer_id', $customerIds)
            ->where('year_month', $monthStart->toDateString())
            ->distinct()
            ->pluck('customer_id');

        $attachmentThreshold = $monthStart->copy()->startOfMonth()->toDateString();
        $noContractAttachmentCount = Customer::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $displayedCustomerIds)
            ->whereDoesntHave('contracts', function ($q) use ($attachmentThreshold) {
                $q->where('attachments.created_at', '>=', $attachmentThreshold);
            })
            ->count();

        $today = Carbon::today();
        $expiringIn30dCount = Customer::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $displayedCustomerIds)
            ->whereNotNull('contract_until')
            ->whereBetween('contract_until', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->where(fn ($q) => $q->where('contract_auto_renewal', false)->orWhereNull('contract_auto_renewal'))
            ->count();

        // ---- Report --------------------------------------------------------
        $money = fn (int $cents) => 'S$' . number_format($cents / 100, 2);
        $delta = fn (int $a, int $b) => ($b - $a >= 0 ? '+' : '-') . 'S$' . number_format(abs($b - $a) / 100, 2);

        $this->line(sprintf('Evaluated %d summary row(s) for %s (expect this to match the on-screen result count).', $rowCount, $monthStart->format('Y-m')));

        $this->newLine();
        $this->table(
            ['Metric', 'Current (actual)', 'Simulated', 'Δ'],
            [
                ['Total Sales',              $money($actual['sales']), $money($actual['sales']), '— (unchanged)'],
                ['Total Gross Earning',      $money($actual['gross']), $money($actual['gross']), '— (unchanged)'],
                ['Total Location Fees',      $money($actual['fees']),  $money($sim['fees']),  $delta($actual['fees'], $sim['fees'])],
                ['Total Vend Earnings',      $money($actual['earn']),  $money($sim['earn']),  $delta($actual['earn'], $sim['earn'])],
                ['# without Contract Att.',  (string) $noContractAttachmentCount, (string) $noContractAttachmentCount, '— (unchanged)'],
                ['# To Be Expired in 30d',   (string) $expiringIn30dCount,        (string) $expiringIn30dCount,        '— (unchanged)'],
            ]
        );

        $this->newLine();
        $this->line('Note: "Current (actual)" sums the stored snapshot — it should match the page header for the same filters/operator scope. Only Location Fees & Vend Earnings move under the PS Term change.');

        return self::SUCCESS;
    }
}
