<?php

namespace App\Services\SmartFreezer;

/**
 * A person's count on AI Recognition (Adjust Qty, `meta_json.correction.basket`) measured against what the AI
 * saw (`smart_freezer_recognitions.verdict_lines`). One rule for the AI Dashboard's accuracy, the page's
 * Adjusted column and Sales Transactions' badge: a person's count is the truth, so one product swapped for
 * another is one unit the AI got wrong.
 *
 * Brian, 2026-11-12: when the person's count equals the AI's, the person only CONFIRMED the AI (the customer
 * took something other than they paid for and the AI saw it right). That must not read as a correction.
 */
final class PersonCount
{
    /**
     * @param  iterable<array<string, mixed>>  $verdictLines  the AI's lines (product_id / code, taken, paid)
     * @param  array<int|string, int|string>  $basket  the person's count, keyed like the AI's lines
     * @return array{int, int} [units the AI had right, units the person changed]
     */
    public static function compare(iterable $verdictLines, array $basket): array
    {
        $taken = [];
        foreach ($verdictLines as $line) {
            $key = self::key($line);
            $taken[$key] = ($taken[$key] ?? 0) + (int) ($line['taken'] ?? 0);
        }
        $counted = array_map('intval', $basket);
        $right = array_sum(array_map(fn ($key) => min($taken[$key] ?? 0, $counted[$key] ?? 0), array_keys($taken)));

        return [$right, max(array_sum($taken), array_sum($counted)) - $right];
    }

    /**
     * True when the AI answered with its lines and the person's count changed nothing it saw. With no lines
     * there is nothing to agree with, so a count is an adjustment.
     *
     * @param  array<int, array<string, mixed>>|null  $verdictLines
     */
    public static function confirmsAi(?string $verdict, ?array $verdictLines, mixed $correction): bool
    {
        if ($verdict === null || empty($verdictLines) || ! is_array($correction)) {
            return false;
        }

        return self::compare($verdictLines, (array) ($correction['basket'] ?? []))[1] === 0;
    }

    /** The key a line is counted under: its product, else its code. */
    public static function key(array $line): int|string
    {
        return (int) ($line['product_id'] ?? 0) ?: 'code:'.($line['code'] ?? '?');
    }
}
