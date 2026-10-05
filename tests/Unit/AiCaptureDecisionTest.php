<?php

namespace Tests\Unit;

use App\Services\CardTerminal\AiCaptureDecision as D;
use PHPUnit\Framework\TestCase;

/**
 * Brian's charge rules for a T05 hold (2026-10-03/04): the AI verdict decides the total, taken
 * in ONE capture — above the hold too (Payrallel increments the authorisation).
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

    public function test_took_less_charges_only_what_was_taken(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'took_less', $this->lines(1, 1), self::PAID, []);

        $this->assertSame([D::CAPTURE, 460, 460, null], [$d->action, $d->captureCents, $d->judgedCents, $d->fallbackCents]);
    }

    public function test_nothing_taken_releases_the_hold(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'took_less', $this->lines(0, 0), self::PAID, []);

        $this->assertSame([D::VOID, 0], [$d->action, $d->captureCents]);
    }

    public function test_mixed_below_the_hold_charges_the_judged_value_at_the_shelf_price_for_the_extra(): void
    {
        // Paid 2×1 + 1×2; took 1×1 + 1×2 + 1×3 (shelf 200c): 300 + 160 + 200 = 660.
        $d = D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(1, 1, 1), self::PAID, [3 => 200, 1 => 999]);

        $this->assertSame([D::CAPTURE, 660], [$d->action, $d->captureCents], 'paid price wins over shelf price');
    }

    public function test_mixed_above_the_hold_is_one_capture_of_the_whole_amount_with_the_hold_as_fallback(): void
    {
        // took 2×1 + 0×2 + 2×3 (shelf 200c) = 1000 > 760.
        $d = D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(2, 0, 2), self::PAID, [3 => 200]);

        $this->assertSame([D::CAPTURE, 1000, 760, 1000], [$d->action, $d->captureCents, $d->fallbackCents, $d->judgedCents]);
    }

    public function test_took_more_is_one_capture_above_the_hold(): void
    {
        // A 300c hold (one of product 1); the AI saw 5 taken = 1500c, captured at once.
        $d = D::decide(300, 300, 'took_more', [['product_id' => 1, 'taken' => 5]], self::PAID, []);

        $this->assertSame([D::CAPTURE, 1500, 300], [$d->action, $d->captureCents, $d->fallbackCents]);
    }

    public function test_an_unrecognised_product_charges_the_cart(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'unrecognised', $this->lines(0, 0), self::PAID, []);

        $this->assertSame([D::CAPTURE, 760, null], [$d->action, $d->captureCents, $d->judgedCents]);
    }

    /**
     * The three 2026-10-05 sessions on 50001 (Original 11c has a barcode; Chocolate 22c and
     * Solero 140c had none, so the AI was never asked about them).
     *
     * @return array<string, array{0: int, 1: list<array<string, mixed>>, 2: array<int, int>, 3: int, 4: int|null}>
     */
    public static function incompleteSessions(): array
    {
        return [
            'paid Solero, took nothing: charged as paid' => [140, [
                ['product_id' => 447, 'code' => null, 'paid' => 1, 'taken' => 0],
            ], [447 => 140], 140, null],
            'paid Original + Chocolate, took the Original' => [33, [
                ['product_id' => 874, 'code' => '9726436016148', 'paid' => 1, 'taken' => 1],
                ['product_id' => 875, 'code' => null, 'paid' => 1, 'taken' => 0],
            ], [874 => 11, 875 => 22], 33, null],
            'paid Original + Chocolate, took 2 Originals + the Chocolate' => [33, [
                ['product_id' => 874, 'code' => '9726436016148', 'paid' => 1, 'taken' => 2],
                ['product_id' => 875, 'code' => null, 'paid' => 1, 'taken' => 0],
            ], [874 => 11, 875 => 22], 44, 33],
            'paid 2 Originals + Chocolate, took 1 Original: less is still seen' => [44, [
                ['product_id' => 874, 'code' => '9726436016148', 'paid' => 2, 'taken' => 1],
                ['product_id' => 875, 'code' => null, 'paid' => 1, 'taken' => 0],
            ], [874 => 11, 875 => 22], 33, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteSessions')]
    public function test_incomplete_counts_what_the_ai_saw_and_charges_unasked_products_as_paid(int $hold, array $lines, array $paid, int $expected, ?int $fallback): void
    {
        $d = D::decide($hold, $hold, 'incomplete', $lines, $paid, []);

        $this->assertSame([D::CAPTURE, $expected, $fallback], [$d->action, $d->captureCents, $d->fallbackCents]);
    }

    public function test_incomplete_with_no_lines_charges_the_cart_never_releases(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'incomplete', [], self::PAID, []);

        $this->assertSame([D::CAPTURE, 760], [$d->action, $d->captureCents]);
    }

    public function test_incomplete_never_releases_the_hold(): void
    {
        // Nothing the AI was asked about was taken, but an unasked product was paid for.
        $d = D::decide(162, 162, 'incomplete', [
            ['product_id' => 874, 'code' => '9726436016148', 'paid' => 0, 'taken' => 0],
            ['product_id' => 447, 'code' => null, 'paid' => 1, 'taken' => 0],
        ], [447 => 140], []);

        $this->assertSame([D::CAPTURE, 140], [$d->action, $d->captureCents]);
    }

    public function test_a_taken_product_with_no_price_charges_the_cart_rather_than_guess_low(): void
    {
        $d = D::decide(self::HOLD, self::HOLD, 'mixed', $this->lines(1, 0, 1), self::PAID, []);

        $this->assertSame([D::CAPTURE, 760, null], [$d->action, $d->captureCents, $d->judgedCents]);
    }

    public function test_the_cart_is_never_charged_above_the_hold(): void
    {
        $this->assertSame(500, D::decide(500, 760, 'match', [], [], [])->captureCents);
        $this->assertSame(500, D::backstop(500, 760, 72)->captureCents);
    }
}
