<?php

namespace Tests\Feature;

use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Product;
use App\Models\StockCheck;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * "Stock Count" spot check (tables: stock_checks). A supervisor assigns a
 * machine with all or a random N of its stocked channels; the driver sees the
 * current qty and picks the real one; the variance is shown to him; a
 * supervisor may re-draw before the count and sync the result after it.
 */
class StockCheckTest extends TestCase
{
    use RefreshDatabase;

    private const DRIVER = ['read', 'update'];

    private const SUPERVISOR = ['read', 'update', 'create', 'redraw', 'sync', 'delete', 'export'];

    private Operator $operator;

    private User $supervisor;

    private User $driver;

    private OpsJob $job;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SaveVendChannelsJson::class]);

        $this->operator = Operator::create(['code' => 'OP1', 'name' => 'Operator One']);
        $this->supervisor = $this->userWith($this->operator, self::SUPERVISOR);
        $this->driver = $this->userWith($this->operator, self::DRIVER);
        $customer = Customer::create(['name' => 'Site A', 'operator_id' => $this->operator->id]);
        $this->vend = Vend::create([
            'code' => 'V801', 'operator_id' => $this->operator->id, 'customer_id' => $customer->id,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
        ]);
        $this->job = OpsJob::create([
            'code' => 80001, 'date' => Carbon::today(), 'operator_id' => $this->operator->id,
            'created_by' => $this->supervisor->id, 'delivered_by' => $this->driver->id,
        ]);

        $magnum = Product::create(['code' => 'P1', 'name' => 'Magnum', 'operator_id' => $this->operator->id]);
        $cornetto = Product::create(['code' => 'P2', 'name' => 'Cornetto', 'operator_id' => $this->operator->id]);
        $this->channel($this->vend, 11, 8, $magnum->id);
        $this->channel($this->vend, 12, 1, $magnum->id);
        $this->channel($this->vend, 13, 0, $cornetto->id); // sold out: never drawn
        $this->channel($this->vend, 14, 5, $cornetto->id);
    }

    private function channel(Vend $vend, int $code, int $qty, int $productId): VendChannel
    {
        return VendChannel::create([
            'vend_id' => $vend->id, 'code' => $code, 'qty' => $qty, 'capacity' => 10, 'amount' => 350,
            'product_id' => $productId, 'is_active' => 1, 'error_rate_json' => [],
        ]);
    }

    private function userWith(Operator $operator, array $actions): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        foreach ($actions as $action) {
            Permission::findOrCreate($action.' stock-checks', 'web');
            $user->givePermissionTo($action.' stock-checks');
        }

        return $user;
    }

    private function assign(array $options = []): StockCheck
    {
        $this->actingAs($this->supervisor)
            ->postJson('/ops-jobs/'.$this->job->id.'/stock-checks', $options + ['vend_id' => $this->vend->id, 'is_random' => false])
            ->assertOk();

        return StockCheck::latest('id')->firstOrFail();
    }

    /** @param  array<int,int>  $byCode  channel code => real qty */
    private function answers(StockCheck $check, array $byCode): array
    {
        return ['channels' => $check->channels->map(fn ($c) => [
            'id' => $c->id, 'counted_qty' => $byCode[$c->vend_channel_code] ?? null,
        ])->all()];
    }

    public function test_not_random_draws_every_stocked_channel_and_skips_the_sold_out_one(): void
    {
        $check = $this->assign();

        $this->assertSame('SC-10001', $check->display_code);
        $this->assertFalse($check->is_random);
        $this->assertSame([11, 12, 14], $check->channels->pluck('vend_channel_code')->all());
        $this->assertSame(350, $check->channels->first()->amount);
        $this->assertNull($check->channels->first()->system_qty, 'system qty is frozen at submit, not at the draw');
    }

    public function test_random_draws_the_asked_number_and_the_product_filter_narrows_it(): void
    {
        $check = $this->assign(['is_random' => true, 'sample_size' => 2]);
        $this->assertCount(2, $check->channels);
        $this->assertSame(2, $check->sample_size);

        $magnum = Product::where('code', 'P1')->value('id');
        $filtered = $this->assign(['is_random' => true, 'sample_size' => 5, 'product_ids' => [$magnum]]);
        $this->assertSame([11, 12], $filtered->channels->pluck('vend_channel_code')->all(), 'fewer matches than asked: take them all');
    }

    public function test_random_needs_a_size_and_a_machine_with_nothing_stocked_is_refused(): void
    {
        $this->actingAs($this->supervisor);
        $this->postJson('/ops-jobs/'.$this->job->id.'/stock-checks', ['vend_id' => $this->vend->id, 'is_random' => true])
            ->assertStatus(422)->assertJsonValidationErrors('sample_size');

        VendChannel::where('vend_id', $this->vend->id)->update(['qty' => 0]);
        $this->postJson('/ops-jobs/'.$this->job->id.'/stock-checks', ['vend_id' => $this->vend->id, 'is_random' => false])
            ->assertStatus(422)->assertJsonValidationErrors('vend_id');

        $this->assertSame(0, StockCheck::count());
    }

    public function test_a_machine_already_on_the_job_for_a_top_up_can_still_be_counted(): void
    {
        OpsJobItem::create([
            'ops_job_id' => $this->job->id, 'vend_id' => $this->vend->id,
            'customer_id' => $this->vend->customer_id, 'status' => (int) OpsJob::STATUS_PENDING,
        ]);

        $this->assertCount(3, $this->assign()->channels);
    }

    public function test_the_draw_options_tell_the_modal_what_can_be_counted(): void
    {
        $this->actingAs($this->supervisor)
            ->getJson('/ops-jobs/'.$this->job->id.'/stock-checks/draw-options/'.$this->vend->id)
            ->assertOk()
            ->assertJsonPath('eligible_count', 3)
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.value', 'P1 Magnum')
            ->assertJsonPath('products.0.channels', 2);
    }

    public function test_the_driver_counts_and_sees_the_variance(): void
    {
        $check = $this->assign();

        // "System shows 1 left, driver finds nothing": 0 must be pickable.
        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 8, 12 => 0, 14 => 7]))
            ->assertOk()
            ->assertJsonPath('stockCheck.status_name', 'Counted')
            ->assertJsonPath('stockCheck.mismatch_count', 2)
            ->assertJsonPath('stockCheck.variance_qty', 1)       // -1 + 2
            ->assertJsonPath('stockCheck.variance_value', 350);  // cents

        $byCode = $check->channels()->get()->keyBy('vend_channel_code');
        $this->assertSame([8, 8, 0], [$byCode[11]->system_qty, $byCode[11]->counted_qty, $byCode[11]->variance_qty]);
        $this->assertSame([1, 0, -1], [$byCode[12]->system_qty, $byCode[12]->counted_qty, $byCode[12]->variance_qty]);
        $this->assertSame(2, $byCode[14]->variance_qty);
        $this->assertSame($this->driver->id, $check->fresh()->counted_by);
        // Counting alone never touches the system quantity.
        $this->assertSame(1, (int) VendChannel::where('vend_id', $this->vend->id)->where('code', 12)->value('qty'));
    }

    public function test_every_drawn_channel_must_be_answered_within_range(): void
    {
        $check = $this->assign();
        $ids = $check->channels->keyBy('vend_channel_code');

        $this->actingAs($this->driver);
        $this->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 8, 14 => 5]))
            ->assertStatus(422)->assertJsonValidationErrors('channels.'.$ids[12]->id);

        $this->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 11, 12 => 1, 14 => 5]))
            ->assertStatus(422)->assertJsonValidationErrors('channels.'.$ids[11]->id);

        $this->assertTrue($check->fresh()->isPending());
        $this->assertNull($ids[11]->fresh()->counted_qty, 'a refused submit writes nothing');
    }

    public function test_the_system_qty_is_the_one_at_the_moment_of_submit(): void
    {
        $check = $this->assign();
        VendChannel::where('vend_id', $this->vend->id)->where('code', 11)->update(['qty' => 6]); // two sold on the way there

        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 6, 12 => 1, 14 => 5]))->assertOk();

        $this->assertSame(0, $check->channels()->where('vend_channel_code', 11)->value('variance_qty'));
    }

    public function test_only_supervisor_and_above_can_assign_redraw_sync_or_delete(): void
    {
        $check = $this->assign(['is_random' => true, 'sample_size' => 1]);

        $this->actingAs($this->driver);
        $this->postJson('/ops-jobs/'.$this->job->id.'/stock-checks', ['vend_id' => $this->vend->id, 'is_random' => false])->assertForbidden();
        $this->postJson('/stock-checks/'.$check->id.'/redraw')->assertForbidden();
        $this->postJson('/stock-checks/'.$check->id.'/sync')->assertForbidden();
        $this->deleteJson('/stock-checks/'.$check->id)->assertForbidden();
        $this->get('/stock-checks/excel')->assertForbidden();

        $this->actingAs(User::factory()->create(['operator_id' => $this->operator->id]))
            ->get('/stock-checks/'.$check->id.'/edit')->assertForbidden();
    }

    public function test_redraw_repeats_the_same_rule_and_is_refused_once_counted(): void
    {
        $check = $this->assign(['is_random' => true, 'sample_size' => 2]);
        $before = $check->channels->pluck('id')->all();

        $this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/redraw')->assertOk();

        $after = $check->channels()->get();
        $this->assertCount(2, $after);
        $this->assertEmpty(array_intersect($before, $after->pluck('id')->all()), 'a re-draw writes a fresh sample');

        $this->actingAs($this->driver)->postJson('/stock-checks/'.$check->id.'/submit', [
            'channels' => $after->map(fn ($c) => ['id' => $c->id, 'counted_qty' => 1])->all(),
        ])->assertOk();

        $this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/redraw')->assertStatus(422);
    }

    public function test_sync_applies_the_variance_to_the_current_system_qty(): void
    {
        $check = $this->assign();
        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 5, 12 => 0, 14 => 5]))->assertOk();

        // Two more sold from channel 11 between the count and the sync: three are
        // still missing, so the right figure is 6 - 3 = 3, NOT the counted 5.
        VendChannel::where('vend_id', $this->vend->id)->where('code', 11)->update(['qty' => 6]);

        $this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/sync')
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('stockCheck.unsynced_mismatch_count', 0);

        $qty = fn (int $code) => (int) VendChannel::where('vend_id', $this->vend->id)->where('code', $code)->value('qty');
        $this->assertSame(3, $qty(11));
        $this->assertSame(0, $qty(12));
        $this->assertSame(5, $qty(14), 'a matching channel is left alone');

        $synced = $check->channels()->where('vend_channel_code', 11)->first();
        $this->assertSame([6, 3], [$synced->qty_before_sync, $synced->qty_after_sync]);
        $this->assertSame($this->supervisor->id, $synced->synced_by);
        $this->assertNotNull($check->fresh()->synced_at);
        Bus::assertDispatched(SaveVendChannelsJson::class);

        // Idempotent: a second press finds nothing left and changes nothing.
        $this->postJson('/stock-checks/'.$check->id.'/sync')->assertStatus(422)->assertJsonValidationErrors('sync');
        $this->assertSame(3, $qty(11));

        // A synced count is history.
        $this->postJson('/stock-checks/'.$check->id.'/undo')->assertStatus(422);
        $this->deleteJson('/stock-checks/'.$check->id)->assertStatus(422);
    }

    public function test_a_queue_that_cannot_be_reached_does_not_fail_a_committed_sync(): void
    {
        $check = $this->assign();
        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 8, 12 => 0, 14 => 5]))->assertOk();

        // Seen in a preview with no Redis: the qty was written, then the snapshot
        // job's dispatch threw and the person saw a 500 for a sync that had worked.
        Bus::swap(new class(app(\Illuminate\Contracts\Bus\Dispatcher::class)) extends \Illuminate\Support\Testing\Fakes\BusFake
        {
            public function dispatch($command)
            {
                throw new \RuntimeException('Connection refused');
            }
        });

        $this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/sync')->assertOk();

        $this->assertSame(0, (int) VendChannel::where('vend_id', $this->vend->id)->where('code', 12)->value('qty'));
        $this->assertNotNull($check->fresh()->synced_at);
    }

    public function test_sync_skips_a_channel_whose_product_changed_and_never_goes_below_zero(): void
    {
        $check = $this->assign();
        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 2, 12 => 1, 14 => 0]))->assertOk();

        VendChannel::where('vend_id', $this->vend->id)->where('code', 11)->update(['qty' => 3]);  // 3 - 6 would be -3
        VendChannel::where('vend_id', $this->vend->id)->where('code', 14)->update(['product_id' => Product::where('code', 'P1')->value('id')]);

        $results = collect($this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/sync')->assertOk()->json('results'))
            ->keyBy('channel_code');

        $this->assertTrue($results[11]['applied']);
        $this->assertSame(0, $results[11]['qty_after']);
        $this->assertFalse($results[14]['applied']);
        $this->assertStringContainsString('product', $results[14]['reason']);
        $this->assertSame(5, (int) VendChannel::where('vend_id', $this->vend->id)->where('code', 14)->value('qty'));
    }

    public function test_a_citybox_chiller_is_counted_but_never_synced(): void
    {
        $this->vend->update(['machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER]);
        $check = $this->assign();
        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 5, 12 => 1, 14 => 5]))->assertOk();

        $this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/sync')
            ->assertStatus(422)->assertJsonValidationErrors('sync');

        $this->assertSame(8, (int) VendChannel::where('vend_id', $this->vend->id)->where('code', 11)->value('qty'));
        $this->assertNull($check->fresh()->synced_at);
    }

    public function test_undo_reopens_an_unsynced_count(): void
    {
        $check = $this->assign();
        $this->actingAs($this->driver);
        $this->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 5, 12 => 1, 14 => 5]))->assertOk();
        $this->postJson('/stock-checks/'.$check->id.'/undo')->assertOk();

        $check->refresh();
        $this->assertTrue($check->isPending());
        $this->assertSame($this->driver->id, $check->undo_counted_by);
        $this->assertNull($check->channels()->first()->counted_qty);
    }

    public function test_another_operators_count_does_not_exist_for_a_scoped_viewer(): void
    {
        $check = $this->assign();
        $other = Operator::create(['code' => 'OP2', 'name' => 'Operator Two']);
        $this->assertNotSame(1, $other->id);

        $this->actingAs($this->userWith($other, self::SUPERVISOR));
        $this->get('/stock-checks/'.$check->id.'/edit')->assertNotFound();
        $this->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 0, 12 => 0, 14 => 0]))->assertNotFound();
        $this->postJson('/stock-checks/'.$check->id.'/redraw')->assertNotFound();
        $this->postJson('/ops-jobs/'.$this->job->id.'/stock-checks', ['vend_id' => $this->vend->id, 'is_random' => false])->assertNotFound();

        $this->assertTrue($check->fresh()->isPending());
    }

    public function test_it_never_touches_the_nightly_stock_count_report_tables(): void
    {
        $check = $this->assign();
        $this->actingAs($this->driver)
            ->postJson('/stock-checks/'.$check->id.'/submit', $this->answers($check, [11 => 5, 12 => 0, 14 => 5]))->assertOk();
        $this->actingAs($this->supervisor)->postJson('/stock-checks/'.$check->id.'/sync')->assertOk();

        $this->assertDatabaseCount('stock_counts', 0);
        $this->assertDatabaseCount('stock_count_items', 0);
    }
}
