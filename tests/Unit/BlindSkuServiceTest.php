<?php

namespace Tests\Unit;

use App\Services\BlindSkuService;
use PHPUnit\Framework\TestCase;

/**
 * Pure-math coverage for the blind SKU allocator + blended cost.
 * No DB — these prove the corner cases in §4.5 of the plan in isolation.
 */
class BlindSkuServiceTest extends TestCase
{
    private function child($key, int $weight, array $opts = []): array
    {
        return array_merge(['key' => $key, 'weight' => $weight], $opts);
    }

    /* ---------- allocateToPick ---------- */

    public function test_even_split_is_exact()
    {
        $r = BlindSkuService::allocateToPick(9, [
            $this->child('a', 1), $this->child('b', 1), $this->child('c', 1),
        ]);
        $this->assertSame(['a' => 3, 'b' => 3, 'c' => 3], $r);
        $this->assertSame(9, array_sum($r));
    }

    public function test_the_canonical_example_10_over_4_and_5()
    {
        // needed 10, weights 4 & 5 (W=9): A=4.44 B=5.56 -> A=4, B=6
        $r = BlindSkuService::allocateToPick(10, [
            $this->child('A', 4), $this->child('B', 5),
        ]);
        $this->assertSame(['A' => 4, 'B' => 6], $r);
        $this->assertSame(10, array_sum($r));
    }

    public function test_leftover_goes_to_larger_remainder_then_larger_weight_tiebreak()
    {
        // needed 10, three equal weights -> 4/3/3, extra unit by order tie-break
        $r = BlindSkuService::allocateToPick(10, [
            $this->child('a', 1, ['sort' => 0]),
            $this->child('b', 1, ['sort' => 1]),
            $this->child('c', 1, ['sort' => 2]),
        ]);
        $this->assertSame(10, array_sum($r));
        $this->assertSame(4, $r['a']); // first by tie-break gets the extra
        $this->assertSame(3, $r['b']);
        $this->assertSame(3, $r['c']);
    }

    public function test_percentage_weights_summing_to_100()
    {
        // needed 7, 34/33/33 -> 3/2/2
        $r = BlindSkuService::allocateToPick(7, [
            $this->child('a', 34), $this->child('b', 33), $this->child('c', 33),
        ]);
        $this->assertSame(7, array_sum($r));
        $this->assertSame(['a' => 3, 'b' => 2, 'c' => 2], $r);
    }

    public function test_unavailable_child_share_redistributes()
    {
        $r = BlindSkuService::allocateToPick(10, [
            $this->child('a', 50),
            $this->child('b', 50, ['available' => false]),
        ]);
        $this->assertSame(['a' => 10, 'b' => 0], $r);
    }

    public function test_all_unavailable_yields_zero()
    {
        $r = BlindSkuService::allocateToPick(10, [
            $this->child('a', 50, ['available' => false]),
            $this->child('b', 50, ['available' => false]),
        ]);
        $this->assertSame(['a' => 0, 'b' => 0], $r);
        $this->assertSame(0, array_sum($r));
    }

    public function test_needed_zero_yields_all_zero()
    {
        $r = BlindSkuService::allocateToPick(0, [
            $this->child('a', 1), $this->child('b', 1),
        ]);
        $this->assertSame(['a' => 0, 'b' => 0], $r);
    }

    public function test_single_eligible_child_takes_everything()
    {
        $r = BlindSkuService::allocateToPick(10, [$this->child('a', 7)]);
        $this->assertSame(['a' => 10], $r);
    }

    public function test_cap_clamps_and_redistributes_to_others()
    {
        // needed 10, equal weights, but 'a' capped at 2 -> a=2, b=8
        $r = BlindSkuService::allocateToPick(10, [
            $this->child('a', 50, ['cap' => 2]),
            $this->child('b', 50),
        ]);
        $this->assertSame(10, array_sum($r));
        $this->assertSame(2, $r['a']);
        $this->assertSame(8, $r['b']);
    }

    public function test_caps_exhaust_supply_allocates_max_possible()
    {
        // needed 10, both capped at 3 -> only 6 achievable
        $r = BlindSkuService::allocateToPick(10, [
            $this->child('a', 50, ['cap' => 3]),
            $this->child('b', 50, ['cap' => 3]),
        ]);
        $this->assertSame(6, array_sum($r));
        $this->assertSame(3, $r['a']);
        $this->assertSame(3, $r['b']);
    }

    public function test_cap_zero_excludes_child()
    {
        $r = BlindSkuService::allocateToPick(5, [
            $this->child('a', 50, ['cap' => 0]),
            $this->child('b', 50),
        ]);
        $this->assertSame(['a' => 0, 'b' => 5], $r);
    }

    public function test_zero_weight_never_allocated()
    {
        $r = BlindSkuService::allocateToPick(5, [
            $this->child('a', 0),
            $this->child('b', 1),
        ]);
        $this->assertSame(['a' => 0, 'b' => 5], $r);
    }

    public function test_chain_redistribution_two_caps()
    {
        // needed 12, three equal; a capped 2, b capped 3 -> a2 b3 c7
        $r = BlindSkuService::allocateToPick(12, [
            $this->child('a', 1, ['cap' => 2]),
            $this->child('b', 1, ['cap' => 3]),
            $this->child('c', 1),
        ]);
        $this->assertSame(12, array_sum($r));
        $this->assertSame(2, $r['a']);
        $this->assertSame(3, $r['b']);
        $this->assertSame(7, $r['c']);
    }

    public function test_large_numbers_conserve_total()
    {
        $r = BlindSkuService::allocateToPick(1000, [
            $this->child('a', 17), $this->child('b', 53), $this->child('c', 30),
        ]);
        $this->assertSame(1000, array_sum($r));
    }

    /* ---------- blendedCostCents ---------- */

    public function test_blend_simple_average()
    {
        // 100c & 200c equal weight -> 150c
        $this->assertSame(150, BlindSkuService::blendedCostCents([
            ['weight' => 1, 'cost_cents' => 100],
            ['weight' => 1, 'cost_cents' => 200],
        ]));
    }

    public function test_blend_weighted()
    {
        // 100c@1 + 200c@3 = (100+600)/4 = 175
        $this->assertSame(175, BlindSkuService::blendedCostCents([
            ['weight' => 1, 'cost_cents' => 100],
            ['weight' => 3, 'cost_cents' => 200],
        ]));
    }

    public function test_blend_rounds_half_up()
    {
        // (100+101)/2 = 100.5 -> 101
        $this->assertSame(101, BlindSkuService::blendedCostCents([
            ['weight' => 1, 'cost_cents' => 100],
            ['weight' => 1, 'cost_cents' => 101],
        ]));
    }

    public function test_blend_zero_weight_is_zero()
    {
        $this->assertSame(0, BlindSkuService::blendedCostCents([]));
    }
}
