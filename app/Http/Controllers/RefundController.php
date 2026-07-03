<?php

namespace App\Http\Controllers;

use App\Models\RefundPayoutBatch;
use App\Models\RefundTicket;
use App\Models\RefundTicketAttachment;
use App\Models\RefundTicketItem;
use App\Services\Refund\RefundEmailService;
use App\Services\Refund\RefundPayoutCsvService;
use App\Services\Refund\RefundTicketService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin/back-office refund management (Ops -> Admin -> Manager).
 * Route access is gated by Spatie permissions (read/verify/approve/update/payout refunds).
 */
class RefundController extends Controller
{
    protected RefundTicketService $tickets;
    protected RefundPayoutCsvService $payout;
    protected RefundEmailService $email;

    public function __construct(RefundTicketService $tickets, RefundPayoutCsvService $payout, RefundEmailService $email)
    {
        $this->tickets = $tickets;
        $this->payout = $payout;
        $this->email = $email;
    }

    public function index(Request $request)
    {
        $allStatuses = array_keys($this->statusLabels());
        // default view: everything except already-completed ("refunded") tickets
        $defaultStatuses = array_values(array_diff($allStatuses, [RefundTicket::STATUS_COMPLETED]));

        // default date window = last 4 weeks up to today (first load); user can override/clear
        $dateFrom = $request->has('date_from') ? $request->input('date_from') : now()->subWeeks(4)->toDateString();
        $dateTo = $request->has('date_to') ? $request->input('date_to') : now()->toDateString();

        $statusSel = $request->has('status')
            ? array_values(array_filter((array) $request->input('status')))
            : $defaultStatuses;
        $applyStatus = !empty($statusSel) && !in_array('all', $statusSel, true);

        $query = RefundTicket::query()
            ->when($applyStatus, fn ($q) => $q->whereIn('status', $statusSel))
            ->when($request->refund_method, fn ($q, $s) => $q->where('refund_method', $s))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($request->search, function ($q, $s) {
                $q->where(function ($w) use ($s) {
                    $w->where('reference', 'like', "%{$s}%")
                        ->orWhere('vend_code', 'like', "%{$s}%")
                        ->orWhere('contact_email', 'like', "%{$s}%")
                        ->orWhere('payout_destination', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('created_at'); // latest on top

        $page = $query->paginate(25)->withQueryString();

        // Batch-load the source transaction / gateway log / export batch for the
        // rows on this page (25 max). Loaded manually withoutGlobalScopes because
        // VendTransaction carries operator scopes that would filter rows away.
        $rows = collect($page->items());
        $txns = \App\Models\VendTransaction::withoutGlobalScopes()->with('paymentMethod')
            ->whereIn('id', $rows->pluck('vend_transaction_id')->filter()->unique())
            ->get()->keyBy('id');
        $logs = \App\Models\PaymentGatewayLog::query()
            ->whereIn('id', $rows->pluck('payment_gateway_log_id')->filter()->unique())
            ->get()->keyBy('id');
        $batches = RefundPayoutBatch::query()
            ->whereIn('id', $rows->pluck('payout_batch_id')->filter()->unique())
            ->get()->keyBy('id');

        $tickets = $page->through(fn (RefundTicket $t) => $this->toRow(
            $t,
            $t->vend_transaction_id ? $txns->get($t->vend_transaction_id) : null,
            $t->payment_gateway_log_id ? $logs->get($t->payment_gateway_log_id) : null,
            $t->payout_batch_id ? $batches->get($t->payout_batch_id) : null,
        ));

        // counts are over all tickets (unfiltered) so the chips always show true totals
        $counts = RefundTicket::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Refund/Index', [
            'tickets' => $tickets,
            'counts' => $counts,
            'filters' => [
                'search' => $request->input('search', ''),
                'refund_method' => $request->input('refund_method', ''),
                // only reflect status in the UI when the user explicitly chose some;
                // the default (all except completed) is applied silently and shows as "All statuses"
                'status' => $request->has('status') ? $statusSel : [],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'statuses' => $this->statusLabels(),
            'banks' => \App\Services\Refund\BankTemplates\BankTemplateRegistry::all(),
            'defaultBank' => config('refund.default_bank', 'cimb'),
        ]);
    }

    public function exportBatch(Request $request)
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
            'bank' => ['required', 'string'],
        ]);
        abort_unless(\App\Services\Refund\BankTemplates\BankTemplateRegistry::has($data['bank']), 422, 'Unknown bank template.');

        try {
            $res = $this->payout->exportBank($data['ticket_ids'], $data['bank'], auth()->id());
        } catch (\RuntimeException $e) {
            // Business-rule rejections (mixed operators, no eligible tickets,
            // missing originating account) → 422 with a readable message.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($res['content'], 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $res['filename'] . '"',
            'X-Filename' => $res['filename'],
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
        ]);
    }

    public function show(RefundTicket $ticket)
    {
        $ticket->load(['items', 'logs', 'attachments']);

        return Inertia::render('Refund/Show', [
            'ticket' => $this->toDetail($ticket),
            'emailTemplates' => [
                RefundEmailService::T_AUTO_REFUND => 'Auto-refund already triggered',
                RefundEmailService::T_CANCELLED_NO_CHARGE => 'Transaction cancelled (no charge)',
                RefundEmailService::T_INFO_REQUIRED => 'Additional info required (PayNow)',
                RefundEmailService::T_IN_PROGRESS => 'Refund in progress',
                RefundEmailService::T_COMPLETED => 'Refund completed',
            ],
            'emailTemplateContents' => $this->email->templates(),
            'statuses' => $this->statusLabels(),
        ]);
    }

    /**
     * Guard a workflow transition: abort 422 unless the ticket is currently in
     * one of the states this action is allowed to run from. Without this, any
     * action could fire from any state (e.g. completing a ticket that was never
     * approved, or re-approving a completed one and pulling it into a second
     * payout). rejected / completed appear in no allowed-from set, so they are
     * terminal.
     */
    private function guardTransition(RefundTicket $ticket, array $allowedFrom, string $verb): void
    {
        if (!in_array($ticket->status, $allowedFrom, true)) {
            abort(422, "Cannot {$verb} a refund that is currently '" . ($this->statusLabels()[$ticket->status] ?? $ticket->status) . "'.");
        }
    }

    public function verify(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_PENDING_TRANSFER_INFO,
        ], 'verify');
        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_VERIFIED,
            'ops_verified_by' => auth()->id(),
            'ops_verified_at' => now(),
        ]);
        $this->tickets->log($ticket, 'verified', $from, $ticket->status, 'Ops verified claim', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    public function reject(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_VERIFIED,
            RefundTicket::STATUS_PENDING_APPROVAL,
            RefundTicket::STATUS_PENDING_TRANSFER_INFO,
        ], 'reject');
        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_REJECTED,
            'ops_remarks' => $data['remarks'] ?? $ticket->ops_remarks,
        ]);
        $this->tickets->log($ticket, 'rejected', $from, $ticket->status, $data['remarks'] ?? 'Rejected', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    public function requestInfo(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_VERIFIED,
        ], 'request info on');
        $from = $ticket->status;
        $ticket->update(['status' => RefundTicket::STATUS_PENDING_TRANSFER_INFO]);
        $this->email->send($ticket, RefundEmailService::T_INFO_REQUIRED);
        $this->tickets->log($ticket, 'request_info', $from, $ticket->status, 'Requested valid PayNow details', auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    public function submitForApproval(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_VERIFIED,
        ], 'submit for approval');
        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_PENDING_APPROVAL,
            'submitted_for_approval_by' => auth()->id(),
            'submitted_for_approval_at' => now(),
        ]);
        $this->tickets->log($ticket, 'submit_approval', $from, $ticket->status, 'Submitted for manager approval', auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    public function approve(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_PENDING_APPROVAL,
        ], 'approve');

        // Double-refund guard: block if another ticket is already refunding this transaction.
        if ($conflict = $ticket->conflictingRefund()) {
            return back()->withErrors([
                'ticket' => "Cannot approve — this transaction is already being refunded under {$conflict->reference} (" . ($this->statusLabels()[$conflict->status] ?? $conflict->status) . ').',
            ]);
        }

        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_APPROVED,
            'manager_approved_by' => auth()->id(),
            'manager_approved_at' => now(),
        ]);
        $this->tickets->log($ticket, 'approved', $from, $ticket->status, 'Manager approved', auth()->user()?->name ?? 'Manager', auth()->id());

        return back();
    }

    public function complete(RefundTicket $ticket)
    {
        // Only a manager-approved (or already-scheduled-into-a-batch) ticket can
        // be marked paid — this is what stops 'complete' from skipping the
        // approval gate, and stops a completed ticket being completed twice.
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_APPROVED,
            RefundTicket::STATUS_SCHEDULED,
        ], 'complete');
        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_COMPLETED,
            'paid_at' => $ticket->paid_at ?? now(),
            'completed_at' => now(),
        ]);
        $this->email->send($ticket, RefundEmailService::T_COMPLETED);
        $this->tickets->log($ticket, 'completed', $from, $ticket->status, 'Refund completed', auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    /**
     * Ops manually attaches the source transaction (by Order ID) to a
     * manual-submitted ticket that had no match at submission time.
     */
    public function match(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate(['order_id' => ['required', 'string', 'max:191']]);

        try {
            $this->tickets->matchOrder($ticket, $data['order_id'], auth()->id(), auth()->user()?->name ?? 'Ops');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return back();
    }

    public function clearMatch(RefundTicket $ticket)
    {
        try {
            $this->tickets->clearMatch($ticket, auth()->id(), auth()->user()?->name ?? 'Ops');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['match' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Batch "Refund done" — mark the selected Approved/Scheduled tickets as
     * completed in one go (after the bank bulk file has been uploaded). Sends
     * the completion email per ticket, gated by REFUND_EMAIL_ENABLED
     * (logged-only while the flag is off).
     */
    public function completeBatch(Request $request)
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);

        $tickets = RefundTicket::whereIn('id', $data['ticket_ids'])
            ->whereIn('status', [RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED])
            ->get();

        if ($tickets->isEmpty()) {
            return back()->withErrors(['batch' => 'None of the selected tickets can be completed (must be Approved or Scheduled).']);
        }

        foreach ($tickets as $ticket) {
            $from = $ticket->status;
            $ticket->update([
                'status' => RefundTicket::STATUS_COMPLETED,
                'paid_at' => $ticket->paid_at ?? now(),
                'completed_at' => now(),
            ]);
            $this->email->send($ticket, RefundEmailService::T_COMPLETED);
            $this->tickets->log($ticket, 'completed', $from, $ticket->status, 'Refund completed (batch)', auth()->user()?->name ?? 'Admin', auth()->id());
        }

        return back()->with('success', $tickets->count() . ' refund(s) marked completed.');
    }

    public function updateItem(Request $request, RefundTicket $ticket, RefundTicketItem $item)
    {
        abort_unless($item->refund_ticket_id === $ticket->id, 404);
        $data = $request->validate(['approved' => ['required', 'boolean']]);
        $item->update(['approved' => $data['approved']]);
        $this->tickets->log($ticket, 'item_decision', null, null, ($data['approved'] ? 'Approved' : 'Excluded') . ' item: ' . ($item->product_name ?? $item->id), auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    public function sendEmail(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate(['template' => ['required', 'string']]);
        $sent = $this->email->send($ticket, $data['template']);
        $this->tickets->log($ticket, 'email', null, null, ($sent ? 'Sent' : 'Logged') . ' email: ' . $data['template'], auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    public function generateBatch(Request $request)
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);
        $batch = $this->payout->generate($data['ticket_ids'], auth()->id());

        return back()->with('batch_reference', $batch->reference);
    }

    public function markBatchUploaded(RefundPayoutBatch $batch)
    {
        $batch->update(['status' => RefundPayoutBatch::STATUS_UPLOADED, 'uploaded_at' => now()]);

        return back();
    }

    public function destroy(RefundTicket $ticket)
    {
        // Permanent clean delete (testing): remove children, attachment files, then the ticket.
        foreach ($ticket->attachments as $a) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($a->path);
        }
        $ticket->attachments()->delete();
        $ticket->items()->delete();
        $ticket->logs()->delete();
        $ticket->forceDelete();

        return redirect('/refunds')->with('success', 'Refund ticket deleted.');
    }

    public function downloadBatch(RefundPayoutBatch $batch)
    {
        abort_unless($batch->csv_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($batch->csv_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($batch->csv_path, $batch->reference . '.csv');
    }

    public function viewAttachment(RefundTicket $ticket, RefundTicketAttachment $attachment)
    {
        abort_unless($attachment->refund_ticket_id === $ticket->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($attachment->path);
    }

    // ---- mappers ----

    protected function toRow(RefundTicket $t, $txn = null, $log = null, $batch = null): array
    {
        $matched = (bool) ($t->vend_transaction_id || $t->payment_gateway_log_id);

        // Resolve the customer's "Today / Yesterday" choice to a real calendar
        // date, anchored on the SUBMISSION date (not now) so it still reads right
        // when Ops handles the refund days later. Custom picks are already a date.
        $enteredDayDate = $this->resolveEnteredDayDate($t);

        // Transaction details come from the matched source; manual tickets show
        // nothing here until Ops matches the Order ID.
        $txnDate = $txn?->transaction_datetime ?? $log?->approved_at ?? $log?->created_at;
        $paidCents = $txn?->amount ?? $log?->amount;

        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'vend_code' => $t->vend_code,
            'amount' => number_format($t->claimed_amount_cents / 100, 2),
            'refund_method' => $t->refund_method,
            'payment_channel' => $t->payment_channel,
            'reason_code' => $t->reason_code,
            'status' => $t->status,
            'recommendation' => $t->system_recommendation,
            'is_manual' => $t->is_manual,
            'matched' => $matched,
            'entered_day_date' => $enteredDayDate ? $enteredDayDate->format('ymd') : null,
            'contact_name' => $t->contact_name,
            'contact_email' => $t->contact_email,
            'created_at' => optional($t->created_at)->toDateTimeString(),
            'created_ago' => optional($t->created_at)->diffForHumans(),
            'submitted_at' => optional($t->created_at)->format('ymd h:i a'),
            'txn_datetime' => $matched ? optional($txnDate)->format('ymd h:i a') : null,
            'paid_amount' => ($matched && $paidCents !== null) ? number_format($paidCents / 100, 2) : null,
            'pay_method' => $matched ? ($txn?->paymentMethod?->name ?? ($log ? 'QR' : null)) : null,
            'batch' => $batch ? [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'filename' => $batch->csv_path ? basename($batch->csv_path) : null,
            ] : null,
            'completed_at' => optional($t->completed_at)->format('ymd h:i a'),
        ];
    }

    /**
     * Resolve the customer's Today/Yesterday choice to a real date, anchored on
     * the submission date. Custom picks (a YYYY-MM-DD string) are parsed as-is.
     */
    protected function resolveEnteredDayDate(RefundTicket $t): ?\Carbon\Carbon
    {
        if (! $t->entered_day || ! $t->created_at) {
            return null;
        }
        if ($t->entered_day === 'today') {
            return $t->created_at->copy();
        }
        if ($t->entered_day === 'yesterday') {
            return $t->created_at->copy()->subDay();
        }
        try {
            return \Carbon\Carbon::parse($t->entered_day);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function approxTimeToMinutes(?string $s): ?int
    {
        if (! $s) {
            return null;
        }
        try {
            $c = \Carbon\Carbon::parse(trim($s));

            return $c->hour * 60 + $c->minute;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sanity-check the customer's claim against the matched transaction so Ops can
     * catch a wrong Order ID. Each entry: key, ok (bool), soft (bool = warning
     * only), label, detail (tooltip). Only produced when a transaction is linked.
     */
    protected function matchChecks(RefundTicket $t, $txn): array
    {
        if (! $txn) {
            return [];
        }
        $checks = [];

        // Machine / vend code — the Order ID may belong to a different machine.
        $txnMachine = $txn->vend?->code;
        if ($txnMachine !== null && $t->vend_code !== null) {
            $ok = (string) $txnMachine === (string) $t->vend_code;
            $checks[] = [
                'key' => 'machine', 'ok' => $ok, 'soft' => false,
                'label' => $ok ? 'Machine matches' : 'Machine mismatch',
                'detail' => $ok
                    ? "Machine {$t->vend_code} matches the transaction."
                    : "The customer's machine is {$t->vend_code} but this transaction is on machine {$txnMachine}. The Order ID likely belongs to a different machine.",
            ];
        }

        // Amount — customer's stated amount paid vs the actual transaction amount.
        if ($t->entered_amount_cents !== null) {
            $ok = (int) $t->entered_amount_cents === (int) $txn->amount;
            $paid = number_format($t->entered_amount_cents / 100, 2);
            $act = number_format($txn->amount / 100, 2);
            $checks[] = [
                'key' => 'amount', 'ok' => $ok, 'soft' => false,
                'label' => $ok ? 'Amount matches' : 'Amount mismatch',
                'detail' => $ok
                    ? "The customer's amount (\${$paid}) matches the transaction."
                    : "The customer said they paid \${$paid}, but this transaction is \${$act}. Double-check the Order ID.",
            ];
        }

        // Date — day chosen (anchored on submission) vs transaction date.
        $enteredDate = $this->resolveEnteredDayDate($t);
        if ($enteredDate && $txn->transaction_datetime) {
            $ok = $enteredDate->isSameDay($txn->transaction_datetime);
            $chose = $enteredDate->format('ymd');
            $txd = $txn->transaction_datetime->format('ymd');
            $checks[] = [
                'key' => 'date', 'ok' => $ok, 'soft' => false,
                'label' => $ok ? 'Date matches' : 'Date mismatch',
                'detail' => $ok
                    ? "The chosen day ({$chose}) matches the transaction date."
                    : "The customer chose {$chose}, but this transaction is dated {$txd}. The Order ID may be for the wrong day.",
            ];
        }

        // Timing — soft check; the customer only gives an approximate time.
        $mins = $this->approxTimeToMinutes($t->approx_time);
        if ($mins !== null && $txn->transaction_datetime) {
            $txnMins = $txn->transaction_datetime->hour * 60 + $txn->transaction_datetime->minute;
            $ok = abs($mins - $txnMins) <= 90; // within ~1.5h is fine for an estimate
            $txt = $txn->transaction_datetime->format('H:i');
            $checks[] = [
                'key' => 'time', 'ok' => $ok, 'soft' => true,
                'label' => $ok ? 'Timing plausible' : 'Timing off',
                'detail' => $ok
                    ? "The customer's approx time (~{$t->approx_time}) is close to the transaction time ({$txt})."
                    : "The customer's approx time (~{$t->approx_time}) is far from the transaction time ({$txt}). Possibly the wrong Order ID — but this is only an estimate.",
            ];
        }

        return $checks;
    }

    protected function toDetail(RefundTicket $t): array
    {
        $txn = $t->vend_transaction_id
            ? \App\Models\VendTransaction::withoutGlobalScopes()
                ->with(['paymentMethod', 'vend', 'vendTransactionItems.product', 'vendTransactionItems.vendChannel'])
                ->find($t->vend_transaction_id)
            : null;
        $log = $t->payment_gateway_log_id ? \App\Models\PaymentGatewayLog::find($t->payment_gateway_log_id) : null;
        $batch = $t->payout_batch_id ? RefundPayoutBatch::find($t->payout_batch_id) : null;

        return array_merge($this->toRow($t, $txn, $log, $batch), [
            'reason_text' => $t->reason_text,
            'manual_items_summary' => $t->manual_items_summary,
            'manual_pay_method' => $t->manual_pay_method,
            'payout_destination' => $t->payout_destination,
            'contact_phone' => $t->contact_phone,
            'vend_transaction_id' => $t->vend_transaction_id,
            'payment_gateway_log_id' => $t->payment_gateway_log_id,
            'payout_batch_id' => $t->payout_batch_id,
            'is_auto_refund_channel' => $t->is_auto_refund_channel,
            'auto_refund_detected' => $t->auto_refund_detected,
            'system_validation' => $t->system_validation_json,
            'entered_day' => $t->entered_day,
            'entered_amount' => $t->entered_amount_cents !== null ? number_format($t->entered_amount_cents / 100, 2) : null,
            'approx_time' => $t->approx_time,
            'last_email_template' => $t->last_email_template,
            'last_email_sent_at' => optional($t->last_email_sent_at)->toDateTimeString(),
            'items' => $t->items->map(fn (RefundTicketItem $i) => [
                'id' => $i->id,
                'product_name' => $i->product_name,
                'product_sku' => $i->product_sku,
                'vend_channel_code' => $i->vend_channel_code,
                'unit_price' => number_format($i->unit_price_cents / 100, 2),
                'had_channel_error' => $i->had_channel_error,
                'vend_channel_error_code' => $i->vend_channel_error_code,
                'channel_error_desc' => $i->channel_error_desc,
                'channel_error_weightage' => $i->channel_error_weightage,
                'item_recommendation' => $i->item_recommendation,
                'approved' => $i->approved,
            ])->values(),
            // Full basket for the matched transaction: every item the customer
            // bought (multiple-purchase context), with the customer-claimed ones
            // flagged so Ops still gets the per-item decision toggle on those.
            'flagged_items' => $this->flaggedItems($t, $txn),
            'match_checks' => $this->matchChecks($t, $txn),
            'related_transactions' => $this->relatedTransactions($t),
            'attachments' => $t->attachments->map(fn (RefundTicketAttachment $a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime' => $a->mime,
                'url' => '/refunds/' . $t->id . '/attachments/' . $a->id,
            ])->values(),
            'logs' => $t->logs->map(fn ($l) => [
                'actor_label' => $l->actor_label,
                'action' => $l->action,
                'from_status' => $l->from_status,
                'to_status' => $l->to_status,
                'note' => $l->note,
                'created_at' => optional($l->created_at)->toDateTimeString(),
            ])->values(),
        ]);
    }

    /**
     * Items to show in the "Items flagged" table. When the ticket is matched to
     * a transaction we list the FULL basket (every item the customer bought that
     * visit — multiple-purchase context), keyed by channel to the customer's
     * claimed RefundTicketItem so the per-item decision toggle still rides only
     * on the claimed rows. Non-claimed rows are read-only context. When there is
     * no matched transaction yet (manual claim awaiting Order ID) we fall back to
     * the stored claimed items.
     */
    protected function flaggedItems(RefundTicket $ticket, $txn): array
    {
        $claimedByChannel = $ticket->items->keyBy('vend_channel_code');

        $mapClaim = fn ($claim, array $overrides = []) => array_merge([
            'id' => $claim?->id,
            'product_name' => $claim?->product_name,
            'product_sku' => $claim?->product_sku,
            'vend_channel_code' => $claim?->vend_channel_code,
            'unit_price' => $claim ? number_format($claim->unit_price_cents / 100, 2) : null,
            'had_channel_error' => (bool) ($claim?->had_channel_error),
            'vend_channel_error_code' => $claim?->vend_channel_error_code,
            'channel_error_desc' => $claim?->channel_error_desc,
            'channel_error_weightage' => $claim?->channel_error_weightage,
            'item_recommendation' => $claim?->item_recommendation,
            'approved' => $claim?->approved,
            'claimed' => (bool) $claim,
        ], $overrides);

        // No matched transaction yet — just the customer's claimed items.
        if (! $txn) {
            return $ticket->items->map(fn ($i) => $mapClaim($i))->values()->all();
        }

        $rows = [];
        foreach ($txn->vendTransactionItems as $vti) {
            $claim = $claimedByChannel->pull($vti->vend_channel_code);
            $rows[] = $mapClaim($claim, [
                'product_name' => $claim?->product_name ?? $vti->product?->name ?? ($vti->vend_channel_code ? 'Channel ' . $vti->vend_channel_code : 'Item'),
                'product_sku' => $claim?->product_sku ?? $vti->product?->code,
                'vend_channel_code' => $vti->vend_channel_code,
                'unit_price' => $claim
                    ? number_format($claim->unit_price_cents / 100, 2)
                    : number_format((($vti->unit_price_amount ?: ($vti->vendChannel?->amount ?? 0))) / 100, 2),
            ]);
        }
        // Any claimed items whose channel wasn't found in the basket (safety net).
        foreach ($claimedByChannel as $claim) {
            $rows[] = $mapClaim($claim);
        }

        return $rows;
    }

    /**
     * The source transaction(s) behind a ticket, with a deep link into Sales
     * Transactions filtered by order_id + that day.
     */
    protected function relatedTransactions(RefundTicket $ticket): array
    {
        $q = \App\Models\VendTransaction::withoutGlobalScopes()
            ->with(['paymentMethod', 'operator', 'customer', 'vendPrefix', 'vendChannel', 'vendChannelError',
                'vendTransactionItems.product', 'vendTransactionItems.vendChannel']);

        if ($ticket->order_id) {
            $q->where('order_id', $ticket->order_id);
        } elseif ($ticket->vend_transaction_id) {
            $q->where('id', $ticket->vend_transaction_id);
        } else {
            return [];
        }

        return $q->orderByDesc('transaction_datetime')->get()->map(function ($t) use ($ticket) {
            $date = $t->transaction_datetime;
            $link = '/vends/transactions?' . http_build_query(array_filter([
                'order_id' => $t->order_id,
                'date_from' => $date ? $date->copy()->startOfDay()->toDateTimeString() : null,
                'date_to' => $date ? $date->copy()->endOfDay()->toDateTimeString() : null,
            ]));

            $errCode = $t->vendChannelError?->code;
            $errCode = $errCode === null ? null : (int) $errCode;
            $paymentStatus = $t->is_payment_received
                ? 'Successful'
                : (($errCode === null || in_array($errCode, [0, 6], true)) ? 'Successful' : 'Unsuccessful');
            $site = trim(($t->customer?->virtual_customer_code ? $t->customer->virtual_customer_code . ' - ' : '') . ($t->customer?->name ?? ''));

            return [
                'id' => $t->id,
                'order_id' => $t->order_id,
                'datetime' => optional($date)->format('ymd h:i a'),
                'amount' => number_format($t->amount / 100, 2),
                'machine' => $ticket->vend_code,
                'vend_prefix_name' => $t->vendPrefix?->name,
                'site' => $site !== '' ? $site : null,
                'operator_code' => $t->operator?->code,
                'payment_method' => $t->paymentMethod?->name,
                'payment_status' => $paymentStatus,
                'channel_error' => ($t->vendChannelError && $errCode !== null && !in_array($errCode, [0, 6], true)) ? $t->vendChannelError->desc : null,
                'price_type' => ($t->vendChannel && (int) $t->amount === (int) $t->vendChannel->amount)
                    ? 'P1'
                    : (($t->vendChannel && (int) $t->amount === (int) $t->vendChannel->amount2) ? 'P2' : null),
                'txn_src' => $t->interface_type,
                'qty' => $t->qty,
                'dispensed_qty' => $t->dispensed_qty,
                'is_refunded' => (bool) $t->is_refunded,
                'items' => $t->vendTransactionItems->map(fn ($i) => [
                    'product' => $i->product?->name ?? ($i->vend_channel_code ? 'Channel ' . $i->vend_channel_code : 'Item'),
                    'product_code' => $i->product?->code,
                    'channel' => $i->vend_channel_code,
                    'price' => number_format((($i->unit_price_amount ?: ($i->vendChannel?->amount ?? 0))) / 100, 2),
                ])->values(),
                'link' => $link,
            ];
        })->values()->all();
    }

    protected function statusLabels(): array
    {
        return [
            RefundTicket::STATUS_SUBMITTED => 'Submitted',
            RefundTicket::STATUS_AUTO_RESOLVED => 'Auto-resolved',
            RefundTicket::STATUS_VERIFIED => 'Verified',
            RefundTicket::STATUS_REJECTED => 'Rejected',
            RefundTicket::STATUS_PENDING_APPROVAL => 'Pending approval',
            RefundTicket::STATUS_APPROVED => 'Approved',
            RefundTicket::STATUS_PENDING_TRANSFER_INFO => 'Pending info',
            RefundTicket::STATUS_COMPLETED => 'Completed',
        ];
    }
}
