<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Customer;
use App\Models\FreezerControlCommand;
use App\Models\FreezerFailedSession;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\Freezer\FreezerSaleCorrection;
use App\Services\HappyHour\HappyHourPricing;
use App\Services\NoteNotificationService;
use App\Services\SmartFreezer\DoorFault;
use App\Services\SmartFreezer\FreezerClipLinks;
use App\Services\SmartFreezer\FreezerDoorTimeline;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\PersonCount;
use App\Services\SmartFreezer\RecognitionBadges;
use App\Services\SmartFreezer\RecognitionVerdict;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use App\Services\SmartFreezer\Zijia\ZijiaSkuLinks;
use App\Support\FreezerTestSale;
use App\Support\Sales\CampaignLabelFilter;
use App\Support\VendCode;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Transactions → AI Recognition (Smart Freezer): one row per freezer door session put to Zijia's algorithm,
 * with what it saw and the verdict against the paid sale.
 *
 * Reads `smart_freezer_recognitions` only — it already carries `vend_transaction_id`, so nothing
 * is added to `vend_transactions` and the sales grid is untouched. Lookups (machines, sales,
 * product names, videos) are batched per page.
 */
class FreezerRecognitionController extends Controller
{
    private const SORTS = ['transaction_time', 'created_at', 'status', 'verdict', 'completed_at'];

    /** How long after a door session starts a hold with no session can still be its payment (session 9 took 2m41s over three tries). */
    private const SESSIONLESS_HOLD_WINDOW_SECONDS = 600;

    /** Freezer app 41 published, 2026-11-02 16:39: a session before this never showed the invoice QR. */
    private const INVOICE_QR_SINCE = 1793608740;

    /**
     * The row's main time (Brian, 2026-11-03): its sale's Transaction DateTime, the time Sales
     * Transactions shows, else the door session until a sale is matched. The door session is when
     * the videos reached us, minutes after the sale.
     */
    /** Longer than this and the video is not simply on its way: the row stops promising it. */
    private const WAITING_FOR_VIDEO_MINUTES = 60;

    private const TRANSACTION_TIME = '(COALESCE((SELECT vt.transaction_datetime FROM vend_transactions vt WHERE vt.id = smart_freezer_recognitions.vend_transaction_id), smart_freezer_recognitions.created_at))';

    public function __construct()
    {
        $this->middleware(['permission:read ai-recognition']);
        $this->middleware(['permission:charge ai-recognition'])->only('charge', 'correctionEditor', 'correct');
    }

    public function index(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/'],
            'processed_by' => ['nullable', 'in:all,ai,human,none'],
        ]);

        // Sidebar badge (RecognitionIssues): opening the page clears it. Not a table reload, and not
        // a link to one session (Sales Transactions' verdict badge), which shows only that row.
        if ($request->user() && ! $request->hasHeader('X-Inertia-Partial-Data') && ! filled($request->input('search'))) {
            app(NoteNotificationService::class)->markViewed($request->user(), NoteNotificationService::PAGE_AI_RECOGNITION);
        }

        $numberPerPage = $request->numberPerPage ?: 100;
        $sortKey = in_array($request->sortKey, self::SORTS, true) ? $request->sortKey : 'transaction_time';
        $sortBy = filter_var($request->sortBy ?? false, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        // Opens on today (Brian, 2026-11-04). A date cleared on the form stays cleared, and a link to
        // one session (Sales Transactions' verdict badge) finds it whatever its day.
        $opensOnToday = ! $request->hasAny(['date_from', 'date_to']) && ! filled($request->input('search'));
        $dateFrom = $opensOnToday ? Carbon::today()->toDateString() : $request->input('date_from');
        $dateTo = $opensOnToday ? Carbon::today()->toDateString() : $request->input('date_to');

        $query = SmartFreezerRecognition::query()
            ->with([
                'vend:id,code,code_prefix,operator_id,machine_type,customer_id',
                'vendTransaction:id,order_id,amount,transaction_datetime,vend_transaction_json,meta_json,customer_id,is_multiple,happy_hour_slot_id',
                'vendTransaction.vendTransactionItems:id,vend_transaction_id,product_id,happy_hour_slot_id',
                'videos:id,smart_freezer_recognition_id,video_urls,created_at',
                'videos.copies:id,smart_freezer_video_id,url_hash,status,duration_ms',
            ])
            ->when($request->input('status'), fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            // A door try that never opened (app 54+, Brian 2026-11-08) is one door session with the try
            // that did: listed only when no try of that sale opened, and then once, as its first try.
            ->when($request->input('status') !== SmartFreezerRecognition::STATUS_SKIPPED, fn ($q) => $q->where(fn ($q) => $q
                ->where('status', '!=', SmartFreezerRecognition::STATUS_SKIPPED)
                ->orWhere(fn ($q) => $q->whereColumn('trade_id', 'session_ref')
                    ->whereNotExists(fn ($e) => $e->selectRaw('1')->from('smart_freezer_recognitions as door_try')
                        ->whereColumn('door_try.vend_id', 'smart_freezer_recognitions.vend_id')
                        ->whereColumn('door_try.session_ref', 'smart_freezer_recognitions.session_ref')
                        ->where('door_try.status', '!=', SmartFreezerRecognition::STATUS_SKIPPED)))))
            ->when($request->input('verdict'), fn ($q, $v) => match ($v) {
                'all' => $q,
                'none' => $q->whereNull('verdict'),
                default => $q->where('verdict', $v),
            })
            // Processed by: orderStatus 0 = the AI, any other code = a person (RecognitionResult::processedBy).
            ->when($request->input('processed_by'), fn ($q, $p) => match ($p) {
                RecognitionResult::PROCESSED_BY_AI => $q->where('order_status', RecognitionResult::STATUS_NORMAL),
                RecognitionResult::PROCESSED_BY_HUMAN => $q->where('order_status', '<>', RecognitionResult::STATUS_NORMAL),
                'none' => $q->whereNull('order_status'),
                default => $q,
            })
            ->when($dateFrom, fn ($q, $d) => $q->whereRaw(self::TRANSACTION_TIME.' >= ?', [$d.' 00:00:00']))
            ->when($dateTo, fn ($q, $d) => $q->whereRaw(self::TRANSACTION_TIME.' <= ?', [$d.' 23:59:59']))
            // Machine ID box: "50001", "C6003", or a comma list for an exact set — the Sales grid's rule.
            ->when(trim((string) $request->input('codes')), fn ($q, $codes) => $q->whereIn('vend_id', Vend::withoutGlobalScopes()->select('id')
                ->where(fn ($v) => VendCode::whereSearch($v, $codes))))
            ->when(trim((string) $request->input('search')), function ($q, $search) {
                $q->where(fn ($q) => $q
                    ->whereIn('vend_transaction_id', VendTransaction::withoutGlobalScopes()->select('id')->where('order_id', $search))
                    ->orWhere('trade_id', 'LIKE', "%{$search}%")
                    ->orWhere('session_ref', 'LIKE', "%{$search}%")
                    ->orWhere('device_id', 'LIKE', "%{$search}%")
                    ->orWhereIn('vend_id', Vend::withoutGlobalScopes()->select('id')->where(fn ($v) => VendCode::whereSearch($v, $search))));
            })
            ->when($sortKey === 'transaction_time',
                fn ($q) => $q->orderByRaw(self::TRANSACTION_TIME.' '.$sortBy),
                fn ($q) => $q->orderBy($sortKey, $sortBy))
            ->orderByDesc('id');

        // Viewer ceiling (mark1 CLAUDE.md, operator isolation): this table has no scope of its own.
        // An operator-restricted viewer sees only its own machines' sessions; a session no freezer
        // was matched to (a supplier test cabinet) is visible to the unrestricted operator only.
        if (($operatorId = OperatorVendFilterScope::viewerOperatorId()) !== null) {
            $query->whereIn('vend_id', Vend::withoutGlobalScopes()->select('id')->where('operator_id', $operatorId));
        }

        // Props are closures: the page's 30 s refresh names only some of them, and Inertia then skips the rest (audit SF2-06).
        $dashboardQuery = clone $query;
        $page = $query->paginate($numberPerPage === 'All' ? 10000 : (int) $numberPerPage)->withQueryString();
        $names = $this->productNames($page->getCollection());
        $failedSessions = $this->failedSessions($page->getCollection());
        $cards = $this->cardCharges($page->getCollection(), $failedSessions);
        $doorErrors = $this->doorErrors($page->getCollection());
        $purchasedProducts = $this->purchasedProducts($page->getCollection(), $failedSessions);
        $sites = $this->sites($page->getCollection());
        $invoiceScans = $this->invoiceScans($page->getCollection());
        $happyHour = CampaignLabelFilter::happyHourLabels($page->getCollection()->map(fn (SmartFreezerRecognition $r) => $r->vendTransaction)->filter()
            ->flatMap(fn (VendTransaction $sale) => $sale->vendTransactionItems->pluck('happy_hour_slot_id')->push($sale->happy_hour_slot_id)));

        // Our own camera-5 recordings of the same door sessions (smart_freezer_clips), by session ref.
        $clips = app(FreezerClipLinks::class)->linksFor($page->getCollection()
            ->filter(fn (SmartFreezerRecognition $r) => $r->vend_id && $r->session_ref)
            ->mapWithKeys(fn (SmartFreezerRecognition $r) => [$r->id => [(int) $r->vend_id, (string) $r->session_ref]])
            ->all());

        $payments = app(CardPaymentService::class);
        $page->through(fn (SmartFreezerRecognition $r) => $this->row($r, $names, $cards, $purchasedProducts, $happyHour, $payments, $failedSessions) + [
            'door_error' => $this->doorError($r, $doorErrors, $failedSessions),
            'door_timeline' => $this->doorTimeline($r, $doorErrors, $failedSessions, $cards),
            'clips' => $clips[$r->id] ?? [],
            'invoice' => $this->invoiceScan($r, $invoiceScans),
            'site' => $sites[$r->vendTransaction?->customer_id ?? $r->vend?->customer_id] ?? null,
        ]);

        return Inertia::render('AiRecognition/Index', [
            // Resource-collection shape (data / links / meta) — what Components/Paginator.vue reads.
            'recognitions' => JsonResource::collection($page),
            'dashboard' => fn () => $this->dashboard($dashboardQuery),
            // Per freezer, how many of its products the AI is asked about today (SKU Links).
            'aiCoverage' => fn () => array_values(app(ZijiaSkuLinks::class)->coverage(ZijiaSkuLinks::viewerVendIds())),
            'statuses' => [
                SmartFreezerRecognition::STATUS_PENDING,
                SmartFreezerRecognition::STATUS_SUBMITTING,
                SmartFreezerRecognition::STATUS_SUBMITTED,
                SmartFreezerRecognition::STATUS_COMPLETED,
                SmartFreezerRecognition::STATUS_FAILED,
                SmartFreezerRecognition::STATUS_SKIPPED,
            ],
            'verdicts' => [
                RecognitionVerdict::MATCH, RecognitionVerdict::TOOK_MORE, RecognitionVerdict::TOOK_LESS,
                RecognitionVerdict::MIXED, RecognitionVerdict::UNRECOGNISED, RecognitionVerdict::INCOMPLETE,
            ],
            'canCharge' => (bool) $request->user()?->can('charge ai-recognition'),
            'canRefund' => (bool) $request->user()?->can('verify refunds'),
            'sessionlessHolds' => fn () => $this->sessionlessHolds($payments, $operatorId),
            'waitingForVideo' => fn () => $this->waitingForVideo($request, $dateFrom, $dateTo, $operatorId),
            // Zijia's orderStatus codes (§6.2) with ops' plain words — the legend above the table.
            'orderStatusLegend' => array_map(fn (array $l) => $l + [
                'plain' => RecognitionBadges::PLAIN_REASONS[$l['code']] ?? 'the AI answered on its own',
            ], RecognitionResult::legend()),
            'doorFaultLegend' => DoorFault::legend(),
            'filters' => [
                'status' => $request->input('status', 'all'),
                'verdict' => $request->input('verdict', 'all'),
                'processed_by' => $request->input('processed_by', 'all'),
                'search' => $request->input('search', ''),
                'codes' => $request->input('codes', ''),
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'sortKey' => $sortKey,
                'sortBy' => $request->sortBy ?? false,
            ],
        ]);
    }

    /**
     * The AI Dashboard over everything the filters list and the viewer may see, not just this page
     * (Brian, 2026-11-04). Each session lands in one outcome and its units in one count, so both
     * add up to their total.
     */
    private function dashboard($query): array
    {
        $rows = $query->reorder()
            ->setEagerLoads(['vendTransaction' => fn ($sale) => $sale->select(['id', 'amount', 'is_refunded', 'settlement_status', 'meta_json', 'vend_transaction_json'])])
            ->get(['id', 'vend_id', 'session_ref', 'vend_transaction_id', 'verdict', 'verdict_lines']);
        $failed = $this->failedSessions($rows);
        $cards = $this->cardCharges($rows, $failed);
        $doorErrors = $this->doorErrors($rows);
        $invoiceScans = $this->invoiceScans($rows);

        $charged = $refunded = 0;
        $outcomes = ['successful' => 0, 'void' => 0, 'machine_error' => 0, 'other' => 0];
        $correct = $adjusted = $unchecked = 0;
        $invoiceSales = $invoiceScanned = $invoiceFinalSeen = 0;
        foreach ($rows as $r) {
            $sale = $r->vendTransaction;
            $card = $r->session_ref ? ($cards[$r->vend_id.'|'.$r->session_ref] ?? null) : null;
            $rowCharged = match (true) {
                $card !== null => $card->state === CardPaymentIntent::STATE_CAPTURED ? (int) $card->captured_cents : 0,
                (int) $sale?->settlement_status === VendTransaction::SETTLEMENT_SETTLED => (int) $sale?->amount,
                default => 0,
            };
            $charged += $rowCharged;
            $refunded += $card !== null ? (int) $card->refunded_cents : ($sale?->is_refunded ? (int) $sale->amount : 0);

            $outcomes[match (true) {
                $rowCharged > 0 => 'successful',
                $this->doorError($r, $doorErrors, $failed) !== null || ($sale === null && $card !== null && $card->session_ref === null) => 'machine_error',
                $card?->state === CardPaymentIntent::STATE_VOIDED => 'void',
                default => 'other',
            }]++;

            [$rowCorrect, $rowAdjusted, $rowUnchecked] = $this->qtyCheck($r, $card);
            $correct += $rowCorrect;
            $adjusted += $rowAdjusted;
            $unchecked += $rowUnchecked;

            // The scan rate is over real paid sales that showed the QR, so staff in Testing Mode do not lift it.
            $invoice = $this->invoiceScan($r, $invoiceScans);
            if ($rowCharged > 0 && $invoice['offered'] && ! FreezerTestSale::is($sale)) {
                $invoiceSales++;
                $invoiceScanned += $invoice['opened_at'] !== null ? 1 : 0;
                $invoiceFinalSeen += $invoice['final_seen_at'] !== null ? 1 : 0;
            }
        }
        $totalQty = $correct + $adjusted + $unchecked;

        return $outcomes + [
            'charged_cents' => $charged,
            'refunded_cents' => $refunded,
            'total' => $rows->count(),
            'successful_pct' => $rows->isNotEmpty() ? round($outcomes['successful'] / $rows->count() * 100, 2) : null,
            'correct_qty' => $correct,
            'correct_pct' => $totalQty > 0 ? round($correct / $totalQty * 100, 2) : null,
            'adjusted_qty' => $adjusted,
            'unchecked_qty' => $unchecked,
            'total_qty' => $totalQty,
            'invoice_sales' => $invoiceSales,
            'invoice_scanned' => $invoiceScanned,
            'invoice_scanned_pct' => $invoiceSales > 0 ? round($invoiceScanned / $invoiceSales * 100, 2) : null,
            'invoice_final_seen' => $invoiceFinalSeen,
        ];
    }

    /**
     * The invoice QR's scans, batched: a row whose first open is empty only pinned a signature.
     *
     * @return array<string, object> "vend_id|session_ref" => its freezer_receipts row
     */
    private function invoiceScans($recognitions): array
    {
        $refs = $recognitions->pluck('session_ref')->filter()->unique()->values();
        if ($refs->isEmpty()) {
            return [];
        }

        return DB::table('freezer_receipts')
            ->whereIn('vend_id', $recognitions->pluck('vend_id')->filter()->unique()->values())
            ->whereIn('session_ref', $refs)
            ->whereNotNull('first_opened_at')
            ->get(['vend_id', 'session_ref', 'open_count', 'first_opened_at', 'final_seen_at', 'device'])
            ->keyBy(fn (object $scan) => $scan->vend_id.'|'.$scan->session_ref)
            ->all();
    }

    /** One session's invoice QR as the page shows it. `offered` = the app of its day could show one. */
    private function invoiceScan(SmartFreezerRecognition $r, array $invoiceScans): array
    {
        $scan = $invoiceScans[$r->vend_id.'|'.$r->session_ref] ?? null;

        return [
            'offered' => (self::sessionStart($r->session_ref) ?? 0) >= self::INVOICE_QR_SINCE,
            'opened_at' => $scan?->first_opened_at,
            'opens' => (int) ($scan?->open_count ?? 0),
            'final_seen_at' => $scan?->final_seen_at,
            'device' => $scan?->device,
        ];
    }

    /**
     * One session's units as [AI correct, a person changed, nobody has checked]. A person's count is
     * the truth, so one product swapped for another is one unit wrong. With no count the AI stands
     * only where nothing is in doubt: a match, or a hold released with nothing seen taken. A session
     * the AI gave no verdict on and nobody counted has no units to judge.
     *
     * @return array{int, int, int}
     */
    private function qtyCheck(SmartFreezerRecognition $r, ?CardPaymentIntent $card): array
    {
        $correction = ((array) $r->vendTransaction?->meta_json)['correction'] ?? null;
        if ($r->verdict === null && ! is_array($correction)) {
            return [0, 0, 0];
        }

        if (is_array($correction)) {
            return [...PersonCount::compare((array) $r->verdict_lines, (array) ($correction['basket'] ?? [])), 0];
        }

        $taken = $paid = [];
        foreach ((array) $r->verdict_lines as $line) {
            $key = PersonCount::key($line);
            $taken[$key] = ($taken[$key] ?? 0) + (int) ($line['taken'] ?? 0);
            $paid[$key] = ($paid[$key] ?? 0) + (int) ($line['paid'] ?? 0);
        }

        $agreed = fn (array $other) => array_sum(array_map(fn ($key) => min($taken[$key] ?? 0, $other[$key] ?? 0), array_keys($taken)));
        if ($r->verdict === RecognitionVerdict::MATCH || ($card?->state === CardPaymentIntent::STATE_VOIDED && array_sum($taken) === 0)) {
            return [array_sum($taken), 0, 0];
        }
        $right = $agreed($paid);

        return [$right, 0, max(array_sum($taken), array_sum($paid)) - $right];
    }

    /**
     * A person keys in the final charge for a T05 hold with no AI verdict (Brian, 2026-10-09; and
     * 2026-10-10 for a session the AI never answered). Only T05 holds come here: NETS and QR sales
     * were charged at the machine, and the AI only reconciles their stock.
     */
    public function charge(Request $request, CardPaymentIntent $intent, CardPaymentService $payments)
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        // Viewer ceiling, as on the list: an operator-restricted viewer acts on its own freezers only.
        if (($operatorId = OperatorVendFilterScope::viewerOperatorId()) !== null
            && (int) Vend::withoutGlobalScopes()->whereKey($intent->vend_id)->value('operator_id') !== $operatorId) {
            abort(404);
        }

        try {
            $intent = $payments->chargeByPerson($intent, (int) $data['amount_cents'], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['amount_cents' => ucfirst($e->getMessage()).'.']);
        } catch (LockTimeoutException) {
            // A busy hold (another charge in flight) is a retry, not a 500 — as correct() answers (audit SF2-05).
            return back()->withErrors(['amount_cents' => __('This charge is busy. Try again in a moment.')]);
        }

        $amount = number_format($data['amount_cents'] / 100, 2);

        return match (true) {
            $intent->state === CardPaymentIntent::STATE_VOIDED => back()->with('success', __('Hold released — nothing charged.')),
            $intent->state === CardPaymentIntent::STATE_CAPTURED => back()->with('success', __('Charged :amount on the T05.', ['amount' => number_format($intent->captured_cents / 100, 2)])),
            // Kept on the hold either way: an unknown outcome waits for a person checking Payrallel,
            // a refusal is retried by card-payments:reconcile every 10 minutes.
            isset($intent->ai_decision['uncertain']) => back()->withErrors(['amount_cents' => __('The charge of :amount may have gone through — it is not resent. Check Payrallel\'s portal.', ['amount' => $amount])]),
            default => back()->withErrors(['amount_cents' => __('Payrallel refused the charge of :amount (:reason). mark1 keeps the amount and retries every 10 minutes.', ['amount' => $amount, 'reason' => $intent->last_error ?? __('no reason given')])]),
        };
    }

    /** What the Correct screen shows. */
    public function correctionEditor(SmartFreezerRecognition $recognition, FreezerSaleCorrection $corrections): JsonResponse
    {
        $this->assertVisible($recognition);

        return response()->json(['editor' => $corrections->editor($recognition)]);
    }

    /** A person says what left the cabinet (FreezerSaleCorrection). Every figure is worked out here. */
    public function correct(Request $request, SmartFreezerRecognition $recognition, FreezerSaleCorrection $corrections): JsonResponse
    {
        $data = $request->validate([
            'taken' => ['present', 'array'],
            'taken.*' => ['integer', 'min:0', 'max:50'],
            'amount_cents' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        $this->assertVisible($recognition);

        try {
            $done = $corrections->correct($recognition, $data['taken'], (int) $data['amount_cents'], $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'editor' => $corrections->editor($recognition)], 422);
        } catch (LockTimeoutException) {
            return response()->json(['message' => __('This charge is busy. Try again in a moment.'), 'editor' => $corrections->editor($recognition)], 409);
        }

        $intent = $done['intent'];
        $amount = '$'.number_format($done['value_cents'] / 100, 2);
        $held = $done['mode'] === FreezerSaleCorrection::MODE_HOLD;
        $failed = $held && ! in_array($intent->state, [CardPaymentIntent::STATE_CAPTURED, CardPaymentIntent::STATE_VOIDED], true);

        return response()->json([
            'ok' => ! $failed,
            'mode' => $done['mode'],
            'intent_id' => $intent?->id,
            'to_refund_cents' => $done['to_refund_cents'],
            'owed_cents' => $done['owed_cents'],
            'message' => match (true) {
                $failed && isset($intent->ai_decision['uncertain']) => __('The charge of :amount may have gone through — it is not resent. Check Payrallel\'s portal.', ['amount' => $amount]),
                $failed => __('Payrallel refused the charge of :amount (:reason). mark1 keeps the amount and retries every 10 minutes.', ['amount' => $amount, 'reason' => $intent->last_error ?? __('no reason given')]),
                $held && $intent->state === CardPaymentIntent::STATE_VOIDED => __('Hold released — nothing charged.'),
                $held => __('Charged :amount on the T05.', ['amount' => number_format($intent->captured_cents / 100, 2)]),
                default => __('Saved. Sale items and stock now follow this count.'),
            },
        ], $failed ? 422 : 200);
    }

    /** Viewer ceiling, as on the list: an operator-restricted viewer acts on its own freezers only. */
    private function assertVisible(SmartFreezerRecognition $recognition): void
    {
        if (($operatorId = OperatorVendFilterScope::viewerOperatorId()) !== null
            && (int) Vend::withoutGlobalScopes()->whereKey($recognition->vend_id)->value('operator_id') !== $operatorId) {
            abort(404);
        }
    }

    /**
     * @param  array{by_id: array<int, string>, by_code: array<string, string>}  $names
     * @param  array<string, CardPaymentIntent>  $cards  "vend_id|session_ref" => the session's T05 hold
     * @param  array<int, Product>  $products  the TRADE products of this page, by id
     * @param  array<int, string>  $happyHour  slot id => "HH 30%", for this page's sales
     * @param  array<string, FreezerFailedSession>  $failedSessions  "vend_id|session_ref" => what the machine reported
     */
    private function row(SmartFreezerRecognition $r, array $names, array $cards, array $products, array $happyHour, CardPaymentService $payments, array $failedSessions): array
    {
        $sale = $r->vendTransaction;
        $failed = $sale === null && $r->session_ref ? ($failedSessions[$r->vend_id.'|'.$r->session_ref] ?? null) : null;
        $card = $r->session_ref ? ($cards[$r->vend_id.'|'.$r->session_ref] ?? null) : null;
        $submitted = self::submittedCodes($r);
        // Paid, but no sale was ever written: the app names a hold's session only at door close.
        $paidWithoutSale = $sale === null && $card !== null && $card->session_ref === null;

        return [
            'id' => $r->id,
            'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
            'completed_at' => $r->completed_at?->format('Y-m-d H:i:s'),
            // How long the result took, from the door session (when its videos reached us).
            'result_seconds' => $r->created_at && $r->completed_at ? max(0, $r->completed_at->timestamp - $r->created_at->timestamp) : null,
            'vend_id' => $r->vend_id,
            'vend_label' => $r->vend?->codeLabel(),
            'device_id' => $r->device_id,
            'trade_id' => $r->trade_id,
            // The door try this row is (freezer app 54+ retries a lock that will not open): 1 for the plain ref.
            'door_try' => FreezerRecognitionService::doorTryOf($r->trade_id),
            'session_ref' => $r->session_ref,
            'status' => $r->status,
            // "sale not found yet" would promise a sale that is not coming.
            'status_reason' => $paidWithoutSale ? __('No sale was recorded for this door session.') : $r->status_reason,
            'algorithm_status' => $this->algorithmStatus($r),
            'processed_by' => RecognitionResult::processedBy($r->order_status === null ? null : (int) $r->order_status),
            'callback_verified' => $r->callback_verified,
            'request_id' => $r->request_id,
            // What was (or will be) sent: Zijia's push once it is in, else the files mark1 found early.
            'videos' => FreezerRecognitionService::sendableVideos($r->videos)->flatMap(fn ($v) => (array) $v->video_urls)->unique()->values()->all(),
            // Each video through mark1 (their link, else our copy) and its length (2026-11-09).
            'video_links' => $this->videoLinks($r),
            // What the algorithm saw: barcode => units, named where the barcode is on one of our products.
            'items' => collect((array) $r->items)->map(fn ($n, $code) => [
                'code' => (string) $code,
                'name' => $names['by_code'][(string) $code] ?? null,
                'number' => (int) $n,
            ])->values()->all(),
            // What the customer paid for, from the sale's TRADE (`transf_info`, one entry per unit).
            // `has_barcode` false = mark1 never asked the AI to look for it, so "not taken" cannot
            // be judged for that product.
            'purchased' => $sale ? $this->purchased($sale, $products, $submitted) : [],
            // The candidate list sent with the videos (`goodsList`, FreezerRecognitionService::candidateCodes
            // at submit time): the AI only ever names a product from it. Null = not sent yet.
            'submitted' => $submitted === null ? null : array_map(fn (string $code) => [
                'code' => $code,
                'name' => $names['by_code'][$code] ?? null,
            ], $submitted),
            // The Verification grid: one line per product, photo, price, paid, AI saw, the difference and a person's count.
            'ai_check' => $this->aiCheck($r, $sale, $products, $happyHour, $payments, $failed),
            'verdict' => $r->verdict,
            'verdict_lines' => collect((array) $r->verdict_lines)->map(fn ($line) => $line + [
                'name' => isset($line['product_id']) ? ($names['by_id'][(int) $line['product_id']] ?? null) : null,
            ])->values()->all(),
            'sale' => $sale ? [
                'id' => $sale->id,
                'order_id' => $sale->order_id,
                'amount' => (int) $sale->amount,
                'date' => $sale->transaction_datetime ? substr((string) $sale->transaction_datetime, 0, 10) : null,
                'time' => $sale->transaction_datetime ? substr((string) $sale->transaction_datetime, 11, 8) : null,
                'is_test_sale' => FreezerTestSale::is($sale),
            ] : null,
            'paid_without_sale' => $paidWithoutSale ? [
                'method' => self::cardBrand($card->payment_method),
            ] : null,
            'failed_session' => $failed ? [
                'method' => $failed->payment_method === FreezerFailedSession::METHOD_CARD ? __('Card') : __('PayNow'),
                'amount_cents' => $failed->amount_cents,
            ] : null,
            // The Correct button (FreezerSaleCorrection, which says why when it refuses).
            'can_correct' => $sale !== null && $r->vend?->isSmartFreezer(),
            'correction' => $this->correctionOf($r, $sale, $card),
            // A T05 hold this session's verdict charges (CardPaymentService::settleAwaitingAi).
            'card' => $card ? [
                'id' => $card->id,
                'state' => $card->state,
                'hold_cents' => $card->amount_cents,
                'captured_cents' => $card->captured_cents,
                'owed_cents' => $card->owed_cents,
                'refunded_cents' => (int) $card->refunded_cents,
                // Each refund that went back: how much, when it was sent and who sent it.
                'refunds' => $card->confirmedRefunds->map(fn (CardRefund $refund) => [
                    'id' => $refund->id,
                    'amount_cents' => $refund->amount_cents,
                    'at' => $refund->created_at?->format('Y-m-d H:i'),
                    'by' => $refund->requested_by_name,
                ])->values()->all(),
                'judged_cents' => $card->ai_decision['judged_cents'] ?? null,
                // Payrallel refused to raise the authorisation: the hold was charged, the rest is owed.
                'increment_refused' => isset($card->ai_decision['increment_refused']),
                // A charge that may have gone through; nothing more is sent until resolved
                // (`card-payments:resolve-charge`).
                'uncertain_cents' => $card->ai_decision['uncertain'] ?? null,
                'reason' => $card->ai_decision['reason'] ?? null,
                'error' => $card->last_error,
                // Anything but a matching AI verdict: the hold stays until a person keys in the charge
                // (CardPaymentService::chargeByPerson, which re-checks this).
                'person_reason' => $reason = ($card->state === CardPaymentIntent::STATE_AWAITING_AI ? $payments->personNeededBecause($card) : null),
                'awaits_person' => $reason !== null,
                'ai_suggested_cents' => $reason === CardPaymentService::PERSON_AI_MISMATCH ? $payments->personSuggestedCents($card) : null,
                'by_person' => $card->ai_decision['by_person']['name'] ?? null,
            ] : null,
        ];
    }

    /**
     * Each product of the session once: what was paid against what the AI saw, problems first.
     * `state`: ok = both agree, extra = taken but not paid, missing = paid but not taken, unknown = the
     * AI could not check it (never asked, or a barcode we do not know), pending = no answer yet,
     * none = neither paid nor seen, on the list only because a person counted it (`adjusted`),
     * na = a failed session's cart: paid, nothing to check it against, unless the AI did see something.
     *
     * @param  array<int, Product>  $products  this page's products, by id, with their photos
     * @param  array<int, string>  $happyHour  slot id => "HH 30%", for this page's sales
     * @return list<array<string, mixed>>
     */
    private function aiCheck(SmartFreezerRecognition $r, ?VendTransaction $sale, array $products, array $happyHour, CardPaymentService $payments, ?FreezerFailedSession $failed = null): array
    {
        $paid = [];
        foreach ($sale ? $this->tradeUnits($sale) : [] as $unit) {
            $id = (int) ($unit['goods_id'] ?? 0);
            if ($id > 0) {
                $paid[$id]['qty'] = ($paid[$id]['qty'] ?? 0) + 1;
                $paid[$id]['unit_cents'] = isset($unit['Price']) && is_numeric($unit['Price']) ? (int) $unit['Price'] : ($paid[$id]['unit_cents'] ?? null);
            }
        }

        // A released hold leaves a sale of 0, which the nightly odd-amount sweep deletes. The verdict
        // still says what was paid for, so the line never reads as nothing paid and nothing taken.
        if ($sale === null) {
            foreach ((array) $r->verdict_lines as $line) {
                if (($id = (int) ($line['product_id'] ?? 0)) > 0 && (int) ($line['paid'] ?? 0) > 0) {
                    $paid[$id] = ['qty' => (int) $line['paid'], 'unit_cents' => null];
                }
            }
        }

        // A failed session has no sale; its cart says what the customer tried to buy. A line the machine
        // could not tie to a product still shows, by the name on the cart.
        $unlinked = [];
        foreach ($failed ? (array) $failed->items : [] as $item) {
            if (($id = (int) ($item['product_id'] ?? 0)) > 0) {
                $paid[$id] = ['qty' => ($paid[$id]['qty'] ?? 0) + (int) $item['qty'], 'unit_cents' => (int) $item['unit_cents']];
            } else {
                $unlinked[] = $item;
            }
        }
        // If the AI did see goods on a failed session, its count stays: that is what would catch them leaving.
        $notApplicable = $failed !== null && (array) $r->items === [];

        $verdict = $r->verdict;
        $answered = $verdict !== null || $r->order_status !== null;
        $seen = [];
        $strangers = [];
        if ($verdict !== null) {
            foreach ((array) $r->verdict_lines as $line) {
                $id = (int) ($line['product_id'] ?? 0);
                if ($id > 0) {
                    $seen[$id] = ['taken' => (int) ($line['taken'] ?? 0), 'not_asked' => $verdict === RecognitionVerdict::INCOMPLETE && ($line['code'] ?? null) === null];
                } else {
                    $strangers[(string) ($line['code'] ?? '?')] = (int) ($line['taken'] ?? 0);
                }
            }
        } else {
            // An answer with no verdict: match what it saw to our products by barcode where it can.
            $byBarcode = collect($products)->filter(fn (Product $p) => filled($p->barcode))->keyBy(fn (Product $p) => trim((string) $p->barcode));
            foreach ((array) $r->items as $code => $number) {
                $product = $byBarcode->get((string) $code);
                if ($product) {
                    $seen[$product->id] = ['taken' => (int) $number, 'not_asked' => true];
                } else {
                    $strangers[(string) $code] = (int) $number;
                }
            }
        }

        // A person's count (FreezerSaleCorrection): a product it leaves out was counted as none taken.
        $correction = ((array) $sale?->meta_json)['correction'] ?? null;
        $adjusted = is_array($correction) ? array_map('intval', (array) ($correction['basket'] ?? [])) : null;
        $adjustedPrices = is_array($correction) ? array_column((array) ($correction['lines'] ?? []), 'unit_cents', 'product_id') : [];

        // A product with no price on the sale (taken but not paid, or the sale is gone): the machine's
        // at that moment.
        $at = $sale?->transaction_datetime ? Carbon::parse($sale->transaction_datetime) : ($r->created_at ? Carbon::parse($r->created_at) : null);
        $isTest = $sale && FreezerTestSale::is($sale);
        $priced = array_keys(array_filter($paid, fn (array $line) => ($line['unit_cents'] ?? null) !== null));
        $extras = array_values(array_diff(array_keys($paid + $seen + ($adjusted ?? [])), $priced));
        $shelf = $extras !== [] && $r->vend_id
            ? ($isTest
                ? array_fill_keys($extras, FreezerTestSale::UNIT_PRICE_CENTS)
                : $payments->shelfPrices((int) $r->vend_id, $extras, $at))
            : [];

        // Happy Hour: what the sale was tagged with (a single sale's header, a basket's item rows),
        // and for an extra unit the slot that priced it at that moment.
        $hh = [];
        if ($sale?->is_multiple) {
            foreach ($sale->vendTransactionItems as $item) {
                $hh[(int) $item->product_id] ??= $happyHour[(int) $item->happy_hour_slot_id] ?? null;
            }
        } elseif ($sale?->happy_hour_slot_id) {
            $hh = array_fill_keys(array_keys($paid), $happyHour[(int) $sale->happy_hour_slot_id] ?? null);
        }
        if ($extras !== [] && $r->vend_id && $at && ! $isTest) {
            foreach (HappyHourPricing::discountsAt((int) $r->vend_id, $extras, $at) as $id => $pct) {
                $hh[$id] = CampaignLabelFilter::happyHourLabel($pct);
            }
        }

        $lines = [];
        foreach (array_keys($paid + $seen + ($adjusted ?? [])) as $id) {
            $product = $products[$id] ?? null;
            $qty = (int) ($paid[$id]['qty'] ?? 0);
            $taken = $notApplicable ? null : ($seen[$id]['taken'] ?? ($answered ? 0 : null));
            $lines[] = [
                'product_id' => $id,
                'name' => $product?->name ?? __('product :id', ['id' => $id]),
                'code' => $product?->code,
                'photo' => $product?->thumbnail?->full_url,
                'unit_cents' => $paid[$id]['unit_cents'] ?? $adjustedPrices[$id] ?? $shelf[$id] ?? null,
                'happy_hour' => $hh[$id] ?? null,
                'paid' => $qty,
                'taken' => $taken,
                'adjusted' => $adjusted === null ? null : ($adjusted[$id] ?? 0),
                'delta' => $taken === null ? null : $taken - $qty,
                'state' => match (true) {
                    $notApplicable => 'na',
                    ! $answered => 'pending',
                    $qty === 0 && $taken === 0 => 'none',
                    $verdict === null || ($seen[$id]['not_asked'] ?? false) => 'unknown',
                    $taken > $qty => 'extra',
                    $taken < $qty => 'missing',
                    default => 'ok',
                },
            ];
        }
        foreach ($strangers as $code => $taken) {
            $lines[] = ['product_id' => null, 'name' => (string) $code, 'code' => null, 'photo' => null, 'unit_cents' => null, 'happy_hour' => null, 'paid' => 0, 'taken' => $taken, 'adjusted' => $adjusted === null ? null : 0, 'delta' => $taken, 'state' => 'unknown'];
        }
        foreach ($unlinked as $item) {
            $lines[] = ['product_id' => null, 'name' => (string) ($item['name'] ?? $item['sku'] ?? '?'), 'code' => $item['sku'] ?? null, 'photo' => null, 'unit_cents' => (int) $item['unit_cents'], 'happy_hour' => null, 'paid' => (int) $item['qty'], 'taken' => null, 'adjusted' => null, 'delta' => null, 'state' => 'na'];
        }
        $order = ['extra' => 0, 'missing' => 1, 'unknown' => 2, 'pending' => 3, 'na' => 3, 'ok' => 4, 'none' => 5];
        usort($lines, fn (array $a, array $b) => $order[$a['state']] <=> $order[$b['state']]);

        return $lines;
    }

    /** The person's correction beside the AI's verdict, and what it still says should go back. */
    private function correctionOf(SmartFreezerRecognition $r, ?VendTransaction $sale, ?CardPaymentIntent $card): ?array
    {
        $correction = ((array) $sale?->meta_json)['correction'] ?? null;
        if (! is_array($correction)) {
            return null;
        }
        $collected = $card ? (int) $card->captured_cents - (int) $card->refunded_cents : null;

        return [
            'by' => $correction['by']['name'] ?? null,
            // The person's count equals what the AI saw: they confirmed it, nothing was corrected (Brian, 2026-11-12).
            'confirms_ai' => PersonCount::confirmsAi($r->verdict, (array) $r->verdict_lines, $correction),
            'at' => isset($correction['at']) ? Carbon::parse($correction['at'])->format('ymd h:i a') : null,
            'lines' => array_values((array) ($correction['lines'] ?? [])),
            'value_cents' => (int) ($correction['value_cents'] ?? 0),
            'to_refund_cents' => $card?->state === CardPaymentIntent::STATE_CAPTURED ? max(0, $collected - (int) ($correction['value_cents'] ?? 0)) : 0,
        ];
    }

    /**
     * T05 holds waiting for a person whose door session has no row on this page at all — Zijia
     * never pushed its videos, or the device never confirmed the door and named no session — so
     * there is nowhere else to key in the charge.
     *
     * @return list<array<string, mixed>>
     */
    private function sessionlessHolds(CardPaymentService $payments, ?int $operatorId): array
    {
        return CardPaymentIntent::query()
            ->with('vend:id,code,code_prefix')
            ->where('state', CardPaymentIntent::STATE_AWAITING_AI)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('smart_freezer_recognitions as r')
                ->whereColumn('r.vend_id', 'card_payment_intents.vend_id')
                ->whereColumn('r.session_ref', 'card_payment_intents.session_ref'))
            ->when($operatorId !== null, fn ($q) => $q->whereIn('vend_id', Vend::withoutGlobalScopes()->select('id')->where('operator_id', $operatorId)))
            ->orderBy('id')
            ->get()
            ->filter(fn (CardPaymentIntent $i) => $payments->awaitsPerson($i))
            ->map(fn (CardPaymentIntent $i) => [
                'id' => $i->id,
                'vend_label' => $i->vend?->codeLabel(),
                'session_ref' => $i->session_ref ?? $i->reference,
                'reference' => $i->reference,
                'hold_cents' => $i->amount_cents,
                'door_closed_at' => ($i->door_closed_at ?? $i->updated_at)?->format('Y-m-d H:i:s'),
                'person_reason' => CardPaymentService::PERSON_AI_NO_ANSWER,
            ])
            ->values()
            ->all();
    }

    /**
     * Freezer sales of the last WAITING_FOR_VIDEO_MINUTES whose door session has no row yet: a row is
     * born when Zijia pushes the videos, usually about 6 minutes after the door closes, and until then
     * the table looked as if the sale had not happened (Brian, 2026-11-05). Only on an unfiltered view
     * that includes today, since these sales have no AI status or verdict to filter by.
     *
     * @return list<array<string, mixed>>
     */
    private function waitingForVideo(Request $request, ?string $dateFrom, ?string $dateTo, ?int $operatorId): array
    {
        $today = Carbon::today()->toDateString();
        $filtered = filled($request->input('search')) || filled($request->input('codes'))
            || collect(['status', 'verdict', 'processed_by'])->contains(fn (string $f) => ! in_array($request->input($f), [null, '', 'all'], true));
        if ($filtered || ($dateFrom && $dateFrom > $today) || ($dateTo && $dateTo < $today) || (int) $request->input('page', 1) > 1) {
            return [];
        }

        $freezers = Vend::withoutGlobalScopes()->select('id')->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            ->when($operatorId !== null, fn ($q) => $q->where('operator_id', $operatorId));
        $sales = VendTransaction::query()
            ->with('vend:id,code,code_prefix')
            ->whereIn('vend_id', $freezers)
            ->where('transaction_datetime', '>=', Carbon::now()->subMinutes(self::WAITING_FOR_VIDEO_MINUTES))
            ->orderByDesc('transaction_datetime')
            ->get(['id', 'vend_id', 'order_id', 'amount', 'transaction_datetime', 'vend_transaction_json', 'meta_json']);
        $refOf = function (VendTransaction $sale): ?string {
            $frame = is_string($sale->vend_transaction_json) ? json_decode($sale->vend_transaction_json, true) : $sale->vend_transaction_json;

            return ((array) $frame)['SFREF'] ?? null;
        };
        $known = SmartFreezerRecognition::query()->whereIn('session_ref', $sales->map($refOf)->filter()->values())
            ->where('status', '!=', SmartFreezerRecognition::STATUS_SKIPPED)->pluck('session_ref')->flip();
        $waiting = $sales->filter(fn (VendTransaction $sale) => $refOf($sale) !== null && ! $known->has($refOf($sale)));
        $ids = $waiting->flatMap(fn (VendTransaction $sale) => array_map(fn ($u) => (int) ($u['goods_id'] ?? 0), $this->tradeUnits($sale)))->filter()->unique();
        $products = Product::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'code', 'name', 'barcode'])->keyBy('id')->all();

        return $waiting->map(fn (VendTransaction $sale) => [
            'id' => $sale->id,
            'time' => $sale->transaction_datetime?->format('Y-m-d H:i:s'),
            'order_id' => $sale->order_id,
            'session_ref' => $refOf($sale),
            'vend_label' => $sale->vend?->codeLabel(),
            'amount_cents' => (int) $sale->amount,
            'waited_minutes' => (int) floor(($sale->transaction_datetime?->diffInSeconds(Carbon::now(), true) ?? 0) / 60),
            'is_test_sale' => FreezerTestSale::is($sale),
            'items' => array_map(fn (array $line) => ['name' => $line['name'], 'qty' => $line['qty']], $this->purchased($sale, $products, null)),
        ])->values()->all();
    }

    /**
     * @param  array<int, Product>  $products
     * @param  list<string>|null  $submitted  the barcodes sent to the AI, null when nothing was sent
     * @return list<array{product_id: int|null, name: string, code: string|null, qty: int, unit_cents: int|null, has_barcode: bool, barcode: string|null, in_submitted: bool|null}>
     */
    private function purchased(VendTransaction $sale, array $products, ?array $submitted): array
    {
        $lines = [];
        foreach ($this->tradeUnits($sale) as $unit) {
            $id = (int) ($unit['goods_id'] ?? 0);
            $key = $id ?: 'name:'.($unit['goods_name'] ?? '?');
            $product = $products[$id] ?? null;
            $lines[$key] ??= [
                'product_id' => $id ?: null,
                'name' => $product?->name ?? (string) ($unit['goods_name'] ?? 'Unknown product'),
                'code' => $product?->code,
                'qty' => 0,
                'unit_cents' => isset($unit['Price']) && is_numeric($unit['Price']) ? (int) $unit['Price'] : null,
                'has_barcode' => filled($product?->barcode),
                'barcode' => filled($product?->barcode) ? trim((string) $product->barcode) : null,
                // False = it has a barcode now but was not on the list sent (added to the freezer, or
                // its barcode set, after the session was sent), so the AI could not have named it.
                'in_submitted' => $submitted === null || ! filled($product?->barcode) ? null
                    : in_array(trim((string) $product->barcode), $submitted, true),
            ];
            $lines[$key]['qty']++;
        }

        return array_values($lines);
    }

    /**
     * The barcodes sent to the AI with this session (`request_payload.goodsList[].sn`), in the
     * order sent, or null when the session was never sent.
     *
     * @return list<string>|null
     */
    private static function submittedCodes(SmartFreezerRecognition $r): ?array
    {
        $goods = $r->request_payload['goodsList'] ?? null;
        if (! is_array($goods)) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map(fn ($g) => is_array($g) ? trim((string) ($g['sn'] ?? '')) : '', $goods))));
    }

    /** @return list<array<string, mixed>> the TRADE's per-unit entries */
    private function tradeUnits(VendTransaction $sale): array
    {
        $frame = $sale->vend_transaction_json;
        if (is_string($frame)) {
            $frame = json_decode($frame, true);
        }

        return array_values(array_filter((array) (((array) $frame)['transf_info'] ?? []), 'is_array'));
    }

    /**
     * The products named in this page's TRADEs, one query.
     *
     * @return array<int, Product>
     */
    private function purchasedProducts($recognitions, array $failedSessions = []): array
    {
        $carts = collect($failedSessions)->flatMap(fn (FreezerFailedSession $f) => array_map('intval', array_column((array) $f->items, 'product_id')));
        $ids = $recognitions->map(fn (SmartFreezerRecognition $r) => $r->vendTransaction)->filter()
            ->flatMap(fn (VendTransaction $sale) => array_map(fn ($u) => (int) ($u['goods_id'] ?? 0), $this->tradeUnits($sale)))
            ->merge($recognitions->flatMap(fn (SmartFreezerRecognition $r) => array_map('intval', array_column((array) $r->verdict_lines, 'product_id'))))
            ->merge($recognitions->flatMap(fn (SmartFreezerRecognition $r) => array_map('intval', array_keys((array) (((array) $r->vendTransaction?->meta_json)['correction']['basket'] ?? [])))))
            ->merge($carts)
            ->filter()->unique()->values();
        $barcodes = $recognitions->flatMap(fn (SmartFreezerRecognition $r) => array_map('strval', array_keys((array) $r->items)))->unique()->values();

        if ($ids->isEmpty() && $barcodes->isEmpty()) {
            return [];
        }

        return Product::withoutGlobalScopes()->with('thumbnail')
            ->where(fn ($q) => $q->whereIn('id', $ids)->orWhereIn('barcode', $barcodes))
            ->get(['id', 'code', 'name', 'barcode'])->keyBy('id')->all();
    }

    /**
     * Each session's Site: the one the sale was booked to, else the machine's now. Its RP tier is the
     * Site's current one. Unscoped: the row itself is already behind the viewer ceiling.
     *
     * @return array<int, array{id: int, name: string, rp: string|null}>
     */
    private function sites($recognitions): array
    {
        $ids = $recognitions->map(fn (SmartFreezerRecognition $r) => $r->vendTransaction?->customer_id ?? $r->vend?->customer_id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Customer::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'name', 'selling_price_type'])
            ->mapWithKeys(fn (Customer $site) => [$site->id => [
                'id' => $site->id,
                'name' => $site->name,
                'rp' => $site->selling_price_type ? 'RP'.$site->selling_price_type : null,
            ]])->all();
    }

    /**
     * The T05 holds of this page's sessions with their refunds, two queries.
     *
     * @return array<string, CardPaymentIntent>
     */
    private function cardCharges($recognitions, array $failedSessions = []): array
    {
        $refs = $recognitions->pluck('session_ref')->filter()->unique()->values();
        if ($refs->isEmpty()) {
            return [];
        }

        $intents = CardPaymentIntent::query()
            ->whereIn('session_ref', $refs)
            ->whereIn('vend_id', $recognitions->pluck('vend_id')->filter()->unique())
            ->orderBy('id')
            ->get(['id', 'vend_id', 'session_ref', 'state', 'amount_cents', 'captured_cents', 'refunded_cents', 'owed_cents', 'ai_decision', 'last_error', 'approved_at', 'voided_at'])
            ->keyBy(fn (CardPaymentIntent $i) => $i->vend_id.'|'.$i->session_ref);
        foreach ($this->holdsWithoutSession($recognitions, $failedSessions) as $key => $hold) {
            $intents[$key] ??= $hold;
        }

        $refunds = CardRefund::query()
            ->whereIn('card_payment_intent_id', $intents->pluck('id'))
            ->where('state', CardRefund::STATE_REFUNDED)
            ->orderBy('id')
            ->get(['id', 'card_payment_intent_id', 'amount_cents', 'requested_by_name', 'created_at'])
            ->groupBy('card_payment_intent_id');

        return $intents->each(fn (CardPaymentIntent $i) => $i->setRelation('confirmedRefunds', $refunds->get($i->id, collect())))->all();
    }

    /**
     * Holds approved for a door session that never became a sale. The app names a hold's session only
     * in the door-closed capture, so when the lock refuses (2026-11-04, 50001) the hold is voided with
     * no session_ref and the row has no sale. Each such hold goes to the latest session started before
     * it (the ref carries the second it started), and only to a row with no sale. A session the machine
     * reported (app 48+) names its payment, which beats the time.
     *
     * @return array<string, CardPaymentIntent> "vend_id|session_ref" => the hold
     */
    /**
     * url => [href, seconds] for the row's Zijia videos: `href` plays through ZijiaVideoController (their
     * link, else our Spaces copy), `seconds` is the length measured on our copy (null until copied).
     * First push naming a URL wins.
     *
     * @return array<string, array{href: string, seconds: int|null}>
     */
    private function videoLinks(SmartFreezerRecognition $r): array
    {
        $links = [];
        foreach ($r->videos as $video) {
            $kept = $video->relationLoaded('copies')
                ? $video->copies->where('status', \App\Models\SmartFreezerVideoCopy::STATUS_COPIED)->keyBy('url_hash')
                : collect();
            foreach (array_values((array) $video->video_urls) as $index => $url) {
                if (! is_string($url) || isset($links[$url])) {
                    continue;
                }
                $links[$url] = [
                    'href' => route('freezer-zijia-videos.show', ['video' => $video->id, 'index' => $index]),
                    'seconds' => ($ms = $kept->get(\App\Models\SmartFreezerVideoCopy::hashOf($url))?->duration_ms) !== null ? (int) round($ms / 1000) : null,
                ];
            }
        }

        return $links;
    }

    private function holdsWithoutSession($recognitions, array $failedSessions = []): array
    {
        $unsold = $recognitions
            // A "-0" ref is a remote camera check (op `clip`), which no customer pays for.
            ->filter(fn (SmartFreezerRecognition $r) => $r->vend_id && ! $r->vend_transaction_id && self::sessionStart($r->session_ref) !== null && ! str_ends_with((string) $r->session_ref, '-0'))
            ->keyBy(fn (SmartFreezerRecognition $r) => $r->vend_id.'|'.$r->session_ref);
        if ($unsold->isEmpty()) {
            return [];
        }

        $vendIds = $unsold->pluck('vend_id')->unique()->values();
        $firstStart = $unsold->map(fn (SmartFreezerRecognition $r) => self::sessionStart($r->session_ref))->min();
        $lastStart = $unsold->map(fn (SmartFreezerRecognition $r) => self::sessionStart($r->session_ref))->max();
        $from = Carbon::createFromTimestamp($firstStart, config('app.timezone'));
        $to = Carbon::createFromTimestamp($lastStart + self::SESSIONLESS_HOLD_WINDOW_SECONDS, config('app.timezone'));

        $holds = CardPaymentIntent::query()
            ->whereIn('vend_id', $vendIds)
            ->whereNull('session_ref')
            ->whereNotNull('approved_at')
            ->whereIn('state', [CardPaymentIntent::STATE_APPROVED, CardPaymentIntent::STATE_VOIDED, CardPaymentIntent::STATE_VOID_FAILED])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->get(['id', 'vend_id', 'session_ref', 'state', 'amount_cents', 'captured_cents', 'refunded_cents', 'owed_cents', 'payment_method', 'ai_decision', 'last_error', 'created_at', 'reference', 'provider_txn_id', 'approved_at', 'voided_at']);
        if ($holds->isEmpty()) {
            return [];
        }

        // Every session on these machines, not just this page's: a later one with a sale still ends the one before it.
        $starts = SmartFreezerRecognition::query()
            ->whereIn('vend_id', $vendIds)
            ->where('created_at', '>=', $from)
            ->whereNotNull('session_ref')
            ->get(['vend_id', 'session_ref'])
            ->groupBy('vend_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (SmartFreezerRecognition $r) => [(string) $r->session_ref => self::sessionStart($r->session_ref)])->filter()->sort());

        $reported = collect($failedSessions)
            ->filter(fn (FreezerFailedSession $f, string $key) => $f->payment_ref && $unsold->has($key))
            ->mapWithKeys(fn (FreezerFailedSession $f, string $key) => [$f->vend_id.'|'.$f->payment_ref => $key]);

        $found = [];
        $named = [];
        foreach ($holds as $hold) {
            $session = $reported->get($hold->vend_id.'|'.$hold->provider_txn_id) ?? $reported->get($hold->vend_id.'|'.$hold->reference);
            if ($session !== null) {
                $named[$session] = $hold;

                continue;
            }
            $at = $hold->created_at->timestamp;
            $sessions = ($starts->get($hold->vend_id) ?? collect())->filter(fn (int $start) => $start <= $at);
            $ref = $sessions->keys()->last();
            $key = $hold->vend_id.'|'.$ref;
            if ($ref !== null && $unsold->has($key) && $at - $sessions->last() <= self::SESSIONLESS_HOLD_WINDOW_SECONDS) {
                $found[$key] = $hold;
            }
        }

        return $named + $found;
    }

    /**
     * What the machines reported about this page's sessions that ended in an error.
     *
     * @return array<string, FreezerFailedSession> "vend_id|session_ref" => the report
     */
    private function failedSessions($recognitions): array
    {
        $refs = $recognitions->pluck('session_ref')->filter()->unique()->values();
        if ($refs->isEmpty()) {
            return [];
        }

        return FreezerFailedSession::query()
            ->whereIn('session_ref', $refs)
            ->whereIn('vend_id', $recognitions->pluck('vend_id')->filter()->unique()->values())
            ->get()
            ->keyBy(fn (FreezerFailedSession $f) => $f->vend_id.'|'.$f->session_ref)
            ->all();
    }

    /** The app's error line for this session, else what the machine reported at the end of it. */
    private function doorError(SmartFreezerRecognition $r, array $doorErrors, array $failedSessions): ?array
    {
        $key = $r->vend_id.'|'.$r->session_ref;
        $failed = $r->vendTransaction === null ? ($failedSessions[$key] ?? null) : null;
        if (isset($doorErrors[$key]) || $failed === null) {
            return $doorErrors[$key] ?? null;
        }
        $fault = DoorFault::fromReport($failed->reason, $failed->detail);

        return $fault + ['plain' => DoorFault::plain($fault['code']), 'at' => $failed->ended_at?->format('H:i:s')];
    }

    /**
     * How the failed door went (FreezerDoorTimeline): tries, waits, the sorry page and the hold's times. Only a
     * session the machine reported as failed has one.
     */
    private function doorTimeline(SmartFreezerRecognition $r, array $doorErrors, array $failedSessions, array $cards): ?array
    {
        $key = $r->vend_id.'|'.$r->session_ref;
        $failed = $r->vendTransaction === null ? ($failedSessions[$key] ?? null) : null;

        return $failed === null ? null : FreezerDoorTimeline::build($failed, $cards[$key] ?? null, $doorErrors[$key]['tries'] ?? null);
    }

    /**
     * Why a session's door failed, whatever it was paid with. The app files a failed door as one line naming
     * the session, and the machine's own words are the result. It files one line per 2 minutes, so a failure
     * right after another goes unlogged (50001's session 7, 2026-11-04).
     *
     * @return array<string, array{code: string, text: string, plain: string, at: ?string, tries: ?int}> "vend_id|session_ref" => the error
     */
    private function doorErrors($recognitions): array
    {
        $starts = $recognitions
            ->filter(fn (SmartFreezerRecognition $r) => $r->vend_id)
            ->map(fn (SmartFreezerRecognition $r) => self::sessionStart($r->session_ref))
            ->filter();
        if ($starts->isEmpty()) {
            return [];
        }

        return FreezerControlCommand::query()
            ->whereIn('vend_id', $recognitions->pluck('vend_id')->filter()->unique()->values())
            ->where('op', 'error:BoxVmc')
            ->where(fn ($q) => $q->where('response_msg', 'like', 'Door refused for %')->orWhere('response_msg', 'like', 'Door call never reached host for %'))
            ->whereBetween('created_at', [
                Carbon::createFromTimestamp($starts->min(), config('app.timezone')),
                Carbon::createFromTimestamp($starts->max() + self::SESSIONLESS_HOLD_WINDOW_SECONDS, config('app.timezone')),
            ])
            ->orderBy('id')
            ->get(['vend_id', 'response_msg', 'created_at'])
            ->mapWithKeys(function (FreezerControlCommand $c) {
                $fault = DoorFault::fromLog((string) $c->response_msg);

                return $fault === null ? [] : [$c->vend_id.'|'.$fault['session_ref'] => [
                    'code' => $fault['code'],
                    'text' => $fault['text'],
                    'plain' => DoorFault::plain($fault['code']),
                    'at' => $c->created_at?->format('H:i:s'),
                    'tries' => $fault['tries'],
                ]];
            })
            ->all();
    }

    /** The second a kiosk session started: "SF-50001-1793784418-6" => 1793784418. */
    private static function sessionStart(?string $sessionRef): ?int
    {
        return preg_match('/^SF-.+-(\d{9,})-\d+$/', (string) $sessionRef, $m) ? (int) $m[1] : null;
    }

    /** Payrallel's card type as a person reads it. */
    private static function cardBrand(?string $method): ?string
    {
        return match ($method) {
            null, '' => null,
            'master' => 'Mastercard',
            default => ucfirst($method),
        };
    }

    /** "501 recognition error — 503 goods not listed in the model (商品未上架)", or null before a result. */
    private function algorithmStatus(SmartFreezerRecognition $r): ?string
    {
        if ($r->order_status === null) {
            return null;
        }
        $biz = json_decode((string) ($r->callback_payload['bizContent'] ?? ''), true);

        try {
            return RecognitionResult::fromBizContent(is_array($biz) ? $biz : ['tradeId' => $r->trade_id, 'orderStatus' => $r->order_status])->statusLabel();
        } catch (InvalidArgumentException) {
            return (string) $r->order_status;
        }
    }

    /**
     * Product names for one page, in two queries: by the barcodes the algorithm answered with or
     * was sent, and by the product ids on verdict lines.
     *
     * @return array{by_id: array<int, string>, by_code: array<string, string>}
     */
    private function productNames($recognitions): array
    {
        $codes = $recognitions->flatMap(fn ($r) => array_merge(
            array_map('strval', array_keys((array) $r->items)),
            self::submittedCodes($r) ?? [],
        ))->unique()->values();
        $ids = $recognitions->flatMap(fn ($r) => array_column((array) $r->verdict_lines, 'product_id'))->filter()->unique()->values();

        return [
            'by_code' => $codes->isEmpty() ? [] : Product::withoutGlobalScopes()->whereIn('barcode', $codes)->orderBy('id')->get(['barcode', 'name'])
                ->unique('barcode')->pluck('name', 'barcode')->all(),
            'by_id' => $ids->isEmpty() ? [] : Product::withoutGlobalScopes()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        ];
    }
}
