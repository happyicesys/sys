<?php

namespace App\Services\Sales;

use App\Models\CardSettlementRow;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\VendTransaction;
use App\Services\CardSettlement\Payout\SettlementPayoutResolver;
use App\Support\AutoRefundSource;
use App\Support\CardSettlement\PayoutTerms;
use App\Support\OperatorScope;
use App\Support\SaleFacts;
use App\Support\SaleStatus;
use App\Support\VendCode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Txn, Revenue & Settlement" — one sale followed down one line (Brian's
 * meeting, 2026-09-28): what the MACHINE says was sold, what a payment rail
 * CONFIRMS was received (revenue, before MDR), and what reaches the bank
 * (settlement, after MDR). Each stage is a different source and the figure
 * only ever shrinks along the line.
 *
 *   Vending Transaction  vend_transactions — our own record, from the TRADE.
 *   Revenue              the rail's own evidence: the NETS report line
 *                        (card_settlement_rows) or the Omise charge
 *                        (payment_gateway_logs). No figure when the rail
 *                        returned the money. Cash / HID / Grab have no rail
 *                        we reconcile, so they stay blank.
 *   Settlement           NETS: payout date + gateway from the acquirer
 *                        schedule, MDR ESTIMATED from its rate. Omise: MDR and
 *                        net are the ACTUAL figures on the charge; its
 *                        transfer date is not tracked, so the date is blank.
 *
 * The revenue verdict is ONE SQL expression (revenueStateSql) so the grid, the
 * filter and the totals cannot disagree about which rows are revenue.
 */
class TxnRevenueSettlement
{
    public const RAIL_CARD = 'card';

    public const RAIL_QR = 'qr';

    public const RAIL_CASH = 'cash';

    public const RAIL_OTHER = 'other';

    /** The rail confirmed the money: NETS line, or approved Omise charge. */
    public const REV_MATCHED = 'matched';

    /** The rail has not ruled yet: NETS day not final, Omise not approved. */
    public const REV_PENDING = 'pending';

    /** NETS is final for the day and carries no line for this sale. */
    public const REV_NOT_FOUND = 'not_found';

    /** No report covers this terminal (unbound, or a partly-covered supplier). */
    public const REV_UNVERIFIABLE = 'unverifiable';

    /** The rail returned the money (reversal, void, Omise refund, auto-refund). */
    public const REV_REFUNDED = 'refunded';

    /** Paid from credit the reader kept after an earlier failed vend — no new money. */
    public const REV_RETAINED = 'retained';

    /** Cash, HID card, Grab — no rail mark1 reconciles against. */
    public const REV_NONE = 'none';

    public function __construct(protected SettlementPayoutResolver $payouts) {}

    public static function railSql(): string
    {
        return 'CASE'
            .' WHEN payment_methods.payment_gateway_id IS NOT NULL THEN \''.self::RAIL_QR.'\''
            .' WHEN payment_methods.code = '.PaymentMethod::CODE_CARD_TERMINAL.' THEN \''.self::RAIL_CARD.'\''
            .' WHEN payment_methods.code = 0 THEN \''.self::RAIL_CASH.'\''
            .' ELSE \''.self::RAIL_OTHER.'\' END';
    }

    /**
     * A re-vended sale (auto_refund_source = retained_credit_revend) carries
     * is_refunded, but its money was KEPT — the reader held it as credit and a
     * later vend consumed it (SaleStatus::RE_VENDED). It is revenue.
     */
    public static function revenueStateSql(): string
    {
        $rail = self::railSql();
        $refunded = '(vend_transactions.settlement_status = '.VendTransaction::SETTLEMENT_REFUNDED
            .' OR (vend_transactions.is_refunded = 1 AND COALESCE(vend_transactions.auto_refund_source, \'\') <> \''
            .AutoRefundSource::RETAINED_CREDIT_REVEND.'\'))';

        return 'CASE'
            ." WHEN ($rail) IN ('".self::RAIL_CASH."', '".self::RAIL_OTHER."') THEN '".self::REV_NONE."'"
            ." WHEN $refunded THEN '".self::REV_REFUNDED."'"
            ." WHEN ($rail) = '".self::RAIL_QR."' THEN CASE"
            .' WHEN payment_gateway_logs.status = '.PaymentGatewayLog::STATUS_REFUND." THEN '".self::REV_REFUNDED."'"
            .' WHEN payment_gateway_logs.status = '.PaymentGatewayLog::STATUS_APPROVE." THEN '".self::REV_MATCHED."'"
            ." ELSE '".self::REV_PENDING."' END"
            .' WHEN tr_line.id IS NOT NULL THEN CASE'
            ." WHEN tr_line.reversed_by_row_id IS NOT NULL THEN '".self::REV_REFUNDED."'"
            ." ELSE '".self::REV_MATCHED."' END"
            ." WHEN vend_transactions.is_retained_credit_settlement = 1 THEN '".self::REV_RETAINED."'"
            ." WHEN vend_transactions.card_settlement_state = 'not_captured' THEN '".self::REV_NOT_FOUND."'"
            ." WHEN vend_transactions.card_settlement_state IN ('uncovered', 'unbound') THEN '".self::REV_UNVERIFIABLE."'"
            ." ELSE '".self::REV_PENDING."' END";
    }

    /** Omise money fields live under `data` on approve payloads, at the top on older ones. */
    protected static function omiseCents(string $field): string
    {
        return "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payment_gateway_logs.response, '$.data.$field')),"
            ." JSON_UNQUOTE(JSON_EXTRACT(payment_gateway_logs.response, '$.$field'))) AS SIGNED)";
    }

    /**
     * Rooted at VendTransaction so its operator/user global scopes apply; the
     * operator filter is narrowed to the viewer's ceiling on top of that.
     */
    public function query(Request $request): Builder
    {
        $operators = $request->input('operators');
        if (blank($operators)) {
            $user = $request->user();
            $operators = $user?->operator?->code === 'HIPL'
                ? OperatorScope::defaultFilterIds()
                : [$user?->operator_id];
        }
        $operatorIds = OperatorScope::narrow($operators, $request->user());

        [$from, $to] = $this->dateRange($request);

        $query = VendTransaction::query()
            ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('payment_gateway_logs', 'payment_gateway_logs.id', '=', 'vend_transactions.payment_gateway_log_id')
            // matched_vend_transaction_id is UNIQUE and only purchase lines
            // claim a sale, so this is at most one row per sale.
            ->leftJoin('card_settlement_rows as tr_line', function ($join) {
                $join->on('tr_line.matched_vend_transaction_id', '=', 'vend_transactions.id')
                    ->where('tr_line.status', CardSettlementRow::STATUS_MATCHED);
            })
            ->whereBetween('vend_transactions.transaction_datetime', [$from, $to])
            ->whereIn('vend_transactions.operator_id', $operatorIds ?: [0])
            ->where('vends.is_testing', false);

        if (filled($request->input('codes'))) {
            VendCode::whereLabels($query, preg_split('/[\s,]+/', (string) $request->input('codes')));
        }

        $rails = array_values(array_intersect(
            (array) $request->input('rails', []),
            [self::RAIL_CARD, self::RAIL_QR, self::RAIL_CASH, self::RAIL_OTHER],
        ));
        if ($rails !== []) {
            $query->whereIn(DB::raw(self::railSql()), $rails);
        }

        $states = array_values(array_intersect(
            (array) $request->input('revenue_states', []),
            [self::REV_MATCHED, self::REV_PENDING, self::REV_NOT_FOUND, self::REV_UNVERIFIABLE, self::REV_REFUNDED, self::REV_RETAINED, self::REV_NONE],
        ));
        if ($states !== []) {
            $query->whereIn(DB::raw(self::revenueStateSql()), $states);
        }

        if (filled($request->input('order_id'))) {
            $query->where('vend_transactions.order_id', 'like', '%'.trim((string) $request->input('order_id')).'%');
        }

        return $query;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function dateRange(Request $request): array
    {
        $tz = config('app.timezone');
        $from = $request->input('date_from') ? Carbon::parse($request->input('date_from'), $tz) : Carbon::today($tz);
        $to = $request->input('date_to') ? Carbon::parse($request->input('date_to'), $tz) : $from->copy();

        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }

    /** Columns every row needs — the page, the export and describe() read the same set. */
    public function selectRows(Builder $query): Builder
    {
        return $query
            ->leftJoin('card_settlement_reports as tr_report', 'tr_report.id', '=', 'tr_line.card_settlement_report_id')
            ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
            ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
            ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->select([
                'vend_transactions.id',
                'vend_transactions.order_id',
                'vend_transactions.transaction_datetime',
                'vend_transactions.amount',
                'vend_transactions.qty',
                'vend_transactions.is_multiple',
                'vend_transactions.is_refunded',
                'vend_transactions.auto_refund_source',
                'vend_transactions.settlement_status',
                'vend_transactions.is_found_in_transaction',
                'vend_transactions.is_retained_credit_settlement',
                'vend_transactions.card_settlement_synced_at',
                'vend_transactions.card_settlement_state',
                'vend_transactions.vend_channel_code',
                'vend_transactions.refund_request_id',
                'vend_transactions.refund_request_reference',
                'vend_transactions.refund_request_status',
                'vend_transactions.cashless_mfg',
                DB::raw(VendCode::sqlLabel().' AS vend_code'),
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'operators.code AS operator_code',
                'products.code AS product_code',
                'products.name AS product_name',
                'vend_channel_errors.code AS vend_channel_error_code',
                'payment_methods.name AS payment_method_name',
                'payment_methods.payment_gateway_id AS payment_method_gateway_id',
                DB::raw(self::railSql().' AS rail'),
                DB::raw(self::revenueStateSql().' AS revenue_state'),
                'payment_gateway_logs.status AS gateway_status',
                'payment_gateway_logs.approved_at AS gateway_approved_at',
                DB::raw('ROUND(payment_gateway_logs.amount * 100) AS gateway_amount_cents'),
                DB::raw(self::omiseCents('amount').' AS omise_amount_cents'),
                DB::raw(self::omiseCents('fee').' AS omise_fee_cents'),
                DB::raw(self::omiseCents('fee_vat').' AS omise_fee_vat_cents'),
                DB::raw(self::omiseCents('net').' AS omise_net_cents'),
                'tr_line.id AS line_id',
                'tr_line.amount_cents AS line_amount_cents',
                'tr_line.product AS line_product',
                'tr_line.card_issuer AS line_card_issuer',
                'tr_line.transaction_date AS line_transaction_date',
                'tr_line.transaction_time AS line_transaction_time',
                'tr_line.card_last4 AS line_card_last4',
                'tr_line.card_settlement_report_id AS line_report_id',
                'tr_report.provider AS line_provider',
            ]);
    }

    /**
     * One sale as the three groups. Pure over the selected columns, so it is
     * what the tests pin.
     *
     * @return array<string, mixed>
     */
    public function describe(object $row): array
    {
        $rail = (string) $row->rail;
        $state = (string) $row->revenue_state;
        $facts = SaleFacts::fromRow($row);

        $revenueCents = null;
        $source = null;
        $mdrCents = null;
        $mdrRate = null;
        $mdrIsEstimate = false;
        $bankInCents = null;
        $settlementDate = null;
        $settlementGateway = null;
        $settlementNote = null;

        if ($rail === self::RAIL_QR) {
            $source = 'Omise API';
            if ($state === self::REV_MATCHED) {
                $revenueCents = (int) ($row->omise_amount_cents ?: $row->gateway_amount_cents);
                if ($row->omise_fee_cents !== null) {
                    $mdrCents = (int) $row->omise_fee_cents + (int) $row->omise_fee_vat_cents;
                    $bankInCents = $row->omise_net_cents !== null ? (int) $row->omise_net_cents : $revenueCents - $mdrCents;
                    $mdrRate = $revenueCents > 0 ? $this->percent($mdrCents, $revenueCents) : null;
                }
                $settlementNote = 'Fee and net are Omise\'s own figures on the charge. Omise transfer dates are not tracked in mark1.';
            }
        } elseif ($rail === self::RAIL_CARD) {
            $source = $row->line_id ? strtoupper((string) ($row->line_provider ?: 'nets')).' report' : 'NETS report';
            if ($row->line_id) {
                // The line's amount, not the sale's: a top-up line on a
                // retained-credit sale is smaller than the sale.
                $lineCents = (int) $row->line_amount_cents;
                if ($state === self::REV_MATCHED) {
                    $revenueCents = $lineCents;
                }

                $payout = $this->payouts->for(
                    $row->line_provider,
                    $row->line_product,
                    $row->line_card_issuer,
                    $row->line_transaction_date,
                );
                if ($payout !== null && $state === self::REV_MATCHED) {
                    $terms = $payout->terms;
                    $mdrCents = $terms->mdrCents($lineCents, (int) config('card_settlement.mdr_gst_bps', 900));
                    $mdrRate = $terms->mdrRateLabel();
                    $mdrIsEstimate = $mdrCents !== null;
                    $bankInCents = $mdrCents === null ? null : ($terms->mdrDeducted ? $lineCents - $mdrCents : $lineCents);
                    $settlementDate = $payout->shortDate();
                    $settlementGateway = $payout->gateway();
                    $settlementNote = $payout->describe()
                        .($mdrCents !== null && ! $terms->mdrDeducted ? '. MDR is billed separately, so the full amount is banked.' : '')
                        .($mdrCents !== null ? ' MDR is estimated from the rate, rounded per line.' : '');
                }
            } elseif ($state === self::REV_NOT_FOUND) {
                $revenueCents = 0;
            }
        }

        return [
            'id' => $row->id,
            'order_id' => $row->order_id,
            'transaction_datetime' => $row->transaction_datetime
                ? Carbon::parse($row->transaction_datetime)->format('ymd h:ia')
                : null,
            'txn_date' => $row->transaction_datetime ? Carbon::parse($row->transaction_datetime)->toDateString() : null,
            'vend_code' => $row->vend_code,
            'customer_code' => $row->customer_code,
            'customer_name' => $row->customer_name,
            'operator_code' => $row->operator_code,
            'channel' => $row->vend_channel_code,
            'product' => $row->is_multiple
                ? 'Multiple ('.(int) $row->qty.' items)'
                : trim(($row->product_code ? $row->product_code.' ' : '').($row->product_name ?? '')),
            'amount_cents' => (int) $row->amount,
            'payment_method' => $row->payment_method_name,
            'cashless_mfg' => $row->cashless_mfg,
            'rail' => $rail,
            'dispense' => SaleStatus::dispense($facts),
            'dispense_reason' => SaleStatus::dispenseReason($facts),
            'payment_status' => SaleStatus::payment($facts),
            'auto_refund_source' => $row->auto_refund_source,
            'refund_request_id' => $row->refund_request_id,
            'refund_request_reference' => $row->refund_request_reference,
            'refund_request_status' => $row->refund_request_status,

            'revenue_state' => $state,
            'revenue_source' => $source,
            'revenue_cents' => $revenueCents,
            'revenue_diff_cents' => $revenueCents === null ? null : $revenueCents - (int) $row->amount,
            'nets_state' => $rail === self::RAIL_CARD ? $row->card_settlement_state : null,
            'line_time' => $row->line_id
                ? trim(CarbonImmutable::parse($row->line_transaction_date)->format('ymd').' '.substr((string) $row->line_transaction_time, 0, 5))
                : null,
            'line_card_last4' => $row->line_card_last4,
            'line_report_id' => $row->line_report_id,
            'line_synced' => ! empty($row->card_settlement_synced_at),

            'settlement_date' => $settlementDate,
            'settlement_gateway' => $settlementGateway,
            'settlement_note' => $settlementNote,
            'bank_in_cents' => $bankInCents,
            'mdr_cents' => $mdrCents,
            'mdr_rate' => $mdrRate,
            'mdr_is_estimate' => $mdrIsEstimate,
        ];
    }

    /**
     * Totals over the WHOLE filtered set, in cents. Card MDR is computed per
     * distinct (terms, amount) and multiplied, which is exactly the sum of the
     * per-line rounding the grid shows.
     *
     * @return array<string, mixed>
     */
    public function totals(Builder $filtered): array
    {
        $state = self::revenueStateSql();
        $rail = self::railSql();

        $byRail = (clone $filtered)
            ->where('vend_transactions.settlement_status', '<>', VendTransaction::SETTLEMENT_REFUNDED)
            ->groupBy(DB::raw($rail))
            ->get([DB::raw("$rail AS rail"), DB::raw('COUNT(*) AS n'), DB::raw('SUM(vend_transactions.amount) AS cents')])
            ->keyBy('rail');

        $byState = (clone $filtered)
            ->groupBy(DB::raw($state))
            ->get([DB::raw("$state AS revenue_state"), DB::raw('COUNT(*) AS n'), DB::raw('SUM(vend_transactions.amount) AS cents')])
            ->keyBy('revenue_state');

        $matched = fn (Builder $q) => $q->whereRaw("($state) = ?", [self::REV_MATCHED]);

        $qr = $matched((clone $filtered)->whereRaw("($rail) = ?", [self::RAIL_QR]))
            ->first([
                DB::raw('COUNT(*) AS n'),
                DB::raw('SUM(COALESCE('.self::omiseCents('amount').', ROUND(payment_gateway_logs.amount * 100))) AS revenue'),
                DB::raw('SUM(COALESCE('.self::omiseCents('fee').', 0) + COALESCE('.self::omiseCents('fee_vat').', 0)) AS mdr'),
                DB::raw('SUM('.self::omiseCents('net').') AS net'),
            ]);

        $cardGroups = $matched((clone $filtered)->whereRaw("($rail) = ?", [self::RAIL_CARD]))
            ->leftJoin('card_settlement_reports as tr_report', 'tr_report.id', '=', 'tr_line.card_settlement_report_id')
            ->groupBy('tr_report.provider', 'tr_line.product', 'tr_line.card_issuer', 'tr_line.amount_cents')
            ->get([
                'tr_report.provider', 'tr_line.product', 'tr_line.card_issuer', 'tr_line.amount_cents',
                DB::raw('COUNT(*) AS n'),
            ]);

        $gst = (int) config('card_settlement.mdr_gst_bps', 900);
        $card = ['revenue' => 0, 'mdr' => 0, 'bank_in' => 0, 'unpriced' => 0];
        foreach ($cardGroups as $g) {
            $lineCents = (int) $g->amount_cents;
            $n = (int) $g->n;
            $card['revenue'] += $lineCents * $n;
            /** @var PayoutTerms|null $terms */
            $terms = $this->payouts->resolverFor((string) $g->provider)?->resolve($g->product, $g->card_issuer);
            $fee = $terms?->mdrCents($lineCents, $gst);
            if ($fee === null) {
                $card['unpriced'] += $lineCents * $n;

                continue;
            }
            $card['mdr'] += $fee * $n;
            $card['bank_in'] += ($terms->mdrDeducted ? $lineCents - $fee : $lineCents) * $n;
        }

        $qrRevenue = (int) ($qr->revenue ?? 0);
        $qrMdr = (int) ($qr->mdr ?? 0);
        $revenue = $card['revenue'] + $qrRevenue;
        $mdr = $card['mdr'] + $qrMdr;

        $railRow = fn (string $r) => [
            'count' => (int) ($byRail->get($r)->n ?? 0),
            'cents' => (int) ($byRail->get($r)->cents ?? 0),
        ];
        $stateRow = fn (string $s) => [
            'count' => (int) ($byState->get($s)->n ?? 0),
            'cents' => (int) ($byState->get($s)->cents ?? 0),
        ];

        return [
            'txn' => [
                'count' => (int) $byRail->sum('n'),
                'cents' => (int) $byRail->sum('cents'),
                'card' => $railRow(self::RAIL_CARD),
                'qr' => $railRow(self::RAIL_QR),
                'cash' => $railRow(self::RAIL_CASH),
                'other' => $railRow(self::RAIL_OTHER),
            ],
            'revenue' => [
                'cents' => $revenue,
                'card_cents' => $card['revenue'],
                'qr_cents' => $qrRevenue,
                'states' => collect([
                    self::REV_MATCHED, self::REV_PENDING, self::REV_NOT_FOUND, self::REV_UNVERIFIABLE,
                    self::REV_REFUNDED, self::REV_RETAINED, self::REV_NONE,
                ])->mapWithKeys(fn ($s) => [$s => $stateRow($s)])->all(),
            ],
            'settlement' => [
                'mdr_cents' => $mdr,
                'card_mdr_cents' => $card['mdr'],
                'qr_mdr_cents' => $qrMdr,
                'net_cents' => $revenue - $mdr,
                'bank_in_cents' => $card['bank_in'] + (int) ($qr->net ?? 0),
                'unpriced_cents' => $card['unpriced'],
            ],
        ];
    }

    /** "2.69%" — display only. */
    protected function percent(int $part, int $whole): string
    {
        return rtrim(rtrim(number_format($part * 100 / $whole, 2, '.', ''), '0'), '.').'%';
    }

    /** @return Collection<int, array<string, mixed>> */
    public function describeAll(iterable $rows): Collection
    {
        return collect($rows)->map(fn ($row) => $this->describe($row))->values();
    }
}
