<?php

namespace App\Services\SmartFreezer;

use App\Models\Product;
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
            'items', 'callback_payload', 'verdict', 'verdict_lines', 'created_at', 'submitted_at', 'completed_at'];
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
        // The AI answers in barcodes; one query names them for the whole page.
        $codes = collect($matched)->flatMap(fn ($r) => array_map('strval', array_keys((array) $r->items)))->unique()->values();
        $names = $codes->isEmpty() ? [] : Product::withoutGlobalScopes()->whereIn('barcode', $codes)->orderBy('id')
            ->get(['barcode', 'name'])->unique('barcode')->pluck('name', 'barcode')->all();

        $out = [];
        foreach ($matched as $saleId => $recognition) {
            $out[$saleId] = $this->badge($recognition, $names);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $names  barcode => product name
     * @return array<string, mixed>
     */
    public function badge(SmartFreezerRecognition $r, array $names = []): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'verdict' => $r->verdict,
            'reason' => $this->reason($r),
            // What the AI saw taken.
            'items' => collect((array) $r->items)->map(fn ($n, $code) => [
                'code' => (string) $code, 'name' => $names[(string) $code] ?? null, 'number' => (int) $n,
            ])->values()->all(),
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

    private function reason(SmartFreezerRecognition $r): ?string
    {
        if ($r->order_status !== null && $r->order_status !== RecognitionResult::STATUS_NORMAL) {
            $biz = json_decode((string) ($r->callback_payload['bizContent'] ?? ''), true);
            try {
                return 'AI: '.RecognitionResult::fromBizContent(is_array($biz) ? $biz : ['tradeId' => $r->trade_id, 'orderStatus' => $r->order_status])->statusLabel();
            } catch (InvalidArgumentException) {
                return "AI: status {$r->order_status}";
            }
        }

        return $r->status_reason;
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
