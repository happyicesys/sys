<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardSettlementReportResource;
use App\Jobs\MatchCardSettlementReport;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminalUnit;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementSyncService;
use App\Services\CardSettlement\CardTerminalBindingService;
use App\Services\CardSettlement\ParserRegistry;
use App\Services\UserLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->middleware(['permission:read card-settlements'])->only(['index', 'show', 'download']);
        $this->middleware(['permission:create card-settlements'])->only(['store']);
        $this->middleware(['permission:update card-settlements'])->only(['rematch', 'fixBindings', 'bindUnbound', 'resolveRow', 'ignoreRow', 'ignoreRows', 'sync']);
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

        // Private object storage (DO Spaces / S3), never the public app disk:
        // a settlement report is finance data. It is only ever served through
        // the authed download route below, so full_url points there.
        $disk = CardSettlementReport::storageDisk();
        $dir = config('card_settlement.storage_folder', 'card-settlements');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $storedPath = Storage::disk($disk)->putFileAs(
            $dir,
            $file,
            now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension
        );

        $report = CardSettlementReport::create([
            'provider' => $validated['provider'],
            'original_filename' => $file->getClientOriginalName(),
            'storage_disk' => $disk,
            'status' => CardSettlementReport::STATUS_UPLOADED,
            'uploaded_by' => auth()->id(),
        ]);

        $report->attachments()->create([
            'full_url' => route('card-settlements.download', $report->id),
            'local_url' => $storedPath,
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
            ->when($status === 'reversals', fn ($q) => $q->where('is_reversal', true))
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
            ->get(['id', 'transaction_datetime', 'amount', 'is_refunded', 'auto_refund_source', 'card_settlement_synced_at'])
            ->keyBy('id');

        // Which of this page's candidate sales are already taken, and by which
        // line — so the UI shows "claimed by row #N" instead of a Pick button
        // that would only bounce with "already claimed".
        $candidateTxnIds = $rows
            ->flatMap(fn (CardSettlementRow $r) => collect($r->candidates_json ?? [])->pluck('vend_transaction_id'))
            ->filter()
            ->unique();
        $claimedBy = $candidateTxnIds->isEmpty() ? collect() : CardSettlementRow::query()
            ->whereIn('matched_vend_transaction_id', $candidateTxnIds)
            ->get(['matched_vend_transaction_id', 'row_no', 'card_settlement_report_id'])
            ->keyBy('matched_vend_transaction_id');
        $decorateCandidates = fn (?array $candidates) => collect($candidates ?? [])->map(function ($c) use ($claimedBy, $report) {
            $holder = $claimedBy->get($c['vend_transaction_id'] ?? null);
            $c['claimed_by_row'] = $holder ? [
                'row_no' => $holder->row_no,
                'report_id' => $holder->card_settlement_report_id,
                'same_report' => $holder->card_settlement_report_id === $report->id,
            ] : null;

            return $c;
        })->values()->all();

        // Row numbers of the reversal ↔ purchase counterparts on this page
        // (a counterpart may live in another report, hence the report id too).
        $linkedRows = CardSettlementRow::query()
            ->whereIn('id', $rows->pluck('reverses_row_id')->merge($rows->pluck('reversed_by_row_id'))->filter()->unique())
            ->get(['id', 'row_no', 'card_settlement_report_id', 'transaction_time'])
            ->keyBy('id');
        $linked = fn (?int $id) => ($id && ($r = $linkedRows->get($id))) ? [
            'id' => $r->id,
            'row_no' => $r->row_no,
            'report_id' => $r->card_settlement_report_id,
            'transaction_time' => $r->transaction_time,
        ] : null;

        $suspectBindings = $this->suspectBindings($report);

        // Terminals in this report with no binding — the one-time setup the
        // user must do before those rows can ever match.
        $unboundTerminals = $this->unboundTerminals($report);

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
                'reversal_rows' => $report->reversal_rows,
                'partial_time_rows' => $report->partial_time_rows,
                'matched_count' => $report->matched_count,
                'unmatched_count' => $report->unmatched_count,
                'ambiguous_count' => $report->ambiguous_count,
                'duplicate_count' => $report->duplicate_count,
                'ignored_count' => $report->ignored_count,
                'synced_count' => $report->synced_count,
                'refunded_count' => $report->refunded_count,
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
                        'auto_refund_source' => $txn->auto_refund_source,
                        'synced' => $txn->card_settlement_synced_at !== null,
                    ] : null,
                    'is_reversal' => $row->is_reversal,
                    'reverses_row' => $linked($row->reverses_row_id),
                    'reversed_by_row' => $linked($row->reversed_by_row_id),
                    'match_time_delta' => $row->match_time_delta,
                    'candidates' => $decorateCandidates($row->candidates_json),
                    'resolution_note' => $row->resolution_note,
                ])->values(),
            ],
            'rowFilters' => ['row_status' => $status],
            'unboundTerminals' => $unboundTerminals,
            'suspectBindings' => $suspectBindings,
            'statusLabels' => CardSettlementRow::STATUS_LABELS,
        ]);
    }

    /**
     * Terminals whose unmatched lines have a fitting sale on ANOTHER machine —
     * the binding sheet is probably wrong for that TID. Grouped so the user
     * fixes the binding once, either by hand or through fixBindings() below.
     *
     * `from_date` is the earliest line that fits the suggested machine, i.e.
     * the earliest date this report can prove the terminal was already there —
     * so a repair never claims more history than the evidence covers.
     */
    /**
     * TIDs in this report with no binding on the lines' dates, split by whether
     * the terminal exists in Data Management at all, each with the machine the
     * matcher suggests (the ONE machine every fitting sale of its lines sits on
     * — see CardSettlementMatcher::suggestMachineForUnbound) and how many
     * lines back that suggestion.
     */
    private function unboundTerminals(CardSettlementReport $report)
    {
        $lines = $report->rows()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('resolution_note', 'No terminal binding')
            ->get(['terminal_id', 'transaction_date', 'candidates_json']);

        $knownUnits = CardTerminalUnit::query()
            ->whereIn('terminal_id', $lines->pluck('terminal_id')->unique())
            ->pluck('terminal_id')
            ->flip();

        return $lines->groupBy('terminal_id')
            ->map(function ($group, $terminalId) use ($knownUnits) {
                $suggested = $group
                    ->flatMap(fn ($l) => collect($l->candidates_json ?? [])->where('other_vend', true)->pluck('vend_code')->unique())
                    ->countBy()
                    ->sortDesc();

                return [
                    'terminal_id' => (string) $terminalId,
                    'row_count' => $group->count(),
                    'unit_exists' => $knownUnits->has($terminalId),
                    'suggested_vend_code' => $suggested->keys()->first(),
                    'suggested_hits' => (int) ($suggested->first() ?? 0),
                    'from_date' => $group->min('transaction_date')?->toDateString(),
                ];
            })
            ->sortByDesc('row_count')
            ->values();
    }

    private function suspectBindings(CardSettlementReport $report)
    {
        $lines = $report->rows()
            ->where('status', CardSettlementRow::STATUS_UNMATCHED)
            ->where('resolution_note', 'like', 'No matching sale on bound machine%')
            ->get(['terminal_id', 'vend_id', 'transaction_date', 'candidates_json']);

        $vendCodes = \App\Models\Vend::withoutGlobalScopes()
            ->whereIn('id', $lines->pluck('vend_id')->filter()->unique())
            ->pluck('code', 'id');

        return $lines
            ->groupBy('terminal_id')
            ->map(function ($lines, $terminalId) use ($vendCodes) {
                $suggestedCode = $lines
                    ->flatMap(fn ($l) => collect($l->candidates_json ?? [])->where('other_vend', true)->pluck('vend_code'))
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();

                // Date the evidence from the lines that fit the SUGGESTED
                // machine; a line fitting some third machine must not drag the
                // binding further back than this move can justify.
                $fitting = $lines->filter(fn ($l) => collect($l->candidates_json ?? [])
                    ->contains(fn ($c) => ($c['other_vend'] ?? false)
                        && (string) ($c['vend_code'] ?? '') === (string) $suggestedCode));

                $fromDate = ($fitting->isNotEmpty() ? $fitting : $lines)
                    ->min(fn ($l) => $l->transaction_date->toDateString());

                return [
                    'terminal_id' => (string) $terminalId,
                    'bound_vend_code' => $vendCodes->get($lines->first()->vend_id),
                    'suggested_vend_code' => $suggestedCode,
                    'row_count' => $lines->count(),
                    'suggested_hits' => $fitting->count(),
                    'from_date' => $fromDate,
                ] + $this->bindingMoveImpact((string) $terminalId, $suggestedCode, $fromDate);
            })
            ->sortByDesc('row_count')
            ->values();
    }

    /**
     * What moving this terminal would cost as well as fix, counted BEFORE the
     * move so the trade-off is visible on the button rather than discovered in
     * the rematch afterwards.
     *
     * A binding is global, so the blast radius is EVERY report from `$fromDate`
     * on, not just the one on screen:
     *  - `would_fix`   unmatched lines that have a fitting sale on the machine
     *                  we would move to — the point of the exercise.
     *  - `would_break` lines matched TODAY whose sale sits on some other
     *                  machine; re-resolving the terminal takes their binding
     *                  away and they fall back to unmatched.
     *  - `would_break_synced` the subset of those already stamped onto a sale
     *                  by Sync. Those are settled finance data, so a move that
     *                  breaks any of them wants a human, not a bulk button.
     *
     * Counted from `card_settlement_rows.vend_id`, which a matched row carries
     * as the machine its sale was found on — no join to 5M vend_transactions.
     *
     * @return array{would_fix: int, would_break: int, would_break_synced: int}
     */
    private function bindingMoveImpact(string $terminalId, $suggestedVendCode, ?string $fromDate): array
    {
        $empty = ['would_fix' => 0, 'would_break' => 0, 'would_break_synced' => 0];

        if (! $fromDate || $suggestedVendCode === null) {
            return $empty;
        }

        $target = \App\Models\Vend::withoutGlobalScopes()->where('code', $suggestedVendCode)->get(['id']);
        if ($target->count() !== 1) {
            return $empty;
        }
        $targetId = (int) $target->first()->id;

        $affected = CardSettlementRow::query()
            ->where('terminal_id', $terminalId)
            ->whereDate('transaction_date', '>=', $fromDate)
            ->whereIn('status', [CardSettlementRow::STATUS_MATCHED, CardSettlementRow::STATUS_UNMATCHED])
            ->get(['id', 'status', 'vend_id', 'matched_vend_transaction_id', 'candidates_json']);

        [$matched, $unmatched] = $affected->partition(
            fn (CardSettlementRow $r) => $r->status === CardSettlementRow::STATUS_MATCHED
        );

        $breaking = $matched->reject(fn (CardSettlementRow $r) => (int) $r->vend_id === $targetId);

        $syncedTxnIds = $breaking->pluck('matched_vend_transaction_id')->filter();
        $syncedCount = $syncedTxnIds->isEmpty() ? 0 : VendTransaction::withoutGlobalScopes()
            ->whereIn('id', $syncedTxnIds)
            ->whereNotNull('card_settlement_synced_at')
            ->count();

        return [
            'would_fix' => $unmatched->filter(fn (CardSettlementRow $r) => collect($r->candidates_json ?? [])
                ->contains(fn ($c) => ($c['other_vend'] ?? false)
                    && (string) ($c['vend_code'] ?? '') === (string) $suggestedVendCode))->count(),
            'would_break' => $breaking->count(),
            'would_break_synced' => $syncedCount,
        ];
    }

    /**
     * Move every suspected wrong binding in this report onto the machine its
     * sales are actually on, then rematch — the one-click form of the manual
     * "go to the right machine's Settings page" instruction.
     *
     * Bindings are global and dated, so this changes how EVERY report resolves
     * that terminal, not just this one; each move is therefore reported back by
     * name, and anything the service will not do unattended (no terminal
     * record, an ambiguous machine code, a date another binding already claims)
     * is skipped with its reason rather than forced.
     */
    public function fixBindings(Request $request, $id, CardTerminalBindingService $bindings)
    {
        $validated = $request->validate([
            'terminal_ids' => ['nullable', 'array', 'max:200'],
            'terminal_ids.*' => ['string', 'max:64'],
        ]);

        $report = CardSettlementReport::findOrFail($id);
        abort_if($report->status === CardSettlementReport::STATUS_MATCHING, 422, 'Matching is still running.');

        $suspects = $this->suspectBindings($report);

        // The page ticks only the suggestions the user accepts, so act on those
        // and no others. The suggestion set is always recomputed here rather
        // than trusted from the request — the ticks choose WHICH terminal to
        // move, never which machine to move it to or from when.
        if (! empty($validated['terminal_ids'])) {
            $chosen = collect($validated['terminal_ids'])->map(fn ($t) => (string) $t)->all();
            $suspects = $suspects->whereIn('terminal_id', $chosen)->values();
        }

        $moved = [];
        $skipped = [];

        foreach ($suspects as $suspect) {
            $terminalId = $suspect['terminal_id'];

            $unit = CardTerminalUnit::where('terminal_id', $terminalId)->first();
            if (! $unit) {
                $skipped[] = "{$terminalId}: no terminal record — add it under Data Management → Card Terminal";

                continue;
            }

            // withoutGlobalScopes to match the matcher's own fleet lookup: an
            // operator-scoped read would silently skip another operator's
            // machine and leave those lines unmatched forever.
            $candidates = \App\Models\Vend::withoutGlobalScopes()
                ->where('code', $suspect['suggested_vend_code'])->get();
            if ($candidates->count() !== 1) {
                $skipped[] = "{$terminalId}: machine {$suspect['suggested_vend_code']} "
                    .($candidates->isEmpty() ? 'not found' : 'is not unique');

                continue;
            }
            $vend = $candidates->first();

            // Breaking a line Sync already stamped onto a sale is un-settling
            // finance data. The bulk button never does that unattended — the
            // Settings page is still there for a human who means it.
            if ($suspect['would_break_synced'] > 0) {
                $skipped[] = "{$terminalId}: would break {$suspect['would_break_synced']} already-synced line(s) — move it by hand from machine {$vend->code}'s Settings page";

                continue;
            }

            $before = $bindings->currentUnitFor($vend)?->terminal_id;
            $result = $bindings->moveToVend($unit, $vend, $suspect['from_date']);

            if (! $result['moved']) {
                $skipped[] = "{$terminalId}: {$result['note']}";

                continue;
            }

            // Bindings live on their own table, so the app-wide audit never
            // sees this as a vend change — record it by hand under the same
            // synthetic column VendController uses for the Settings page.
            UserLogger::recordChanges($vend, [
                'card_terminal_unit_id' => [$before, $unit->terminal_id],
            ]);

            $moved[] = "{$terminalId} → {$vend->code} ({$result['note']})";
        }

        if ($moved) {
            MatchCardSettlementReport::dispatch($report->id);
        }

        $message = $moved
            ? 'Moved '.count($moved).' of '.$suspects->count().' terminal(s) — rematch queued: '.implode('; ', $moved).'.'
            : 'No binding was changed.';
        if ($skipped) {
            $message .= ' Left alone: '.implode('; ', $skipped).'.';
        }

        return back()->with('message', $message);
    }

    /**
     * Accept the matcher's machine suggestion for TIDs that have no binding:
     * create the terminal in Data Management if it is new, put it on the
     * suggested machine from the first date this report saw it, then rematch.
     * Only the ticked TIDs are acted on; the suggestion set is recomputed
     * server-side, never trusted from the request.
     */
    public function bindUnbound(Request $request, $id, CardTerminalBindingService $bindings)
    {
        $validated = $request->validate([
            'terminal_ids' => ['required', 'array', 'min:1', 'max:200'],
            'terminal_ids.*' => ['string', 'max:64'],
        ]);

        $report = CardSettlementReport::findOrFail($id);
        abort_if($report->status === CardSettlementReport::STATUS_MATCHING, 422, 'Matching is still running.');

        $chosen = collect($validated['terminal_ids'])->map(fn ($t) => (string) $t)->all();
        $targets = $this->unboundTerminals($report)
            ->whereIn('terminal_id', $chosen)
            ->filter(fn ($t) => $t['suggested_vend_code'] !== null);

        $bound = [];
        $skipped = [];

        foreach ($targets as $t) {
            $terminalId = $t['terminal_id'];

            $vends = \App\Models\Vend::withoutGlobalScopes()->where('code', $t['suggested_vend_code'])->get();
            if ($vends->count() !== 1) {
                $skipped[] = "{$terminalId}: machine {$t['suggested_vend_code']} ".($vends->isEmpty() ? 'not found' : 'is not unique');

                continue;
            }
            $vend = $vends->first();

            // A new TID gets its Data Management row here, under the same
            // supplying company as the machine it lands on (mirrors the
            // import command), so it is editable from Settings afterwards.
            $unit = CardTerminalUnit::firstOrCreate(
                ['terminal_id' => $terminalId],
                ['card_terminal_id' => $vend->card_terminal_id, 'remarks' => "Created from settlement report #{$report->id}"]
            );

            $before = $bindings->currentUnitFor($vend)?->terminal_id;
            try {
                $changed = $bindings->assignToVend($vend, $unit, $t['from_date']);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $skipped[] = "{$terminalId}: ".collect($e->errors())->flatten()->first();

                continue;
            }
            if (! $changed) {
                $skipped[] = "{$terminalId}: already the current terminal on {$vend->code}";

                continue;
            }

            UserLogger::recordChanges($vend, [
                'card_terminal_unit_id' => [$before, $unit->terminal_id],
            ]);
            $bound[] = "{$terminalId} → {$vend->code} from {$t['from_date']}";
        }

        if ($bound) {
            MatchCardSettlementReport::dispatch($report->id);
        }

        $message = $bound
            ? 'Bound '.count($bound).' terminal(s) — rematch queued: '.implode('; ', $bound).'.'
            : 'No binding was created.';
        if ($skipped) {
            $message .= ' Left alone: '.implode('; ', $skipped).'.';
        }

        return back()->with('message', $message);
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
            // vend_transactions.id (the #123456 the page shows) OR the machine's
            // Order ID from Sales Transactions (17–19 digits) — see resolveSale().
            'vend_transaction_id' => ['required', 'regex:/^\d{1,20}$/'],
        ]);

        $report = CardSettlementReport::findOrFail($id);
        $row = $report->rows()->findOrFail($rowId);

        // A reversal line never claims a sale itself (the UNIQUE claim stays with
        // the purchase line it undoes); resolving it would steal that claim and
        // Sync would then stamp and count the reversal as a purchase.
        abort_if($row->is_reversal, 422, 'A reversal line is paired with its purchase line, not resolved to a sale.');
        abort_unless(in_array($row->status, [
            CardSettlementRow::STATUS_UNMATCHED,
            CardSettlementRow::STATUS_AMBIGUOUS,
            CardSettlementRow::STATUS_MATCHED,
        ]), 422, 'Row cannot be resolved.');

        $txn = $this->resolveSale($validated['vend_transaction_id'], $row->vend_id);
        if (! $txn) {
            return back()->withErrors(['vend_transaction_id' => 'No sale found for that Txn ID / Order ID.']);
        }
        if ($txn === 'ambiguous') {
            return back()->withErrors(['vend_transaction_id' => 'That Order ID exists on more than one machine — use the numeric Txn ID (#…) instead.']);
        }

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
            // The line follows the sale it was assigned to — a cross-machine
            // pick (wrong binding) must not keep showing the old machine.
            'vend_id' => $txn->vend_id,
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

        // Ignoring a paired reversal must release its purchase line, or that
        // sale would still be marked refunded on Sync with no reversal behind it.
        if ($row->is_reversal && $row->reverses_row_id) {
            CardSettlementRow::where('id', $row->reverses_row_id)
                ->where('reversed_by_row_id', $row->id)
                ->update(['reversed_by_row_id' => null]);
        }

        $row->update([
            'status' => CardSettlementRow::STATUS_IGNORED,
            'matched_vend_transaction_id' => null,
            'match_time_delta' => null,
            'reverses_row_id' => null,
            'resolution_note' => $request->input('note') ?: 'Ignored by user',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
        $report->refreshCounts();

        return back()->with('message', 'Row ignored.');
    }

    /**
     * The "Txn ID" box accepts either identifier a user can see: the sale's
     * numeric id (as the page prints it, #5963112) or the machine's Order ID
     * as shown on Sales Transactions (yyyymmddHHMMSS + trade counter, 17–19
     * digits). Order IDs are unique per machine, not globally, so a bound
     * machine narrows the lookup; without one, a duplicate is refused.
     *
     * @return VendTransaction|'ambiguous'|null
     */
    protected function resolveSale(string $identifier, ?int $vendId)
    {
        if (strlen($identifier) < 12) {
            return VendTransaction::withoutGlobalScopes()->find((int) $identifier);
        }

        $matches = VendTransaction::withoutGlobalScopes()
            ->where('order_id', $identifier)
            ->when($vendId, fn ($q) => $q->where('vend_id', $vendId))
            ->limit(2)
            ->get();

        if ($matches->isEmpty() && $vendId) {
            // Wrong-binding case: the sale may be on another machine.
            $matches = VendTransaction::withoutGlobalScopes()->where('order_id', $identifier)->limit(2)->get();
        }

        if ($matches->count() > 1) {
            return 'ambiguous';
        }

        return $matches->first();
    }

    /** Ignore many query lines at once (checkbox selection on the report page). */
    public function ignoreRows(Request $request, $id)
    {
        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'row_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $report = CardSettlementReport::findOrFail($id);

        // Only open queries can be batch-ignored — never a matched line (that
        // would silently drop a settled sale) or a paired reversal.
        $count = $report->rows()
            ->whereIn('id', $validated['row_ids'])
            ->whereIn('status', [CardSettlementRow::STATUS_UNMATCHED, CardSettlementRow::STATUS_AMBIGUOUS])
            ->update([
                'status' => CardSettlementRow::STATUS_IGNORED,
                'matched_vend_transaction_id' => null,
                'match_time_delta' => null,
                'resolution_note' => $validated['note'] ?? 'Ignored by user (batch)',
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
        $report->refreshCounts();

        return back()->with('message', "Ignored {$count} line(s).");
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
                Storage::disk($report->fileDisk())->delete($attachment->local_url);
            }
            $attachment->delete();
        }
        $report->delete(); // rows cascade

        return redirect()->route('card-settlements')->with('message', 'Report deleted.');
    }

    /** The uploaded file, streamed from private storage to a permitted viewer. */
    public function download($id)
    {
        $report = CardSettlementReport::with('attachment')->findOrFail($id);
        $attachment = $report->attachment;
        abort_unless($attachment && $attachment->local_url, 404);

        $disk = Storage::disk($report->fileDisk());
        abort_unless($disk->exists($attachment->local_url), 404);

        return $disk->download($attachment->local_url, $report->original_filename);
    }
}
