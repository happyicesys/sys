<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds the CMS API Invoice payload for a customer + period range.
 *
 * Triggered by the "Create API Invoice" buttons on Customer Management >
 * Summary. The CMS endpoint (/api/transactions/deals) is the same one
 * OpsJob uses, so the outer payload shape mirrors SyncOpsJobTransactionCMS:
 *
 *   [
 *     'date' => 'YYYY-MM-DD',          // period_end
 *     'driver' => '<user>',            // who triggered the invoice
 *     'created_by' => '<user>',
 *     'status' => 'Delivered',
 *     'customers' => [
 *        <person_id> => [
 *           'attachments' => [],
 *           'cash_collected' => 0,
 *           'channels' => [
 *              '<line_key>' => [
 *                  'amount'       => 32.51,         // total $ for this line
 *                  'unit_price'   => 0.10,          // $ per unit (PS rate as decimal)
 *                  'product_code' => '055',         // hardcoded item code
 *                  'capacity'     => 0,
 *                  'qty'          => 0,
 *                  'needed'       => 325.14,        // qty/unit count
 *              ],
 *              ...
 *           ],
 *           'ops_job_item_id' => null,
 *           'sequence'        => 1,
 *           // Customer-summary specific fields the CMS uses to recognise
 *           // this as an "API report" invoice rather than a delivery deal:
 *           'invoice_kind'    => 'api_report',
 *           'period_start'    => 'YYYY-MM-DD',
 *           'period_end'      => 'YYYY-MM-DD',
 *           'reference_id'    => <customer_period_summary_invoice_id>,
 *        ],
 *     ],
 *   ]
 *
 * Item-code mapping (matches the screenshots the user shared, derived from
 * Customer.contract_commission_type):
 *
 *   PS              -> 055    (Vending Machine Sales Commission)
 *   U               -> V01    (Utilities Subsidy)
 *   R (Fix Rental)  -> 60     (Rental payment to landlord)
 *   PS+U            -> 055 + V01
 *   PSORU           -> 055 only when PS amount is higher,
 *                      V01 only when Utility amount is higher
 *   F / S           -> NO invoice (caller should not invoke this service)
 *
 * Calculations re-use PerformanceReportContentService so the dollars
 * shown in the modal/email match what CMS receives.
 */
class CustomerInvoiceService
{
    /**
     * Hardcoded CMS item codes per contract type. Kept on the class
     * (rather than env/config) so devs can grep for the strings — these
     * codes were defined out-of-band by the CMS team and don't change
     * per-environment.
     */
    public const ITEM_CODE_PS  = '055';   // Vending Machine Sales Commission
    public const ITEM_CODE_U   = 'V01';   // Utilities Subsidy
    public const ITEM_CODE_R   = '60';    // Rental payment to landlord

    /** Description shown on each invoice line (matches the screenshots). */
    public const LINE_DESC = [
        self::ITEM_CODE_PS => 'Vending Machine Sales Commission',
        self::ITEM_CODE_U  => 'Utilities Subsidy',
        self::ITEM_CODE_R  => 'Rental payment to landlord',
    ];

    /**
     * Contract types we will NOT invoice via this flow. Free Placement
     * has nothing to bill; Subsidized Plan is paid TO the customer (income),
     * which is intentionally out of scope for the API Report invoice flow.
     */
    public const NON_INVOICEABLE_TYPES = ['F', 'S'];

    public function __construct(
        protected ?PerformanceReportContentService $reportService = null
    ) {
        $this->reportService = $reportService ?: new PerformanceReportContentService();
    }

    /**
     * Whether we should attempt to create an API invoice for this customer.
     * Mirrors PerformanceReportContentService::isAvailable() but ALSO
     * requires a CMS-linked person_id (the CMS deals endpoint keys on
     * person_id).
     */
    public function isInvoiceable(Customer $customer): bool
    {
        if (!$customer->person_id) {
            return false;
        }
        $type = $customer->contract_commission_type;
        if (!$type || in_array($type, self::NON_INVOICEABLE_TYPES, true)) {
            return false;
        }
        // Re-use the report-content availability check to make sure all
        // required contract values are present (e.g. PS needs ps_term).
        return $this->reportService->isAvailable($customer);
    }

    /**
     * Build the CMS payload for a single customer / period.
     *
     * Returns an array shaped for direct posting to /api/transactions/deals.
     * Returns null when the contract type is non-invoiceable so the caller
     * can short-circuit cleanly.
     *
     * @param array{ops_job_item_id?:int|null, sequence?:int, reference_id?:int|null, driver?:string, created_by?:string} $context
     */
    public function buildPayload(
        Customer $customer,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CustomerPeriodSummary $summary = null,
        array $context = []
    ): ?array {
        if (!$this->isInvoiceable($customer)) {
            return null;
        }

        $report = $this->reportService->generate($customer, $periodStart, $periodEnd, $summary);
        $lines = $this->buildLines($customer, $periodStart, $periodEnd, $summary, $report);

        if (empty($lines)) {
            return null;
        }

        $personId = $customer->person_id;
        $driver = $context['driver'] ?? (auth()->user()->username ?? '');
        $createdBy = $context['created_by'] ?? (auth()->user()->username ?? '');

        return [
            'date' => Carbon::parse($periodEnd)->format('Y-m-d'),
            'driver' => $driver,
            'created_by' => $createdBy,
            'status' => 'Delivered',
            'customers' => [
                $personId => [
                    'attachments' => [],
                    'cash_collected' => 0,
                    'channels' => $lines,
                    'ops_job_item_id' => $context['ops_job_item_id'] ?? null,
                    'sequence' => $context['sequence'] ?? 1,
                    // Marker fields letting the CMS distinguish API Report
                    // invoices from OpsJob delivery deals — same endpoint,
                    // different semantic. CMS may ignore these for now.
                    'invoice_kind' => 'api_report',
                    'period_start' => Carbon::parse($periodStart)->toDateString(),
                    'period_end' => Carbon::parse($periodEnd)->toDateString(),
                    'reference_id' => $context['reference_id'] ?? null,
                ],
            ],
        ];
    }

    /**
     * Calculate the line items for a customer's invoice given the contract
     * type. Each line has 'amount' (total $), 'unit_price', 'product_code',
     * and 'needed' (qty/unit count) — matching SyncOpsJobTransactionCMS's
     * channels[] shape.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function buildLines(
        Customer $customer,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        ?CustomerPeriodSummary $summary,
        array $report
    ): array {
        $type = $customer->contract_commission_type;

        $value  = (float) ($customer->contract_commission_value ?? 0);
        $value2 = (float) ($customer->contract_commission_value2 ?? 0);
        $psTerm = (float) ($customer->contract_ps_term ?? 0);

        $activeDays = (int) ($report['active_days'] ?? 0);
        $monthDays  = (int) ($report['month_days'] ?? 0);
        $dayRatio   = $monthDays > 0 ? $activeDays / $monthDays : 0.0;

        // Sales (excl GST) for PS-family math. Stored as cents on
        // customer_period_summaries.sales_cents.
        $salesDollars = $summary && $summary->sales_cents
            ? ((int) $summary->sales_cents) / 100.0
            : 0.0;

        $lines = [];

        switch ($type) {
            case 'R': {
                // Rental: 1 Unit × <rate × day_ratio>
                $amount = round($value * $dayRatio, 2);
                $lines[$this->lineKey(self::ITEM_CODE_R)] = $this->makeLine(
                    code: self::ITEM_CODE_R,
                    qty: 1,
                    unitPrice: $amount,
                    amount: $amount,
                );
                break;
            }
            case 'U': {
                $amount = round($value * $dayRatio, 2);
                $lines[$this->lineKey(self::ITEM_CODE_U)] = $this->makeLine(
                    code: self::ITEM_CODE_U,
                    qty: 1,
                    unitPrice: $amount,
                    amount: $amount,
                );
                break;
            }
            case 'PS': {
                // PS: <total_revenue> Unit × <ps_rate as decimal>
                // total_revenue = sales × ps_term%
                // unit_price    = ps_rate / 100 (e.g. 10% -> 0.10)
                // amount        = total_revenue × unit_price
                $totalRevenue = round($salesDollars * ($psTerm / 100.0), 2);
                $unitPrice = round($value / 100.0, 4);
                $amount = round($totalRevenue * $unitPrice, 2);
                $lines[$this->lineKey(self::ITEM_CODE_PS)] = $this->makeLine(
                    code: self::ITEM_CODE_PS,
                    qty: $totalRevenue,
                    unitPrice: $unitPrice,
                    amount: $amount,
                );
                break;
            }
            case 'PS+U': {
                // PS+U: both lines added together (same as PS line + U line).
                $totalRevenue = round($salesDollars * ($psTerm / 100.0), 2);
                $psUnitPrice = round($value / 100.0, 4);
                $psAmount = round($totalRevenue * $psUnitPrice, 2);
                $uAmount = round($value2 * $dayRatio, 2);
                $lines[$this->lineKey(self::ITEM_CODE_PS)] = $this->makeLine(
                    code: self::ITEM_CODE_PS,
                    qty: $totalRevenue,
                    unitPrice: $psUnitPrice,
                    amount: $psAmount,
                );
                $lines[$this->lineKey(self::ITEM_CODE_U)] = $this->makeLine(
                    code: self::ITEM_CODE_U,
                    qty: 1,
                    unitPrice: $uAmount,
                    amount: $uAmount,
                );
                break;
            }
            case 'PSORU': {
                // Whichever-higher: emit ONLY the winning line. The CMS
                // sees a single product code so the customer is billed
                // for one or the other, never both.
                $totalRevenue = round($salesDollars * ($psTerm / 100.0), 2);
                $psUnitPrice = round($value / 100.0, 4);
                $psAmount = round($totalRevenue * $psUnitPrice, 2);
                $uAmount = round($value2 * $dayRatio, 2);
                if ($psAmount >= $uAmount) {
                    $lines[$this->lineKey(self::ITEM_CODE_PS)] = $this->makeLine(
                        code: self::ITEM_CODE_PS,
                        qty: $totalRevenue,
                        unitPrice: $psUnitPrice,
                        amount: $psAmount,
                    );
                } else {
                    $lines[$this->lineKey(self::ITEM_CODE_U)] = $this->makeLine(
                        code: self::ITEM_CODE_U,
                        qty: 1,
                        unitPrice: $uAmount,
                        amount: $uAmount,
                    );
                }
                break;
            }
        }

        // Drop zero-amount lines — invoicing $0 is noise and CMS may
        // reject them. Keeps the payload clean for short periods or
        // months with no sales.
        return array_filter($lines, fn ($line) => ($line['amount'] ?? 0) > 0);
    }

    /**
     * Total amount in cents for the rows we're about to send to CMS.
     * Used to populate customer_period_summary_invoices.total_amount_cents
     * for fast UI badge rendering.
     */
    public function totalCentsFromPayload(array $payload): int
    {
        $total = 0.0;
        foreach (($payload['customers'] ?? []) as $cust) {
            foreach (($cust['channels'] ?? []) as $line) {
                $total += (float) ($line['amount'] ?? 0);
            }
        }
        return (int) round($total * 100);
    }

    /**
     * The line key matches the convention used in SyncOpsJobTransactionCMS
     * (vend_channel_code . '_' . product_code). For API Report invoices
     * we don't have a vend channel, so we prefix with "API_<period>".
     */
    protected function lineKey(string $productCode): string
    {
        return 'API_' . $productCode;
    }

    /**
     * Build one channels[] entry. Mirrors the shape OpsJob uses so the
     * CMS doesn't need a different parser branch.
     */
    protected function makeLine(string $code, float $qty, float $unitPrice, float $amount): array
    {
        return [
            'amount' => $amount,
            'unit_price' => $unitPrice,
            'product_code' => $code,
            'description' => self::LINE_DESC[$code] ?? null,
            'capacity' => 0,
            'qty' => 0,
            'needed' => $qty,
        ];
    }
}
