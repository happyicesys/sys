<?php

namespace Tests\Feature;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Jobs\Vend\SyncVendChannels;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\DeliveryProductMappingService;
use App\Services\ProductMappingService;
use App\Services\VendChannelDuplicateResolver;
use App\Services\VendTransactionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * One vend_channels row per (vend_id, code) — the invariant, its enforcement,
 * and the merge tool for pre-existing violations.
 *
 * Regression origin: vend 4753 / channel 17, 2026-08-03. A channel report
 * created a SECOND code-17 row (the keyed lookup missed the existing one),
 * after which every report updated the newcomer while the original froze at
 * qty 22/34 and inflated the machine's stock and capacity totals. Vend 2013
 * accumulated six duplicated codes the same week from report races.
 */
class VendChannelDuplicateGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Child jobs take a Redis unique-lock even on the sync driver — same
        // constraint CityboxChannelsTest works under.
        Queue::fake([SyncVendChannelErrorLog::class, SaveVendChannelsJson::class]);
    }

    private function vend(int $code = 9600): Vend
    {
        return Vend::create(['code' => $code, 'is_active' => 1, 'operator_id' => 1]);
    }

    private function runSyncJob(Vend $vend, array $channels): void
    {
        (new SyncVendChannels(['channels' => $channels], $vend))->handle(
            app(DeliveryProductMappingService::class),
            app(ProductMappingService::class),
        );
    }

    // ── the DB-level guarantee ─────────────────────────────────────────────

    public function test_unique_index_rejects_a_second_row_for_the_same_vend_and_code(): void
    {
        $vend = $this->vend();
        VendChannel::create(['vend_id' => $vend->id, 'code' => 17]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('vend_channels')->insert([
            'vend_id' => $vend->id,
            'code' => 17,
            'error_rate_json' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── SyncVendChannels: report processing can no longer duplicate ────────

    public function test_non_canonical_channel_code_string_updates_the_existing_row(): void
    {
        // "017" is how the 4753 duplicate was born: the string missed the
        // int-keyed lookup while MySQL coerced it to 17 on insert.
        $vend = $this->vend();
        $existing = VendChannel::create([
            'vend_id' => $vend->id, 'code' => 17,
            'qty' => 22, 'capacity' => 34, 'amount' => 0, 'is_active' => 1,
        ]);

        $this->runSyncJob($vend, [
            ['channel_code' => '017', 'amount' => 300, 'capacity' => 9, 'qty' => 3, 'error_code' => 0],
        ]);

        $rows = VendChannel::where('vend_id', $vend->id)->where('code', 17)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($existing->id, $rows->first()->id);
        $this->assertSame(300, $rows->first()->amount);
        $this->assertSame(9, $rows->first()->capacity);
        $this->assertSame(3, $rows->first()->qty);
    }

    public function test_repeated_reports_for_a_new_channel_create_exactly_one_row(): void
    {
        $vend = $this->vend();
        $entry = ['channel_code' => 25, 'amount' => 150, 'capacity' => 8, 'qty' => 5, 'error_code' => 0];

        $this->runSyncJob($vend, [$entry]);
        $this->runSyncJob($vend, [['channel_code' => '25'] + $entry]);

        $this->assertSame(1, VendChannel::where('vend_id', $vend->id)->where('code', 25)->count());
    }

    // ── VendTransactionService: a TRADE on an invisible channel reuses it ──

    public function test_trade_channel_creation_reuses_an_inactive_row(): void
    {
        // The service's lookup preloads the FILTERED vendChannels() relation
        // (is_active AND capacity > 0), so an inactive row is a lookup miss —
        // pre-fix, that miss inserted a duplicate. createVendChannel is private
        // and create() sets session transaction characteristics that fight
        // RefreshDatabase's wrapping transaction, so the seam is invoked
        // directly by reflection.
        $vend = $this->vend();
        $inactive = VendChannel::create([
            'vend_id' => $vend->id, 'code' => 17,
            'qty' => 0, 'capacity' => 0, 'is_active' => 0,
        ]);

        $method = new \ReflectionMethod(VendTransactionService::class, 'createVendChannel');
        $resolved = $method->invoke(new VendTransactionService, $vend->id, '17');

        $this->assertSame($inactive->id, $resolved->id);
        $this->assertSame(1, VendChannel::where('vend_id', $vend->id)->where('code', 17)->count());

        // And a genuinely absent channel is still created.
        $fresh = $method->invoke(new VendTransactionService, $vend->id, 33);
        $this->assertSame(33, $fresh->code);
        $this->assertSame(2, VendChannel::where('vend_id', $vend->id)->count());
    }

    // ── the merge tool for pre-index duplicates ────────────────────────────

    public function test_resolver_merges_duplicates_and_repoints_references(): void
    {
        // Duplicates cannot exist under the unique index, so it is dropped for
        // the duration. The DDL implicitly commits RefreshDatabase's wrapping
        // transaction, so everything this test writes is cleaned up — and the
        // index restored — in the finally block.
        Schema::table('vend_channels', fn (Blueprint $table) => $table->dropUnique(['vend_id', 'code']));

        $vend = null;

        try {
            $vend = $this->vend(9601);

            // The 4753 shape: stranded original (old, stale state) + newcomer
            // holding the machine's current state.
            $survivor = VendChannel::create([
                'vend_id' => $vend->id, 'code' => 17,
                'qty' => 22, 'capacity' => 34, 'amount' => 0, 'is_active' => 1,
                'created_at' => now()->subYears(2), 'updated_at' => now()->subDays(23),
            ]);
            $newcomer = VendChannel::create([
                'vend_id' => $vend->id, 'code' => 17,
                'qty' => 4, 'capacity' => 9, 'amount' => 300, 'is_active' => 1,
                'created_at' => now()->subDays(23), 'updated_at' => now(),
            ]);

            $eventOnSurvivor = DB::table('vend_channel_stock_events')->insertGetId([
                'vend_channel_id' => $survivor->id, 'vend_id' => $vend->id,
                'event_type' => 'restocked', 'qty_before' => 0, 'qty_after' => 22,
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $eventOnNewcomer = DB::table('vend_channel_stock_events')->insertGetId([
                'vend_channel_id' => $newcomer->id, 'vend_id' => $vend->id,
                'event_type' => 'sold_out', 'qty_before' => 9, 'qty_after' => 0,
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            $resolver = new VendChannelDuplicateResolver;
            $this->assertContains('vend_channel_stock_events', $resolver->referencingTables());
            $this->assertContains('vend_transactions', $resolver->referencingTables());

            // Dry-run: full plan, no writes.
            $plan = $resolver->resolve(apply: false);
            $this->assertCount(1, $plan);
            $this->assertSame($survivor->id, $plan[0]['survivor']);
            $this->assertSame($newcomer->id, $plan[0]['donor']);
            $this->assertSame([$newcomer->id], $plan[0]['deleted']);
            $this->assertSame(1, $plan[0]['repointed']['vend_channel_stock_events']);
            $this->assertSame(2, VendChannel::where('vend_id', $vend->id)->count());

            // Apply: survivor keeps its id + history, adopts the current state.
            $result = $resolver->resolve(apply: true);
            $this->assertSame(1, $result[0]['repointed']['vend_channel_stock_events']);

            $rows = VendChannel::where('vend_id', $vend->id)->where('code', 17)->get();
            $this->assertCount(1, $rows);
            $merged = $rows->first();
            $this->assertSame($survivor->id, $merged->id);
            $this->assertSame(4, $merged->qty);
            $this->assertSame(9, $merged->capacity);
            $this->assertSame(300, $merged->amount);

            $this->assertSame(
                [$survivor->id, $survivor->id],
                DB::table('vend_channel_stock_events')
                    ->whereIn('id', [$eventOnSurvivor, $eventOnNewcomer])
                    ->pluck('vend_channel_id')->map(fn ($id) => (int) $id)->all(),
            );

            $this->assertSame([], $resolver->resolve(apply: false), 'resolver is idempotent');
        } finally {
            if ($vend) {
                DB::table('vend_channel_stock_events')->where('vend_id', $vend->id)->delete();
                DB::table('vend_channels')->where('vend_id', $vend->id)->delete();
                DB::table('vends')->where('id', $vend->id)->delete();
            }
            Schema::table('vend_channels', fn (Blueprint $table) => $table->unique(['vend_id', 'code']));
        }
    }
}
