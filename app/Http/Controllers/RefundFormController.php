<?php

namespace App\Http\Controllers;

use App\Services\Refund\RefundMatchingService;
use App\Services\Refund\RefundTicketService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public, unauthenticated customer refund form. Reached via the QR the machine
 * renders: /refund?machineID=<vend_code> (machineID appended by mqtt-config).
 */
class RefundFormController extends Controller
{
    const REASON_CODES = [
        'not_dispensed' => 'Product did not dispense',
        'partial' => 'Only part of my order dispensed',
        'wrong_item' => 'Wrong item dispensed',
        'quality' => 'Quality issue',
        'double_charge' => 'Charged twice',
        'other' => 'Other',
    ];

    protected RefundMatchingService $matching;
    protected RefundTicketService $tickets;

    public function __construct(RefundMatchingService $matching, RefundTicketService $tickets)
    {
        $this->matching = $matching;
        $this->tickets = $tickets;
    }

    public function show(Request $request)
    {
        $machineID = (string) $request->query('machineID', '');
        $vend = $machineID !== '' ? $this->matching->resolveMachine($machineID) : null;

        return Inertia::render('Refund/Form', [
            'machineID' => $machineID,
            'machineFound' => (bool) $vend,
            'machineName' => $vend?->name,
            'machineLocation' => $vend ? trim(($vend->name ?? '') . '') : null,
            'reasonCodes' => collect(self::REASON_CODES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'allowedDays' => config('refund.match.days', ['today', 'yesterday']),
        ]);
    }

    public function resolve(Request $request)
    {
        $data = $request->validate(['machineID' => ['required', 'string', 'max:191']]);
        $vend = $this->matching->resolveMachine($data['machineID']);

        return response()->json([
            'found' => (bool) $vend,
            'machineID' => $data['machineID'],
            'machineName' => $vend?->name,
        ]);
    }

    public function candidates(Request $request)
    {
        $data = $request->validate([
            'machineID' => ['required', 'string', 'max:191'],
            'day' => ['required', 'string', 'in:' . implode(',', config('refund.match.days', ['today', 'yesterday']))],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $amountCents = (int) round(((float) $data['amount']) * 100);
        $result = $this->matching->candidates($data['machineID'], $data['day'], $amountCents);

        return response()->json([
            'machineFound' => (bool) $result['vend'],
            'candidates' => $result['candidates'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'machineID' => ['required', 'string', 'max:191'],
            'vend_transaction_id' => ['nullable', 'integer'],
            'payment_gateway_log_id' => ['nullable', 'integer'],
            'selected_item_ids' => ['nullable', 'array'],
            'selected_item_ids.*' => ['integer'],
            'reason_code' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::REASON_CODES))],
            'reason_text' => ['nullable', 'string', 'max:2000'],
            'refund_method' => ['nullable', 'string', 'in:paynow,paypal'],
            'payout_destination' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'is_manual' => ['nullable', 'boolean'],
            'entered_day' => ['nullable', 'string', 'max:30'],
            'entered_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'approx_time' => ['nullable', 'string', 'max:191'],
        ]);

        // require either a matched source or the manual path
        if (empty($data['is_manual']) && empty($data['vend_transaction_id']) && empty($data['payment_gateway_log_id'])) {
            return response()->json(['message' => 'No transaction selected.'], 422);
        }

        $ticket = $this->tickets->create([
            'machineID' => $data['machineID'],
            'vend_transaction_id' => $data['vend_transaction_id'] ?? null,
            'payment_gateway_log_id' => $data['payment_gateway_log_id'] ?? null,
            'selected_item_ids' => $data['selected_item_ids'] ?? [],
            'reason_code' => $data['reason_code'] ?? null,
            'reason_text' => $data['reason_text'] ?? null,
            'refund_method' => $data['refund_method'] ?? null,
            'payout_destination' => $data['payout_destination'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'is_manual' => (bool) ($data['is_manual'] ?? false),
            'entered_day' => $data['entered_day'] ?? null,
            'entered_amount_cents' => isset($data['entered_amount']) ? (int) round(((float) $data['entered_amount']) * 100) : null,
            'approx_time' => $data['approx_time'] ?? null,
            'submit_ip' => $request->ip(),
        ]);

        return response()->json([
            'reference' => $ticket->reference,
            'status' => $ticket->status,
            'auto_resolved' => $ticket->status === \App\Models\RefundTicket::STATUS_AUTO_RESOLVED,
            'is_auto_refund_channel' => $ticket->is_auto_refund_channel,
            'recommendation' => $ticket->system_recommendation,
        ]);
    }
}
