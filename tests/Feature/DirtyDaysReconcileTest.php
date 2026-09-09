<?php

namespace Tests\Feature;

use App\Jobs\ProcessGpMetricsDay;
use App\Jobs\Sales\ClearDirtyDay;
use App\Jobs\StoreVendProductRecords;
use App\Jobs\StoreVendsRecord;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\Sales\LateTradeTracker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Late TRADE → the day is recorded → `reconcile:sales-rollups --dirty`
 * rebuilds exactly those days once, as one chain per day whose tail clears
 * the day. Today is never recorded. The registry is a container singleton
 * (array store under phpunit), so every `app(DirtyDayRegistry::class)` in a
 * test sees the same set and a fresh app per test resets it.
 */
class DirtyDaysReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function registry(): DirtyDayRegistry
    {
        return app(DirtyDayRegistry::class);
    }

    public function test_registry_records_only_past_days_sorts_them_and_is_one_instance(): void
    {
        $r = $this->registry();
        $r->mark('2026-09-09 10:00:00'); // today → ignored
        $r->mark('2026-08-15');
        $r->mark(Carbon::parse('2026-09-01 23:59:59'));
        $r->mark('2026-08-15');

        $this->assertSame(['2026-08-15', '2026-09-01'], $this->registry()->days());
        $this->assertSame($r, $this->registry(), 'singleton');

        $r->clear('2026-08-15');
        $this->assertSame(['2026-09-01'], $r->days());

        // A cleared day can be re-marked straight away (the per-process dedupe forgets it on clear).
        $r->mark('2026-08-15');
        $this->assertSame(['2026-08-15', '2026-09-01'], $r->days());
    }

    public function test_tracker_dirties_past_days_after_commit_and_never_today(): void
    {
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op']);
        $vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id]);
        $row = VendTransaction::forceCreate([
            'order_id' => 'O-1', 'vend_id' => $vend->id, 'vend_channel_id' => 0, 'amount' => 250, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-08-20 09:00:00', 'operator_id' => $operator->id,
        ]);
        $today = VendTransaction::forceCreate([
            'order_id' => 'O-2', 'vend_id' => $vend->id, 'vend_channel_id' => 0, 'amount' => 250, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-09-09 13:00:00', 'operator_id' => $operator->id,
        ]);

        $tracker = app(LateTradeTracker::class);
        $tracker->noteLanded($row);
        $tracker->noteLanded($today);

        $this->assertSame(['2026-08-20'], $this->registry()->days());
    }

    public function test_clear_stamp_is_added_once_to_an_open_99_mark_and_nothing_else(): void
    {
        $now = Carbon::parse('2026-09-09 14:00:00');

        $marked = ['missing_trade' => ['marked_at' => '2026-08-21 00:01:00'], 'apk_ver' => 303];
        $this->assertSame(
            ['missing_trade' => ['marked_at' => '2026-08-21 00:01:00', 'cleared_at' => '2026-09-09 14:00:00'], 'apk_ver' => 303],
            LateTradeTracker::withClearStamp($marked, $now)
        );

        $already = ['missing_trade' => ['marked_at' => '2026-08-21 00:01:00', 'cleared_at' => '2026-09-01 08:00:00']];
        $this->assertSame($already, LateTradeTracker::withClearStamp($already, $now), 'an earlier clear is kept');

        $this->assertNull(LateTradeTracker::withClearStamp(null, $now));
        $this->assertSame(['apk_ver' => 303], LateTradeTracker::withClearStamp(['apk_ver' => 303], $now));
    }

    public function test_dirty_mode_chains_each_recorded_day_once_and_clears_it_at_the_tail(): void
    {
        Bus::fake();
        $r = $this->registry();
        $r->mark('2026-08-20');
        $r->mark('2026-09-01');
        $r->mark('2026-09-09'); // today: ignored by mark()

        $this->artisan('reconcile:sales-rollups --dirty --skip-cascade')
            ->expectsOutputToContain('Dirty days (2): 2026-08-20, 2026-09-01')
            ->assertSuccessful();

        Bus::assertChained([StoreVendsRecord::class, ProcessGpMetricsDay::class, StoreVendProductRecords::class, ClearDirtyDay::class]);
        Bus::assertDispatchedTimes(StoreVendsRecord::class, 2);
        // The command itself clears nothing: the chain's tail does, once the rebuild succeeded.
        $this->assertSame(['2026-08-20', '2026-09-01'], $r->days());
    }

    public function test_the_chain_tail_clears_the_day_when_it_runs(): void
    {
        $r = $this->registry();
        $r->mark('2026-08-20');

        // Run the tail exactly as the worker would. It is a JOB, not a queued
        // closure: the closure form was `fn ($d) => fn () => …clear($d)`, two
        // arrow functions on one line, and laravel/serializable-closure rebuilt
        // the OUTER one, so every tail died in the worker with "Unable to resolve
        // dependency [Parameter #0 [ <required> string $d ]]" while the rebuilds
        // themselves succeeded — the dirty set never drained (prod, 2026-09-09).
        (new ClearDirtyDay('2026-08-20'))->handle($r);

        $this->assertSame([], $r->days());
    }

    public function test_the_tail_job_carries_only_the_date_so_it_always_unserialises(): void
    {
        $job = unserialize(serialize(new ClearDirtyDay('2026-08-20')));

        $this->assertSame('2026-08-20', $job->day);
    }

    public function test_dry_run_dispatches_nothing_and_keeps_the_days(): void
    {
        Bus::fake();
        $this->registry()->mark('2026-08-20');

        $this->artisan('reconcile:sales-rollups --dirty --dry-run')->assertSuccessful();

        Bus::assertNothingDispatched();
        $this->assertSame(['2026-08-20'], $this->registry()->days());
    }

    public function test_no_dirty_days_is_a_quiet_success(): void
    {
        $this->artisan('reconcile:sales-rollups --dirty')
            ->expectsOutputToContain('No dirty days recorded.')
            ->assertSuccessful();
    }
}
