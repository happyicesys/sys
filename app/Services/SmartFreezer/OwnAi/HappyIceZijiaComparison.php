<?php

namespace App\Services\SmartFreezer\OwnAi;

use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use App\Models\SmartFreezerOwnAiRecognition;
use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\Claude\ClaudeCostMoney;
use App\Services\SmartFreezer\Claude\ClaudeZijiaComparison;
use App\Services\SmartFreezer\FreezerSaleLocator;
use App\Services\SmartFreezer\PersonCount;
use App\Services\SmartFreezer\RecognitionVerdict;
use Illuminate\Support\Collection;

/**
 * HappyIce AI against Zijia on the same door sessions — the AI Dashboard box (Brian, 2026-11-19: "Macbook/Claude
 * (happyice ai) vs Zijia", replacing "Claude vs Zijia"). HappyIce AI is OUR side as a whole: the office Mac's answer
 * (the mini's first, then the M1's — `own_ai.worker_priority`), else Claude's count where Claude was called instead
 * (it is called only when neither Zijia nor the Mac settled a session). Shadow only; the questions are
 * {@see ClaudeZijiaComparison}'s, each counted only where it can be asked:
 *  - matches paid: our verdict is `match` (the Mac's by OwnAiLink::verdict, Claude's stored one);
 *  - right by a person: a person counted the session (Adjust Qty) and the count equals theirs;
 *  - same count: both answered with the same units of the same products;
 *  - together would settle: Zijia matched, or ours matched and was sure (the Mac auto-accepted / Claude not flagged) —
 *    the sessions no person would have had to look at; of those a person counted, how many would have been charged
 *    wrongly (the paid order is not what the person counted).
 *
 * The box shows UNITS, not sessions (Brian, 2026-11-19 recording: "AI 裡面不要靠 transaction 的 count … 所有的 accuracy
 * 都是 quantity"), counted by the Zijia box's own rule ({@see \App\Http\Controllers\SmartFreezer\FreezerRecognitionController::qtyCheck}):
 * a person's Adjust Qty count is the truth (units ours had right / units it got wrong); with no person, units equal to
 * the paid order are right and any difference is "not checked yet". Zijia's units over the SAME sessions sit beside it.
 * The session figures above stay in the payload; the page no longer shows them.
 */
final class HappyIceZijiaComparison
{
    public function __construct(private readonly OwnAiLink $link, private readonly FreezerSaleLocator $sales) {}

    /**
     * @param  Collection<int, SmartFreezerRecognition>  $recognitions  with `vendTransaction` loaded (meta_json, frame)
     * @param  (callable(SmartFreezerRecognition): array{int, int, int})|null  $zijiaQty  Zijia's [right, adjusted, not
     *                                                                                    checked] units for a session, as the Zijia box counts them
     * @return array<string, int|float|string|null>|null null while no session has an answer of ours
     */
    public function summarise(Collection $recognitions, ?callable $zijiaQty = null): ?array
    {
        $ids = $recognitions->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }
        $order = array_flip((array) config('smart_freezer.own_ai.worker_priority', []));
        $mac = SmartFreezerOwnAiRecognition::whereIn('smart_freezer_recognition_id', $ids)->get()
            ->sortBy([
                fn ($a, $b) => ($order[$a->worker] ?? PHP_INT_MAX) <=> ($order[$b->worker] ?? PHP_INT_MAX),
                fn ($a, $b) => $b->received_at <=> $a->received_at,
            ])
            ->unique('smart_freezer_recognition_id')->keyBy('smart_freezer_recognition_id');
        $claude = ClaudeRow::whereIn('smart_freezer_recognition_id', $ids)->get()->keyBy('smart_freezer_recognition_id');
        if ($mac->isEmpty() && $claude->isEmpty()) {
            return null;
        }

        $s = [
            'sessions' => $recognitions->count(),
            'ours_answered' => 0, 'by_mac' => 0, 'by_claude' => 0, 'zijia_answered' => 0,
            'ours_match_paid' => 0, 'zijia_match_paid' => 0,
            'both_answered' => 0, 'agree' => 0,
            'person_counted' => 0, 'ours_right' => 0, 'zijia_person_counted' => 0, 'zijia_right' => 0,
            'together_judged' => 0, 'together_settle' => 0, 'together_settle_checked' => 0, 'together_settle_wrong' => 0,
            'cost_usd_micros' => (int) $claude->sum('cost_usd_micros'),
            'ours_correct_qty' => 0, 'ours_wrong_qty' => 0, 'ours_unchecked_qty' => 0,
            'zijia_same_correct_qty' => 0, 'zijia_same_total_qty' => 0,
        ];

        foreach ($recognitions as $r) {
            $ours = $this->ours($r, $mac->get($r->id), $claude->get($r->id));
            $zijia = $this->zijiaCount($r);
            $correction = ((array) $r->vendTransaction?->meta_json)['correction'] ?? null;
            $person = is_array($correction) && isset($correction['basket']) ? array_filter(array_map('intval', (array) $correction['basket'])) : null;

            if ($ours !== null) {
                $s['ours_answered']++;
                $s[$ours['by'] === 'mac' ? 'by_mac' : 'by_claude']++;
                $s['ours_match_paid'] += $ours['verdict'] === RecognitionVerdict::MATCH ? 1 : 0;

                // Units, by the Zijia box's rule: a person's count first, else the paid order.
                if ($person !== null) {
                    [$right, $wrong] = self::units($ours['items'], $person);
                    $s['ours_correct_qty'] += $right;
                    $s['ours_wrong_qty'] += $wrong;
                } else {
                    [$right, $differs] = self::units($ours['items'], $this->paid($r));
                    $s['ours_correct_qty'] += $right;
                    $s['ours_unchecked_qty'] += $differs;
                }
                if ($zijiaQty !== null) {
                    [$zRight, $zAdjusted, $zUnchecked] = $zijiaQty($r);
                    $s['zijia_same_correct_qty'] += $zRight;
                    $s['zijia_same_total_qty'] += $zRight + $zAdjusted + $zUnchecked;
                }
            }
            if ($zijia !== null) {
                $s['zijia_answered']++;
                $s['zijia_match_paid'] += $r->verdict === RecognitionVerdict::MATCH ? 1 : 0;
            }
            if ($ours !== null && $zijia !== null) {
                $s['both_answered']++;
                $s['agree'] += ClaudeZijiaComparison::same($ours['items'], $zijia) ? 1 : 0;
            }
            if ($person !== null && $ours !== null) {
                $s['person_counted']++;
                $s['ours_right'] += ClaudeZijiaComparison::same($ours['items'], $person) ? 1 : 0;
            }
            if ($person !== null && $zijia !== null) {
                $s['zijia_person_counted']++;
                $s['zijia_right'] += ClaudeZijiaComparison::same($zijia, $person) ? 1 : 0;
            }
            if ($ours !== null || $zijia !== null) {
                $s['together_judged']++;
                if ($r->verdict === RecognitionVerdict::MATCH || ($ours['settles'] ?? false)) {
                    $s['together_settle']++;
                    if ($person !== null) {
                        $s['together_settle_checked']++;
                        $s['together_settle_wrong'] += ClaudeZijiaComparison::same($this->paid($r), $person) ? 0 : 1;
                    }
                }
            }
        }

        $pct = fn (int $n, int $of) => $of > 0 ? round($n / $of * 100, 1) : null;
        $s['ours_match_paid_pct'] = $pct($s['ours_match_paid'], $s['ours_answered']);
        $s['zijia_match_paid_pct'] = $pct($s['zijia_match_paid'], $s['zijia_answered']);
        $s['ours_total_qty'] = $s['ours_correct_qty'] + $s['ours_wrong_qty'] + $s['ours_unchecked_qty'];
        $s['ours_correct_pct'] = $pct($s['ours_correct_qty'], $s['ours_total_qty']);
        $s['zijia_same_correct_pct'] = $pct($s['zijia_same_correct_qty'], $s['zijia_same_total_qty']);
        $calls = $claude->filter(fn (ClaudeRow $c) => $c->cost_usd_micros !== null)->count();
        $s['cost_text'] = ClaudeCostMoney::text($s['cost_usd_micros']);
        $s['cost_per_session_text'] = $calls > 0 ? ClaudeCostMoney::text(intdiv($s['cost_usd_micros'], $calls)) : null;

        return $s;
    }

    /**
     * Units two counts agree on, and the units they differ by (the larger total less the agreed) — PersonCount's arithmetic.
     *
     * @param  array<int|string, int>  $ours
     * @param  array<int|string, int>  $other
     * @return array{int, int}
     */
    private static function units(array $ours, array $other): array
    {
        $right = 0;
        foreach ($ours as $key => $n) {
            $right += min((int) $n, (int) ($other[$key] ?? 0));
        }

        return [$right, max(array_sum($ours), array_sum($other)) - $right];
    }

    /**
     * Our answer for one session: the Mac's, else Claude's where it counted.
     *
     * @return array{by: string, items: array<int, int>, verdict: ?string, settles: bool}|null
     */
    private function ours(SmartFreezerRecognition $r, ?SmartFreezerOwnAiRecognition $mac, ?ClaudeRow $claude): ?array
    {
        if ($mac !== null) {
            $verdict = $this->link->verdict($mac, $this->link->saleOf($r));

            return [
                'by' => 'mac',
                'items' => array_filter(array_map('intval', (array) $mac->items)),
                'verdict' => $verdict,
                'settles' => $verdict === RecognitionVerdict::MATCH && $mac->auto_accept !== false,
            ];
        }
        if ($claude !== null && $claude->status === ClaudeRow::STATUS_DONE) {
            return [
                'by' => 'claude',
                'items' => array_filter(array_map('intval', (array) $claude->items)),
                'verdict' => $claude->verdict,
                'settles' => $claude->verdict === RecognitionVerdict::MATCH && ! $claude->needs_review,
            ];
        }

        return null;
    }

    /** @return array<int|string, int>|null Zijia's count, null while it has not answered */
    private function zijiaCount(SmartFreezerRecognition $r): ?array
    {
        if ($r->verdict === null) {
            return null;
        }
        $count = [];
        foreach ((array) $r->verdict_lines as $line) {
            $key = PersonCount::key($line);
            $count[$key] = ($count[$key] ?? 0) + (int) ($line['taken'] ?? 0);
        }

        return array_filter($count);
    }

    /** @return array<int, int> product id => units paid: Zijia's verdict lines, else the TRADE */
    private function paid(SmartFreezerRecognition $r): array
    {
        if ($r->verdict !== null) {
            $paid = [];
            foreach ((array) $r->verdict_lines as $line) {
                $id = (int) ($line['product_id'] ?? 0);
                if ($id > 0) {
                    $paid[$id] = ($paid[$id] ?? 0) + (int) ($line['paid'] ?? 0);
                }
            }

            return $paid;
        }
        $sale = $this->link->saleOf($r);

        return $sale !== null ? $this->sales->paidUnits($sale) : [];
    }
}
