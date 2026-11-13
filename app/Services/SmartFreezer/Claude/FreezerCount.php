<?php

namespace App\Services\SmartFreezer\Claude;

/**
 * Claude's answer for one door session, with what the call used. `items` are per catalog product; a product
 * Claude did not list was seen neither taken nor returned.
 */
final class FreezerCount
{
    /**
     * @param  list<array{product_id: int, taken: int, returned: int}>  $items
     * @param  list<array{description: string, taken: int}>  $unknownItems
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $answer  the parsed JSON as Claude returned it
     */
    public function __construct(
        public readonly array $items,
        public readonly array $unknownItems,
        public readonly int $peopleSeen,
        public readonly int $confidence,
        public readonly bool $needsReview,
        public readonly array $reasons,
        public readonly string $notes,
        public readonly array $answer,
        public readonly CallUsage $usage,
    ) {}

    /** @return array<int, int> product id => units that left the cabinet and stayed out (taken − returned, never below 0) */
    public function netByProduct(): array
    {
        $net = [];
        foreach ($this->items as $line) {
            $net[$line['product_id']] = ($net[$line['product_id']] ?? 0) + $line['taken'] - $line['returned'];
        }

        return array_filter(array_map(fn (int $n) => max(0, $n), $net));
    }

    public function unknownTaken(): int
    {
        return array_sum(array_column($this->unknownItems, 'taken'));
    }

    /** What the count says left the cabinet, as one comparable string: net units per product plus unknown units. */
    public function key(): string
    {
        $net = $this->netByProduct();
        ksort($net);

        return json_encode([$net, $this->unknownTaken()]);
    }

    /**
     * Several counts of the same session as one (Brian, 2026-11-13: tuning on 20 sessions, one count 15-16 right,
     * the majority of three 17). The answer is the count most runs gave; with no majority (every run different) the
     * most confident run, and a person is asked. It is "sure" only when a majority agrees and no run in it asked for
     * a person. The bill is every run's.
     *
     * @param  list<self>  $runs  in the order they were made
     */
    public static function byMajority(array $runs): self
    {
        if (count($runs) === 1) {
            return $runs[0];
        }
        $groups = [];
        foreach ($runs as $i => $run) {
            $groups[$run->key()][] = $i;
        }
        uasort($groups, fn (array $a, array $b) => count($b) <=> count($a) ?: $a[0] <=> $b[0]);
        $top = reset($groups);
        $majority = count($top) * 2 > count($runs);
        $pick = $majority ? $runs[$top[0]] : collect($runs)->sortByDesc(fn (self $r) => $r->confidence)->first();
        $agreeing = array_map(fn (int $i) => $runs[$i], $top);

        $usage = $runs[0]->usage;
        foreach (array_slice($runs, 1) as $run) {
            $usage = $usage->plus($run->usage);
        }
        $needsReview = ! $majority || collect($agreeing)->contains(fn (self $r) => $r->needsReview);
        $reasons = $pick->reasons;
        if (! $majority && ! in_array('other', $reasons, true)) {
            $reasons[] = 'other';
        }

        return new self(
            items: $pick->items,
            unknownItems: $pick->unknownItems,
            peopleSeen: $pick->peopleSeen,
            confidence: $majority ? (int) round(collect($agreeing)->avg(fn (self $r) => $r->confidence)) : min($pick->confidence, 40),
            needsReview: $needsReview,
            reasons: $reasons,
            notes: $pick->notes,
            answer: $pick->answer + ['runs' => array_map(fn (self $r) => [
                'net' => $r->netByProduct(), 'unknown' => $r->unknownTaken(), 'confidence' => $r->confidence, 'needs_review' => $r->needsReview,
            ], $runs), 'agreed' => $majority ? count($top) : 0],
            usage: $usage,
        );
    }
}
