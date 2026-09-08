<?php

namespace Tests\Feature;

use App\Jobs\ProcessGpMetricsDay;
use App\Jobs\StoreVendProductRecords;
use App\Jobs\StoreVendsRecord;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\Sales\DirtyDayRegistry;
use App\Services\Sales\LateTradeTracker;
use App\Support\DispenseVerdict;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Late TRADE → the day is recorded → `reconcile:sales-rollups --dirty`
 * rebuilds exactly those days once and clears them. Today is never rebuilt.
 */
class DirtyDaysReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 14:00:00');
        DirtyDayRegistry::flushMemory();
        LateTradeTracker::forgetNotFoundId();
    }

    protected function tearDown(): void
    {
        DirtyDayRegistry::flushMemory();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_registry_records_only_past_days_and_sorts_them(): void
    {
        $r = new DirtyDayRegistry;
        $r->mark('2026-09-09 10:00:00'); // today → ignored
        $r->mark('2026-08-15');
        $r->mark(Carbon::parse('2026-09-01 23:59:59'));
        $r->mark('2026-08-15');

        $this->assertSame(['2026-08-15', '2026-09-01'], $r->days());

        $r->clear('2026-08-15');
        $this->assertSame(['2026-09-01'], $r->days());
    }

    public function test_tracker_dirties_past_days_and_stamps_a_cleared_99_mark(): void
    {
        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op']);
        $vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id]);
        $naId = VendChannelError::where('code', DispenseVerdict::NOT_FOUND_CODE)->value('id');
        $row = VendTransaction::forceCreate([
            'order_id' => 'O-1', 'vend_id' => $vend->id, 'vend_channel_id' => 0, 'amount' => 250, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-08-20 09:00:00', 'operator_id' => $operator->id,
            'meta_json' => ['missing_trade' => ['marked_at' => '2026-08-21 00:01:00']],
        ]);

        $registry = new DirtyDayRegistry;
        (new LateTradeTracker($registry))->noteLanded($row, $naId);

        $this->assertSame(['2026-08-20'], $registry->days());
        $this->assertSame('2026-09-09 14:00:00', $row->fresh()->meta_json['missing_trade']['cleared_at']);
        $this->assertSame('2026-08-21 00:01:00', $row->fresh()->meta_json['missing_trade']['marked_at']);

        // A TRADE on today's date dirties nothing; a fresh row (no previous code) gets no clear stamp.
        $today = VendTransaction::forceCreate([
            'order_id' => 'O-2', 'vend_id' => $vend->id, 'vend_channel_id' => 0, 'amount' => 250, 'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-09-09 13:00:00', 'operator_id' => $operator->id,
        ]);
        (new LateTradeTracker($registry))->noteLanded($today, null);
        $this->assertSame(['2026-08-20'], $registry->days());
        $this->assertNull($today->fresh()->meta_json);
    }

    public function test_dirty_mode_rebuilds_each_recorded_day_once_and_clears_it(): void
    {
        Bus::fake();
        $r = new DirtyDayRegistry;
        $r->mark('2026-08-20');
        $r->mark('2026-09-01');
        $r->mark('2026-09-09'); // today: ignored by mark()

        $this->artisan('reconcile:sales-rollups --dirty --skip-cascade')
            ->expectsOutputToContain('Dirty days (2): 2026-08-20, 2026-09-01')
            ->assertSuccessful();

        Bus::assertDispatchedTimes(StoreVendsRecord::class, 2);
        Bus::assertDispatchedTimes(ProcessGpMetricsDay::class, 2);
        Bus::assertDispatchedTimes(StoreVendProductRecords::class, 2);
        $this->assertSame([], $r->days());

        $this->artisan('reconcile:sales-rollups --dirty')
            ->expectsOutputToContain('No dirty days recorded.')
            ->assertSuccessful();
    }

    public function test_dry_run_dispatches_nothing_and_keeps_the_days(): void
    {
        Bus::fake();
        (new DirtyDayRegistry)->mark('2026-08-20');

        $this->artisan('reconcile:sales-rollups --dirty --dry-run')->assertSuccessful();

        Bus::assertNothingDispatched();
        $this->assertSame(['2026-08-20'], (new DirtyDayRegistry)->days());
    }
}
