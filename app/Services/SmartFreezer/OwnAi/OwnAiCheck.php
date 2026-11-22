<?php

namespace App\Services\SmartFreezer\OwnAi;

use App\Models\Product;
use App\Models\SmartFreezerOwnAiCheck;
use App\Models\SmartFreezerOwnAiRecognition;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\VendChannel;
use App\Services\Freezer\FreezerSaleAudit;
use DomainException;

/**
 * A person checks HappyIce AI's count of one door session (Brian, 2026-11-22: "a button at Happy ice AI ... mark it as
 * accurate or ... tell the answer straight over there, so every 3 AM you can train based on the truth there"). Right =
 * HappyIce AI's count is the truth; wrong = the person keys in what really left the cabinet, with a note on what to tune.
 * The office Macs read it through their read-only export and train on it at 03:15.
 * SHADOW ONLY: it never charges, refunds or moves stock -- Adjust Qty does that.
 */
class OwnAiCheck
{
    public const MAX_UNITS = 20;

    public function __construct(private readonly OwnAiLink $link, private readonly FreezerSaleAudit $audit) {}

    /** The answer the page shows (mini before M1, newest), as in the AI Recognition column. */
    public function answerOf(SmartFreezerRecognition $r): ?SmartFreezerOwnAiRecognition
    {
        $order = array_flip((array) config('smart_freezer.own_ai.worker_priority', []));

        return SmartFreezerOwnAiRecognition::where('smart_freezer_recognition_id', $r->id)->get()
            ->sortBy([
                fn ($a, $b) => ($order[$a->worker] ?? PHP_INT_MAX) <=> ($order[$b->worker] ?? PHP_INT_MAX),
                fn ($a, $b) => $b->received_at <=> $a->received_at,
            ])->first();
    }

    /** What the check form needs: each product with what was paid, what Zijia and HappyIce AI saw, and the count so far. */
    public function editor(SmartFreezerRecognition $r): array
    {
        $answer = $this->answerOf($r);
        $check = SmartFreezerOwnAiCheck::with('checker:id,name')->where('smart_freezer_recognition_id', $r->id)->first();
        $own = $this->units((array) ($answer?->items ?? []));
        $zijia = [];
        foreach ((array) $r->verdict_lines as $line) {
            if ((int) ($line['product_id'] ?? 0) > 0) {
                $zijia[(int) $line['product_id']] = ($zijia[(int) $line['product_id']] ?? 0) + (int) ($line['taken'] ?? 0);
            }
        }
        $sale = $this->link->saleOf($r);
        $paid = $sale ? array_map('intval', $this->audit->paidUnits($sale)) : [];
        $start = $check && $check->outcome !== SmartFreezerOwnAiCheck::UNCLEAR ? $this->units((array) $check->items) : $own;

        $machine = $r->vend_id ? VendChannel::where('vend_id', $r->vend_id)->where('is_active', true)->whereNotNull('product_id')
            ->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
        $ids = array_values(array_unique(array_filter(array_merge(array_keys($paid), array_keys($zijia), array_keys($own), array_keys($start), $machine))));
        $products = Product::withoutGlobalScopes()->with('thumbnail')->whereIn('id', $ids)->get(['id', 'name', 'code'])->keyBy('id');

        $lines = array_map(fn (int $id) => [
            'product_id' => $id,
            'name' => (string) ($products[$id]->name ?? __('product :id', ['id' => $id])),
            'code' => $products[$id]->code ?? null,
            'photo' => ($products[$id] ?? null)?->thumbnail?->full_url,
            'paid' => $paid[$id] ?? 0,
            'zijia' => $zijia[$id] ?? 0,
            'happyice' => $own[$id] ?? 0,
            'taken' => $start[$id] ?? 0,
        ], $ids);
        usort($lines, function (array $a, array $b) {
            $used = fn (array $l) => $l['paid'] + $l['zijia'] + $l['happyice'] + $l['taken'] > 0;

            return [$used($b), $a['name']] <=> [$used($a), $b['name']];
        });

        return [
            'recognition_id' => $r->id,
            'order_id' => $sale?->order_id,
            'answered' => $answer !== null,
            'zijia_answered' => $r->verdict !== null,
            'lines' => $lines,
            'max_units' => self::MAX_UNITS,
            'check' => $check ? $this->summary($check) : null,
        ];
    }

    /**
     * @param  array<int|string, int>  $taken  product id => units that really left the cabinet (only for "wrong")
     *
     * @throws DomainException when HappyIce AI has not answered, or "wrong" names the same count HappyIce AI gave
     */
    public function save(SmartFreezerRecognition $r, string $outcome, array $taken, ?string $note, User $by): SmartFreezerOwnAiCheck
    {
        $answer = $this->answerOf($r);
        if ($answer === null) {
            throw new DomainException(__('HappyIce AI has not answered this session yet.'));
        }
        $own = $this->units((array) $answer->items);
        $truth = match ($outcome) {
            SmartFreezerOwnAiCheck::RIGHT => $own,
            SmartFreezerOwnAiCheck::UNCLEAR => [],          // no truth: the office Macs leave the session out
            default => $this->units($taken),
        };
        if ($outcome === SmartFreezerOwnAiCheck::WRONG && $truth == $own) {
            throw new DomainException(__('That is the same count HappyIce AI gave. Press "HappyIce AI is right" instead, or change the count.'));
        }

        return SmartFreezerOwnAiCheck::updateOrCreate(
            ['smart_freezer_recognition_id' => $r->id],
            [
                'vend_id' => $r->vend_id,
                'outcome' => $outcome,
                'items' => (object) $truth,
                'ai_items' => (object) $own,
                'ai_worker' => $answer->worker,
                'ai_model' => $answer->model,
                'note' => filled($note) ? trim((string) $note) : null,
                'checked_by' => $by->id,
                'checked_at' => now(),
            ],
        );
    }

    /** For the page: who checked, when, and (when wrong) the right count in words. */
    public function summary(SmartFreezerOwnAiCheck $check, array $names = []): array
    {
        $items = $this->units((array) $check->items);
        if ($items && ! $names) {
            $names = Product::withoutGlobalScopes()->whereIn('id', array_keys($items))->pluck('name', 'id')->all();
        }

        return [
            'outcome' => $check->outcome,
            'by' => $check->checker?->name,
            'at' => $check->checked_at?->format('Y-m-d H:i'),
            'note' => $check->note,
            'items' => collect($items)->map(fn ($n, $id) => [
                'product_id' => (int) $id,
                'name' => $names[(int) $id] ?? __('product :id', ['id' => $id]),
                'number' => (int) $n,
            ])->values()->all(),
        ];
    }

    /** @return array<int, int> only units taken, by product id, sorted */
    private function units(array $items): array
    {
        $out = [];
        foreach ($items as $id => $n) {
            if ((int) $id > 0 && (int) $n > 0) {
                $out[(int) $id] = min((int) $n, self::MAX_UNITS);
            }
        }
        ksort($out);

        return $out;
    }
}
