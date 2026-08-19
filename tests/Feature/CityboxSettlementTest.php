<?php

namespace Tests\Feature;

use App\Services\CustomerSummaryAggregator;
use PHPUnit\Framework\TestCase;

/**
 * Design §8f (Option B, decided 2026-08-19): chiller sites carry S$0 sales
 * in mark1 (no sales feed from CityBox), and Site Summary / Settlement exist
 * to compute LOCATION FEES settled via CIMB. These lock the two consequences:
 * a fixed fee survives zero sales; a %-of-sales term computes to zero — which
 * is why the chiller contract step warns against it.
 */
class CityboxSettlementTest extends TestCase
{
    public function test_fixed_rental_fee_is_charged_in_full_with_zero_sales(): void
    {
        $fee = CustomerSummaryAggregator::computeLocationFeeCents(
            CustomerSummaryAggregator::CONTRACT_TYPE_RENTAL, 500.00, null, null,
            salesCents: 0, grossEarningCents: 0, gstRatePct: 9.0, flatDayRatio: 1.0,
        );

        $this->assertSame(50000, $fee); // S$500.00 fixed fee, sales irrelevant
    }

    public function test_rental_plus_utility_both_flat_survive_zero_sales(): void
    {
        $fee = CustomerSummaryAggregator::computeLocationFeeCents(
            CustomerSummaryAggregator::CONTRACT_TYPE_RENTAL_UTILITY, 300.00, 50.00, null,
            salesCents: 0, grossEarningCents: 0,
        );

        $this->assertSame(35000, $fee);
    }

    public function test_percent_of_sales_contract_computes_to_zero_on_a_chiller_site(): void
    {
        // The trap §8f warns about: with no sales feed, a PS term pays nothing.
        $fee = CustomerSummaryAggregator::computeLocationFeeCents(
            CustomerSummaryAggregator::CONTRACT_TYPE_PS, 20.0, null, 100.0,
            salesCents: 0, grossEarningCents: 0, gstRatePct: 9.0,
        );

        $this->assertSame(0, $fee);
    }

    public function test_ps_or_utility_falls_back_to_the_flat_utility_with_zero_sales(): void
    {
        // Sensible chiller contract shape if a % term is wanted "in principle": PS OR U → the flat U wins.
        $fee = CustomerSummaryAggregator::computeLocationFeeCents(
            CustomerSummaryAggregator::CONTRACT_TYPE_PS_OR_U, 20.0, 80.00, 100.0,
            salesCents: 0, grossEarningCents: 0,
        );

        $this->assertSame(8000, $fee);
    }
}
