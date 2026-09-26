<?php

namespace Tests\Feature;

use App\Jobs\Vend\RecordVendTradeQueue;
use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Services\VendDataService;
use App\Support\OperatorScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Unsent Sales" (2026-09-26): the TradeOutbox backlog that big 307+ / small
 * v15+ report on the P heartbeat (TrdQ = TRADEs on disk not yet accepted by
 * mark1, TrdQAge = seconds the oldest has waited).
 *
 * Pinned: a reading is stored only when the machine sends one (older builds
 * are "no data", never "0 unsent"); it is a gauge, so the LATEST reading wins
 * and an out-of-order job cannot restore a stale one; the Ops Dashboard shows
 * it inline, on the deferred path, and sorts no-data last.
 */
class VendTradeQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-26 11:00:00');
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        OperatorScope::flush();
        parent::tearDown();
    }

    private function heartbeat(int $code, array $extra = []): void
    {
        $service = new VendDataService;
        $input = [
            'f' => '120', 't' => '5', 'm' => (string) $code, 'g' => '20',
            'p' => base64_encode(json_encode(array_merge(['Type' => 'P'], $extra))),
        ];
        $std = $service->standardizedVendData($input, 'http');
        $service->processVendData($std, $service->decodeVendData($std), '10.0.0.1', 'http');
    }

    public function test_a_heartbeat_with_trdq_is_recorded_and_one_without_is_not(): void
    {
        Queue::fake();
        $vend = Vend::forceCreate(['code' => 2031]);

        $this->heartbeat(2031, ['OfflineRestartCount' => 0]);
        Queue::assertNotPushed(RecordVendTradeQueue::class);

        $this->heartbeat(2031, ['TrdQ' => 3, 'TrdQAge' => 725]);
        Queue::assertPushedOn('low', RecordVendTradeQueue::class, fn (RecordVendTradeQueue $job) => $job->vendId === $vend->id
            && $job->date === '2026-09-26' && $job->count === 3 && $job->ageSeconds === 725
            && $job->reportedAt === '2026-09-26 11:00:00');
    }

    public function test_it_writes_only_when_the_count_or_the_minute_of_age_moves(): void
    {
        Queue::fake();
        Vend::forceCreate(['code' => 2031]);

        $this->heartbeat(2031, ['TrdQ' => 0, 'TrdQAge' => 0]);
        $this->heartbeat(2031, ['TrdQ' => 0, 'TrdQAge' => 0]);       // healthy, unchanged
        Queue::assertPushed(RecordVendTradeQueue::class, 1);

        $this->heartbeat(2031, ['TrdQ' => 2, 'TrdQAge' => 61]);       // backlog appears
        $this->heartbeat(2031, ['TrdQ' => 2, 'TrdQAge' => 100]);      // same minute
        Queue::assertPushed(RecordVendTradeQueue::class, 2);

        $this->heartbeat(2031, ['TrdQ' => 2, 'TrdQAge' => 125]);      // next minute
        $this->heartbeat(2031, ['TrdQ' => 0, 'TrdQAge' => 0]);        // drained
        Queue::assertPushed(RecordVendTradeQueue::class, 4);

        Carbon::setTestNow('2026-09-27 00:01:00');                    // new day: 0 again, once
        $this->heartbeat(2031, ['TrdQ' => 0, 'TrdQAge' => 0]);
        Queue::assertPushed(RecordVendTradeQueue::class, 5);
    }

    public function test_the_latest_reading_wins_and_a_stale_job_cannot_restore_an_old_one(): void
    {
        $vend = Vend::forceCreate(['code' => 2031]);

        (new RecordVendTradeQueue($vend->id, '2031', '2026-09-26', 5, 900, '2026-09-26 10:00:00'))->handle();
        (new RecordVendTradeQueue($vend->id, '2031', '2026-09-26', 0, 0, '2026-09-26 10:30:00'))->handle();
        // Arrives late (low queue ran it out of order): must not bring back 5.
        (new RecordVendTradeQueue($vend->id, '2031', '2026-09-26', 5, 1200, '2026-09-26 10:05:00'))->handle();

        $rows = DB::table('vend_daily_stats')->where('vend_id', $vend->id)->get()->keyBy('metric');
        $this->assertSame(0, (int) $rows['trade_queue']->count, 'drained backlog stays drained');
        $this->assertSame(0, (int) $rows['trade_queue_age_s']->count);
        $this->assertSame('2026-09-26 10:30:00', (string) $rows['trade_queue']->updated_at);
    }

    public function test_the_ops_dashboard_shows_it_inline_on_the_deferred_path_and_sorts_no_data_last(): void
    {
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $ids = [];
        foreach ([1001, 1002, 1003] as $code) {
            $customerId = DB::table('customers')->insertGetId([
                'name' => "Site {$code}", 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
                'operator_id' => $hipl->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $ids[$code] = ['vend_id' => DB::table('vends')->insertGetId([
                'code' => $code, 'name' => "Machine {$code}", 'operator_id' => $hipl->id,
                'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]), 'customer_id' => $customerId];
        }
        // 1001: 4 unsent, oldest 40 min. 1002: older build, nothing. 1003: real zero.
        (new RecordVendTradeQueue($ids[1001]['vend_id'], '1001', '2026-09-26', 4, 2400, '2026-09-26 10:55:00'))->handle();
        (new RecordVendTradeQueue($ids[1003]['vend_id'], '1003', '2026-09-26', 0, 0, '2026-09-26 10:58:00'))->handle();
        // Yesterday's backlog on 1002 must not leak into today's column.
        (new RecordVendTradeQueue($ids[1002]['vend_id'], '1002', '2026-09-25', 9, 60, '2026-09-25 20:00:00'))->handle();

        $user = User::factory()->create(['operator_id' => $hipl->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();

        $page = function (array $query) use ($user): array {
            $rows = [];
            $this->actingAs($user)
                ->get('/vends/customers?'.http_build_query(['autoload' => 1] + $query))
                ->assertOk()
                ->assertInertia(function (Assert $p) use (&$rows) {
                    foreach ($p->toArray()['props']['vends']['data'] as $vend) {
                        $rows[(int) $vend['code']] = $vend;
                    }
                });

            return $rows;
        };

        $rows = $page([]);
        $this->assertSame(4, $rows[1001]['trade_queue']);
        $this->assertSame(2400, $rows[1001]['trade_queue_age_s']);
        $this->assertSame('2026-09-26 10:55:00', $rows[1001]['trade_queue_at']);
        $this->assertNull($rows[1002]['trade_queue'], 'no reading today = no data, not 0');
        $this->assertSame(0, $rows[1003]['trade_queue'], 'a real zero stays zero');

        $this->assertSame([1001, 1003, 1002], array_keys($page(['sortKey' => 'trade_queue', 'sortBy' => 'false'])));
        $this->assertSame([1003, 1001, 1002], array_keys($page(['sortKey' => 'trade_queue', 'sortBy' => 'true'])));

        $map = $this->actingAs($user)
            ->postJson('/vends/customers/aggregates', ['rows' => array_values($ids)])
            ->assertOk()
            ->json('rows');
        $this->assertSame(4, $map[(string) $ids[1001]['vend_id']]['trade_queue']);
        $this->assertArrayHasKey('trade_queue', $map[(string) $ids[1002]['vend_id']]);
        $this->assertNull($map[(string) $ids[1002]['vend_id']]['trade_queue']);
    }
}
