<?php

namespace App\Http\Controllers\SmartFreezer;

use App\Http\Controllers\Controller;
use App\Models\CardPaymentIntent;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\SmartFreezer\FreezerClipLinks;
use App\Services\SmartFreezer\RecognitionBadges;
use App\Services\SmartFreezer\RecognitionVerdict;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use App\Support\VendCode;
use DomainException;
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
        $this->middleware(['permission:charge ai-recognition'])->only('charge');
    }

    public function index(Request $request)
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i'],
            'numberPerPage' => ['nullable', 'regex:/^(\d+|All)$/'],
            'processed_by' => ['nullable', 'in:all,ai,human,none'],
        ]);

        $numberPerPage = $request->numberPerPage ?: 100;
        $sortKey = in_array($request->sortKey, self::SORTS, true) ? $request->sortKey : 'created_at';
        $sortBy = filter_var($request->sortBy ?? false, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

        $query = SmartFreezerRecognition::query()
            ->with([
                'vend:id,code,code_prefix,operator_id',
                'vendTransaction:id,order_id,amount,transaction_datetime,vend_transaction_json',
                'videos:id,smart_freezer_recognition_id,video_urls,created_at',
            ])
            ->when($request->input('status'), fn ($q, $s) => $s !== 'all' ? $q->where('status', $s) : $q)
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
        $purchasedProducts = $this->purchasedProducts($page->getCollection());

        // Our own camera-5 recordings of the same door sessions (smart_freezer_clips), by session ref.
        $clips = app(FreezerClipLinks::class)->linksFor($page->getCollection()
            ->filter(fn (SmartFreezerRecognition $r) => $r->vend_id && $r->session_ref)
            ->mapWithKeys(fn (SmartFreezerRecognition $r) => [$r->id => [(int) $r->vend_id, (string) $r->session_ref]])
            ->all());

        $payments = app(CardPaymentService::class);
        $page->through(fn (SmartFreezerRecognition $r) => $this->row($r, $names, $cards, $purchasedProducts, $payments) + [
            'clips' => $clips[$r->id] ?? [],
        ]);

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
            'canCharge' => (bool) $request->user()?->can('charge ai-recognition'),
            'sessionlessHolds' => $this->sessionlessHolds($payments, $operatorId),
            // Zijia's orderStatus codes (§6.2) with ops' plain words — the legend above the table.
            'orderStatusLegend' => array_map(fn (array $l) => $l + [
                'plain' => RecognitionBadges::PLAIN_REASONS[$l['code']] ?? 'the AI answered on its own',
            ], RecognitionResult::legend()),
            'filters' => [
                'status' => $request->input('status', 'all'),
                'verdict' => $request->input('verdict', 'all'),
                'processed_by' => $request->input('processed_by', 'all'),
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
        }

        $amount = number_format($data['amount_cents'] / 100, 2);

        return match (true) {
            $intent->state === CardPaymentIntent::STATE_VOIDED => back()->with('success', 'Hold released — nothing charged.'),
            $intent->state === CardPaymentIntent::STATE_CAPTURED => back()->with('success', 'Charged '.number_format($intent->captured_cents / 100, 2).' on the T05.'),
            // Kept on the hold either way: an unknown outcome waits for a person checking Payrallel,
            // a refusal is retried by card-payments:reconcile every 10 minutes.
            isset($intent->ai_decision['uncertain']) => back()->withErrors(['amount_cents' => "The charge of {$amount} may have gone through — it is not resent. Check Payrallel's portal."]),
            default => back()->withErrors(['amount_cents' => "Payrallel refused the charge of {$amount} (".($intent->last_error ?? 'no reason given').'). mark1 keeps the amount and retries every 10 minutes.']),
        };
    }

    /**
     * @param  array{by_id: array<int, string>, by_code: array<string, string>}  $names
     * @param  array<string, CardPaymentIntent>  $cards  "vend_id|session_ref" => the session's T05 hold
     * @param  array<int, Product>  $products  the TRADE products of this page, by id
     */
    private function row(SmartFreezerRecognition $r, array $names, array $cards, array $products, CardPaymentService $payments): array
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
            'processed_by' => RecognitionResult::processedBy($r->order_status === null ? null : (int) $r->order_status),
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
            // What the customer paid for, from the sale's TRADE (`transf_info`, one entry per unit).
            // `has_barcode` false = mark1 never asked the AI to look for it, so "not taken" cannot
            // be judged for that product.
            'purchased' => $sale ? $this->purchased($sale, $products) : [],
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
                'id' => $card->id,
                'state' => $card->state,
                'hold_cents' => $card->amount_cents,
                'captured_cents' => $card->captured_cents,
                'owed_cents' => $card->owed_cents,
                'judged_cents' => $card->ai_decision['judged_cents'] ?? null,
                // Payrallel refused to raise the authorisation: the hold was charged, the rest is owed.
                'increment_refused' => isset($card->ai_decision['increment_refused']),
                // A charge that may have gone through; nothing more is sent until resolved
                // (`card-payments:resolve-ai-charge`).
                'uncertain_cents' => $card->ai_decision['uncertain'] ?? null,
                'reason' => $card->ai_decision['reason'] ?? null,
                'error' => $card->last_error,
                // No AI verdict: the hold stays until a person keys in the charge
                // (CardPaymentService::chargeByPerson, which re-checks this).
                'person_reason' => $reason = ($card->state === CardPaymentIntent::STATE_AWAITING_AI ? $payments->personNeededBecause($card) : null),
                'awaits_person' => $reason !== null,
                'by_person' => $card->ai_decision['by_person']['name'] ?? null,
            ] : null,
        ];
    }

    /**
     * T05 holds waiting for a person whose door session has no row on this page at all — Zijia
     * never pushed its videos, so there is nowhere else to key in the charge.
     *
     * @return list<array<string, mixed>>
     */
    private function sessionlessHolds(CardPaymentService $payments, ?int $operatorId): array
    {
        return CardPaymentIntent::query()
            ->with('vend:id,code,code_prefix')
            ->where('state', CardPaymentIntent::STATE_AWAITING_AI)
            ->whereNotNull('session_ref')
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
                'session_ref' => $i->session_ref,
                'reference' => $i->reference,
                'hold_cents' => $i->amount_cents,
                'door_closed_at' => ($i->door_closed_at ?? $i->updated_at)?->format('Y-m-d H:i:s'),
                'person_reason' => CardPaymentService::PERSON_AI_NO_ANSWER,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, Product>  $products
     * @return list<array{product_id: int|null, name: string, code: string|null, qty: int, unit_cents: int|null, has_barcode: bool}>
     */
    private function purchased(VendTransaction $sale, array $products): array
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
            ];
            $lines[$key]['qty']++;
        }

        return array_values($lines);
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
    private function purchasedProducts($recognitions): array
    {
        $ids = $recognitions->map(fn (SmartFreezerRecognition $r) => $r->vendTransaction)->filter()
            ->flatMap(fn (VendTransaction $sale) => array_map(fn ($u) => (int) ($u['goods_id'] ?? 0), $this->tradeUnits($sale)))
            ->filter()->unique()->values();

        return $ids->isEmpty() ? [] : Product::withoutGlobalScopes()->whereIn('id', $ids)
            ->get(['id', 'code', 'name', 'barcode'])->keyBy('id')->all();
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
