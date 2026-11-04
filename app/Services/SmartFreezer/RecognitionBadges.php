<?php

namespace App\Services\SmartFreezer;

use App\Models\CardPaymentIntent;
use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\Zijia\RecognitionResult;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * The "AI Recognition" cell of Sales Transactions: for one page of sales, the AI check of each
 * sale's door session, as badge data.
 *
 * Nothing is stored on vend_transactions. A sale is found by the recognition's
 * `vend_transaction_id` (set once the verdict is judged) or, before that, by the TRADE's own
 * `SFREF` = the recognition's `session_ref` — so a session still waiting for, or on, the AI shows
 * too. Two indexed lookups per page, whatever the page size; rows of other machine kinds carry
 * no SFREF and cost nothing. Built for any AI-checked machine (Smart Freezer today; a CityBox
 * chiller when its order data arrives).
 */
class RecognitionBadges
{
    /**
     * @param  iterable<object>  $sales  rows with `id` and `vend_transaction_json`
     * @return array<int, array<string, mixed>> sale id => badge data
     */
    public function forSales(iterable $sales): array
    {
        $sales = collect($sales);
        $ids = $sales->pluck('id')->filter()->unique()->values();
        $refs = $sales->mapWithKeys(fn ($s) => [$s->id => $this->sessionRef($s)])->filter();
        if ($ids->isEmpty()) {
            return [];
        }

        $columns = ['id', 'vend_transaction_id', 'session_ref', 'trade_id', 'status', 'status_reason', 'order_status',
            'callback_payload', 'verdict', 'verdict_lines', 'created_at', 'submitted_at', 'completed_at'];
        $byId = SmartFreezerRecognition::query()->whereIn('vend_transaction_id', $ids)->orderBy('id')->get($columns)
            ->keyBy('vend_transaction_id');
        $byRef = $refs->isEmpty() ? collect() : SmartFreezerRecognition::query()->whereIn('session_ref', $refs->values()->unique())
            ->orderBy('id')->get($columns)->keyBy('session_ref');

        $matched = [];
        foreach ($sales as $sale) {
            $recognition = $byId->get($sale->id) ?? (isset($refs[$sale->id]) ? $byRef->get($refs[$sale->id]) : null);
            if ($recognition !== null) {
                $matched[$sale->id] = $recognition;
            }
        }
        // The session's T05 hold: keyed in by a person, or released. One query for the page.
        $holds = collect($matched)->isEmpty() ? collect() : CardPaymentIntent::query()
            ->whereIn('session_ref', collect($matched)->pluck('session_ref')->filter()->unique()->values())
            ->orderBy('id')->get(['session_ref', 'state', 'ai_decision'])->keyBy('session_ref');

        $saleById = $sales->keyBy('id');
        $out = [];
        foreach ($matched as $saleId => $recognition) {
            $hold = $holds->get($recognition->session_ref);
            $out[$saleId] = $this->badge($recognition) + [
                'handled' => $this->handled($saleById->get($saleId), $hold),
                // Nothing was charged, so nothing is left to do, whoever released it.
                'released' => $hold?->state === CardPaymentIntent::STATE_VOIDED,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function badge(SmartFreezerRecognition $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'verdict' => $r->verdict,
            'reason' => $this->reason($r),
            'mismatch' => collect((array) $r->verdict_lines)->filter(fn ($l) => ($l['delta'] ?? 0) !== 0)->values()->all(),
            // Processing time, in seconds: the AI's own turnaround (sent → result), and door
            // close → result (push received → result), which adds our wait and any hold-up.
            'ai_seconds' => $this->seconds($r->submitted_at, $r->completed_at),
            'total_seconds' => $this->seconds($r->created_at, $r->completed_at),
            'received_at' => $r->created_at?->format('Y-m-d H:i:s'),
            'submitted_at' => $r->submitted_at?->format('Y-m-d H:i:s'),
            'completed_at' => $r->completed_at?->format('Y-m-d H:i:s'),
            'trade_id' => $r->trade_id,
        ];
    }

    /**
     * Plain words for ops (the Sales Transactions tooltip) — the technical wording stays on the AI
     * Recognition page and in `status_reason`. Keyed by the finer `jsOrderStatus` when the callback
     * carries one, else `orderStatus` (Zijia's §6.2 codes).
     */
    public const PLAIN_REASONS = [
        401 => 'the video was unclear or incomplete',
        501 => 'the camera check could not work it out',
        502 => 'the items were handled unusually',
        503 => 'a product is not set up for camera checking yet',
        504 => 'a product\'s packaging has changed',
        505 => 'unsafe handling was seen',
    ];

    private function reason(SmartFreezerRecognition $r): ?string
    {
        if ($r->order_status !== null && $r->order_status !== RecognitionResult::STATUS_NORMAL) {
            $biz = json_decode((string) ($r->callback_payload['bizContent'] ?? ''), true);
            try {
                $result = RecognitionResult::fromBizContent(is_array($biz) ? $biz : ['tradeId' => $r->trade_id, 'orderStatus' => $r->order_status]);
                $code = $result->jsOrderStatus ?: $result->orderStatus;

                return 'Reason: '.(self::PLAIN_REASONS[$code] ?? self::PLAIN_REASONS[$result->orderStatus] ?? $result->statusLabel());
            } catch (InvalidArgumentException) {
                return "Reason: camera check status {$r->order_status}";
            }
        }

        return $r->status_reason !== null ? 'Note: '.$r->status_reason : null;
    }

    /**
     * A person has dealt with this sale: their count (`meta_json.correction`, FreezerSaleCorrection) or
     * their keyed-in charge (`ai_decision.by_person`). The AI's verdict is kept; this only says it is settled.
     *
     * @return array{kind: string, by: ?string}|null
     */
    private function handled(?object $sale, ?CardPaymentIntent $intent): ?array
    {
        $meta = $sale->meta_json ?? null;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        $correction = is_array($meta) ? ($meta['correction'] ?? null) : null;
        if (is_array($correction)) {
            return ['kind' => 'adjusted', 'by' => $correction['by']['name'] ?? null];
        }
        $person = $intent?->ai_decision['by_person'] ?? null;

        return is_array($person) ? ['kind' => 'keyed_in', 'by' => $person['name'] ?? null] : null;
    }

    private function sessionRef(object $sale): ?string
    {
        $json = $sale->vend_transaction_json ?? null;
        if (is_string($json)) {
            $json = json_decode($json, true);
        }
        $ref = is_array($json) ? ($json['SFREF'] ?? null) : null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    private function seconds(?Carbon $from, ?Carbon $to): ?int
    {
        return $from !== null && $to !== null ? max(0, (int) $from->diffInSeconds($to, true)) : null;
    }
}
