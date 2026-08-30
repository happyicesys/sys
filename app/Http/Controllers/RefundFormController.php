<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Refund\RefundEmailService;
use App\Services\Refund\RefundMatchingService;
use App\Services\Refund\RefundTicketService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Public, unauthenticated customer refund form. Reached via the QR the machine
 * renders: /refund?machineID=<vend_code> (machineID appended by mqtt-config).
 *
 * 2026-08-23: the APK also shows a QR right on the dispensing dialog when an
 * item fails, carrying &order_id=<ORDRID>. With a resolvable order the form
 * skips day/amount/candidate search and opens on that purchase.
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

    protected RefundEmailService $email;

    public function __construct(RefundMatchingService $matching, RefundTicketService $tickets, RefundEmailService $email)
    {
        $this->matching = $matching;
        $this->tickets = $tickets;
        $this->email = $email;
    }

    /**
     * Validate a PayNow destination — we only refund to a personal mobile number,
     * so it must be a valid phone number for the operator's country.
     */
    protected function isValidPaynowDestination(string $value, string $country): bool
    {
        $country = strtoupper($country ?: 'SG');

        try {
            $phone = new \Propaganistas\LaravelPhone\PhoneNumber($value, $country);
            if (! $phone->isValid()) {
                return false;
            }
            try {
                return $phone->isOfType('mobile');
            } catch (\Throwable $e) {
                return true; // type lookup unavailable -> accept any valid number
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve a machine and apply the eligibility gate. Blocked machines
     * (deactivated / unbound, see RefundMatchingService::machineBlocked) are
     * reported separately from unknown codes so the form can say why.
     *
     * @return array{0: ?\App\Models\Vend, 1: bool} [eligible vend or null, blocked]
     */
    protected function resolveEligibleMachine(string $machineID): array
    {
        $vend = $machineID !== '' ? $this->matching->resolveMachine($machineID) : null;

        if ($vend && $this->matching->machineBlocked($vend)) {
            return [null, true];
        }

        return [$vend, false];
    }

    /** Site (customer) name for a machine — public context, so skip operator scopes. */
    protected function siteName(?\App\Models\Vend $vend): ?string
    {
        if (! $vend || ! $vend->customer_id) {
            return null;
        }

        return optional(Customer::withoutGlobalScopes()->find($vend->customer_id))->name;
    }

    public function show(Request $request)
    {
        $machineID = (string) $request->query('machineID', '');
        [$vend, $blocked] = $this->resolveEligibleMachine($machineID);

        // order_id from the dispense-failure QR (APK ORDRID: digits, ~19 chars).
        // Anything else is ignored rather than rejected — the form still works
        // as a plain machine link.
        $orderId = $this->cleanOrderId($request->query('order_id'));
        $prefilled = ($vend && $orderId !== '') ? $this->matching->candidateByOrderId($machineID, $orderId) : null;

        return Inertia::render('Refund/Form', [
            'machineID' => $machineID,
            'machineFound' => (bool) $vend,
            'machineBlocked' => $blocked,
            'machineName' => $vend?->name,
            'siteName' => $this->siteName($vend),
            'orderId' => $orderId,
            'prefilledCandidate' => $prefilled,
            'reasonCodes' => collect(self::REASON_CODES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'allowedDays' => config('refund.match.days', ['today', 'yesterday']),
            'maxLookbackDays' => (int) config('refund.match.max_lookback_days', 14),
        ]);
    }

    /** Strip an order id down to what the APK emits; '' when unusable. */
    protected function cleanOrderId($raw): string
    {
        $s = trim((string) $raw);

        return preg_match('/^[A-Za-z0-9_-]{6,64}$/', $s) ? $s : '';
    }

    /**
     * Retry hook for the order-pinned QR: the TRADE upload can trail the scan by
     * a few seconds, so the form asks again before falling back to the manual
     * day + amount search.
     */
    public function byOrder(Request $request)
    {
        $data = $request->validate([
            'machineID' => ['required', 'string', 'max:191'],
            'order_id' => ['required', 'string', 'max:64'],
        ]);
        [$vend] = $this->resolveEligibleMachine($data['machineID']);
        $orderId = $this->cleanOrderId($data['order_id']);
        $candidate = ($vend && $orderId !== '') ? $this->matching->candidateByOrderId($data['machineID'], $orderId) : null;

        return response()->json(['found' => (bool) $candidate, 'candidate' => $candidate]);
    }

    public function resolve(Request $request)
    {
        $data = $request->validate(['machineID' => ['required', 'string', 'max:191']]);
        [$vend, $blocked] = $this->resolveEligibleMachine($data['machineID']);

        return response()->json([
            'found' => (bool) $vend,
            'blocked' => $blocked,
            'machineID' => $data['machineID'],
            'machineName' => $vend?->name,
            'siteName' => $this->siteName($vend),
        ]);
    }

    /**
     * Distinct products currently loaded in a machine — feeds the manual-review
     * "What did you buy?" dropdown (name + thumbnail + price). Public endpoint,
     * so it returns only harmless catalogue data.
     */
    public function machineProducts(Request $request)
    {
        $data = $request->validate(['machineID' => ['required', 'string', 'max:191']]);
        [$vend] = $this->resolveEligibleMachine($data['machineID']);

        if (! $vend) {
            return response()->json(['found' => false, 'products' => []]);
        }

        // Public, customer-facing endpoint: the products shown must reflect the
        // machine the customer is standing at, independent of who (if anyone) is
        // logged in. Product has an auth-gated OperatorProductFilterScope, so an
        // admin viewing this while logged in as another operator would otherwise
        // see an empty list. Bypass global scopes on the product relation.
        $channels = \App\Models\VendChannel::query()
            ->where('vend_id', $vend->id)
            ->where('is_active', true)
            ->whereNotNull('product_id')
            ->with(['product' => fn ($q) => $q->withoutGlobalScopes()->with('thumbnail')])
            ->get(['id', 'vend_id', 'product_id', 'amount', 'code']);

        // One row per channel (not grouped by product) so the customer can pick the
        // exact channel they bought from. Ordered by product name, then channel number.
        $products = $channels
            ->filter(fn ($c) => $c->product)
            ->map(function ($c) {
                $p = $c->product;

                return [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'price_cents' => (int) ($c->amount ?? 0),
                    'image_url' => $p->thumbnail?->full_url,
                    'channel_code' => $c->code,
                ];
            })
            ->sortBy(fn ($r) => mb_strtolower($r['name']).'|'.str_pad((string) $r['channel_code'], 6, '0', STR_PAD_LEFT))
            ->values();

        return response()->json(['found' => true, 'products' => $products]);
    }

    public function candidates(Request $request)
    {
        $data = $request->validate([
            'machineID' => ['required', 'string', 'max:191'],
            // 'today' | 'yesterday' | a YYYY-MM-DD custom date. dayRange() enforces
            // the eligibility window; out-of-range dates yield no candidates.
            'day' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        [$vend] = $this->resolveEligibleMachine($data['machineID']);
        if (! $vend) {
            return response()->json(['machineFound' => false, 'candidates' => []]);
        }

        $amountCents = (int) round(((float) $data['amount']) * 100);
        $result = $this->matching->candidates($data['machineID'], $data['day'], $amountCents);

        return response()->json([
            'machineFound' => (bool) $result['vend'],
            'candidates' => $result['candidates'],
        ]);
    }

    public function store(Request $request)
    {
        // On the manual-review path (no matched transaction) every descriptive
        // field is compulsory (Brian, 2026-08-24): time, amount (> $1), payment
        // method, affected items (the frontend's "Not sure / not listed" pick
        // still produces a manual_items_summary line), and the explanation.
        // Matched claims derive all of that from the transaction, so the same
        // fields stay optional there.
        $isManual = $request->boolean('is_manual');
        $manualRequired = fn (array $rules) => array_merge($isManual ? ['required'] : ['nullable'], $rules);

        $data = $request->validate([
            'machineID' => ['required', 'string', 'max:191'],
            'vend_transaction_id' => ['nullable', 'integer'],
            'payment_gateway_log_id' => ['nullable', 'integer'],
            'selected_item_ids' => ['nullable', 'array'],
            'selected_item_ids.*' => ['integer'],
            'reason_code' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::REASON_CODES))],
            'reason_text' => $manualRequired(['string', 'max:2000']),
            'manual_items_summary' => $manualRequired(['string', 'max:1000']),
            'manual_pay_method' => $manualRequired(['string', 'max:100']),
            'refund_method' => ['nullable', 'string', 'in:paynow,paypal'],
            'payout_destination' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['required', 'email', 'max:191'], // email is compulsory
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'is_manual' => ['nullable', 'boolean'],
            'entered_day' => ['nullable', 'string', 'max:30'],
            'entered_amount' => $isManual
                ? ['required', 'numeric', 'gt:1', 'max:100000']
                : ['nullable', 'numeric', 'min:0', 'max:100000'],
            'approx_time' => $manualRequired(['string', 'max:191']),
            'photos' => ['nullable', 'array', 'max:'.config('refund.attachments.max_count', 3)],
            'photos.*' => ['file', 'mimetypes:image/*,video/*', 'max:'.config('refund.attachments.max_kb', 30720)],
        ], [
            'entered_amount.required' => 'Please enter the total amount you paid.',
            'entered_amount.gt' => 'The amount you paid must be more than $1.',
            'approx_time.required' => 'Please tell us roughly what time you bought it.',
            'manual_pay_method.required' => 'Please select how you paid.',
            'manual_items_summary.required' => 'Please add the affected item(s), or pick "Not sure / not listed".',
            'reason_text.required' => 'Please explain what happened.',
        ]);

        // A photo/video is required on every path (matched and manual review).
        if (! $request->hasFile('photos')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photos' => 'Please attach at least one photo or a short video.',
            ]);
        }
        $method = $data['refund_method'] ?? null;
        $dest = trim((string) ($data['payout_destination'] ?? ''));

        if ($method === 'paynow') {
            if ($dest === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payout_destination' => 'Please enter your PayNow mobile number.',
                ]);
            }
            if (! $this->isValidPaynowDestination($dest, config('refund.paynow_country', 'SG'))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payout_destination' => 'Please enter a valid Singapore mobile number registered for PayNow.',
                ]);
            }
        }

        // require either a matched source or the manual path
        if (empty($data['is_manual']) && empty($data['vend_transaction_id']) && empty($data['payment_gateway_log_id'])) {
            return response()->json(['message' => 'No transaction selected.'], 422);
        }

        // Eligibility gate: no tickets against machines that are out of service.
        // The form never gets this far on its own (resolve already refuses), so
        // this is the backstop against direct POSTs.
        [, $machineIsBlocked] = $this->resolveEligibleMachine($data['machineID']);
        if ($machineIsBlocked) {
            return response()->json(['message' => 'This machine is no longer in service, so we cannot take a refund request for it.'], 422);
        }

        $ticket = $this->tickets->create([
            'machineID' => $data['machineID'],
            'vend_transaction_id' => $data['vend_transaction_id'] ?? null,
            'payment_gateway_log_id' => $data['payment_gateway_log_id'] ?? null,
            'selected_item_ids' => $data['selected_item_ids'] ?? [],
            'reason_code' => $data['reason_code'] ?? null,
            'reason_text' => $data['reason_text'] ?? null,
            'manual_items_summary' => $data['manual_items_summary'] ?? null,
            'manual_pay_method' => $data['manual_pay_method'] ?? null,
            'refund_method' => $data['refund_method'] ?? null,
            'payout_destination' => $data['payout_destination'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'is_manual' => (bool) ($data['is_manual'] ?? false),
            'entered_day' => $data['entered_day'] ?? null,
            'entered_amount_cents' => isset($data['entered_amount']) ? (int) round(((float) $data['entered_amount']) * 100) : null,
            'approx_time' => $data['approx_time'] ?? null,
            'submit_ip' => $request->ip(),
        ]);

        // attachments (max 3 images), stored privately on the local disk
        if ($request->hasFile('photos')) {
            foreach (array_slice($request->file('photos'), 0, (int) config('refund.attachments.max_count', 3)) as $photo) {
                // Configured disk (config/refund.php attachments.disk): 'local' or
                // private DO Spaces. Same relative path either way, so the DB rows
                // and the authed viewer route are disk-agnostic.
                $path = $photo->store('refund-attachments/'.$ticket->id, \App\Models\RefundTicketAttachment::storageDisk());
                \App\Models\RefundTicketAttachment::create([
                    'refund_ticket_id' => $ticket->id,
                    'path' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'mime' => $photo->getClientMimeType(),
                    'size' => $photo->getSize(),
                ]);
            }
        }

        // First customer-facing email, sent automatically on submission: the
        // acknowledgement ("we've received your refund request") with the RFD
        // reference. This is the ONLY email that goes out automatically — the
        // auto-refund / approval / etc. emails are now sent by an admin button on
        // the ticket (per the requested workflow), never fired on their own. Even
        // Nayax auto-resolved tickets get the acknowledgement here; the admin then
        // clicks "No charge / auto-refund" to email the customer that it was
        // handled. Delivery is still gated by REFUND_EMAIL_ENABLED (logged-only
        // while off), and every send is recorded on the ticket's audit trail.
        // This first send also establishes the email thread root that later
        // workflow emails reply onto (see RefundEmailService).
        try {
            $this->email->queue($ticket, RefundEmailService::T_RECEIVED);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Refund submission email failed', ['ticket' => $ticket->reference, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'reference' => $ticket->reference,
            'status' => $ticket->status,
            // "Auto-resolved" status retired: the customer-facing "already being
            // refunded" confirmation now keys off the auto-refund flag / channel.
            'auto_resolved' => (bool) ($ticket->auto_refund_detected || $ticket->is_auto_refund_channel),
            'is_auto_refund_channel' => $ticket->is_auto_refund_channel,
            'recommendation' => $ticket->system_recommendation,
        ]);
    }
}
