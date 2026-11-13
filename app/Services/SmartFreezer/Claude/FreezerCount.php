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
}
