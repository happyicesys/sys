<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\CardPaymentIntent;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Services\SmartFreezer\RecognitionVerdict;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use App\Support\VendCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
    private const SORTS = ['created_at', 'status', 'verdict', 'completed_at'];

    public function __construct()
    {
        $this->middleware(['permission:read ai-recognition']);
    }

    public function index(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i'],
            'numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/'],
        ]);

        $numberPerPage = $request->numberPerPage ?: 100;
        $sortKey = in_array($request->sortKey, self::SORTS, true) ? $request->sortKey : 'created_at';
        $sortBy = filter_var($request->sortBy ?? false, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        $query = SmartFreezerRecognition::query()
            ->with([
                'vend:id,code,code_prefix,operator_id',
                'vendTransaction:id,order_id,amount,transaction_datetime',
                'videos:id,smart_freezer_recognition_id,video_urls,created_at',
            ])
            ->when($request->input('status'), fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
            ->when($request->input('verdict'), fn ($q, $v) => match ($v) {
                'all' => $q,
                'none' => $q->whereNull('verdict'),
                default => $q->where('verdict', $v),
            })
            // A time narrows its date to "from 14:00" / "to 16:30"; with no date it is a time-of-day
            // window over every day (e.g. all door sessions between 12:00 and 14:00).
            ->when($request->input('date_from'), fn ($q, $d) => $q->where('created_at', '>=', $d.' '.($request->input('time_from') ?: '00:00').':00'))
            ->when($request->input('date_to'), fn ($q, $d) => $q->where('created_at', '<=', $d.' '.($request->input('time_to') ?: '23:59').':59'))
            ->when(! $request->input('date_from') && $request->input('time_from'), fn ($q) => $q->whereTime('created_at', '>=', $request->input('time_from').':00'))
            ->when(! $request->input('date_to') && $request->input('time_to'), fn ($q) => $q->whereTime('created_at', '<=', $request->input('time_to').':59'))
            // Machine ID box: "50001", "C6003", or a comma list for an exact set — the Sales grid's rule.
            ->when(trim((string) $request->input('codes')), fn ($q, $codes) => $q->whereIn('vend_id', Vend::withoutGlobalScopes()->select('id')
                ->where(fn ($v) => VendCode::whereSearch($v, $codes))))
            ->when(trim((string) $request->input('search')), function ($q, $search) {
                $q->where(fn ($q) => $q
                    ->where('trade_id', 'LIKE', "%{$search}%")
                    ->orWhere('session_ref', 'LIKE', "%{$search}%")
                    ->orWhere('device_id', 'LIKE', "%{$search}%")
                    ->orWhereIn('vend_id', Vend::withoutGlobalScopes()->select('id')->where(fn ($v) => VendCode::whereSearch($v, $search))));
            })
            ->orderBy($sortKey, $sortBy)
            ->orderByDesc('id');

        // Viewer ceiling (mark1 CLAUDE.md, operator isolation): this table has no scope of its own.
        // An operator-restricted viewer sees only its own machines' sessions; a session no freezer
        // was matched to (a supplier test cabinet) is visible to the unrestricted operator only.
        if (($operatorId = OperatorVendFilterScope::viewerOperatorId()) !== null) {
            $query->whereIn('vend_id', Vend::withoutGlobalScopes()->select('id')->where('operator_id', $operatorId));
        }

        $page = $query->paginate($numberPerPage === 'All' ? 10000 : (int) $numberPerPage)->withQueryString();
        $names = $this->productNames($page->getCollection());
        $cards = $this->cardCharges($page->getCollection());

        $page->through(fn (SmartFreezerRecognition $r) => $this->row($r, $names, $cards));

        return Inertia::render('AiRecognition/Index', [
            // Resource-collection shape (data / links / meta) — what Components/Paginator.vue reads.
            'recognitions' => JsonResource::collection($page),
            'statuses' => [
                SmartFreezerRecognition::STATUS_PENDING,
                SmartFreezerRecognition::STATUS_SUBMITTING,
                SmartFreezerRecognition::STATUS_SUBMITTED,
                SmartFreezerRecognition::STATUS_COMPLETED,
                SmartFreezerRecognition::STATUS_FAILED,
            ],
            'verdicts' => [
                RecognitionVerdict::MATCH, RecognitionVerdict::TOOK_MORE, RecognitionVerdict::TOOK_LESS,
                RecognitionVerdict::MIXED, RecognitionVerdict::UNRECOGNISED, RecognitionVerdict::INCOMPLETE,
            ],
            'filters' => [
                'status' => $request->input('status', 'all'),
                'verdict' => $request->input('verdict', 'all'),
                'search' => $request->input('search', ''),
                'codes' => $request->input('codes', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'time_from' => $request->input('time_from', ''),
                'time_to' => $request->input('time_to', ''),
                'sortKey' => $sortKey,
                'sortBy' => $request->sortBy ?? false,
            ],
        ]);
    }

    /**
     * @param  array{by_id: array<int, string>, by_code: array<string, string>}  $names
     * @param  array<string, CardPaymentIntent>  $cards  "vend_id|session_ref" => the session's T05 hold
     */
    private function row(SmartFreezerRecognition $r, array $names, array $cards): array
    {
        $sale = $r->vendTransaction;
        $card = $r->session_ref ? ($cards[$r->vend_id.'|'.$r->session_ref] ?? null) : null;

        return [
            'id' => $r->id,
            'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
            'completed_at' => $r->completed_at?->format('Y-m-d H:i:s'),
            'vend_id' => $r->vend_id,
            'vend_label' => $r->vend?->codeLabel(),
            'device_id' => $r->device_id,
            'trade_id' => $r->trade_id,
            'session_ref' => $r->session_ref,
            'status' => $r->status,
            'status_reason' => $r->status_reason,
            'algorithm_status' => $this->algorithmStatus($r),
            'callback_verified' => $r->callback_verified,
            'request_id' => $r->request_id,
            'videos' => $r->videos->flatMap(fn ($v) => (array) $v->video_urls)->unique()->values()->all(),
            'pushes' => $r->videos->count(),
            // What the algorithm saw: barcode => units, named where the barcode is on one of our products.
            'items' => collect((array) $r->items)->map(fn ($n, $code) => [
                'code' => (string) $code,
                'name' => $names['by_code'][(string) $code] ?? null,
                'number' => (int) $n,
            ])->values()->all(),
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
            ] : null,
            // A T05 hold this session's verdict charges (CardPaymentService::settleAwaitingAi).
            'card' => $card ? [
                'state' => $card->state,
                'hold_cents' => $card->amount_cents,
                'captured_cents' => $card->captured_cents,
                'owed_cents' => $card->owed_cents,
                'judged_cents' => $card->ai_decision['judged_cents'] ?? null,
                'charges' => count((array) ($card->ai_decision['charges'] ?? [])),
                'paid' => count((array) ($card->ai_decision['paid'] ?? [])),
                'reason' => $card->ai_decision['reason'] ?? null,
                'error' => $card->last_error,
            ] : null,
        ];
    }

    /**
     * The T05 holds of this page's sessions, one query.
     *
     * @return array<string, CardPaymentIntent>
     */
    private function cardCharges($recognitions): array
    {
        $refs = $recognitions->pluck('session_ref')->filter()->unique()->values();
        if ($refs->isEmpty()) {
            return [];
        }

        return CardPaymentIntent::query()
            ->whereIn('session_ref', $refs)
            ->whereIn('vend_id', $recognitions->pluck('vend_id')->filter()->unique())
            ->orderBy('id')
            ->get(['id', 'vend_id', 'session_ref', 'state', 'amount_cents', 'captured_cents', 'owed_cents', 'ai_decision', 'last_error'])
            ->keyBy(fn (CardPaymentIntent $i) => $i->vend_id.'|'.$i->session_ref)
            ->all();
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
     * Product names for one page, in two queries: by the barcodes the algorithm answered with,
     * and by the product ids on verdict lines.
     *
     * @return array{by_id: array<int, string>, by_code: array<string, string>}
     */
    private function productNames($recognitions): array
    {
        $codes = $recognitions->flatMap(fn ($r) => array_map('strval', array_keys((array) $r->items)))->unique()->values();
        $ids = $recognitions->flatMap(fn ($r) => array_column((array) $r->verdict_lines, 'product_id'))->filter()->unique()->values();

        return [
            'by_code' => $codes->isEmpty() ? [] : Product::withoutGlobalScopes()->whereIn('barcode', $codes)->orderBy('id')->get(['barcode', 'name'])
                ->unique('barcode')->pluck('name', 'barcode')->all(),
            'by_id' => $ids->isEmpty() ? [] : Product::withoutGlobalScopes()->whereIn('id', $ids)->pluck('name', 'id')->all(),
        ];
    }
}
