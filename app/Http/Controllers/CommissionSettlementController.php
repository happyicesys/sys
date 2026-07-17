<?php

namespace App\Http\Controllers;

use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementExport;
use App\Models\CustomerPeriodSummary;
use App\Models\Operator;
use App\Models\PayoutGroup;
use App\Services\Banking\CimbBankDirectory;
use App\Services\Commission\CommissionSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Site Settlement — batches Site Summary location-fee/commission payouts, exports
 * the CIMB file, and marks rows paid (posting the ledger credit). Admin only
 * ('admin-access customers'). Mirrors the Refund Settlement controller.
 */
class CommissionSettlementController extends Controller
{
    protected CommissionSettlementService $settlements;

    public function __construct(CommissionSettlementService $settlements)
    {
        $this->settlements = $settlements;
    }

    public function index(Request $request)
    {
        $query = CommissionSettlement::query();
        $this->scopeToUser($query);

        $status = $request->input('status');
        $query->when($status && $status !== 'all', function ($q) use ($status) {
                $status === 'open' ? $q->where('status', 'open') : $q->where('status', '!=', 'open');
            })
            ->when($request->input('date_from'), fn ($q, $d) => $q->whereDate('settlement_date', '>=', $d))
            ->when($request->input('date_to'), fn ($q, $d) => $q->whereDate('settlement_date', '<=', $d))
            ->when($request->input('search'), fn ($q, $s) => $q->where('reference', 'like', "%{$s}%"))
            ->orderByDesc('settlement_date')->orderByDesc('id');

        $page = $query->paginate(25)->withQueryString();
        $rows = collect($page->items());

        // Done count per settlement (paid member rows / distinct site+month groups).
        $agg = CustomerPeriodSummary::whereIn('commission_settlement_id', $rows->pluck('id'))
            ->selectRaw('commission_settlement_id, count(*) as c, coalesce(sum(case when paid_at is not null then 1 else 0 end),0) as paid_c')
            ->groupBy('commission_settlement_id')->get()->keyBy('commission_settlement_id');

        $groups = PayoutGroup::whereIn('id', $rows->pluck('payout_group_id')->filter()->unique())->get()->keyBy('id');
        $operators = Operator::withoutGlobalScopes()->whereIn('id', $rows->pluck('operator_id')->filter()->unique())->get()->keyBy('id');

        $settlements = $page->through(function (CommissionSettlement $s) use ($groups, $operators, $agg) {
            $a = $agg->get($s->id);
            return [
                'id' => $s->id,
                'reference' => $s->reference,
                'settlement_date' => optional($s->settlement_date)->format('ymd'),
                'status' => $s->status === CommissionSettlement::STATUS_OPEN ? 'open' : 'closed',
                'head' => $s->payout_group_id
                    ? ($groups->get($s->payout_group_id)?->name ?? ('Group #' . $s->payout_group_id))
                    : ($operators->get($s->operator_id)?->name ?? ('Operator #' . $s->operator_id)),
                'count' => (int) $s->count,
                'total' => number_format($s->total_cents / 100, 2),
                'done_count' => (int) ($a->paid_c ?? 0),
                'row_count' => (int) ($a->c ?? 0),
                'is_stale' => $s->isStale(),
                'created_at' => optional($s->created_at)->format('ymd h:i a'),
            ];
        });

        $mk = function () {
            $q = CommissionSettlement::query();
            $this->scopeToUser($q);
            return $q;
        };
        $statusCounts = [
            'open' => $mk()->where('status', CommissionSettlement::STATUS_OPEN)->count(),
            'closed' => $mk()->where('status', '!=', CommissionSettlement::STATUS_OPEN)->count(),
        ];

        return Inertia::render('SiteSettlement/Index', [
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

    public function show(CommissionSettlement $settlement)
    {
        $this->authorizeView($settlement);

        $members = CustomerPeriodSummary::where('commission_settlement_id', $settlement->id)
            ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'name', 'bank_id', 'bank_account_name', 'bank_account_number']), 'customer.bank:id,name,bic_code,proxy_type'])
            ->get();

        // One display row per site + month (sum machine segments).
        $rows = $members->groupBy(fn ($s) => $s->customer_id . '|' . ($s->year_month ? $s->year_month->toDateString() : ''))
            ->map(function ($group) {
                $first = $group->first();
                $customer = $first->customer;
                $net = (int) $group->sum(fn ($s) => (int) $s->location_fees_cents - (int) $s->external_subsidize_cents);
                $accNo = preg_replace('/[\s\-]+/', '', (string) ($customer?->bank_account_number ?? ''));
                $colE = CimbBankDirectory::resolveColE($customer?->bank?->bic_code, $customer?->bank?->proxy_type, $accNo);
                $isPaid = $group->every(fn ($s) => $s->paid_at !== null);
                $paidDate = $group->max('paid_date');
                return [
                    'key' => $first->customer_id . '|' . ($first->year_month ? $first->year_month->toDateString() : ''),
                    'summary_ids' => $group->pluck('id')->values(),
                    'site_name' => $customer?->name ?: ('Site #' . $first->customer_id),
                    'site_id' => $first->customer_id + 20000,
                    'month' => $first->year_month ? \Carbon\Carbon::parse($first->year_month)->format('M Y') : '—',
                    'amount' => number_format($net / 100, 2),
                    'bank_name' => $customer?->bank?->name ?: '—',
                    'destination' => $accNo ?: '—',
                    'col_e' => $colE ?: null,
                    'missing' => ($accNo === '' || $colE === ''),
                    'is_paid' => $isPaid,
                    'paid_date' => $paidDate ? \Carbon\Carbon::parse($paidDate)->format('ymd') : null,
                ];
            })->values();

        $head = $settlement->payout_group_id
            ? (PayoutGroup::find($settlement->payout_group_id)?->name ?? ('Group #' . $settlement->payout_group_id))
            : (Operator::withoutGlobalScopes()->find($settlement->operator_id)?->name ?? ('Operator #' . $settlement->operator_id));

        return Inertia::render('SiteSettlement/Show', [
            'settlement' => [
                'id' => $settlement->id,
                'reference' => $settlement->reference,
                'settlement_date' => optional($settlement->settlement_date)->format('ymd'),
                'status' => $settlement->status === CommissionSettlement::STATUS_OPEN ? 'open' : 'closed',
                'head' => $head,
                'count' => (int) $settlement->count,
                'total' => number_format($settlement->total_cents / 100, 2),
                'is_stale' => $settlement->isStale(),
                'closed_at' => optional($settlement->closed_at)->format('ymd h:i a'),
                'exported_at' => optional($settlement->exported_at)->format('ymd h:i a'),
            ],
            'rows' => $rows,
            'exports' => $settlement->exports->map(fn (CommissionSettlementExport $e) => [
                'id' => $e->id,
                'filename' => basename($e->file_path),
                'count' => $e->count,
                'total' => number_format($e->total_cents / 100, 2),
                'exported_at' => optional($e->exported_at)->format('ymd h:i a'),
                'download_url' => '/site-settlements/' . $settlement->id . '/exports/' . $e->id . '/download',
            ])->values(),
            'logs' => $settlement->logs->map(fn ($l) => [
                'actor_label' => $l->actor_label,
                'action' => $l->action,
                'note' => $l->note,
                'created_at' => optional($l->created_at)->format('ymd h:i a'),
            ])->values(),
        ]);
    }

    public function push(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        try {
            $res = $this->settlements->push($data['ids'], auth()->id(), auth()->user()?->name ?? 'Admin');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', $res['pushed'] . ' row(s) pushed to settlement (' . implode(', ', $res['settlements']) . ').');
    }

    public function reopen(CommissionSettlement $settlement)
    {
        return $this->run(fn () => $this->settlements->reopen($settlement, auth()->id(), auth()->user()?->name ?? 'Admin'), 'Settlement reopened.');
    }

    public function exportCimb(CommissionSettlement $settlement)
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

    public function downloadExport(CommissionSettlement $settlement, CommissionSettlementExport $export)
    {
        abort_unless((int) $export->commission_settlement_id === (int) $settlement->id, 404);
        abort_unless($export->file_path && Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, basename($export->file_path));
    }

    public function markDone(Request $request, CommissionSettlement $settlement)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'paid_date' => ['nullable', 'date'],
        ]);
        try {
            $n = $this->settlements->markDone($settlement, $data['ids'], auth()->id(), auth()->user()?->name ?? 'Admin', $data['paid_date'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', $n . ' row(s) marked paid.');
    }

    public function returnToPool(CommissionSettlement $settlement, CustomerPeriodSummary $summary)
    {
        return $this->run(fn () => $this->settlements->returnToPool($settlement, $summary, auth()->id(), auth()->user()?->name ?? 'Admin'), 'Row removed from settlement.');
    }

    public function destroy(CommissionSettlement $settlement)
    {
        try {
            $this->settlements->voidEmpty($settlement, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return redirect('/site-settlements')->with('success', 'Empty settlement voided.');
    }

    // ---- helpers ----

    protected function run(\Closure $fn, string $success)
    {
        try {
            $fn();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['settlement' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }

    protected function scopeToUser($query)
    {
        $authOperatorId = auth()->user()?->operator_id;
        if ((int) $authOperatorId !== 1 && $authOperatorId) {
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

    protected function authorizeView(CommissionSettlement $settlement): void
    {
        $authOperatorId = auth()->user()?->operator_id;
        if ((int) $authOperatorId === 1 || !$authOperatorId) {
            return;
        }
        $op = Operator::withoutGlobalScopes()->find($authOperatorId);
        $ok = ((int) $settlement->operator_id === (int) $authOperatorId)
            || ($op?->payout_group_id && (int) $settlement->payout_group_id === (int) $op->payout_group_id);
        abort_unless($ok, 403);
    }
}
