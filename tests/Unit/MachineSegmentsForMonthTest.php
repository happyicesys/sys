<?php

namespace Tests\Unit;

use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure coverage for CustomerSummaryAggregator::machineSegmentsForMonth() — the
 * month-into-machines splitter. No DB. A month splits only when the bound vend
 * changes to a DIFFERENT vend; a same-machine rebind or a single machine
 * (incl. first-ever bind = activation) returns [] (no split).
 */
class MachineSegmentsForMonthTest extends TestCase
{
    private function bind(string $date, int $vend): array
    {
        return ['date' => $date.' 10:00:00', 'vend_id' => $vend];
    }

    private function segs(array $binds, string $monthStart, string $periodEnd): array
    {
        return CustomerSummaryAggregator::machineSegmentsForMonth(
            $binds,
            Carbon::parse($monthStart)->startOfMonth(),
            Carbon::parse($periodEnd)->startOfDay()
        );
    }

    /** One machine bound before the month → no split. */
    public function test_no_swap_returns_empty(): void
    {
        $this->assertSame([], $this->segs(
            [$this->bind('2026-05-01', 101)], '2026-06-01', '2026-06-30'
        ));
    }

    /** Swap 101 → 202 on 06-15 → two segments. */
    public function test_swap_splits_into_two(): void
    {
        $s = $this->segs(
            [$this->bind('2026-05-01', 101), $this->bind('2026-06-15', 202)],
            '2026-06-01',
            '2026-06-30'
        );
        $this->assertCount(2, $s);
        $this->assertSame('2026-06-01', $s[0]['start']->toDateString());
        $this->assertSame('2026-06-14', $s[0]['end']->toDateString());
        $this->assertSame(101, $s[0]['vend_id']);
        $this->assertSame('2026-06-15', $s[1]['start']->toDateString());
        $this->assertSame('2026-06-30', $s[1]['end']->toDateString());
        $this->assertSame(202, $s[1]['vend_id']);
    }

    /** Same machine unbound then rebound (101 @1, 101 @15) → no split. */
    public function test_same_machine_rebind_no_split(): void
    {
        $this->assertSame([], $this->segs(
            [$this->bind('2026-06-01', 101), $this->bind('2026-06-15', 101)],
            '2026-06-01',
            '2026-06-30'
        ));
    }

    /** 101 → 202 → 101 within the month → three segments. */
    public function test_three_way_swap(): void
    {
        $s = $this->segs(
            [$this->bind('2026-05-01', 101), $this->bind('2026-06-10', 202), $this->bind('2026-06-20', 101)],
            '2026-06-01',
            '2026-06-30'
        );
        $this->assertCount(3, $s);
        $this->assertSame([101, 202, 101], array_column($s, 'vend_id'));
        $this->assertSame('2026-06-09', $s[0]['end']->toDateString());
        $this->assertSame('2026-06-10', $s[1]['start']->toDateString());
        $this->assertSame('2026-06-20', $s[2]['start']->toDateString());
    }

    /** First-ever machine binds mid-month (activation, one machine) → no split. */
    public function test_first_bind_mid_month_no_split(): void
    {
        $this->assertSame([], $this->segs(
            [$this->bind('2026-06-15', 202)], '2026-06-01', '2026-06-30'
        ));
    }

    /** Current in-progress month: period_end capped at as-of, swap still splits. */
    public function test_swap_with_capped_period_end(): void
    {
        $s = $this->segs(
            [$this->bind('2026-05-01', 101), $this->bind('2026-06-15', 202)],
            '2026-06-01',
            '2026-06-22'
        );
        $this->assertCount(2, $s);
        $this->assertSame('2026-06-22', $s[1]['end']->toDateString());
    }

    /** No binds at all → no split. */
    public function test_empty_returns_empty(): void
    {
        $this->assertSame([], $this->segs([], '2026-06-01', '2026-06-30'));
    }
}
