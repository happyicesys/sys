<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Services\Sales\TxnRevenueSettlement;
use App\Support\OperatorScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transactions > "Txn, Revenue & Settlement": each sale followed from the
 * machine's record to the rail's confirmation to the bank. All the rules live
 * in App\Services\Sales\TxnRevenueSettlement; this only pages and exports.
 */
class TxnRevenueSettlementController extends Controller
{
    public function __construct(protected TxnRevenueSettlement $service) {}

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('numberPerPage', 50), 10), 500);
        $sortDir = $request->input('sortDir') === 'asc' ? 'asc' : 'desc';
        $sortKey = in_array($request->input('sortKey'), ['amount', 'transaction_datetime'], true)
            ? $request->input('sortKey') : 'transaction_datetime';

        $filtered = $this->service->query($request);

        // Deferred join, as on All Transactions: page the ids off the narrow
        // query, then join the display tables for those ids only.
        $total = (clone $filtered)->count();
        $page = max(1, (int) $request->input('page', 1));
        $ids = (clone $filtered)
            ->orderBy('vend_transactions.'.$sortKey, $sortDir)
            ->orderBy('vend_transactions.id', $sortDir)
            ->forPage($page, $perPage)
            ->pluck('vend_transactions.id')
            ->all();

        $rows = $ids === [] ? collect() : $this->service->selectRows(
            $this->service->query($request)->whereIn('vend_transactions.id', $ids)
        )
            ->orderBy('vend_transactions.'.$sortKey, $sortDir)
            ->orderBy('vend_transactions.id', $sortDir)
            ->get();

        [$from, $to] = $this->service->dateRange($request);

        return Inertia::render('Vend/TxnRevenueSettlement', [
            'rows' => $this->service->describeAll($rows),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'totals' => $this->service->totals($filtered),
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'operators' => array_map('intval', (array) ($request->input('operators') ?: [])),
                'codes' => (string) $request->input('codes', ''),
                'order_id' => (string) $request->input('order_id', ''),
                'rails' => array_values((array) $request->input('rails', [])),
                'revenue_states' => array_values((array) $request->input('revenue_states', [])),
                'numberPerPage' => $perPage,
                'sortKey' => $sortKey,
                'sortDir' => $sortDir,
            ],
            'operatorOptions' => Operator::query()
                ->whereIn('id', OperatorScope::current())
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filtered = $this->service->query($request);
        [$from, $to] = $this->service->dateRange($request);
        $name = 'txn-revenue-settlement_'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';
        $money = fn (?int $cents) => $cents === null ? '' : number_format($cents / 100, 2, '.', '');

        return response()->streamDownload(function () use ($filtered, $money) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Order ID', 'Date Time', 'Machine ID', 'Site Code', 'Site', 'Operator', 'Channel', 'Product',
                'Txn Amount', 'Payment Method', 'Dispense',
                'Revenue Source', 'Revenue Status', 'Revenue Amount', 'Revenue - Txn', 'Report Line Time', 'Card Last 4',
                'Settlement Date', 'Settlement Gateway', 'Bank-in Amount', 'MDR Amount', 'MDR Rate', 'MDR Estimated?',
            ]);

            $this->service->selectRows($filtered)
                ->orderBy('vend_transactions.id')
                ->chunk(2000, function ($rows) use ($out, $money) {
                    foreach ($rows as $row) {
                        $r = $this->service->describe($row);
                        fputcsv($out, [
                            $r['order_id'], $r['transaction_datetime'], $r['vend_code'], $r['customer_code'], $r['customer_name'],
                            $r['operator_code'], $r['channel'], $r['product'],
                            $money($r['amount_cents']), $r['payment_method'], $r['dispense'],
                            $r['revenue_source'], $r['revenue_state'], $money($r['revenue_cents']), $money($r['revenue_diff_cents']),
                            $r['line_time'], $r['line_card_last4'],
                            $r['settlement_date'], $r['settlement_gateway'], $money($r['bank_in_cents']), $money($r['mdr_cents']),
                            $r['mdr_rate'], $r['mdr_is_estimate'] ? 'Y' : '',
                        ]);
                    }
                });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
