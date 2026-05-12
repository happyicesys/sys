<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds the "Performance Report" / Vending Machine Location Fees Report
 * payload that drives the Report Content preview modal AND (eventually) the
 * Email body sent by CustomerController::sendPerformanceReport().
 *
 * Why a service (not just a Blade view): the Report Content modal needs to
 * render the EXACT same content that the email will carry, so both surfaces
 * call this single method. The shape is JSON-friendly so the Vue modal can
 * display it without templating.
 *
 * Calculation reference (from the user's screenshot — pro-rated by active
 * days, intentionally independent of the stored location_fees_cents which
 * is computed flat per-month by CustomerSummaryAggregator):
 *
 *   F (Free Placement)        : no content
 *   S (Subsidized Plan)       : no content
 *   R (Fix Rental)            : Rental rate × (active_days / month_days)
 *   U (Utility Only)          : Utility rate × (active_days / month_days)
 *   PS (Profit Sharing)       : Total Revenue × PS rate%
 *                               where Total Revenue = Sales × PS Term%
 *                               (Sales = CustomerPeriodSummary.sales_cents,
 *                               sourced cent-exact from vend_transactions
 *                               by CustomerSummaryAggregator; INCL-GST,
 *                               matches the Transactions page totals.
 *                               "Total Revenue" here is the negotiated
 *                               PS BASE, not an accounting figure.)
 *   PS+U                      : PS amount + Utility amount
 *   PSORU (whichever higher)  : max(PS amount, Utility amount)
 *
 * All money values are returned as float dollars (rounded to 2dp) ready for
 * direct rendering — the cents-vs-dollars conversion happens here so the
 * frontend doesn't need to know about CustomerSummaryAggregator's storage
 * convention.
 */
class PerformanceReportContentService
{
    public const CONTRACT_LABELS = [
        'F'     => 'FREE PLACEMENT',
        'S'     => 'SUBSIDIZED PLAN',
        'R'     => 'FIX RENTAL',
        'U'     => 'UTILITY',
        'PS'    => 'PROFIT SHARING',
        'PS+U'  => 'PROFIT SHARING + UTILITY',
        'PSORU' => 'PROFIT SHARING OR UTILITY (WHICHEVER HIGHER)',
    ];

    /**
     * True when the customer's contract has enough information to render a
     * non-empty report. F and S deliberately return false (per the spec
     * screenshot — those rows have a blank Report Content cell), so the
     * Report Content button on Customer Summary stays disabled for them.
     */
    public function isAvailable(Customer $customer): bool
    {
        $type = $customer->contract_commission_type;
        if (!$type || in_array($type, ['F', 'S'], true)) {
            return false;
        }

        $value  = $customer->contract_commission_value;
        $value2 = $customer->contract_commission_value2;
        $psTerm = $customer->contract_ps_term;

        switch ($type) {
            case 'R':
            case 'U':
                return $value !== null;
            case 'PS':
                return $value !== null && $psTerm !== null;
            case 'PS+U':
            case 'PSORU':
                return $value !== null && $value2 !== null && $psTerm !== null;
        }
        return false;
    }

    /**
     * Build the full payload for the modal / email body.
     *
     * $summary is optional — only required for PS-family calculations that
     * depend on Sales for the period. When omitted (or for non-PS types) we
     * fall back to 0 sales, which still renders a useful preview ("$0.00").
     *
     * Return shape (frontend contract — keep stable):
     * [
     *   'is_available'         => bool,
     *   'contract_type'        => 'R',
     *   'contract_type_label'  => 'FIX RENTAL',
     *   'period_label'         => '2605'  | '2604–2605' (multi-month),
     *   'period_start'         => '2026-05-01',
     *   'period_end'           => '2026-05-25',
     *   'active_days'          => 25,
     *   'month_days'           => 30,
     *   'lines'                => [{ label, formula, value }, ...],
     *   'has_total'            => bool,
     *   'total_value'          => '$1,234.56',
     *   'footnote'             => '(Amount above is before GST)',
     * ]
     */
    public function generate(
        Customer $customer,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CustomerPeriodSummary $summary = null
    ): array {
        $type = $customer->contract_commission_type;
        $base = [
            'is_available'        => $this->isAvailable($customer),
            'contract_type'       => $type,
            'contract_type_label' => self::CONTRACT_LABELS[$type] ?? $type,
            'period_label'        => $this->periodLabel($periodStart, $periodEnd),
            'period_start'        => Carbon::parse($periodStart)->toDateString(),
            'period_end'          => Carbon::parse($periodEnd)->toDateString(),
            'active_days'         => null,
            'month_days'          => null,
            'lines'               => [],
            'has_total'           => false,
            'total_value'         => null,
            'footnote'            => '(Amount above is before GST)',
        ];

        if (!$base['is_available']) {
            return $base;
        }

        // Day counts. active_days is the inclusive span of the visible
        // period; month_days is the calendar-month span the period sits in
        // (so a single month with end-of-month = 30/30, mid-month = 25/30,
        // and a multi-month aggregated period = e.g. 365/365 for a year).
        //
        // IMPORTANT: Carbon's diffInDays() returns FLOAT in recent versions,
        // which leaks precision noise like 31.999999999988 into the report.
        // Cast through int() to keep the display (and the day-ratio math)
        // in clean whole-day units.
        $start = Carbon::parse($periodStart)->startOfDay();
        $end   = Carbon::parse($periodEnd)->startOfDay();
        $monthSpanStart = $start->copy()->startOfMonth();
        $monthSpanEnd   = $end->copy()->endOfMonth();
        $base['active_days'] = (int) round($start->diffInDays($end)) + 1;
        $base['month_days']  = (int) round($monthSpanStart->diffInDays($monthSpanEnd)) + 1;

        $value  = (float) ($customer->contract_commission_value ?? 0);
        $value2 = (float) ($customer->contract_commission_value2 ?? 0);
        $psTerm = (float) ($customer->contract_ps_term ?? 0);

        // Sales (excl GST) in dollars for PS-family math. Stored as cents
        // on customer_period_summaries.sales_cents.
        $salesDollars = $summary && $summary->sales_cents
            ? ((int) $summary->sales_cents) / 100.0
            : 0.0;

        $dayRatio = $base['month_days'] > 0
            ? $base['active_days'] / $base['month_days']
            : 0.0;

        switch ($type) {
            case 'R': {
                $amount = round($value * $dayRatio, 2);
                $base['lines'][] = [
                    'label'   => 'Rental',
                    'formula' => $this->money($value) . ' × ' . $base['active_days'] . '/' . $base['month_days'],
                    'value'   => $this->money($amount),
                ];
                break;
            }
            case 'U': {
                $amount = round($value * $dayRatio, 2);
                $base['lines'][] = [
                    'label'   => 'Utility',
                    'formula' => $this->money($value) . ' × ' . $base['active_days'] . '/' . $base['month_days'],
                    'value'   => $this->money($amount),
                ];
                break;
            }
            case 'PS': {
                $totalRevenue   = round($salesDollars * ($psTerm / 100.0), 2);
                $profitSharing  = round($totalRevenue * ($value / 100.0), 2);
                $base['lines'][] = [
                    'label'   => 'Total Revenue',
                    'formula' => $this->money($salesDollars) . ' × ' . $this->pct($psTerm),
                    'value'   => $this->money($totalRevenue),
                ];
                $base['lines'][] = [
                    'label'   => 'Profit Sharing',
                    'formula' => $this->money($totalRevenue) . ' × ' . $this->pct($value),
                    'value'   => $this->money($profitSharing),
                ];
                break;
            }
            case 'PS+U': {
                $totalRevenue   = round($salesDollars * ($psTerm / 100.0), 2);
                $profitSharing  = round($totalRevenue * ($value / 100.0), 2);
                $utility        = round($value2 * $dayRatio, 2);
                $total          = round($profitSharing + $utility, 2);
                $base['lines'][] = [
                    'label'   => 'Total Revenue',
                    'formula' => $this->money($salesDollars) . ' × ' . $this->pct($psTerm),
                    'value'   => $this->money($totalRevenue),
                ];
                $base['lines'][] = [
                    'label'   => 'Profit Sharing',
                    'formula' => $this->money($totalRevenue) . ' × ' . $this->pct($value),
                    'value'   => $this->money($profitSharing),
                ];
                $base['lines'][] = [
                    'label'   => 'Utility',
                    'formula' => $this->money($value2) . ' × ' . $base['active_days'] . '/' . $base['month_days'],
                    'value'   => $this->money($utility),
                ];
                $base['has_total']   = true;
                $base['total_value'] = $this->money($total);
                break;
            }
            case 'PSORU': {
                $totalRevenue   = round($salesDollars * ($psTerm / 100.0), 2);
                $profitSharing  = round($totalRevenue * ($value / 100.0), 2);
                $utility        = round($value2 * $dayRatio, 2);
                $total          = max($profitSharing, $utility);
                $base['lines'][] = [
                    'label'   => 'Total Revenue',
                    'formula' => $this->money($salesDollars) . ' × ' . $this->pct($psTerm),
                    'value'   => $this->money($totalRevenue),
                ];
                $base['lines'][] = [
                    'label'   => 'Profit Sharing',
                    'formula' => $this->money($totalRevenue) . ' × ' . $this->pct($value),
                    'value'   => $this->money($profitSharing),
                ];
                $base['lines'][] = [
                    'label'   => 'Utility',
                    'formula' => $this->money($value2) . ' × ' . $base['active_days'] . '/' . $base['month_days'],
                    'value'   => $this->money($utility),
                ];
                $base['has_total']   = true;
                $base['total_value'] = $this->money($total) . ' (whichever higher)';
                break;
            }
        }

        return $base;
    }

    /**
     * "2605" for a single-month period; "2605–2607" for multi-month spans.
     * Matches the screenshot's "Period (YYMM): 2605" style.
     */
    protected function periodLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        $startLabel = Carbon::parse($start)->format('ym');
        $endLabel   = Carbon::parse($end)->format('ym');
        return $startLabel === $endLabel
            ? $startLabel
            : ($startLabel . '–' . $endLabel);
    }

    protected function money(float $dollars): string
    {
        $sign = $dollars < 0 ? '-' : '';
        return $sign . '$' . number_format(abs($dollars), 2);
    }

    protected function pct(float $value): string
    {
        // Drop trailing zeros for the formula display ("70%" not "70.00%")
        // but keep precision when the value isn't a whole number ("12.5%").
        $clean = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        return $clean . '%';
    }
}
