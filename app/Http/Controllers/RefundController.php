<?php

namespace App\Http\Controllers;

use App\Models\RefundPayoutBatch;
use App\Models\RefundTicket;
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
        $query = RefundTicket::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->refund_method, fn ($q, $s) => $q->where('refund_method', $s))
            ->when($request->payment_channel, fn ($q, $s) => $q->where('payment_channel', $s))
            ->when($request->date_from, fn ($q, $s) => $q->whereDate('created_at', '>=', $s))
            ->when($request->date_to, fn ($q, $s) => $q->whereDate('created_at', '<=', $s))
            ->when($request->search, function ($q, $s) {
                $q->where(function ($w) use ($s) {
                    $w->where('reference', 'like', "%{$s}%")
                        ->orWhere('vend_code', 'like', "%{$s}%")
                        ->orWhere('contact_email', 'like', "%{$s}%")
                        ->orWhere('payout_destination', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('created_at');

        $tickets = $query->paginate(25)->withQueryString()
            ->through(fn (RefundTicket $t) => $this->toRow($t));

        $counts = RefundTicket::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Refund/Index', [
            'tickets' => $tickets,
            'counts' => $counts,
            'filters' => $request->only(['status', 'refund_method', 'payment_channel', 'date_from', 'date_to', 'search']),
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function show(RefundTicket $ticket)
    {
        $ticket->load(['items', 'logs']);

        return Inertia::render('Refund/Show', [
            'ticket' => $this->toDetail($ticket),
            'emailTemplates' => [
                RefundEmailService::T_AUTO_REFUND => 'Auto-refund already triggered',
                RefundEmailService::T_CANCELLED_NO_CHARGE => 'Transaction cancelled (no charge)',
                RefundEmailService::T_INFO_REQUIRED => 'Additional info required (PayNow)',
                RefundEmailService::T_COMPLETED => 'Refund completed',
            ],
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

    public function downloadBatch(RefundPayoutBatch $batch)
    {
        abort_unless($batch->csv_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($batch->csv_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($batch->csv_path, $batch->reference . '.csv');
    }

    // ---- mappers ----

    protected function toRow(RefundTicket $t): array
    {
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
            'contact_email' => $t->contact_email,
            'created_at' => optional($t->created_at)->toDateTimeString(),
            'created_ago' => optional($t->created_at)->diffForHumans(),
        ];
    }

    protected function toDetail(RefundTicket $t): array
    {
        return array_merge($this->toRow($t), [
            'reason_text' => $t->reason_text,
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
                'vend_channel_code' => $i->vend_channel_code,
                'unit_price' => number_format($i->unit_price_cents / 100, 2),
                'had_channel_error' => $i->had_channel_error,
                'vend_channel_error_code' => $i->vend_channel_error_code,
                'channel_error_weightage' => $i->channel_error_weightage,
                'item_recommendation' => $i->item_recommendation,
                'approved' => $i->approved,
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
            RefundTicket::STATUS_SCHEDULED => 'Scheduled',
            RefundTicket::STATUS_COMPLETED => 'Completed',
        ];
    }
}
