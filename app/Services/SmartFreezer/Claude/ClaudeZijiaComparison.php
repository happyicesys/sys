<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\SmartFreezerClaudeRecognition as ClaudeRow;
use App\Models\VendTransaction;
use Illuminate\Support\Collection;

/**
 * Claude against Zijia on the same door sessions (Brian, 2026-11-13: "so eventually we can use to compare whether
 * Zijia's one is reliable or yours"). One rule for the command's report and the AI Dashboard box.
 *
 * Three questions, each counted only where it can be asked:
 *  - matches paid: the verdict is `match` (the count equals what was paid for);
 *  - agree: both answered and named the same units of the same products;
 *  - right by a person: a person counted the session (Adjust Qty), and the AI's count equals theirs exactly.
 *    The person is the truth (PersonCount); a session nobody counted proves nothing either way.
 */
final class ClaudeZijiaComparison
{
    /**
     * @param  Collection<int, ClaudeRow>  $rows  with `recognition` loaded
     * @return array<string, int|float|null>
     */
    public static function summarise(Collection $rows): array
    {
        $metas = self::metas($rows);
        $s = [
            'sessions' => $rows->count(),
            'claude_answered' => 0, 'claude_failed' => 0, 'zijia_answered' => 0,
            'claude_match_paid' => 0, 'zijia_match_paid' => 0,
            'both_answered' => 0, 'agree' => 0,
            'person_counted' => 0, 'claude_right' => 0, 'zijia_right' => 0, 'zijia_person_counted' => 0,
            'claude_needs_review' => 0,
            'cost_usd_micros' => 0,
        ];

        foreach ($rows as $row) {
            $zijia = $row->recognition;
            $meta = $metas[$row->vend_transaction_id ?? $zijia?->vend_transaction_id] ?? null;
            $counts = ClaudeShadowRecognitionService::counts($row, $zijia, $meta);

            $s['cost_usd_micros'] += (int) $row->cost_usd_micros;
            $s['claude_failed'] += $row->status === ClaudeRow::STATUS_FAILED ? 1 : 0;
            $s['claude_answered'] += $counts['claude'] !== null ? 1 : 0;
            $s['zijia_answered'] += $counts['zijia'] !== null ? 1 : 0;
            $s['claude_needs_review'] += $row->needs_review ? 1 : 0;
            $s['claude_match_paid'] += $row->verdict === 'match' ? 1 : 0;
            $s['zijia_match_paid'] += $zijia?->verdict === 'match' ? 1 : 0;

            if ($counts['claude'] !== null && $counts['zijia'] !== null) {
                $s['both_answered']++;
                $s['agree'] += self::same($counts['claude'], $counts['zijia']) ? 1 : 0;
            }
            if ($counts['person'] !== null && $counts['claude'] !== null) {
                $s['person_counted']++;
                $s['claude_right'] += self::same($counts['claude'], $counts['person']) ? 1 : 0;
                if ($counts['zijia'] !== null) {
                    $s['zijia_person_counted']++;
                    $s['zijia_right'] += self::same($counts['zijia'], $counts['person']) ? 1 : 0;
                }
            }
        }

        $pct = fn (int $n, int $of) => $of > 0 ? round($n / $of * 100, 1) : null;
        $s['claude_match_paid_pct'] = $pct($s['claude_match_paid'], $s['claude_answered']);
        $s['zijia_match_paid_pct'] = $pct($s['zijia_match_paid'], $s['zijia_answered']);
        $s['agree_pct'] = $pct($s['agree'], $s['both_answered']);
        $s['claude_right_pct'] = $pct($s['claude_right'], $s['person_counted']);
        $s['zijia_right_pct'] = $pct($s['zijia_right'], $s['zijia_person_counted']);
        // In this site's money (ClaudeCostMoney): the total, and per call that was billed.
        $calls = $rows->filter(fn (ClaudeRow $r) => $r->cost_usd_micros !== null)->count();
        $s['cost_text'] = ClaudeCostMoney::text($s['cost_usd_micros']);
        $s['cost_per_session_text'] = $calls > 0 ? ClaudeCostMoney::text(intdiv($s['cost_usd_micros'], $calls)) : null;

        return $s;
    }

    /**
     * @param  array<int|string, int>  $a
     * @param  array<int|string, int>  $b
     */
    public static function same(array $a, array $b): bool
    {
        $a = array_filter(array_map('intval', $a));
        $b = array_filter(array_map('intval', $b));
        ksort($a);
        ksort($b);

        return $a === $b;
    }

    /**
     * The sales' `meta_json` (a person's count lives there), one query.
     *
     * @param  Collection<int, ClaudeRow>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function metas(Collection $rows): array
    {
        $ids = $rows->map(fn (ClaudeRow $r) => $r->vend_transaction_id ?? $r->recognition?->vend_transaction_id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return VendTransaction::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'meta_json'])
            ->mapWithKeys(fn (VendTransaction $t) => [$t->id => (array) $t->meta_json])->all();
    }
}
