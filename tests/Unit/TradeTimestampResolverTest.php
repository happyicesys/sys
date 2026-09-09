<?php

namespace Tests\Unit;

use App\Support\TradeTimestampResolver;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * A TRADE frame's TIME is believed only inside the window: not older than
 * maxDaysBack (30 — machines replay a month of queued frames), not more than
 * 5 minutes ahead. Everything else books at arrival and is flagged.
 */
class TradeTimestampResolverTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-09-09 14:00:00', 'Asia/Singapore');
    }

    public function test_a_live_frame_a_few_seconds_old_is_trusted(): void
    {
        $r = TradeTimestampResolver::resolve('2026-09-09 13:59:48', $this->now, 30);

        $this->assertTrue($r->isTrusted());
        $this->assertSame('2026-09-09 13:59:48', $r->at->toDateTimeString());
        $this->assertNull($r->metaStamp());
    }

    public function test_a_replayed_frame_from_three_weeks_ago_keeps_its_day(): void
    {
        $r = TradeTimestampResolver::resolve('2026-08-19 09:12:00', $this->now, 30);

        $this->assertTrue($r->isTrusted());
        $this->assertSame('2026-08-19', $r->at->toDateString());
    }

    public function test_the_window_edge_is_inclusive_on_the_old_side(): void
    {
        $edge = $this->now->copy()->subDays(30)->toDateTimeString();

        $this->assertTrue(TradeTimestampResolver::resolve($edge, $this->now, 30)->isTrusted());
        $this->assertFalse(TradeTimestampResolver::resolve($this->now->copy()->subDays(30)->subSecond()->toDateTimeString(), $this->now, 30)->isTrusted());
    }

    public function test_a_clock_stuck_in_the_past_is_rejected_and_booked_now(): void
    {
        $r = TradeTimestampResolver::resolve('2030-01-05 10:00:00', $this->now, 30); // "2030" clocks exist in prod; this one is a 2026 board reading 2030 → future actually; use a past one too
        $this->assertFalse($r->isTrusted());

        $r = TradeTimestampResolver::resolve('2025-11-30 10:00:00', $this->now, 30);
        $this->assertFalse($r->isTrusted());
        $this->assertSame(TradeTimestampResolver::REASON_TOO_OLD, $r->reason);
        $this->assertSame($this->now->toDateTimeString(), $r->at->toDateTimeString());
        $this->assertSame(['raw' => '2025-11-30 10:00:00', 'rejected' => true, 'reason' => 'too_old'], $r->metaStamp());
    }

    public function test_a_clock_in_the_future_is_rejected(): void
    {
        $r = TradeTimestampResolver::resolve('2070-01-09 01:19:08', $this->now, 30);

        $this->assertFalse($r->isTrusted());
        $this->assertSame(TradeTimestampResolver::REASON_FUTURE, $r->reason);
        $this->assertSame($this->now->toDateTimeString(), $r->at->toDateTimeString());

        // Four minutes ahead is clock skew, not the future.
        $this->assertTrue(TradeTimestampResolver::resolve($this->now->copy()->addMinutes(4)->toDateTimeString(), $this->now, 30)->isTrusted());
        $this->assertFalse(TradeTimestampResolver::resolve($this->now->copy()->addMinutes(6)->toDateTimeString(), $this->now, 30)->isTrusted());
    }

    public function test_missing_or_garbage_time_books_now(): void
    {
        $missing = TradeTimestampResolver::resolve(null, $this->now, 30);
        $this->assertFalse($missing->isTrusted());
        $this->assertSame(TradeTimestampResolver::REASON_MISSING, $missing->reason);
        $this->assertSame($this->now->toDateTimeString(), $missing->at->toDateTimeString());

        $blank = TradeTimestampResolver::resolve('  ', $this->now, 30);
        $this->assertSame(TradeTimestampResolver::REASON_MISSING, $blank->reason);

        $junk = TradeTimestampResolver::resolve('not a date', $this->now, 30);
        $this->assertFalse($junk->isTrusted());
        $this->assertSame(TradeTimestampResolver::REASON_UNPARSEABLE, $junk->reason);
        $this->assertSame($this->now->toDateTimeString(), $junk->at->toDateTimeString());

        // No TIME at all is not a rejection worth stamping; a garbage TIME is.
        $this->assertNull($missing->metaStamp());
        $this->assertSame(['raw' => 'not a date', 'rejected' => true, 'reason' => 'unparseable'], $junk->metaStamp());
    }

    public function test_the_frame_is_read_in_the_operators_zone_and_booked_in_the_app_zone(): void
    {
        // A Bangkok board (UTC+7) selling at 23:30 local = 00:30 the next day in Singapore.
        $now = Carbon::parse('2026-09-10 00:31:00', 'Asia/Singapore');
        $r = TradeTimestampResolver::resolve('2026-09-09 23:30:00', $now, 30, 300, 'Asia/Bangkok');

        $this->assertTrue($r->isTrusted());
        $this->assertSame('2026-09-10 00:30:00', $r->at->toDateTimeString());
        $this->assertSame('Asia/Singapore', $r->at->getTimezone()->getName());

        // Same wall-clock read as Singapore would be an hour in the past — still trusted, different day.
        $this->assertSame('2026-09-09', TradeTimestampResolver::resolve('2026-09-09 23:30:00', $now, 30)->at->toDateString());

        // An operator AHEAD of the app zone is not rejected as "future" (Tokyo 15:04 = 14:04 SGT).
        $tokyo = TradeTimestampResolver::resolve('2026-09-09 15:04:00', $this->now, 30, 300, 'Asia/Tokyo');
        $this->assertTrue($tokyo->isTrusted());
        $this->assertSame('2026-09-09 14:04:00', $tokyo->at->toDateTimeString());
    }
}
