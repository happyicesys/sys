<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use App\Models\PayoutGroup;
use App\Models\RefundPayoutBatch;
use App\Models\RefundSettlementExport;
use App\Models\RefundTicket;
use App\Services\Refund\RefundSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Refund Settlement — batches approved refund tickets into dated per-payout-group
 * pools, exports the CIMB (.txt) / PayPal (.xlsx) files from the settlement, and
 * marks the paid rows done (flowing completion back to the ticket). See
 * REFUND_SETTLEMENT_PLAN.md. Auth + Spatie 'read/payout refunds'.
 */
class RefundSettlementController extends Controller
{
    protected RefundSettlementService $settlements;

    public function __construct(RefundSettlementService $settlements)
    {
        $this->settlements = $settlements;
    }

    public function index(Request $request)
    {
        $query = RefundPayoutBatch::query()->settlements();

        // Operator scope: HIPL (operator 1) sees all; others only their own
        // operator / payout group settlements (mirrors OperatorFilterScope).
        $this->scopeToUser($query);

        $status = $request->input('status');
        $query->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->input('date_from'), fn ($q, $d) => $q->whereDate('settlement_date', '>=', $d))
            ->when($request->input('date_to'), fn ($q, $d) => $q->whereDate('settlement_date', '<=', $d))
            ->when($request->input('search'), fn ($q, $s) => $q->where('reference', 'like', "%{$s}%"))
            ->orderByDesc('settlement_date')->orderByDesc('id');

        $page = $query->paginate(25)->withQueryString();
        $rows = collect($page->items());

        // Per-stream aggregates for the page's settlements, one grouped query.
        $agg = RefundTicket::whereIn('payout_batch_id', $rows->pluck('id'))
            ->selectRaw("payout_batch_id, refund_method, count(*) as c, coalesce(sum(claimed_amount_cents),0) as t, coalesce(sum(case when status = 'completed' then 1 else 0 end),0) as done_c")
            ->groupBy('payout_batch_id', 'refund_method')
            ->get()
            ->groupBy('payout_batch_id');

        $groups = PayoutGroup::whereIn('id', $rows->pluck('payout_group_id')->filter()->unique())->get()->keyBy('id');
        $operators = Operator::withoutGlobalScopes()->whereIn('id', $rows->pluck('operator_id')->filter()->unique())->get()->keyBy('id');

        $settlements = $page->through(function (RefundPayoutBatch $s) use ($agg, $groups, $operators) {
            $streams = $agg->get($s->id) ?? collect();
            $paynow = $streams->firstWhere('refund_method', RefundTicket::METHOD_PAYNOW);
            $paypal = $streams->firstWhere('refund_method', RefundTicket::METHOD_PAYPAL);

            return [
                'id' => $s->id,
                'reference' => $s->reference,
                'settlement_date' => optional($s->settlement_date)->format('ymd'),
                'status' => $s->status,
                'head' => $s->payout_group_id
                    ? ($groups->get($s->payout_group_id)?->name ?? ('Group #' . $s->payout_group_id))
                    : ($operators->get($s->operator_id)?->name ?? ('Operator #' . $s->operator_id)),
                'count' => (int) $s->count,
                'total' => number_format($s->total_cents / 100, 2),
                'paynow_count' => (int) ($paynow->c ?? 0),
                'paynow_total' => number_format((($paynow->t ?? 0)) / 100, 2),
                'paypal_count' => (int) ($paypal->c ?? 0),
                'paypal_total' => number_format((($paypal->t ?? 0)) / 100, 2),
                'done_count' => (int) (($paynow->done_c ?? 0) + ($paypal->done_c ?? 0)),
                'is_stale' => $s->isStale(),
                'created_at' => optional($s->created_at)->format('ymd h:i a'),
            ];
        });

        $countsQuery = RefundPayoutBatch::query()->settlements();
        $this->scopeToUser($countsQuery);
        $statusCounts = $countsQuery->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('RefundSettlement/Index', [
            'settlements' => $settlements,
            'counts' => $statusCounts,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }

    public function show(RefundPayoutBatch $settlement)
    {
        abort_unless($settlement->is_settlement, 404);
        $this->authorizeView($settlement);

        $tickets = RefundTicket::where('payout_batch_id', $settlement->id)
            ->orderBy('refund_method')->orderBy('id')->get();

        // Site names for the member machines (scopes off — admin context).
        $vends = \App\Models\Vend::withoutGlobalScopes()
            ->whereIn('id', $tickets->pluck('vend_id')->filter()->unique())
            ->get(['id', 'customer_id'])->keyBy('id');
        $siteNames = \App\Models\Customer::withoutGlobalScopes()
            ->whereIn('id', $vends->pluck('customer_id')->filter()->unique())
            ->pluck('name', 'id');

        $mapTicket = function (RefundTicket $t) use ($vends, $siteNames) {
            $site = $t->vend_id && $vends->get($t->vend_id) ? $siteNames->get($vends->get($t->vend_id)->customer_id) : null;
            return [
                'id' => $t->id,
                'reference' => $t->reference,
                'vend_code' => $t->vend_code,
                'site_name' => $site,
                'amount' => number_format($t->claimed_amount_cents / 100, 2),
                'payout_destination' => $t->payout_destination,
                'contact_email' => $t->contact_email,
                'status' => $t->status,
                'is_done' => $t->status === RefundTicket::STATUS_COMPLETED,
                'completed_at' => optional($t->completed_at)->format('ymd h:i a'),
                // PayNow proxy sanity flag (invalid numbers ship a bad CIMB row).
                'proxy_valid' => $t->refund_method === RefundTicket::METHOD_PAYNOW
                    ? $this->isValidPaynow($t->payout_destination)
                    : true,
            ];
        };

        $head = $settlement->payout_group_id
            ? (PayoutGroup::find($settlement->payout_group_id)?->name ?? ('Group #' . $settlement->payout_group_id))
            : (Operator::withoutGlobalScopes()->find($settlement->operator_id)?->name ?? ('Operator #' . $settlement->operator_id));

        return Inertia::render('RefundSettlement/Show', [
            'settlement' => [
                'id' => $settlement->id,
                'reference' => $settlement->reference,
                'settlement_date' => optional($settlement->settlement_date)->format('ymd'),
                'status' => $settlement->status,
                'head' => $head,
                'count' => (int) $settlement->count,
                'total' => number_format($settlement->total_cents / 100, 2),
                'is_stale' => $settlement->isStale(),
                'closed_at' => optional($settlement->closed_at)->format('ymd h:i a'),
                'exported_at' => optional($settlement->exported_at)->format('ymd h:i a'),
            ],
            'paynowTickets' => $tickets->where('refund_method', RefundTicket::METHOD_PAYNOW)->map($mapTicket)->values(),
            'paypalTickets' => $tickets->where('refund_method', RefundTicket::METHOD_PAYPAL)->map($mapTicket)->values(),
            'exports' => $settlement->exports->map(fn (RefundSettlementExport $e) => [
                'id' => $e->id,
                'method' => $e->method,
                'format' => $e->format,
                'filename' => basename($e->file_path),
                'count' => $e->count,
                'total' => number_format($e->total_cents / 100, 2),
                'exported_at' => optional($e->exported_at)->format('ymd h:i a'),
                'download_url' => '/refund-settlements/' . $settlement->id . '/exports/' . $e->id . '/download',
            ])->values(),
            'logs' => $settlement->settlementLogs->map(fn ($l) => [
                'actor_label' => $l->actor_label,
                'action' => $l->action,
                'note' => $l->note,
                'created_at' => optional($l->created_at)->format('ymd h:i a'),
            ])->values(),
        ]);
    }

    /** Push selected Approved tickets from /refunds into their day's open settlement. */
    public function push(Request $request)
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);
        try {
            $res = $this->settlements->push($data['ticket_ids'], auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', $res['pushed'] . ' refund(s) pushed to settlement (' . implode(', ', $res['settlements']) . ').');
    }

    public function close(RefundPayoutBatch $settlement)
    {
        try {
            $this->settlements->close($settlement, auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', 'Settlement closed.');
    }

    public function exportCimb(RefundPayoutBatch $settlement)
    {
        try {
            $res = $this->settlements->exportCimb($settlement, auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($res['content'], 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $res['filename'] . '"',
            'X-Filename' => $res['filename'],
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
        ]);
    }

    public function exportXlsx(RefundPayoutBatch $settlement)
    {
        try {
            $res = $this->settlements->exportXlsx($settlement, auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Storage::disk('local')->download($res['path'], $res['filename']);
    }

    public function downloadExport(RefundPayoutBatch $settlement, RefundSettlementExport $export)
    {
        abort_unless((int) $export->refund_payout_batch_id === (int) $settlement->id, 404);
        abort_unless($export->file_path && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, basename($export->file_path));
    }

    public function markDone(Request $request, RefundPayoutBatch $settlement)
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);
        try {
            $done = $this->settlements->markDone($settlement, $data['ticket_ids'], auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', $done . ' refund(s) marked done.');
    }

    public function returnToPool(RefundPayoutBatch $settlement, RefundTicket $ticket)
    {
        try {
            $this->settlements->returnToPool($settlement, $ticket, auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticket returned to pool.');
    }

    public function destroy(RefundPayoutBatch $settlement)
    {
        try {
            $this->settlements->voidEmpty($settlement, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect('/refund-settlements')->with('success', 'Empty settlement voided.');
    }

    // ---- helpers ----

    protected function scopeToUser($query)
    {
        $authOperatorId = auth()->user()?->operator_id;
        $isHappyIce = (int) $authOperatorId === 1;
        if (!$isHappyIce && $authOperatorId) {
            $op = Operator::withoutGlobalScopes()->find($authOperatorId);
            $groupId = $op?->payout_group_id;
            $query->where(function ($q) use ($authOperatorId, $groupId) {
                $q->where('operator_id', $authOperatorId);
                if ($groupId) {
                    $q->orWhere('payout_group_id', $groupId);
                }
            });
        }
        return $query;
    }

    protected function authorizeView(RefundPayoutBatch $settlement): void
    {
        $authOperatorId = auth()->user()?->operator_id;
        if ((int) $authOperatorId === 1 || !$authOperatorId) {
            return; // HIPL / unscoped admin
        }
        $op = Operator::withoutGlobalScopes()->find($authOperatorId);
        $ok = ((int) $settlement->operator_id === (int) $authOperatorId)
            || ($op?->payout_group_id && (int) $settlement->payout_group_id === (int) $op->payout_group_id);
        abort_unless($ok, 403);
    }

    protected function isValidPaynow(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }
        try {
            return (new \Propaganistas\LaravelPhone\PhoneNumber($value, config('refund.paynow_country', 'SG')))->isValid();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
