<?php

namespace Tests\Unit;

use App\Services\CardTerminal\AiCaptureDecision as D;
use PHPUnit\Framework\TestCase;

/**
 * Brian's charge rules for a T05 hold (2026-10-03/04): the AI verdict decides the total;
 * one charge is never above the hold, so a larger total is the hold plus further charges.
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

        $this->assertSame([D::CAPTURE, [760]], [$d->action, $d->charges]);
    }

    public function test_took_less_charges_only_what_was_taken(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'took_less', $this->lines(1, 1), self::PAID, []);

        $this->assertSame([D::CAPTURE, [460], 460], [$d->action, $d->charges, $d->judgedCents]);
    }

    public function test_nothing_taken_releases_the_hold(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'took_less', $this->lines(0, 0), self::PAID, []);

        $this->assertSame([D::VOID, [], 0], [$d->action, $d->charges, $d->captureCents]);
    }

    public function test_mixed_below_the_hold_charges_the_judged_value_at_the_shelf_price_for_the_extra(): void
    {
        // Paid 2×1 + 1×2; took 1×1 + 1×2 + 1×3 (shelf 200c): 300 + 160 + 200 = 660.
        $d = D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(1, 1, 1), self::PAID, [3 => 200, 1 => 999]);

        $this->assertSame([D::CAPTURE, [660]], [$d->action, $d->charges], 'paid price wins over shelf price');
    }

    public function test_mixed_above_the_hold_charges_the_hold_then_the_rest(): void
    {
        // took 2×1 + 0×2 + 2×3 (shelf 200c) = 1000 > 760.
        $d = D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(2, 0, 2), self::PAID, [3 => 200]);

        $this->assertSame([D::CAPTURE, [760, 240], 1000, 1000], [$d->action, $d->charges, $d->judgedCents, $d->captureCents]);
    }

    public function test_took_more_charges_the_hold_then_the_rest(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'took_more', $this->lines(3, 1), self::PAID, []);

        $this->assertSame([D::CAPTURE, [760, 300], 1060], [$d->action, $d->charges, $d->judgedCents]);
    }

    public function test_as_many_further_charges_as_it_takes_each_at_most_the_hold(): void
    {
        // A 300c hold (one of product 1); the AI saw 5 taken = 1500c.
        $d = D::decide(300, 300, 'took_more', [['product_id' => 1, 'taken' => 5]], self::PAID, []);

        $this->assertSame([300, 300, 300, 300, 300], $d->charges);
        $d = D::decide(300, 300, 'took_more', [['product_id' => 1, 'taken' => 3], ['product_id' => 2, 'taken' => 1]], self::PAID, []);
        $this->assertSame([300, 300, 300, 160], $d->charges);
    }

    public function test_cannot_identify_charges_the_cart(): void
    {
        foreach (['unrecognised', 'incomplete'] as $verdict) {
            $d = D::decide(self::HOLD, self::HOLD, $verdict, $this->lines(0, 0), self::PAID, []);
            $this->assertSame([D::CAPTURE, [760], null], [$d->action, $d->charges, $d->judgedCents], $verdict);
        }
    }

    public function test_a_taken_product_with_no_price_charges_the_cart_rather_than_guess_low(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(1, 0, 1), self::PAID, []);

        $this->assertSame([D::CAPTURE, [760], null], [$d->action, $d->charges, $d->judgedCents]);
    }

    public function test_the_cart_is_never_charged_above_the_hold(): void
    {
        $this->assertSame(500, D::decide(500, 760, 'match', [], [], [])->captureCents);
        $this->assertSame([500], D::backstop(500, 760, 72)->charges);
    }
}
