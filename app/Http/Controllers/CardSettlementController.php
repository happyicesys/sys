<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardSettlementReportResource;
use App\Jobs\MatchCardSettlementReport;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementSyncService;
use App\Services\CardSettlement\ParserRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Card-terminal settlement reconciliation (NETS first).
 *
 * Upload the acquirer's daily report → MatchCardSettlementReport (low queue)
 * parses and matches rows to vend_transactions → the Show page surfaces
 * queries (unmatched / ambiguous) for the user to resolve → "Sync" stamps
 * card_settlement_synced_at onto every matched sale.
 */
class CardSettlementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read card-settlements'])->only(['index', 'show']);
        $this->middleware(['permission:create card-settlements'])->only(['store']);
        $this->middleware(['permission:update card-settlements'])->only(['rematch', 'resolveRow', 'ignoreRow', 'sync']);
        $this->middleware(['permission:delete card-settlements'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        // Filters arrive from the DatePicker as Y-m-d; validate rather than parse,
        // so a hand-edited URL is a 422 back to the page, not a 500.
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/'],
        ]);

        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = in_array($request->sortKey, ['cutover_date', 'original_filename', 'status', 'created_at'])
            ? $request->sortKey
            : 'cutover_date';
        $sortBy = filter_var($request->sortBy ?? false, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        $query = CardSettlementReport::query()
            ->with('uploader:id,name')
            ->when($request->input('status'), fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            ->when($request->input('date_from'), fn ($q, $d) => $q->whereDate('cutover_date', '>=', $d))
            ->when($request->input('date_to'), fn ($q, $d) => $q->whereDate('cutover_date', '<=', $d))
            ->orderBy($sortKey, $sortBy)
            ->orderByDesc('id');

        return Inertia::render('CardSettlement/Index', [
            'reports' => CardSettlementReportResource::collection(
                $query->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)->withQueryString()
            ),
            'providers' => ParserRegistry::providers(),
            'statuses' => [
                CardSettlementReport::STATUS_UPLOADED,
                CardSettlementReport::STATUS_MATCHING,
                CardSettlementReport::STATUS_REVIEW,
                CardSettlementReport::STATUS_SYNCED,
                CardSettlementReport::STATUS_FAILED,
            ],
            'filters' => [
                'status' => $request->input('status', 'all'),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'sortKey' => $sortKey,
                'sortBy' => $request->sortBy ?? false,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', ParserRegistry::providers())],
            'file' => ['required', 'file', 'max:'.(int) config('card_settlement.max_upload_kb', 20480)],
        ]);

        $file = $request->file('file');
        $dir = config('card_settlement.storage_folder', 'sys/card-settlements');
        $storedPath = $file->storePublicly($dir);

        $report = CardSettlementReport::create([
            'provider' => $validated['provider'],
            'original_filename' => $file->getClientOriginalName(),
            'status' => CardSettlementReport::STATUS_UPLOADED,
            'uploaded_by' => auth()->id(),
        ]);

        $report->attachments()->create([
            'full_url' => Storage::url($storedPath),
            'local_url' => $dir.'/'.basename($storedPath),
            'name' => $file->getClientOriginalName(),
            'type' => 'card-settlement-report',
        ]);

        MatchCardSettlementReport::dispatch($report->id);

        return redirect()->route('card-settlements.show', $report->id)
            ->with('message', 'Report uploaded — matching runs in the background, refresh in a moment.');
    }

    public function show(Request $request, $id)
    {
        $report = CardSettlementReport::with('attachment', 'uploader:id,name', 'syncer:id,name')->findOrFail($id);

        $status = $request->input('row_status', 'queries');
        $rowQuery = $report->rows()
            ->when($status === 'queries', fn ($q) => $q->whereIn('status', [
                CardSettlementRow::STATUS_UNMATCHED,
                CardSettlementRow::STATUS_AMBIGUOUS,
            ]))
            ->when(is_numeric($status), fn ($q) => $q->where('status', (int) $status))
            ->orderBy('terminal_id')
            ->orderBy('transaction_date')
            ->orderBy('transaction_time');

        $request->validate(['numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/']]);
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $page = $rowQuery->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)->withQueryString();

        // Vend code per row + per matched sale, one bounded per-page lookup.
        $rows = collect($page->items());
        $vendCodes = \App\Models\Vend::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('vend_id')->filter()->unique())
            ->pluck('code', 'id');
        $txns = VendTransaction::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('matched_vend_transaction_id')->filter())
            ->get(['id', 'transaction_datetime', 'amount', 'is_refunded', 'card_settlement_synced_at'])
            ->keyBy('id');

        // Terminals in this report with no binding — the one-time setup the
        // user must do before those rows can ever match.
        $unboundTerminals = $report->rows()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('resolution_note', 'No terminal binding')
            ->groupBy('terminal_id')
            ->selectRaw('terminal_id, COUNT(*) AS row_count')
            ->orderByDesc('row_count')
            ->get();

        return Inertia::render('CardSettlement/Show', [
            'report' => [
                'id' => $report->id,
                'provider' => $report->provider,
                'original_filename' => $report->original_filename,
                'merchant_account' => $report->merchant_account,
                'cutover_date' => $report->cutover_date?->format('Y-m-d'),
                'report_generated_at' => $report->report_generated_at?->format('Y-m-d H:i'),
                'status' => $report->status,
                'total_rows' => $report->total_rows,
                'purchase_rows' => $report->purchase_rows,
                'matched_count' => $report->matched_count,
                'unmatched_count' => $report->unmatched_count,
                'ambiguous_count' => $report->ambiguous_count,
                'duplicate_count' => $report->duplicate_count,
                'ignored_count' => $report->ignored_count,
                'synced_count' => $report->synced_count,
                'error_message' => $report->error_message,
                'uploaded_by' => $report->uploader?->name,
                'created_at' => $report->created_at?->format('Y-m-d H:i'),
                'matched_at' => $report->matched_at?->format('Y-m-d H:i'),
                'synced_at' => $report->synced_at?->format('Y-m-d H:i'),
                'synced_by' => $report->syncer?->name,
                'file_url' => $report->attachment?->full_url,
            ],
            // Manually shaped into the resource-collection envelope
            // (data / links / meta) the shared Paginator component expects.
            'rows' => [
                'links' => [
                    'prev' => $page->previousPageUrl(),
                    'next' => $page->nextPageUrl(),
                ],
                'meta' => [
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    'total' => $page->total(),
                    'links' => $page->linkCollection(),
                ],
                'data' => collect($page->items())->map(fn (CardSettlementRow $row) => [
                    'id' => $row->id,
                    'row_no' => $row->row_no,
                    'txn_type' => $row->txn_type,
                    'product' => $row->product,
                    'card_issuer' => $row->card_issuer,
                    'terminal_id' => $row->terminal_id,
                    'transaction_date' => $row->transaction_date->format('Y-m-d'),
                    'transaction_time' => $row->transaction_time,
                    'time_is_partial' => $row->time_is_partial,
                    'amount' => $row->amount_cents / 100,
                    'status' => $row->status,
                    'status_label' => CardSettlementRow::STATUS_LABELS[$row->status] ?? $row->status,
                    'vend_code' => $row->vend_id ? $vendCodes->get($row->vend_id) : null,
                    'matched_vend_transaction_id' => $row->matched_vend_transaction_id,
                    'matched_txn' => ($txn = $txns->get($row->matched_vend_transaction_id)) ? [
                        'id' => $txn->id,
                        'transaction_datetime' => $txn->transaction_datetime?->format('Y-m-d H:i:s'),
                        'amount' => $txn->amount / 100,
                        'is_refunded' => (bool) $txn->is_refunded,
                        'synced' => $txn->card_settlement_synced_at !== null,
                    ] : null,
                    'match_time_delta' => $row->match_time_delta,
                    'candidates' => $row->candidates_json,
                    'resolution_note' => $row->resolution_note,
                ])->values(),
            ],
            'rowFilters' => ['row_status' => $status],
            'unboundTerminals' => $unboundTerminals,
            'statusLabels' => CardSettlementRow::STATUS_LABELS,
        ]);
    }

    public function rematch($id)
    {
        $report = CardSettlementReport::findOrFail($id);
        abort_if($report->status === CardSettlementReport::STATUS_MATCHING, 422, 'Matching is already running.');

        MatchCardSettlementReport::dispatch($report->id);

        return back()->with('message', 'Rematch queued.');
    }

    /** Manually resolve a query row to a specific sale (from candidates or by id). */
    public function resolveRow(Request $request, $id, $rowId)
    {
        $validated = $request->validate([
            'vend_transaction_id' => ['required', 'integer'],
        ]);

        $report = CardSettlementReport::findOrFail($id);
        $row = $report->rows()->findOrFail($rowId);

        abort_unless(in_array($row->status, [
            CardSettlementRow::STATUS_UNMATCHED,
            CardSettlementRow::STATUS_AMBIGUOUS,
            CardSettlementRow::STATUS_MATCHED,
        ]), 422, 'Row cannot be resolved.');

        $txn = VendTransaction::withoutGlobalScopes()->findOrFail($validated['vend_transaction_id']);

        if ($txn->amount !== $row->amount_cents) {
            return back()->withErrors(['vend_transaction_id' => 'Sale amount does not match the report row.']);
        }
        $claimed = CardSettlementRow::where('matched_vend_transaction_id', $txn->id)
            ->where('id', '!=', $row->id)
            ->exists();
        if ($claimed) {
            return back()->withErrors(['vend_transaction_id' => 'That sale is already claimed by another report row.']);
        }

        $row->update([
            'status' => CardSettlementRow::STATUS_MATCHED,
            'matched_vend_transaction_id' => $txn->id,
            'match_time_delta' => null,
            'candidates_json' => null,
            'resolution_note' => 'Resolved manually',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        $report->refreshCounts();

        return back()->with('message', 'Row resolved.');
    }

    public function ignoreRow(Request $request, $id, $rowId)
    {
        $report = CardSettlementReport::findOrFail($id);
        $row = $report->rows()->findOrFail($rowId);

        $row->update([
            'status' => CardSettlementRow::STATUS_IGNORED,
            'matched_vend_transaction_id' => null,
            'match_time_delta' => null,
            'resolution_note' => $request->input('note') ?: 'Ignored by user',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        $report->refreshCounts();

        return back()->with('message', 'Row ignored.');
    }

    public function sync($id, CardSettlementSyncService $syncService)
    {
        $report = CardSettlementReport::findOrFail($id);
        abort_if($report->status === CardSettlementReport::STATUS_MATCHING, 422, 'Matching is still running.');

        $count = $syncService->sync($report, auth()->id());

        return back()->with('message', "Synced {$count} matched transaction(s).");
    }

    public function destroy($id)
    {
        $report = CardSettlementReport::findOrFail($id);

        abort_if($report->status === CardSettlementReport::STATUS_SYNCED, 422,
            'A synced report cannot be deleted — its stamps are already on the sales.');

        foreach ($report->attachments as $attachment) {
            if ($attachment->local_url) {
                Storage::delete($attachment->local_url);
            }
            $attachment->delete();
        }
        $report->delete(); // rows cascade

        return redirect()->route('card-settlements')->with('message', 'Report deleted.');
    }
}
