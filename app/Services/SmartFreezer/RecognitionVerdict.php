<?php

namespace App\Services\SmartFreezer;

/**
 * What the algorithm saw leave the cabinet, held against what the customer paid for.
 *
 * Our freezer is pay-first: the customer pays for a cart, the door opens, they take goods. So the
 * questions are "did more leave than was paid for" (stock loss) and "did less leave" (the customer
 * is owed a refund). This class only answers them; it never moves money or stock — per the estate
 * rule the AI result is final, but what mark1 DOES about a mismatch is a separate decision.
 *
 * Pure: arrays in, verdict out, so every case is a unit test.
 */
final class RecognitionVerdict
{
    public const MATCH = 'match';

    /** More left the cabinet than was paid for: unpaid goods. */
    public const TOOK_MORE = 'took_more';

    /** Less left than was paid for: the customer may be owed money. */
    public const TOOK_LESS = 'took_less';

    /** Some products over, some under — usually a swap. */
    public const MIXED = 'mixed';

    /** The algorithm named a code no mark1 product carries: nothing can be concluded. */
    public const UNRECOGNISED = 'unrecognised';

    /**
     * Something was paid for that the algorithm could never have named — a product with no product code
     * (it was not in the candidate list) or a slot with no product. Its "0 taken" is not evidence,
     * so the sale cannot be judged; the lines still show what was seen.
     */
    public const INCOMPLETE = 'incomplete';

    /**
     * @param  list<array{product_id: int|null, code: string|null, paid: int, taken: int, delta: int}>  $lines
     * @param  list<string>  $unknownCodes  codes the algorithm named that no product carries
     * @param  list<int>  $unnameable  paid product ids the algorithm could not have named
     */
    private function __construct(
        public readonly string $outcome,
        public readonly array $lines,
        public readonly array $unknownCodes,
        public readonly array $unnameable,
    ) {}

    /**
     * @param  array<int, int>  $paid  product id => units paid
     * @param  array<string, int>  $taken  product code => units the algorithm saw taken
     * @param  array<string, int>  $productByCode  product code => product id; a paid product absent
     *                                             from it could not have been named
     */
    public static function compare(array $paid, array $taken, array $productByCode): self
    {
        $nameable = array_flip(array_values($productByCode));
        $unnameable = array_values(array_filter(array_keys($paid), fn ($productId) => ! isset($nameable[$productId])));

        $takenByProduct = [];
        $codeOf = array_flip($productByCode);
        $unknown = [];
        foreach ($taken as $code => $units) {
            $productId = $productByCode[$code] ?? null;
            if ($productId === null) {
                $unknown[] = (string) $code;

                continue;
            }
            $takenByProduct[$productId] = ($takenByProduct[$productId] ?? 0) + $units;
        }

        $lines = [];
        $more = $less = false;
        foreach (array_unique(array_merge(array_keys($paid), array_keys($takenByProduct))) as $productId) {
            $p = $paid[$productId] ?? 0;
            $t = $takenByProduct[$productId] ?? 0;
            $more = $more || $t > $p;
            $less = $less || $t < $p;
            $lines[] = [
                'product_id' => $productId ?: null,
                'code' => isset($codeOf[$productId]) ? (string) $codeOf[$productId] : null,
                'paid' => $p,
                'taken' => $t,
                'delta' => $t - $p,
            ];
        }
        foreach ($unknown as $code) {
            $lines[] = ['product_id' => null, 'code' => $code, 'paid' => 0, 'taken' => $taken[$code], 'delta' => $taken[$code]];
        }
        usort($lines, fn ($a, $b) => [$a['product_id'] === null, $a['product_id']] <=> [$b['product_id'] === null, $b['product_id']]);

        $outcome = match (true) {
            $unknown !== [] => self::UNRECOGNISED,
            $unnameable !== [] => self::INCOMPLETE,
            $more && $less => self::MIXED,
            $more => self::TOOK_MORE,
            $less => self::TOOK_LESS,
            default => self::MATCH,
        };

        return new self($outcome, $lines, $unknown, $unnameable);
    }
}
