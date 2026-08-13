<?php

namespace Tests\Unit;

use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure-math coverage for CustomerSummaryAggregator::activeDaysFromLog() — the
 * multi-interval active-day count used ONLY for re-activated sites (removed →
 * active again). No DB. Proves it equals the single-pair logic for normal
 * single-interval sites and sums every interval for re-activations.
 *
 * Scenarios mirror the algorithm verification:
 *   - normal full / activation / removal months  → identical to the pair path
 *   - same-month remove+reactivate               → both stretches counted
 *   - cross-month remove (last month) + reactivate (this month)
 *   - leading "Removed" with no initial Active    → anchored at begin_date
 *   - multiple cycles in one month
 */
class ActiveDaysFromLogTest extends TestCase
{
    private function ev(string $date, bool $active): array
    {
        return ['date' => $date, 'is_active' => $active];
    }

    private function days(array $events, ?string $begin, string $monthStart): int
    {
        return CustomerSummaryAggregator::activeDaysFromLog(
            $events,
            $begin,
            Carbon::parse($monthStart)->startOfMonth()
        );
    }

    /** A site active all month → full month days. */
    public function test_full_month(): void
    {
        $this->assertSame(30, $this->days(
            [$this->ev('2026-06-01', true)], '2026-06-01', '2026-06-01'
        ));
    }

    /** Activation mid-month (06-07) → 24/30. Matches computeActiveDayRatio. */
    public function test_activation_mid_month(): void
    {
        $this->assertSame(24, $this->days(
            [$this->ev('2026-06-07', true)], '2026-06-07', '2026-06-01'
        ));
    }

    /** Removal mid-month (removed 06-20, exclusive) → 06-01..06-19 = 19 days. */
    public function test_removal_mid_month(): void
    {
        $this->assertSame(19, $this->days(
            [$this->ev('2026-01-01', true), $this->ev('2026-06-20', false)],
            '2026-01-01',
            '2026-06-01'
        ));
    }

    /** Scenario 1 — same month: removed 06-10, re-active 06-15 → 9 + 16 = 25. */
    public function test_same_month_remove_then_reactivate(): void
    {
        $events = [
            $this->ev('2026-01-01', true),
            $this->ev('2026-06-10', false),
            $this->ev('2026-06-15', true),
        ];
        $this->assertSame(25, $this->days($events, '2026-01-01', '2026-06-01'));
    }

    /** Scenario 2 — cross month: removed 05-20, re-active 06-15. */
    public function test_cross_month_reactivation(): void
    {
        $events = [
            $this->ev('2026-01-01', true),
            $this->ev('2026-05-20', false),
            $this->ev('2026-06-15', true),
        ];
        // May: 05-01..05-19 = 19 (of 31)
        $this->assertSame(19, $this->days($events, '2026-01-01', '2026-05-01'));
        // June: 06-15..06-30 = 16 (of 30)
        $this->assertSame(16, $this->days($events, '2026-01-01', '2026-06-01'));
        // April: fully active before the removal → 30, so the row stays eligible
        $this->assertSame(30, $this->days($events, '2026-01-01', '2026-04-01'));
        // March (before any event but after begin) → fully active → 31
        $this->assertSame(31, $this->days($events, '2026-01-01', '2026-03-01'));
    }

    /** Leading "Removed" with no initial Active in the log → anchored at begin. */
    public function test_leading_removed_anchored_at_begin(): void
    {
        $events = [
            $this->ev('2026-05-20', false),
            $this->ev('2026-06-15', true),
        ];
        $this->assertSame(19, $this->days($events, '2026-01-01', '2026-05-01'));
    }

    /** Multiple cycles in one month: A1,R5,A10,R20,A25 → 4 + 10 + 6 = 20. */
    public function test_multiple_cycles_in_month(): void
    {
        $events = [
            $this->ev('2026-06-01', true),
            $this->ev('2026-06-05', false),
            $this->ev('2026-06-10', true),
            $this->ev('2026-06-20', false),
            $this->ev('2026-06-25', true),
        ];
        $this->assertSame(20, $this->days($events, '2026-06-01', '2026-06-01'));
    }

    /** Empty log falls back to begin_date anchor → full month if begin is early. */
    public function test_empty_log_uses_begin_anchor(): void
    {
        $this->assertSame(30, $this->days([], '2026-01-01', '2026-06-01'));
    }
}
