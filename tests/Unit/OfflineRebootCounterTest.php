<?php

namespace Tests\Unit;

use App\Support\OfflineRebootCounter;
use PHPUnit\Framework\TestCase;

/** The day's offline auto-reboots are the rise of the board's running counter between heartbeats. */
class OfflineRebootCounterTest extends TestCase
{
    public function test_the_rise_is_counted(): void
    {
        $this->assertSame(3, OfflineRebootCounter::delta(23, '2026-10-17 21:32:00', 26, '2026-10-17 21:32'));
    }

    public function test_no_rise_counts_nothing(): void
    {
        $this->assertSame(0, OfflineRebootCounter::delta(26, '2026-10-17 21:32:00', '26', '2026-10-17 21:32'));
    }

    public function test_a_new_install_or_clear_counts_from_zero(): void
    {
        // The counter's start time moved: everything it now holds happened since.
        $this->assertSame(2, OfflineRebootCounter::delta(26, '2026-10-17 21:32:00', 2, '2026-10-18 09:00'));
        $this->assertSame(30, OfflineRebootCounter::delta(26, '2026-10-17 21:32:00', 30, '2026-10-18 09:00'));
    }

    public function test_a_lower_count_without_a_new_start_is_a_restart_too(): void
    {
        $this->assertSame(1, OfflineRebootCounter::delta(26, null, 1, null));
    }

    public function test_the_first_report_counts_nothing(): void
    {
        // History before the first stored value is unknown: never book it all on one day.
        $this->assertSame(0, OfflineRebootCounter::delta(null, null, 40, '2026-10-01 10:00'));
        $this->assertSame(0, OfflineRebootCounter::delta(5, null, 'x', null));
    }
}
