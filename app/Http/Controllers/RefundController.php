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
        // Sidebar "Refund Requests" unread badge: clear it on a genuine page
        // visit (not an in-page filter/search re-fetch or partial reload) by
        // sliding this user's last_viewed_at to now. The badge counts tickets
        // that arrived since. See NoteNotificationService.
        $authUser = auth()->user();
        if ($authUser && !$request->boolean('searched') && !$request->hasHeader('X-Inertia-Partial-Data')) {
            app(\App\Services\NoteNotificationService::class)
                ->markViewed($authUser, \App\Services\NoteNotificationService::PAGE_REFUNDS);
        }

        // Filtered query + resolved date/status filters (shared with export()).
        [$query, $dateFrom, $dateTo, $statusSel] = $this->buildFilteredQuery($request);

        // Per-page selector (same options as Vend/CustomerIndex): 25/50/100/200/500/All.
        // "All" paginates the full filtered set; anything unrecognised falls back to 25.
        $allowedPerPage = [25, 50, 100, 200, 500];
        $rawPerPage = $request->input('numberPerPage', 50);
        if (in_array($rawPerPage, ['All', 'all'], true)) {
            $perPage = max(1, (clone $query)->count());
        } else {
            $perPage = in_array((int) $rawPerPage, $allowedPerPage, true) ? (int) $rawPerPage : 50;
        }
        $page = $query->paginate($perPage)->withQueryString();

        // Enrich the page's rows (batched, no N+1) and keep the paginator shape.
        $rowsById = $this->buildRows(collect($page->items()));
        $tickets = $page->through(fn (RefundTicket $t) => $rowsById[$t->id]);

        // counts are over all tickets (unfiltered) so the chips always show true totals
        $counts = RefundTicket::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->toArray();
        // Dropped is a pseudo-status (Rejected + is_dropped). Give it its own chip
        // count and subtract it from Rejected so the two chips don't double-count.
        $droppedCount = (int) RefundTicket::where('is_dropped', true)->count();
        $counts['rejected'] = max(0, (int) ($counts['rejected'] ?? 0) - $droppedCount);
        $counts['dropped'] = $droppedCount;
        // "In settlement" tickets are stored as `scheduled` but shown under Approved,
        // so fold their count into the Approved chip. `insufficient_info` keeps its own
        // count (it has its own chip).
        $counts['approved'] = (int) ($counts['approved'] ?? 0) + (int) ($counts['scheduled'] ?? 0);
        unset($counts['scheduled']);

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
                'site_name' => $request->input('site_name', ''),
                'channel' => $request->input('channel', ''),
                'product' => $request->input('product', ''),
                'paid_min' => $request->input('paid_min', ''),
                'paid_max' => $request->input('paid_max', ''),
                'repeat' => $request->input('repeat', ''),
                'product_drop_sensor' => $request->input('product_drop_sensor', ''),
                'error_code' => $request->input('error_code', ''),
                'settlement_ref' => $request->input('settlement_ref', ''),
                'refund_done' => $request->input('refund_done', ''),
                'sent_settlement' => $request->input('sent_settlement', ''),
                'numberPerPage' => $rawPerPage,
            ],
            'statuses' => $this->statusLabels(),
            'banks' => \App\Services\Refund\BankTemplates\BankTemplateRegistry::all(),
            'defaultBank' => config('refund.default_bank', 'cimb'),
        ]);
    }

    /**
     * Build the filtered ticket query shared by the list and the Excel export,
     * so both always apply the exact same status / date / method / search rules.
     * Returns [Builder $query, string $dateFrom, string $dateTo, array $statusSel].
     */
    protected function buildFilteredQuery(Request $request): array
    {
        $allStatuses = array_keys($this->statusLabels());
        // default view: everything except already-completed ("refunded") tickets
        $defaultStatuses = array_values(array_diff($allStatuses, [RefundTicket::STATUS_COMPLETED]));

        // default date window = last 4 weeks up to today (first load); user can override/clear
        $dateFrom = $request->has('date_from') ? $request->input('date_from') : now()->subWeeks(4)->toDateString();
        $dateTo = $request->has('date_to') ? $request->input('date_to') : now()->toDateString();

        $explicitStatus = $request->has('status');
        $statusSel = $explicitStatus
            ? array_values(array_filter((array) $request->input('status')))
            : $defaultStatuses;
        $applyStatus = !empty($statusSel) && !in_array('all', $statusSel, true);

        // "Dropped" is a pseudo-status (dropped tickets are Rejected + is_dropped),
        // so it filters on the flag, not the status column. Strip it out of the real
        // status list; when the user EXPLICITLY picks statuses without Dropped, keep
        // dropped tickets out of the real buckets so "Rejected" shows only truly
        // rejected tickets (the default view still shows them, unchanged).
        $wantDropped = in_array('dropped', $statusSel, true);
        $realStatuses = array_values(array_diff($statusSel, ['dropped']));

        // A ticket pushed into a settlement is stored as `scheduled`, but the UI
        // keeps it under "Approved" (no separate status). So the Approved bucket
        // also matches scheduled tickets; the "Is Sent to Settlement?" filter below
        // distinguishes sent (scheduled) from not-yet-sent (approved).
        if (in_array(RefundTicket::STATUS_APPROVED, $realStatuses, true)) {
            $realStatuses[] = RefundTicket::STATUS_SCHEDULED;
        }
        // `insufficient_info` is its own status/chip now, and is part of the default
        // view (all keys except Completed), so it needs no special folding here.

        $query = RefundTicket::query()
            ->when($applyStatus, function ($q) use ($realStatuses, $wantDropped, $explicitStatus) {
                $q->where(function ($w) use ($realStatuses, $wantDropped, $explicitStatus) {
                    if (!empty($realStatuses)) {
                        $w->orWhere(function ($x) use ($realStatuses, $wantDropped, $explicitStatus) {
                            $x->whereIn('status', $realStatuses);
                            if ($explicitStatus && !$wantDropped) {
                                $x->where('is_dropped', false);
                            }
                        });
                    }
                    if ($wantDropped) {
                        $w->orWhere('is_dropped', true);
                    }
                });
            })
            ->when($request->refund_method, fn ($q, $s) => $q->where('refund_method', $s))
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($request->search, function ($q, $s) {
                // Digits-only form of the term so a plain "81339134" also matches a
                // PayNow / phone number stored with spaces, dashes, or a +65 prefix.
                $digits = preg_replace('/\D+/', '', $s);
                $q->where(function ($w) use ($s, $digits) {
                    $w->where('reference', 'like', "%{$s}%")
                        ->orWhere('vend_code', 'like', "%{$s}%")
                        ->orWhere('contact_email', 'like', "%{$s}%")
                        ->orWhere('payout_destination', 'like', "%{$s}%")
                        ->orWhere('contact_phone', 'like', "%{$s}%");
                    // PayNow number (payout_destination) + requester phone, matched
                    // ignoring spaces / dashes / plus so formatting can't block a hit.
                    if ($digits !== '') {
                        $w->orWhereRaw("REPLACE(REPLACE(REPLACE(payout_destination, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$digits}%"])
                            ->orWhereRaw("REPLACE(REPLACE(REPLACE(contact_phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$digits}%"]);
                    }
                });
            })
            // --- Site name: match the ticket's machine's site (vend -> customer.name).
            // Raw builder subquery so operator global scopes don't drop rows here.
            ->when($request->site_name, function ($q, $s) {
                $q->whereIn('vend_id', function ($sub) use ($s) {
                    $sub->select('vends.id')->from('vends')
                        ->join('customers', 'customers.id', '=', 'vends.customer_id')
                        ->where('customers.name', 'like', "%{$s}%");
                });
            })
            // --- Channel / Product: claimed refund line items on the ticket.
            ->when($request->channel, fn ($q, $s) => $q->whereHas('items', fn ($i) => $i->where('vend_channel_code', 'like', "%{$s}%")))
            ->when($request->product, fn ($q, $s) => $q->whereHas('items', fn ($i) => $i->where('product_name', 'like', "%{$s}%")))
            // --- Paid amount range: the matched transaction / gateway charge amount
            // (cents), read from either source so it matches the "Paid Amt" column.
            ->when($request->filled('paid_min') || $request->filled('paid_max'), function ($q) use ($request) {
                $min = $request->filled('paid_min') ? (int) round((float) $request->paid_min * 100) : null;
                $max = $request->filled('paid_max') ? (int) round((float) $request->paid_max * 100) : null;
                $q->where(function ($w) use ($min, $max) {
                    $w->whereExists(function ($sub) use ($min, $max) {
                        $sub->selectRaw('1')->from('vend_transactions')
                            ->whereColumn('vend_transactions.id', 'refund_tickets.vend_transaction_id')
                            ->when($min !== null, fn ($x) => $x->where('amount', '>=', $min))
                            ->when($max !== null, fn ($x) => $x->where('amount', '<=', $max));
                    })->orWhereExists(function ($sub) use ($min, $max) {
                        $sub->selectRaw('1')->from('payment_gateway_logs')
                            ->whereColumn('payment_gateway_logs.id', 'refund_tickets.payment_gateway_log_id')
                            ->when($min !== null, fn ($x) => $x->where('amount', '>=', $min))
                            ->when($max !== null, fn ($x) => $x->where('amount', '<=', $max));
                    });
                });
            })
            // --- New / Repeat: the stored resubmission flag on the ticket.
            ->when(in_array($request->repeat, ['new', 'repeat'], true), fn ($q) => $q->where('is_repeat', $request->repeat === 'repeat'))
            // --- Product drop / exit sensor state FROZEN on the matched transaction.
            ->when($request->product_drop_sensor, function ($q, $sel) {
                if ($sel === 'enabled' || $sel === 'disabled') {
                    $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('vend_transactions')
                        ->whereColumn('vend_transactions.id', 'refund_tickets.vend_transaction_id')
                        ->where('product_drop_sensor', $sel === 'enabled' ? 1 : 0));
                } elseif ($sel === 'unknown') {
                    // No matched transaction, or one with no recorded sensor reading.
                    $q->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('vend_transactions')
                        ->whereColumn('vend_transactions.id', 'refund_tickets.vend_transaction_id')
                        ->whereNotNull('product_drop_sensor'));
                }
            })
            // --- Error code: a channel error captured on a claimed line item.
            ->when($request->error_code, fn ($q, $s) => $q->whereHas('items', fn ($i) => $i
                ->where('had_channel_error', true)
                ->where('vend_channel_error_code', 'like', "%{$s}%")))
            // --- Settlement / payout batch reference.
            ->when($request->settlement_ref, fn ($q, $s) => $q->whereHas('batch', fn ($b) => $b->where('reference', 'like', "%{$s}%")))
            // --- Refund done: completed vs in-progress (approved/scheduled) vs not started.
            ->when($request->refund_done, function ($q, $sel) {
                if ($sel === 'completed') {
                    $q->where('status', RefundTicket::STATUS_COMPLETED);
                } elseif ($sel === 'in_progress') {
                    $q->whereIn('status', [RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED]);
                } elseif ($sel === 'insufficient_info') {
                    $q->where('status', RefundTicket::STATUS_INSUFFICIENT_INFO);
                } elseif ($sel === 'not_started') {
                    $q->whereNotIn('status', [RefundTicket::STATUS_COMPLETED, RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_INSUFFICIENT_INFO]);
                }
            })
            // --- Is Sent to Settlement? A pushed ticket is stored as `scheduled`.
            ->when(in_array($request->sent_settlement, ['yes', 'no'], true), function ($q) use ($request) {
                // Insufficient-info tickets stay in their settlement (payout_batch_id kept),
                // so they count as "sent" too.
                $request->sent_settlement === 'yes'
                    ? $q->whereIn('status', [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_INSUFFICIENT_INFO])
                    : $q->whereNotIn('status', [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_INSUFFICIENT_INFO]);
            })
            ->orderByDesc('created_at'); // latest on top

        return [$query, $dateFrom, $dateTo, $statusSel];
    }

    /**
     * Enrich a collection of RefundTickets into display rows (the same toRow()
     * shape the list uses), batching every lookup so there is no N+1. Returns an
     * array keyed by ticket id. Shared by the list page and the Excel export.
     */
    protected function buildRows(\Illuminate\Support\Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        // Source transaction / gateway log / export batch, all withoutGlobalScopes
        // because Vend/VendTransaction/Customer carry operator scopes that would
        // drop rows in an admin/public context.
        $txns = \App\Models\VendTransaction::withoutGlobalScopes()->with(['paymentMethod', 'vendPrefix'])
            ->whereIn('id', $rows->pluck('vend_transaction_id')->filter()->unique())
            ->get()->keyBy('id');
        $logIds = $rows->pluck('payment_gateway_log_id')->filter()
            ->merge($txns->pluck('payment_gateway_log_id')->filter())
            ->unique();
        $logs = \App\Models\PaymentGatewayLog::query()
            ->whereIn('id', $logIds)
            ->get()->keyBy('id');
        $batches = RefundPayoutBatch::query()
            ->whereIn('id', $rows->pluck('payout_batch_id')->filter()->unique())
            ->get()->keyBy('id');

        $vends = \App\Models\Vend::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('vend_id')->filter()->unique())
            ->get(['id', 'customer_id'])->keyBy('id');
        $siteNames = \App\Models\Customer::withoutGlobalScopes()
            ->whereIn('id', $vends->pluck('customer_id')->filter()->unique())
            ->pluck('name', 'id');

        $payNowDup = $this->payNowReuseFlags($rows);
        $selfCheck = $this->selfCheckData($rows);

        $itemErrors = \App\Models\RefundTicketItem::whereIn('refund_ticket_id', $rows->pluck('id'))
            ->where('had_channel_error', true)
            ->get(['refund_ticket_id', 'vend_channel_error_code', 'channel_error_desc'])
            ->groupBy('refund_ticket_id')
            ->map->first();

        $claimedItems = \App\Models\RefundTicketItem::whereIn('refund_ticket_id', $rows->pluck('id'))
            ->get(['refund_ticket_id', 'vend_channel_code', 'product_name'])
            ->groupBy('refund_ticket_id');

        // Who did each Refund-Progress stage, and when — read from the audit trail so
        // the Validation / Send-to-Settlement / Refund-Done columns can show the admin
        // (actor) + timestamp beside the status. One batched query; the latest entry
        // per stage wins (ordered ascending, so a later row overwrites an earlier one).
        $stageActions = [
            'validation'   => ['verified', 'rejected', 'pending', 'status_override', 'dropped', 'undropped'],
            'settlement'   => ['scheduled', 'returned_to_pool'],
            'insufficient' => ['insufficient_info'],
            'done'         => ['completed'],
        ];
        $actionStage = [];
        foreach ($stageActions as $stage => $acts) {
            foreach ($acts as $a) {
                $actionStage[$a] = $stage;
            }
        }
        $stageActors = [];
        \App\Models\RefundTicketLog::whereIn('refund_ticket_id', $rows->pluck('id'))
            ->whereIn('action', array_keys($actionStage))
            ->orderBy('created_at')
            ->get(['refund_ticket_id', 'actor_label', 'action', 'created_at'])
            ->each(function ($lg) use (&$stageActors, $actionStage) {
                $stage = $actionStage[$lg->action] ?? null;
                if (! $stage) {
                    return;
                }
                // 'returned_to_pool' means the ticket left settlement — clear the stamp.
                if ($lg->action === 'returned_to_pool') {
                    unset($stageActors[$lg->refund_ticket_id]['settlement']);

                    return;
                }
                $stageActors[$lg->refund_ticket_id][$stage] = [
                    'name' => $lg->actor_label,
                    'at' => optional($lg->created_at)->format('ymd h:i a'),
                ];
            });

        return $rows->mapWithKeys(function (RefundTicket $t) use ($txns, $logs, $batches, $vends, $siteNames, $payNowDup, $selfCheck, $itemErrors, $claimedItems, $stageActors) {
            $txn = $t->vend_transaction_id ? $txns->get($t->vend_transaction_id) : null;
            $log = $t->payment_gateway_log_id
                ? $logs->get($t->payment_gateway_log_id)
                : ($txn?->payment_gateway_log_id ? $logs->get($txn->payment_gateway_log_id) : null);
            $errItem = $itemErrors->get($t->id);

            return [$t->id => $this->toRow(
                $t,
                $txn,
                $log,
                $t->payout_batch_id ? $batches->get($t->payout_batch_id) : null,
                $t->vend_id && $vends->get($t->vend_id) ? $siteNames->get($vends->get($t->vend_id)->customer_id) : null,
                $payNowDup[$t->id] ?? false,
                [
                    'machine_rf_24h' => $selfCheck['rf24h'][$t->id] ?? null,
                    'repeat_flag' => $selfCheck['repeat'][$t->id] ?? false,
                    'repeat_ref' => $selfCheck['repeat_ref'][$t->id] ?? null,
                    'error_code' => $errItem?->vend_channel_error_code,
                    'error_desc' => $errItem?->channel_error_desc,
                    'affected_items' => ($claimedItems->get($t->id) ?? collect())
                        ->map(fn ($i) => [
                            'channel' => $i->vend_channel_code,
                            'product_name' => $i->product_name,
                        ])->values()->all(),
                    'validation_actor' => $stageActors[$t->id]['validation'] ?? null,
                    'settlement_actor' => $stageActors[$t->id]['settlement'] ?? null,
                    'insufficient_actor' => $stageActors[$t->id]['insufficient'] ?? null,
                    'done_actor' => $stageActors[$t->id]['done'] ?? null,
                ],
            )];
        })->all();
    }

    /**
     * Export the current (filtered) refund list to Excel — every table column and
     * its data. Uses FastExcel streaming for a lean, fast download and reuses the
     * exact same filter + row-building code as the list, so the sheet matches the
     * screen. Chunked so even an "All" export stays memory-safe.
     */
    public function export(Request $request)
    {
        [$query] = $this->buildFilteredQuery($request);
        $statusLabels = $this->statusLabels();

        // Stream in offset-paged chunks of 500: each chunk is a real Collection so
        // buildRows can batch-enrich it (no N+1) without re-running a lazy source.
        // Only one chunk of models/rows is held in memory at a time.
        $generator = function () use ($query) {
            $pageNo = 1;
            do {
                $chunk = (clone $query)->forPage($pageNo, 500)->get();
                foreach ($this->buildRows($chunk) as $row) {
                    yield $row;
                }
                $pageNo++;
            } while ($chunk->count() === 500);
        };

        $filename = 'Refund_Requests_' . now()->format('Ymd_His') . '.xlsx';

        return (new \Rap2hpoutre\FastExcel\FastExcel($generator()))->download($filename, function (array $r) use ($statusLabels) {
            // Money columns are built as number_format() strings for the on-screen
            // list (and can carry a thousands "," separator). For the spreadsheet we
            // want real numeric cells so Excel can sum/sort them, so cast back to
            // float (strip the separator first); null/blank stays empty (not 0).
            $money = fn ($v) => ($v === null || $v === '') ? null : (float) str_replace(',', '', (string) $v);
            $channels = collect($r['affected_items'] ?? [])->pluck('channel')->filter()->implode(', ');
            $products = collect($r['affected_items'] ?? [])->pluck('product_name')->filter()->implode(', ');
            // `scheduled` (pushed into a settlement) and `insufficient_info` (parked in
            // one) are both shown under Approved for the Status column — the special
            // state surfaces only in the "Refund Done?" column, mirroring the screen.
            $statusKey = in_array($r['status'], [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_INSUFFICIENT_INFO], true) ? RefundTicket::STATUS_APPROVED : $r['status'];
            $statusText = $r['is_dropped'] ? 'Dropped' : ($statusLabels[$statusKey] ?? $statusKey);
            $sensor = is_null($r['product_drop_sensor']) ? '' : ($r['product_drop_sensor'] ? 'Enabled' : 'Disabled');
            $doneText = $r['status'] === RefundTicket::STATUS_COMPLETED
                ? 'Completed'
                : ($r['status'] === RefundTicket::STATUS_INSUFFICIENT_INFO
                    ? 'Insufficient Info'
                    : (in_array($r['status'], [RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED], true) ? 'In progress' : ''));

            return [
                'Refund ID' => $r['reference'],
                'Machine ID' => $r['vend_code'],
                'Site Name' => $r['site_name'],
                'RF Submitted' => $r['created_at'],
                'Transaction Datetime' => $r['txn_datetime'],
                'Txn Delta' => $r['txn_delta'],
                'Channel' => $channels,
                'Product Name' => $products,
                'Paid Amount' => $money($r['paid_amount']),
                'Pay Method' => $r['pay_method'],
                'Pay Provider' => $r['pay_provider'],
                // Mirror the screen: show the effective payout — the admin's final
                // amount when overridden (incl. admin-set tickets whose original claim
                // is 0), otherwise the original claim. Prevents overridden/admin-set
                // rows exporting as 0.00.
                'Refund Amount' => $money($r['final_refund_overridden'] ? $r['final_refund_amount'] : $r['amount']),
                'Refund Method' => $r['refund_method'],
                'PayNow / PayPal Email' => $r['payout_destination_any'],
                'Machine L24h # of RF' => $r['machine_rf_24h'],
                'New / Repeat' => $r['repeat_flag'] ? 'Repeat' : 'New',
                'Repeat Ref' => $r['repeat_ref'],
                'Prod Exit Sensor' => $sensor,
                'Error Code' => $r['error_code'],
                'Error Description' => $r['error_desc'],
                'Status' => $statusText,
                'Channel Error?' => $r['had_channel_error'] ? 'Yes' : 'No',
                'Manual Match?' => $r['is_manual'] ? 'Yes' : 'No',
                'Already Refunded?' => $r['already_refunded'] ? 'Yes' : 'No',
                'Send to Settlement' => $r['batch']['reference'] ?? '',
                'Refund Done?' => $doneText,
                'Completed At' => $r['completed_at'],
            ];
        });
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

        // Prev / next ticket navigation for the detail page, matching the index
        // ordering (created_at DESC, id DESC — newest on top). "Previous" is the
        // row above the current ticket in that list (a newer request); "next" is
        // the row below (an older request). Global across all tickets (no filter
        // state), so admins can page straight through the whole queue. Only id +
        // reference are selected — enough to build the link and its label.
        $prev = RefundTicket::query()
            ->where(function ($q) use ($ticket) {
                $q->where('created_at', '>', $ticket->created_at)
                    ->orWhere(fn ($w) => $w->where('created_at', $ticket->created_at)->where('id', '>', $ticket->id));
            })
            ->orderBy('created_at')->orderBy('id')
            ->first(['id', 'reference']);
        $next = RefundTicket::query()
            ->where(function ($q) use ($ticket) {
                $q->where('created_at', '<', $ticket->created_at)
                    ->orWhere(fn ($w) => $w->where('created_at', $ticket->created_at)->where('id', '<', $ticket->id));
            })
            ->orderByDesc('created_at')->orderByDesc('id')
            ->first(['id', 'reference']);

        return Inertia::render('Refund/Show', [
            'ticket' => $this->toDetail($ticket),
            'prevTicket' => $prev ? ['id' => $prev->id, 'reference' => $prev->reference] : null,
            'nextTicket' => $next ? ['id' => $next->id, 'reference' => $next->reference] : null,
            'emailTemplates' => [
                RefundEmailService::T_RECEIVED => 'Request received (acknowledgement)',
                RefundEmailService::T_APPROVED => 'Refund approved',
                RefundEmailService::T_AUTO_REFUND => 'Auto-refund already triggered',
                RefundEmailService::T_CANCELLED_NO_CHARGE => 'Transaction cancelled (no charge)',
                RefundEmailService::T_INFO_REQUIRED => 'Additional info required (PayNow)',
                RefundEmailService::T_IN_PROGRESS => 'Refund in progress',
                RefundEmailService::T_COMPLETED => 'Refund completed',
            ],
            // Interpolated per-ticket previews (real {name}/{reference} filled in),
            // keyed by template so the action-button "Preview" popups show exactly
            // what would be sent for THIS ticket.
            'emailTemplateContents' => collect([
                RefundEmailService::T_RECEIVED,
                RefundEmailService::T_APPROVED,
                RefundEmailService::T_AUTO_REFUND,
                RefundEmailService::T_CANCELLED_NO_CHARGE,
                RefundEmailService::T_INFO_REQUIRED,
                RefundEmailService::T_IN_PROGRESS,
                RefundEmailService::T_COMPLETED,
            ])->mapWithKeys(fn ($key) => [$key => $this->email->preview($ticket, $key)])
              ->filter()
              ->all(),
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
            RefundTicket::STATUS_PENDING,
            RefundTicket::STATUS_PENDING_TRANSFER_INFO,
        ], 'verify');

        // Manual submissions carry no matched transaction, so the claimed amount is
        // 0 until an admin records the Final Refund Amount. Block approval until that
        // amount is set and greater than 0 — otherwise the ticket would pay out $0.
        // Uses the same frozen is_manual verdict the ticket page reads.
        $sv = $ticket->system_validation_json ?? [];
        $isManualClaim = array_key_exists('is_manual', $sv) ? (bool) $sv['is_manual'] : (bool) $ticket->is_manual;
        if ($isManualClaim && ($ticket->final_refund_amount_cents === null || (int) $ticket->final_refund_amount_cents <= 0)) {
            return back()->withErrors([
                'ticket' => 'Please set a Final Refund Amount greater than $0 before approving this manual submission.',
            ]);
        }

        // A ticket the system already auto-refunded (third validation icon crossed)
        // can never be approved for a manual payout — approving would pay it twice.
        // Ops must Reject (email) or Drop it instead. This mirrors the hidden
        // Approve button on the ticket page and enforces it server-side.
        if ($ticket->isAlreadyRefunded()) {
            return back()->withErrors([
                'ticket' => 'Cannot approve — this transaction was already auto-refunded by the system. Use “Reject → No charge / auto-refund” or Drop instead.',
            ]);
        }

        // Manager-approval step removed: verifying a claim now moves it straight
        // to the payout-ready (Approved) gate. We carry over the double-refund
        // guard that used to live on approve() so we still block paying the same
        // transaction twice.
        if ($conflict = $ticket->conflictingRefund()) {
            return back()->withErrors([
                'ticket' => "Cannot verify — this transaction is already being refunded under {$conflict->reference} (" . ($this->statusLabels()[$conflict->status] ?? $conflict->status) . ').',
            ]);
        }

        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_APPROVED,
            'ops_verified_by' => auth()->id(),
            'ops_verified_at' => now(),
        ]);
        // Approving the claim emails the customer that the refund is approved and
        // will be paid to their PayNow/PayPal within 5 working days (replies onto
        // the acknowledgement thread). Gated by REFUND_EMAIL_ENABLED, self-guarded.
        // The email is recorded as its own "Email sent" audit line, so the action
        // note doesn't repeat it.
        $this->email->queue($ticket, RefundEmailService::T_APPROVED);
        $this->tickets->log($ticket, 'verified', $from, $ticket->status, 'Ops verified claim (approved)', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    /**
     * "Reject → No charge / auto-refund" close-out. The charge was already
     * auto-refunded by the processor (or no charge was ever captured), so no
     * manual payout is owed. This is an admin REJECTION of the payout request:
     * the ticket is set to Rejected (red on the list, counted under the Rejected
     * chip) so it reads the same way the admin clicked it. We still email the
     * customer that it's been handled and keep auto_refund_detected as the reason.
     * This NEVER initiates a new refund. Allowed from any live state (including
     * auto_resolved, e.g. the Omise job already flipped the status but no longer
     * emails on its own — the admin then closes it out from here).
     */
    public function resolveNoCharge(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_PENDING,
            RefundTicket::STATUS_VERIFIED,
            RefundTicket::STATUS_APPROVED,
            RefundTicket::STATUS_PENDING_TRANSFER_INFO,
            RefundTicket::STATUS_AUTO_RESOLVED,
        ], 'resolve');

        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_REJECTED,
            'auto_refund_detected' => true,
        ]);
        // The customer notice is auto-generated and recorded as its own "Email
        // sent" line on the audit trail, so the action note doesn't repeat it.
        $this->email->queue($ticket, RefundEmailService::T_AUTO_REFUND);
        $this->tickets->log($ticket, 'rejected', $from, $ticket->status, 'Rejected — no charge / auto-refund already handled, no payout required', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    /**
     * "Ignore / drop case" (e.g. a double submission). Closes the ticket as
     * Rejected but flags it dropped so the list strikes a line through it instead
     * of deleting it. No customer email is sent.
     */
    public function drop(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_PENDING,
            RefundTicket::STATUS_VERIFIED,
            RefundTicket::STATUS_APPROVED,
            RefundTicket::STATUS_PENDING_TRANSFER_INFO,
            RefundTicket::STATUS_AUTO_RESOLVED,
        ], 'drop');

        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_REJECTED,
            'is_dropped' => true,
            'ops_remarks' => $data['remarks'] ?? $ticket->ops_remarks,
        ]);
        $this->tickets->log($ticket, 'dropped', $from, $ticket->status, $data['remarks'] ?: 'Dropped / closed (e.g. double submission) — no email', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    /**
     * "Un-drop" — the ONLY reversible outcome. Dropping is a purely internal
     * close-out (no customer email was sent), so it can safely be rewound: the
     * ticket returns to the queue in the exact state it held immediately before
     * it was dropped (read off the drop audit line; falls back to Received).
     * Approve / Reject email the customer, so they are deliberately NOT undoable.
     */
    public function undrop(RefundTicket $ticket)
    {
        if (!$ticket->is_dropped) {
            return back()->withErrors(['ticket' => 'This refund is not dropped, so there is nothing to un-drop.']);
        }

        // logs() is ordered newest-first, so first() is the most recent drop.
        $dropLog = $ticket->logs()->where('action', 'dropped')->first();
        $restoreTo = $dropLog?->from_status ?: RefundTicket::STATUS_SUBMITTED;

        $from = $ticket->status;
        $ticket->update([
            'status' => $restoreTo,
            'is_dropped' => false,
        ]);
        $this->tickets->log($ticket, 'undropped', $from, $restoreTo, 'Un-dropped — restored to ' . ($this->statusLabels()[$restoreTo] ?? $restoreTo) . ' (no email)', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    public function reject(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_PENDING,
            RefundTicket::STATUS_VERIFIED,
            RefundTicket::STATUS_APPROVED,
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

    /**
     * "Pending - manual follow-up": Ops is emailing the customer by hand to get
     * more info. Distinct from pending_transfer_info (which auto-emails T_INFO_REQUIRED);
     * this sends NO system email. Only reachable from Received; Approve / Reject /
     * Drop stay usable from here (STATUS_PENDING is in their allowedFrom lists).
     */
    public function markPending(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
        ], 'mark pending');

        $from = $ticket->status;
        $ticket->update(['status' => RefundTicket::STATUS_PENDING]);
        $this->tickets->log($ticket, 'pending', $from, $ticket->status, 'Marked Pending - manually following up with the customer for more info (no email)', auth()->user()?->name ?? 'Ops', auth()->id());

        return back();
    }

    public function requestInfo(RefundTicket $ticket)
    {
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_SUBMITTED,
            RefundTicket::STATUS_VERIFIED,
            RefundTicket::STATUS_APPROVED,
        ], 'request info on');
        $from = $ticket->status;
        $ticket->update(['status' => RefundTicket::STATUS_PENDING_TRANSFER_INFO]);
        $this->email->queue($ticket, RefundEmailService::T_INFO_REQUIRED);
        $this->tickets->log($ticket, 'request_info', $from, $ticket->status, 'Requested valid PayNow details', auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    public function complete(RefundTicket $ticket)
    {
        // Only a verified/approved (or already-scheduled-into-a-batch) ticket can
        // be marked paid — this is what stops 'complete' from skipping the
        // verification gate, and stops a completed ticket being completed twice.
        $this->guardTransition($ticket, [
            RefundTicket::STATUS_APPROVED,
            RefundTicket::STATUS_SCHEDULED,
            RefundTicket::STATUS_INSUFFICIENT_INFO,
        ], 'complete');
        $from = $ticket->status;
        $ticket->update([
            'status' => RefundTicket::STATUS_COMPLETED,
            'paid_at' => $ticket->paid_at ?? now(),
            'completed_at' => now(),
        ]);
        $this->email->queue($ticket, RefundEmailService::T_COMPLETED);
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
            ->whereIn('status', [RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_INSUFFICIENT_INFO])
            ->get();

        if ($tickets->isEmpty()) {
            return back()->withErrors(['batch' => 'None of the selected tickets can be completed (must be Approved, Scheduled, or Insufficient Info).']);
        }

        foreach ($tickets as $ticket) {
            $from = $ticket->status;
            $ticket->update([
                'status' => RefundTicket::STATUS_COMPLETED,
                'paid_at' => $ticket->paid_at ?? now(),
                'completed_at' => now(),
            ]);
            $this->email->queue($ticket, RefundEmailService::T_COMPLETED);
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

    /**
     * Record the admin's FINAL refund amount (and optional remarks) — the amount we
     * will actually pay out, which may differ from the customer's keyed-in claim
     * (e.g. they entered $5 when only $3 is claimable). The original claim is left
     * untouched (still shown as "Refund Amount"); the payout path reads the
     * effective final ?? claimed amount. Editable up until the ticket is locked into
     * a settlement (Scheduled) or already paid (Completed).
     */
    public function updateFinalAmount(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate([
            'final_refund_amount' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'final_refund_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($ticket->status, [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_COMPLETED], true)) {
            return back()->withErrors([
                'final_refund_amount' => 'The refund amount can no longer be changed — this ticket is already ' . ($this->statusLabels()[$ticket->status] ?? $ticket->status) . '.',
            ]);
        }

        $cents = (int) round($data['final_refund_amount'] * 100);
        $remarks = trim((string) ($data['final_refund_remarks'] ?? '')) ?: null;
        $prev = $ticket->payout_amount_cents;

        $ticket->update([
            'final_refund_amount_cents' => $cents,
            'final_refund_remarks' => $remarks,
        ]);

        $note = 'Final refund amount set to $' . number_format($cents / 100, 2)
            . ' (claim $' . number_format((int) $ticket->claimed_amount_cents / 100, 2) . ')'
            . ($prev !== $cents ? ' — was $' . number_format($prev / 100, 2) : '')
            . ($remarks ? ' — ' . $remarks : '');
        $this->tickets->log($ticket, 'final_amount', null, null, $note, auth()->user()?->name ?? 'Admin', auth()->id());

        return back()->with('success', 'Final refund amount saved.');
    }

    /**
     * "Overwritten status" — an admin FORCES the ticket's status (e.g. flip an
     * Approved ticket back to Rejected) from the Overwritten section. This is a
     * deliberate manual override that bypasses the normal workflow guards, so NO
     * email is sent to the customer. The forced status + the admin's remark are
     * written to the audit trail on ONE line.
     *
     * "Dropped" is a pseudo-status stored as Rejected + is_dropped, exactly like
     * the normal Drop action. Only the review states can be forced — a ticket
     * already locked into a settlement batch (Scheduled) or paid (Completed) is
     * refused so we never desync the settlement / payout exports.
     */
    public function overrideStatus(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:submitted,approved,rejected,dropped'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($ticket->status, [RefundTicket::STATUS_SCHEDULED, RefundTicket::STATUS_COMPLETED], true)) {
            return back()->withErrors([
                'override_status' => 'The status can no longer be overwritten — this ticket is already ' . ($this->statusLabels()[$ticket->status] ?? $ticket->status) . '.',
            ]);
        }

        $target = $data['status'];
        $remarks = trim((string) ($data['remarks'] ?? '')) ?: null;

        // "dropped" is a pseudo-status: stored as Rejected + is_dropped.
        $newStatus = $target === 'dropped' ? RefundTicket::STATUS_REJECTED : $target;
        $isDropped = $target === 'dropped';

        $from = $ticket->status;
        $fromLabel = $ticket->is_dropped ? 'Dropped' : ($this->statusLabels()[$from] ?? $from);
        $toLabel = $this->statusLabels()[$target] ?? $target; // 'dropped' has its own label

        $ticket->update([
            'status' => $newStatus,
            'is_dropped' => $isDropped,
            'status_override_remarks' => $remarks,
            'status_overridden_at' => now(),
        ]);

        // One audit line: the forced status change + the remark entered (no email).
        $note = 'Status overwritten: ' . $fromLabel . ' → ' . $toLabel . ' (no email)'
            . ($remarks ? ' — ' . $remarks : '');
        $this->tickets->log($ticket, 'status_override', $from, $newStatus, $note, auth()->user()?->name ?? 'Admin', auth()->id());

        return back()->with('success', 'Status overwritten to ' . $toLabel . '.');
    }

    public function sendEmail(Request $request, RefundTicket $ticket)
    {
        $data = $request->validate(['template' => ['required', 'string']]);
        // Delivery now runs on the queue; send() records its own "Email sent/queued"
        // audit line when the job runs, so this action line just notes the request.
        $this->email->queue($ticket, $data['template']);
        $this->tickets->log($ticket, 'email', null, null, 'Queued email: ' . $data['template'], auth()->user()?->name ?? 'Admin', auth()->id());

        return back();
    }

    /**
     * LOCAL-ONLY: render a workflow email's HTML in the browser so the design can
     * be previewed and iterated on without actually sending. Nothing is written or
     * dispatched. 404s outside the local environment so it can never be reached in
     * production. Pick the template with ?template=received|approved|completed|…
     * (defaults to the acknowledgement).
     */
    public function emailPreview(Request $request, RefundTicket $ticket)
    {
        abort_unless(app()->environment('local'), 404);

        $template = (string) $request->query('template', RefundEmailService::T_RECEIVED);
        $html = $this->email->previewHtml($ticket, $template);
        abort_if($html === null, 404, 'Unknown email template: ' . $template);

        return response($html);
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

    /**
     * For the PayNow tickets on this page, flag the ones whose PayNow number was
     * ALSO used on another refund ticket within 60 days (either direction) — a
     * repeat-number signal Ops should eyeball before paying. One batched query.
     *
     * @return array<int, bool>  ticket id => reused-within-60d
     */
    protected function payNowReuseFlags($rows): array
    {
        $flags = [];
        $payNow = collect($rows)->filter(
            fn ($t) => $t->refund_method === RefundTicket::METHOD_PAYNOW && filled($t->payout_destination)
        );
        if ($payNow->isEmpty()) {
            return $flags;
        }

        $dests = $payNow->pluck('payout_destination')->unique()->values()->all();
        $windowStart = $payNow->min('created_at')?->copy()->subDays(60);
        $windowEnd = $payNow->max('created_at')?->copy()->addDays(60);

        $others = RefundTicket::where('refund_method', RefundTicket::METHOD_PAYNOW)
            ->whereIn('payout_destination', $dests)
            ->when($windowStart, fn ($q) => $q->where('created_at', '>=', $windowStart))
            ->when($windowEnd, fn ($q) => $q->where('created_at', '<=', $windowEnd))
            ->get(['id', 'payout_destination', 'created_at']);

        foreach ($payNow as $t) {
            $flags[$t->id] = $others->contains(
                fn ($o) => (int) $o->id !== (int) $t->id
                    && $o->payout_destination === $t->payout_destination
                    && abs($o->created_at->diffInDays($t->created_at)) <= 60
            );
        }

        return $flags;
    }

    /**
     * System self-checking signals for the page rows, batched:
     *   - rf24h[id]      : # of refund requests THIS machine had in the trailing
     *                      24h ending at that ticket's submission (spike detection).
     *   - repeat[id]     : true when the SAME order AND the SAME channel were
     *                      already claimed on an EARLIER, non-rejected ticket. See
     *                      the New/Repeat definition below.
     *   - repeat_ref[id] : reference of the earliest such prior ticket (for the
     *                      tooltip / deep link), or null.
     *
     * New vs Repeat is decided ONLY on order + channel — a resubmission for the
     * same order (matched by order_id / vend_transaction_id / payment_gateway_log_id)
     * where at least one of the same machine channels is claimed again. Requester
     * identity (same PayNow/PayPal number or email) is deliberately NOT part of this
     * signal: that reuse case is surfaced separately as the red PayNow number under
     * the Refund Method column. Rejected/dropped tickets don't count as a prior
     * claim.
     *
     * @return array{rf24h: array<int,?int>, repeat: array<int,bool>, repeat_ref: array<int,?string>}
     */
    protected function selfCheckData($rows): array
    {
        $rows = collect($rows);
        $out = ['rf24h' => [], 'repeat' => [], 'repeat_ref' => []];
        if ($rows->isEmpty()) {
            return $out;
        }

        // Machine RF counts: pull every ticket for the page's machines within the
        // widest 24h window, then tally each row's own trailing 24h in PHP.
        $codes = $rows->pluck('vend_code')->filter()->unique()->values()->all();
        $windowStart = $rows->min('created_at')?->copy()->subDay();
        $windowEnd = $rows->max('created_at');
        $machineTickets = $codes
            ? RefundTicket::whereIn('vend_code', $codes)
                ->when($windowStart, fn ($q) => $q->where('created_at', '>=', $windowStart))
                ->when($windowEnd, fn ($q) => $q->where('created_at', '<=', $windowEnd))
                ->get(['id', 'vend_code', 'created_at'])
            : collect();

        // --- New / Repeat (same order + same channel) --------------------------
        // Every non-rejected ticket that shares an order identity with any page row
        // (this set includes the page rows themselves plus any sibling claims on the
        // same orders). We then match earlier siblings on order identity + channel.
        $orderIds = $rows->pluck('order_id')->filter()->unique()->values()->all();
        $vtIds    = $rows->pluck('vend_transaction_id')->filter()->unique()->values()->all();
        $logIds   = $rows->pluck('payment_gateway_log_id')->filter()->unique()->values()->all();

        $candidates = ($orderIds || $vtIds || $logIds)
            ? RefundTicket::where('status', '!=', RefundTicket::STATUS_REJECTED)
                ->where(function ($q) use ($orderIds, $vtIds, $logIds) {
                    if ($orderIds) { $q->orWhereIn('order_id', $orderIds); }
                    if ($vtIds)    { $q->orWhereIn('vend_transaction_id', $vtIds); }
                    if ($logIds)   { $q->orWhereIn('payment_gateway_log_id', $logIds); }
                })
                ->get(['id', 'reference', 'order_id', 'vend_transaction_id', 'payment_gateway_log_id', 'created_at'])
            : collect();

        // Channel sets per ticket (candidates + page rows), one batched query.
        $channelIds = $candidates->pluck('id')->merge($rows->pluck('id'))->unique()->values()->all();
        $channelsByTicket = \App\Models\RefundTicketItem::whereIn('refund_ticket_id', $channelIds)
            ->whereNotNull('vend_channel_code')
            ->get(['refund_ticket_id', 'vend_channel_code'])
            ->groupBy('refund_ticket_id')
            ->map(fn ($g) => $g->pluck('vend_channel_code')->filter()->unique()->values()->all());

        // Two tickets are the "same order" when they share ANY of order_id /
        // vend_transaction_id / payment_gateway_log_id.
        $sharesOrder = function ($t, $o): bool {
            return (filled($t->order_id) && (string) $o->order_id === (string) $t->order_id)
                || (filled($t->vend_transaction_id) && (int) $o->vend_transaction_id === (int) $t->vend_transaction_id)
                || (filled($t->payment_gateway_log_id) && (int) $o->payment_gateway_log_id === (int) $t->payment_gateway_log_id);
        };

        foreach ($rows as $t) {
            $winStart = $t->created_at?->copy()->subDay();
            $out['rf24h'][$t->id] = ($winStart && $t->vend_code)
                ? $machineTickets->filter(fn ($m) => $m->vend_code === $t->vend_code
                    && $m->created_at >= $winStart
                    && $m->created_at <= $t->created_at)->count()
                : null;

            $out['repeat'][$t->id] = false;
            $out['repeat_ref'][$t->id] = null;

            $myChannels = $channelsByTicket->get($t->id, []);
            // No claimed channel on this ticket → nothing to match on → New.
            if (empty($myChannels)) {
                continue;
            }

            $earliest = null;
            foreach ($candidates as $o) {
                if ((int) $o->id === (int) $t->id) {
                    continue;
                }
                // Only tickets submitted STRICTLY earlier count as the prior claim
                // (created_at earlier, or same instant with a smaller id).
                $isEarlier = $o->created_at < $t->created_at
                    || ($o->created_at == $t->created_at && (int) $o->id < (int) $t->id);
                if (! $isEarlier || ! $sharesOrder($t, $o)) {
                    continue;
                }
                // Same channel? (overlap between the two tickets' claimed channels)
                if (empty(array_intersect($myChannels, $channelsByTicket->get($o->id, [])))) {
                    continue;
                }
                if ($earliest === null
                    || $o->created_at < $earliest->created_at
                    || ($o->created_at == $earliest->created_at && (int) $o->id < (int) $earliest->id)) {
                    $earliest = $o;
                }
            }

            if ($earliest) {
                $out['repeat'][$t->id] = true;
                $out['repeat_ref'][$t->id] = $earliest->reference;
            }
        }

        return $out;
    }

    // ---- mappers ----

    protected function toRow(RefundTicket $t, $txn = null, $log = null, $batch = null, $siteName = null, bool $payNowDuplicate = false, array $self = []): array
    {
        $matched = (bool) ($t->vend_transaction_id || $t->payment_gateway_log_id);

        // Frozen validation snapshot (same source the Show page badges read from),
        // so the list-row icons match the ticket detail exactly.
        $sv = $t->system_validation_json ?? [];

        // Resolve the customer's "Today / Yesterday" choice to a real calendar
        // date, anchored on the SUBMISSION date (not now) so it still reads right
        // when Ops handles the refund days later. Custom picks are already a date.
        $enteredDayDate = $this->resolveEnteredDayDate($t);

        // Transaction details come from the matched source; manual tickets show
        // nothing here until Ops matches the Order ID.
        $txnDate = $txn?->transaction_datetime ?? $log?->approved_at ?? $log?->created_at;
        $paidCents = $txn?->amount ?? $log?->amount;

        // Delta = elapsed time between the transaction and the refund submission
        // (how long after buying the customer raised the claim), as "Xd Yh Zm".
        $txnDelta = null;
        if ($matched && $txnDate && $t->created_at) {
            $mins = abs((int) \Illuminate\Support\Carbon::parse($txnDate)->diffInMinutes($t->created_at));
            $txnDelta = intdiv($mins, 1440) . 'd ' . intdiv($mins % 1440, 60) . 'h ' . ($mins % 60) . 'm';
        }

        // Deep link into Sales Transactions showing ALL sales on this machine for
        // the SAME DAY as the disputed transaction (machine code + that day's
        // window), so the admin can eyeball every sale around the claimed one.
        // Only when matched and the machine + transaction date are known.
        $txnLink = null;
        if ($matched && $t->vend_code && $txnDate) {
            $txnDay = \Illuminate\Support\Carbon::parse($txnDate);
            $txnLink = '/vends/transactions?' . http_build_query(array_filter([
                'codes' => $t->vend_code,
                'date_from' => $txnDay->copy()->startOfDay()->toDateTimeString(),
                'date_to' => $txnDay->copy()->endOfDay()->toDateTimeString(),
            ]));
        }

        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'vend_code' => $t->vend_code,
            // VendPrefix (mapping) name of the matched transaction, shown beside the
            // machine ID on the same row. Null for unmatched/manual tickets.
            'vend_prefix_name' => $txn?->vendPrefix?->name,
            'site_name' => $siteName,
            'amount' => number_format($t->claimed_amount_cents / 100, 2),
            // Effective payout (final override ?? original claim), plus a flag for
            // when the admin's Final Refund Amount differs from the claim so the
            // list can show the original struck-through beside it.
            'final_refund_amount' => number_format($t->payout_amount_cents / 100, 2),
            'final_refund_overridden' => $t->hasFinalAmountOverride(),
            'final_refund_remarks' => $t->final_refund_remarks,
            'refund_method' => $t->refund_method,
            // PayNow number shown under the method (PayPal email is intentionally
            // omitted to save space). paynow_duplicate drives the red reuse warning.
            'payout_destination' => $t->refund_method === RefundTicket::METHOD_PAYNOW ? $t->payout_destination : null,
            // Method-agnostic destination for the Excel export only (PayNow number OR
            // PayPal email). The UI keeps 'payout_destination' above, which stays null
            // for non-PayNow so the screen is unchanged.
            'payout_destination_any' => $t->payout_destination,
            'paynow_duplicate' => $payNowDuplicate,
            'payment_channel' => $t->payment_channel,
            'reason_code' => $t->reason_code,
            'status' => $t->status,
            // Dropped/double-submission tickets are kept (status Rejected) but the
            // list strikes a line through them rather than deleting.
            'is_dropped' => (bool) $t->is_dropped,
            // New vs repeat: a repeat is a resubmission for a transaction that
            // already had an active claim (the customer was allowed through, not
            // blocked). replicated_from_reference points at the original.
            'is_repeat' => (bool) $t->is_repeat,
            'replicated_from_reference' => $t->replicated_from_reference,
            'recommendation' => $t->system_recommendation,
            'is_manual' => array_key_exists('is_manual', $sv) ? (bool) $sv['is_manual'] : (bool) $t->is_manual,
            'had_channel_error' => (bool) ($sv['had_channel_error'] ?? false),
            // Frozen self-validation verdict (see RefundValidationService): the icon
            // is decided at submission and re-synced only on re-match / a genuine
            // later auto-refund — a Reject/Drop must not flip it. Reads the SAME
            // frozen key as the ticket page and the server guard; legacy tickets
            // without it fall back to txn_already_refunded (never the mutable
            // auto_refund_detected, which a no-charge reject sets as a reason marker).
            'already_refunded' => (bool) ($sv['already_refunded'] ?? ($sv['txn_already_refunded'] ?? false)),
            'matched' => $matched,
            'entered_day_date' => $enteredDayDate ? $enteredDayDate->format('ymd') : null,
            'contact_name' => $t->contact_name,
            'contact_email' => $t->contact_email,
            'created_at' => optional($t->created_at)->toDateTimeString(),
            'created_ago' => optional($t->created_at)->diffForHumans(),
            'submitted_at' => optional($t->created_at)->format('ymd h:i a'),
            'txn_datetime' => $matched ? optional($txnDate)->format('ymd h:i a') : null,
            'txn_delta' => $txnDelta,
            'txn_link' => $txnLink,
            // --- System self-checking panel ---
            'machine_rf_24h' => $self['machine_rf_24h'] ?? null,
            // New vs Repeat = same order + same channel already claimed on an
            // earlier ticket (see selfCheckData). repeat_ref points at the original.
            'repeat_flag' => (bool) ($self['repeat_flag'] ?? false),
            'repeat_ref' => $self['repeat_ref'] ?? null,
            // Prod Exit Sensor = the machine's Product Drop Sensor state FROZEN on
            // the matched transaction at the moment it occurred (true = Enabled,
            // false = Disabled, null = unknown / not captured). A later machine
            // toggle does not change this recorded value.
            'product_drop_sensor' => (isset($txn) && !is_null($txn->product_drop_sensor)) ? (bool) $txn->product_drop_sensor : null,
            // Payment-gateway "dispense attempted" reading (is_dispensed) is still
            // recorded on the gateway log; kept in the payload but hidden from the
            // UI for now (superseded by product_drop_sensor above).
            'dispense_attempted' => ($log && !is_null($log->is_dispensed)) ? (bool) $log->is_dispensed : null,
            'error_code' => $self['error_code'] ?? null,
            'error_desc' => $self['error_desc'] ?? null,
            // Customer-flagged affected items: channel(s) + product name(s).
            'affected_items' => $self['affected_items'] ?? [],
            'paid_amount' => ($matched && $paidCents !== null) ? number_format($paidCents / 100, 2) : null,
            'pay_method' => $matched ? ($txn?->paymentMethod?->name ?? ($log ? 'QR' : null)) : null,
            // Card-terminal provider (Nayax / Nets / …) from vend_transactions.cashless_mfg,
            // shown on its own line in brackets under the method to keep the column narrow.
            'pay_provider' => ($matched && $txn && $txn->cashless_mfg) ? $txn->cashless_mfg : null,
            'batch' => $batch ? [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'filename' => $batch->csv_path ? basename($batch->csv_path) : null,
                'is_settlement' => (bool) $batch->is_settlement,
            ] : null,
            'completed_at' => optional($t->completed_at)->format('ymd h:i a'),
            // Refund-Progress stage actors (admin who did it + when), from the audit
            // trail — shown beside the status badge in each progress column.
            'validation_actor' => $self['validation_actor'] ?? null,
            'settlement_actor' => $self['settlement_actor'] ?? null,
            'insufficient_actor' => $self['insufficient_actor'] ?? null,
            'done_actor' => $self['done_actor'] ?? null,
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
        // Resolve the gateway log from the ticket, else from its matched txn — same
        // as the index list — so the "Prod Exit Sensor" (is_dispensed) reading is
        // available even when the ticket matched by vend_transaction_id. The txn's
        // own values still take precedence for date/amount (coalesced first in
        // toRow), so this only fills in the otherwise-missing dispense reading.
        $log = $t->payment_gateway_log_id
            ? \App\Models\PaymentGatewayLog::find($t->payment_gateway_log_id)
            : ($txn?->payment_gateway_log_id ? \App\Models\PaymentGatewayLog::find($txn->payment_gateway_log_id) : null);
        $batch = $t->payout_batch_id ? RefundPayoutBatch::find($t->payout_batch_id) : null;

        // System self-checking (machine RF-in-24h + New/Repeat + error code),
        // computed for this single ticket so the Show page mirrors the index
        // "System self-checking" columns. selfCheckData accepts a collection.
        $self = $this->selfCheckData(collect([$t]));
        $errItem = $t->items->first(fn ($i) => $i->had_channel_error);
        $selfRow = [
            'machine_rf_24h' => $self['rf24h'][$t->id] ?? null,
            'repeat_flag' => $self['repeat'][$t->id] ?? false,
            'repeat_ref' => $self['repeat_ref'][$t->id] ?? null,
            'error_code' => $errItem?->vend_channel_error_code,
            'error_desc' => $errItem?->channel_error_desc,
        ];

        // Site (customer) name for the ticket's machine — public/admin context, scopes off.
        $siteVend = $t->vend_id ? \App\Models\Vend::withoutGlobalScopes()->find($t->vend_id) : null;
        $siteName = $siteVend && $siteVend->customer_id
            ? optional(\App\Models\Customer::withoutGlobalScopes()->find($siteVend->customer_id))->name
            : null;

        // LIVE refunded state of the linked source, read fresh here (not from the
        // frozen validation snapshot). The snapshot is captured at submission and
        // is correct for audit, but a charge can be refunded LATER (e.g. the system
        // auto-refunds an Omise non-dispense). The "Already refunded" badge must
        // reflect this current state so it stays in sync with the Related
        // Transactions panel below, which already reads the live is_refunded.
        $liveTxnRefunded = $txn
            ? (bool) $txn->is_refunded
            : ($log ? ((int) $log->status === \App\Models\PaymentGatewayLog::STATUS_REFUND) : false);

        return array_merge($this->toRow($t, $txn, $log, $batch, $siteName, false, $selfRow), [
            'site_name' => $siteName,
            'live_txn_refunded' => $liveTxnRefunded,
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
            // Admin-adjustable final payout amount. Defaults (for the input) to the
            // claimed amount when never overridden; the payout path reads the same
            // effective value (final ?? claimed). Original claim stays in 'amount'.
            'final_refund_amount' => number_format($t->payout_amount_cents / 100, 2, '.', ''),
            'final_refund_amount_set' => $t->final_refund_amount_cents !== null,
            'final_refund_overridden' => $t->hasFinalAmountOverride(),
            'final_refund_remarks' => $t->final_refund_remarks,
            // Admin "Overwritten status" override (Overwritten section on the page).
            'status_override_remarks' => $t->status_override_remarks,
            'status_overridden_at' => optional($t->status_overridden_at)->toDateTimeString(),
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
                // actor_id is null for System / Customer lines and set for admin
                // button clicks — the Show page uses it to badge the action.
                'actor_id' => $l->actor_id,
                'action' => $l->action,
                'from_status' => $l->from_status,
                'to_status' => $l->to_status,
                'note' => $l->note,
                'meta' => $l->meta,
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
            // Link to Sales Transactions filtered by THIS machine on the same day
            // as the transaction (codes = vend_code + that day's window), so the
            // admin can eyeball every sale on the machine around the disputed one
            // — more useful than pinning to the single matched order_id.
            $link = '/vends/transactions?' . http_build_query(array_filter([
                'codes' => $ticket->vend_code,
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
        // "Auto-resolved" was retired: tickets the system auto-refunds now stay in
        // "Received" (flagged already-refunded) so Ops rejects or drops them by
        // hand — approval is disabled for them. No separate chip/status anymore.
        return [
            RefundTicket::STATUS_SUBMITTED => 'Received',
            RefundTicket::STATUS_PENDING => 'Pending',
            RefundTicket::STATUS_REJECTED => 'Rejected',
            // "Dropped" is a pseudo-status, not a real status column value: dropped
            // tickets are stored as Rejected with is_dropped=true. It is surfaced as
            // its own chip / filter option and counted separately from Rejected.
            'dropped' => 'Dropped',
            RefundTicket::STATUS_APPROVED => 'Approved',
            // Scheduled tickets the bank couldn't pay — their own chip so they can be
            // filtered for manual follow-up. The Validation column still shows them as
            // Approved; the red "Insufficient Info" state lives in the Refund Done column.
            RefundTicket::STATUS_INSUFFICIENT_INFO => 'Insufficient Info',
            RefundTicket::STATUS_COMPLETED => 'Completed',
        ];
    }
}
