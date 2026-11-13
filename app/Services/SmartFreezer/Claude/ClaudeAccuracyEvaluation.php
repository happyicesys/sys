<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use Illuminate\Support\Collection;

/**
 * How close Claude's counts come to the final result, and what a correct result costs (Brian, 2026-11-13: "when someone
 * amended the result, use that as final … come up with an average cost for each transaction to get the AI result
 * correctly"). Read by ClaudeFreezerEvaluationSeeder and `smart-freezer:claude-recognition --accuracy`.
 *
 * The final result of a door session, strongest first:
 *  - `person`:   staff amended it with Adjust Qty (`meta_json.correction.basket`). This is the truth.
 *  - `accepted`: nobody amended it and Zijia's count matched what was paid, so the sale stood on it. Two separate
 *                signals agree (the customer's cart and Zijia), but nobody looked; Zijia is right here by definition,
 *                so Zijia's accuracy is only measured on `person`.
 *  - `unknown`:  neither (Zijia unsure or disagreeing, nobody amended): left out of accuracy, still counted in cost.
 *
 * Right = the same products in the same units, exactly. Cost = every Claude call in the set, failed ones too, since
 * they were billed; "cost per correct result" divides it by the sessions Claude got right.
 */
final class ClaudeAccuracyEvaluation
{
    /**
     * @param  Collection<int, ClaudeRow>  $rows  with `recognition` loaded
     * @return array{summary: array<string, mixed>, by_model: array<string, array<string, mixed>>, lines: list<array<string, mixed>>}
     */
    public static function evaluate(Collection $rows): array
    {
        $metas = ClaudeZijiaComparison::metas($rows);
        $lines = [];
        foreach ($rows as $row) {
            $zijia = $row->recognition;
            $counts = ClaudeShadowRecognitionService::counts($row, $zijia, $metas[$row->vend_transaction_id ?? $zijia?->vend_transaction_id] ?? null);
            [$tier, $truth] = match (true) {
                $counts['person'] !== null => ['person', $counts['person']],
                $zijia?->verdict === 'match' && $counts['zijia'] !== null => ['accepted', $counts['zijia']],
                default => ['unknown', null],
            };
            $lines[] = [
                'recognition_id' => $row->smart_freezer_recognition_id,
                'session_ref' => $row->session_ref,
                'machine' => $row->vend?->code,
                'tier' => $tier,
                'truth' => $truth,
                'claude' => $counts['claude'],
                'zijia' => $counts['zijia'],
                'claude_right' => $truth !== null && $counts['claude'] !== null ? ClaudeZijiaComparison::same($counts['claude'], $truth) : null,
                'zijia_right' => $tier === 'person' && $counts['zijia'] !== null ? ClaudeZijiaComparison::same($counts['zijia'], $truth) : null,
                'claude_status' => $row->status,
                'model' => $row->model,
                'confidence' => $row->confidence,
                'needs_review' => $row->needs_review,
                'cost_usd_micros' => $row->cost_usd_micros,
            ];
        }

        $byModel = [];
        foreach (collect($lines)->groupBy(fn (array $l) => $l['model'] ?? 'none') as $model => $group) {
            $byModel[$model] = self::summarise($group);
        }

        return ['summary' => self::summarise(collect($lines)), 'by_model' => $byModel, 'lines' => $lines];
    }

    /**
     * The report as plain lines, costs in US$ and in this site's money.
     *
     * @param  array{summary: array<string, mixed>, by_model: array<string, array<string, mixed>>}  $eval
     * @return list<string>
     */
    public static function reportLines(array $eval): array
    {
        $money = fn (?int $micros) => $micros === null ? '–' : sprintf('US$%.4f (%s)', $micros / 1_000_000, ClaudeCostMoney::text($micros));
        $pct = fn ($v) => $v === null ? '–' : $v.'%';
        $out = [];
        foreach (['All models' => $eval['summary']] + $eval['by_model'] as $label => $s) {
            if ($label !== 'All models' && count($eval['by_model']) < 2) {
                continue;
            }
            $out[] = "== {$label}: {$s['sessions']} sessions, Claude answered {$s['claude_answered']}, failed {$s['claude_failed']}";
            $out[] = sprintf('  Amended by a person (the final):  Claude right %d of %d (%s)   Zijia right %d of %d (%s)',
                $s['claude_right_person'], $s['person_sessions'], $pct($s['claude_right_person_pct']),
                $s['zijia_right_person'], $s['zijia_person_sessions'], $pct($s['zijia_right_person_pct']));
            $out[] = sprintf('  Zijia matched paid, not amended:  Claude agreed %d of %d (%s)',
                $s['claude_right_accepted'], $s['accepted_sessions'], $pct($s['claude_right_accepted_pct']));
            $out[] = sprintf('  Both together:                    Claude right %d of %d (%s); when Claude was sure: %s of %d',
                $s['claude_right'], $s['judged_sessions'], $pct($s['claude_right_pct']), $pct($s['claude_right_when_sure_pct']), $s['claude_sure']);
            $out[] = "  No final result (left out):       {$s['unknown_sessions']}";
            $out[] = sprintf('  Cost: %s in all over %d calls; %s a call; %s per correct result',
                $money($s['cost_usd_micros']), $s['calls'], $money($s['cost_per_call_usd_micros']), $money($s['cost_per_correct_usd_micros']));
        }

        return $out;
    }

    /** One line per session, for a spreadsheet. */
    public static function csv(array $eval): string
    {
        $fmt = fn (?array $c) => $c === null ? '' : (collect($c)->map(fn ($n, $id) => "{$n}x{$id}")->implode(' ') ?: 'nothing');
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['recognition_id', 'session_ref', 'machine', 'final_from', 'final', 'claude', 'zijia', 'claude_right', 'zijia_right', 'claude_status', 'model', 'confidence', 'needs_review', 'cost_usd']);
        foreach ($eval['lines'] as $l) {
            fputcsv($fh, [
                $l['recognition_id'], $l['session_ref'], $l['machine'], $l['tier'], $fmt($l['truth']), $fmt($l['claude']), $fmt($l['zijia']),
                $l['claude_right'] === null ? '' : ($l['claude_right'] ? 'yes' : 'no'),
                $l['zijia_right'] === null ? '' : ($l['zijia_right'] ? 'yes' : 'no'),
                $l['claude_status'], $l['model'], $l['confidence'], $l['needs_review'] === null ? '' : ($l['needs_review'] ? 'yes' : 'no'),
                $l['cost_usd_micros'] === null ? '' : sprintf('%.6f', $l['cost_usd_micros'] / 1_000_000),
            ]);
        }
        rewind($fh);

        return (string) stream_get_contents($fh);
    }

    /** @param  Collection<int, array<string, mixed>>  $lines */
    private static function summarise(Collection $lines): array
    {
        $tier = fn (string $t) => $lines->where('tier', $t);
        $answered = fn (Collection $c) => $c->filter(fn (array $l) => $l['claude'] !== null);
        $right = fn (Collection $c) => $c->where('claude_right', true)->count();
        $pct = fn (int $n, int $of) => $of > 0 ? round($n / $of * 100, 1) : null;

        $person = $answered($tier('person'));
        $accepted = $answered($tier('accepted'));
        $judged = $person->merge($accepted);
        $calls = $lines->filter(fn (array $l) => $l['cost_usd_micros'] !== null);
        $cost = (int) $calls->sum('cost_usd_micros');
        $correct = $right($judged);
        $zijiaOnPerson = $tier('person')->filter(fn (array $l) => $l['zijia_right'] !== null);
        // A count Claude was unsure of, kept apart: it is what a person would be asked to check.
        $sure = $judged->reject(fn (array $l) => (bool) $l['needs_review']);

        return [
            'sessions' => $lines->count(),
            'claude_answered' => $answered($lines)->count(),
            'claude_failed' => $lines->where('claude_status', ClaudeRow::STATUS_FAILED)->count(),
            'person_sessions' => $person->count(),
            'claude_right_person' => $right($person),
            'claude_right_person_pct' => $pct($right($person), $person->count()),
            'zijia_right_person' => $zijiaOnPerson->where('zijia_right', true)->count(),
            'zijia_person_sessions' => $zijiaOnPerson->count(),
            'zijia_right_person_pct' => $pct($zijiaOnPerson->where('zijia_right', true)->count(), $zijiaOnPerson->count()),
            'accepted_sessions' => $accepted->count(),
            'claude_right_accepted' => $right($accepted),
            'claude_right_accepted_pct' => $pct($right($accepted), $accepted->count()),
            'judged_sessions' => $judged->count(),
            'claude_right' => $correct,
            'claude_right_pct' => $pct($correct, $judged->count()),
            'unknown_sessions' => $tier('unknown')->count(),
            'claude_sure' => $sure->count(),
            'claude_right_when_sure_pct' => $pct($right($sure), $sure->count()),
            'cost_usd_micros' => $cost,
            'calls' => $calls->count(),
            'cost_per_call_usd_micros' => $calls->count() > 0 ? intdiv($cost, $calls->count()) : null,
            'cost_per_correct_usd_micros' => $correct > 0 ? intdiv($cost, $correct) : null,
        ];
    }
}
