<?php

namespace Tests\Unit;

use App\Services\CardTerminal\AiCaptureDecision as D;
use PHPUnit\Framework\TestCase;

/**
 * Brian's charge rule for a T05 hold (2026-11-03): only a match charges. A mismatch waits for a
 * person, with what the AI saw valued as a suggestion.
 */
class AiCaptureDecisionTest extends TestCase
{
    /** Two of product 1 (300c) and one of product 2 (160c) paid: a 760c hold. */
    private const HOLD = 760;

    private const PAID = [1 => 300, 2 => 160];

    private function lines(int $one, int $two, int $three = 0): array
    {
        return array_values(array_filter([
            ['product_id' => 1, 'paid' => 2, 'taken' => $one],
            ['product_id' => 2, 'paid' => 1, 'taken' => $two],
            $three ? ['product_id' => 3, 'paid' => 0, 'taken' => $three] : null,
        ]));
    }

    public function test_match_charges_the_cart(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'match', $this->lines(2, 1), self::PAID, []);

        $this->assertSame([D::CAPTURE, 760, null], [$d->action, $d->captureCents, $d->fallbackCents]);
    }

    /** @param  array<int, int>  $basket */
    private function assertSuggests(D $d, int $cents, array $basket): void
    {
        $this->assertSame([D::PERSON, 0, null, $cents, $basket], [$d->action, $d->captureCents, $d->fallbackCents, $d->judgedCents, $d->basket]);
    }

    public function test_took_less_only_suggests_what_was_taken(): void
    {
        $this->assertSuggests(D::decide(self::HOLD, self::HOLD, 'took_less', $this->lines(1, 1), self::PAID, []), 460, [1 => 1, 2 => 1]);
    }

    public function test_nothing_taken_only_suggests_zero(): void
    {
        $this->assertSuggests(D::decide(self::HOLD, self::HOLD, 'took_less', $this->lines(0, 0), self::PAID, []), 0, []);
    }

    public function test_mixed_suggests_the_judged_value_at_the_shelf_price_for_the_extra(): void
    {
        // Paid 2×1 + 1×2; took 1×1 + 1×2 + 1×3 (shelf 200c): 300 + 160 + 200 = 660. Paid price wins over shelf.
        $this->assertSuggests(D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(1, 1, 1), self::PAID, [3 => 200, 1 => 999]), 660, [1 => 1, 2 => 1, 3 => 1]);
    }

    public function test_a_mismatch_above_the_hold_only_suggests_it_too(): void
    {
        // 50001's 2026-11-03 20:00 session was captured at 1140c on a 1000c hold this way.
        $this->assertSuggests(D::decide(300, 300, 'took_more', [['product_id' => 1, 'taken' => 5]], self::PAID, []), 1500, [1 => 5]);
    }

    /**
     * Brian, 2026-11-03: what the AI cannot judge goes to a person with no suggestion. The first case is
     * 50001's 2026-11-03 19:42 session: 4 paid, a paid product with no barcode, and the old middle
     * ground captured 1130c on an 880c cart.
     *
     * @return array<string, array{0: string, 1: list<array<string, mixed>>}>
     */
    public static function sessionsTheAiCannotJudge(): array
    {
        return [
            'incomplete: above the cart' => ['incomplete', [
                ['product_id' => 439, 'code' => '8851932028103', 'paid' => 0, 'taken' => 1],
                ['product_id' => 563, 'code' => null, 'paid' => 1, 'taken' => 0],
            ]],
            'incomplete: nothing the AI was asked about taken' => ['incomplete', [
                ['product_id' => 563, 'code' => null, 'paid' => 1, 'taken' => 0],
            ]],
            'incomplete: no lines' => ['incomplete', []],
            'unrecognised' => ['unrecognised', [['product_id' => 1, 'taken' => 1]]],
            'a taken product with no price' => ['mixed', [['product_id' => 3, 'taken' => 1]]],
            'a taken line with no product' => ['took_more', [['product_id' => null, 'taken' => 1]]],
            'no verdict word we know' => ['something_new', []],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sessionsTheAiCannotJudge')]
    public function test_what_the_ai_cannot_judge_goes_to_a_person_and_charges_nothing(string $verdict, array $lines): void
    {
        $d = D::decide(self::HOLD, self::HOLD, $verdict, $lines, self::PAID, []);

        $this->assertSame([D::PERSON, 0, null, null, null], [$d->action, $d->captureCents, $d->fallbackCents, $d->judgedCents, $d->basket]);
    }

    public function test_the_cart_is_never_charged_above_the_hold(): void
    {
        $this->assertSame(500, D::decide(500, 760, 'match', [], [], [])->captureCents);
    }
}
