<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Contracts\Citybox\VisitWindowProvider;
use App\Enums\Citybox\MovementType;
use App\Exceptions\CityboxApiException;
use App\Jobs\SubmitCityboxCount;
use App\Models\CityboxDoorOpenLog;
use App\Models\CityboxStockMovement;
use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelRecord;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\RestockVisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class CityboxRestockVisitTest extends TestCase
{
    use RefreshDatabase;

    protected FakeChillerGateway $gw;

    protected Vend $vend;

    protected User $driver;

    protected OpsJob $job;

    protected OpsJobItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        Queue::fake([\App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        Permission::findOrCreate('update operations', 'web');

        $customer = Customer::create(['name' => 'Raffles L1', 'code' => 10001, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->vend = Vend::create(['code' => 9600, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => 1, 'customer_id' => $customer->id]);
        $this->driver = User::factory()->create();
        $this->job = OpsJob::create(['code' => 900100, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->driver->id, 'operator_id' => 1]);
        $this->item = OpsJobItem::create(['ops_job_id' => $this->job->id, 'vend_id' => $this->vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PICKED]);

        $this->gw->seedDevice('E1');
        $this->gw->seedPar('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 5, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12'],
        ]);
        $this->gw->seedStock('E1', [
            ['id' => 90340, 'name' => 'Peach', 'qty' => 1, 'layer' => 1, 'price' => '0.10'],
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 0, 'layer' => 1, 'price' => '0.12'],
        ]);
        // Bring channels into existence via a poll (codes: 90338→101, 90340→102)
        app(CityboxOpenapiSync::class)->syncAll();
        foreach (VendChannel::where('vend_id', $this->vend->id)->get() as $vc) {
            OpsJobItemChannel::create(['ops_job_id' => $this->job->id, 'ops_job_item_id' => $this->item->id, 'vend_channel_id' => $vc->id, 'vend_channel_code' => $vc->code, 'vend_code' => $this->vend->code, 'product_id' => $vc->product_id ?? 1, 'qty' => $vc->qty, 'capacity' => $vc->capacity, 'picked_qty' => 0]);
        }
    }

    // ── open door ──────────────────────────────────────────────────────────

    public function test_open_door_logs_stores_msg_id_on_item_and_dispatches_a_b_frame(): void
    {
        $session = app(RestockVisitService::class)->openDoor($this->item, $this->driver, CityboxDoorOpenLog::SOURCE_OPS_JOB_PAGE);

        $log = CityboxDoorOpenLog::sole();
        $this->assertSame([CityboxDoorOpenLog::RESULT_OPENED, $this->item->id, $this->driver->id, 'ops_job_page', 'FREE'],
            [$log->result, $log->ops_job_item_id, $log->user_id, $log->source, $log->device_state_before]);
        $this->assertSame($session->msgId, $log->msg_id);
        $this->assertSame($session->msgId, $this->item->fresh()->citybox_msg_id);
        // B frame landed: a vend_channel_records row with before data
        $rec = VendChannelRecord::where('vend_id', $this->vend->id)->latest('id')->first();
        $this->assertNotNull($rec);
        $this->assertSame('B', $rec->before_label);
        $this->assertNotNull($rec->before_data_created_at);
    }

    /** Brian, 2026-09-03: the door stays openable after Stocked-In (rearranging goods);
     *  an open on an item whose push failed for want of a session re-queues the push
     *  and does NOT file a B frame (the goods are already in — not a "before"). */
    public function test_open_after_stocked_in_rearms_a_failed_push_and_files_no_b_frame(): void
    {
        Queue::fake();
        $this->item->forceFill(['status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now(), 'citybox_submit_status' => 'failed', 'citybox_submit_error' => 'No door-open session'])->saveQuietly();

        $session = app(RestockVisitService::class)->openDoor($this->item, $this->driver, CityboxDoorOpenLog::SOURCE_OPS_JOB_PAGE);

        $fresh = $this->item->fresh();
        $this->assertSame($session->msgId, $fresh->citybox_msg_id);
        $this->assertSame('pending', $fresh->citybox_submit_status);
        $this->assertNull($fresh->citybox_submit_error);
        Queue::assertPushed(SubmitCityboxCount::class, fn ($j) => $j->opsJobItemId === $this->item->id);
        $this->assertSame(1, CityboxDoorOpenLog::where('ops_job_item_id', $this->item->id)->count()); // movement logged
        $this->assertNull(VendChannelRecord::where('vend_id', $this->vend->id)->first()); // no B frame
    }

    public function test_open_after_stocked_in_with_a_successful_push_does_not_resubmit(): void
    {
        Queue::fake();
        $this->item->forceFill(['status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now(), 'citybox_submit_status' => 'ok', 'citybox_msg_id' => 'old'])->saveQuietly();

        app(RestockVisitService::class)->openDoor($this->item, $this->driver);

        $this->assertSame('ok', $this->item->fresh()->citybox_submit_status);
        Queue::assertNotPushed(SubmitCityboxCount::class);
    }

    public function test_second_open_overwrites_msg_id_with_latest(): void
    {
        $svc = app(RestockVisitService::class);
        $svc->openDoor($this->item, $this->driver);
        $second = $svc->openDoor($this->item, $this->driver);

        $this->assertSame($second->msgId, $this->item->fresh()->citybox_msg_id);
        $this->assertSame(2, CityboxDoorOpenLog::count());
    }

    public function test_refused_open_is_logged_as_refused_and_rethrown(): void
    {
        $this->gw->openRefusals['E1'] = '售货机失联=>20002';

        try {
            app(RestockVisitService::class)->openDoor($this->item, $this->driver);
            $this->fail('expected refusal');
        } catch (CityboxApiException) {
        }

        $log = CityboxDoorOpenLog::sole();
        $this->assertSame(CityboxDoorOpenLog::RESULT_REFUSED, $log->result);
        $this->assertStringContainsString('失联', $log->citybox_message);
        $this->assertNull($this->item->fresh()->citybox_msg_id);
    }

    // ── stock-in → submit ──────────────────────────────────────────────────

    protected function stockIn(array $refillByCode): void
    {
        // Mirror the controller: item flips to DELIVERED first, THEN channels get
        // actual_* — hence the observer queues SubmitCityboxCount with a delay.
        // The sync queue driver ignores delays (it would run BEFORE actual_qty is
        // written, exactly the hazard the delay prevents in prod), so tests fake
        // that one job and drive it by hand after the channel writes.
        Queue::fake([SubmitCityboxCount::class, \App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        $this->item->update(['status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now(), 'completed_by' => $this->driver->id]);
        foreach ($this->item->opsJobItemChannels as $ch) {
            $ch->update(['actual_before_qty' => $ch->qty, 'actual_qty' => $refillByCode[(int) $ch->vend_channel_code] ?? 0]);
        }
    }

    public function test_observer_marks_pending_and_queues_the_delayed_submit_only_for_chillers(): void
    {
        Queue::fake();
        $this->stockIn([101 => 5, 102 => 4]);

        $this->assertSame('pending', $this->item->fresh()->citybox_submit_status);
        Queue::assertPushed(SubmitCityboxCount::class, fn ($j) => $j->opsJobItemId === $this->item->id && $j->delay !== null);

        // A vending-machine item never queues anything
        $vm = Vend::create(['code' => 9601, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1]);
        $vmItem = OpsJobItem::create(['ops_job_id' => $this->job->id, 'vend_id' => $vm->id, 'customer_id' => $this->vend->customer_id, 'status' => OpsJob::STATUS_PICKED]);
        $vmItem->update(['status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now()]);
        $this->assertNull($vmItem->fresh()->citybox_submit_status);
        Queue::assertPushed(SubmitCityboxCount::class, 1);
    }

    public function test_submit_pushes_before_plus_stock_in_per_product_marks_ok_and_dispatches_a_frame(): void
    {
        app(RestockVisitService::class)->openDoor($this->item, $this->driver); // msg_id
        $this->stockIn([101 => 5, 102 => 4]);   // Suntory: 0+5, Peach: 1+4

        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));

        $this->assertCount(1, $this->gw->submits);
        $rows = collect($this->gw->submits[0]['rows'])->keyBy('product_id');
        $this->assertSame(5, $rows[90338]['reality_stock']);
        $this->assertSame(5, $rows[90340]['reality_stock']);
        $this->assertSame($this->item->fresh()->citybox_msg_id, $this->gw->submits[0]['msgId']);
        $item = $this->item->fresh();
        $this->assertSame('ok', $item->citybox_submit_status);
        $this->assertNotNull($item->citybox_submitted_at);
        // A frame: record now has after data
        $rec = VendChannelRecord::where('vend_id', $this->vend->id)->latest('id')->first();
        $this->assertSame('A', $rec->after_label);
        // and the fake mirrored the submit into live stock, so channels show the new on-hand
        $this->assertSame(5, VendChannel::where('vend_id', $this->vend->id)->where('code', 102)->first()->qty);
    }

    public function test_submit_without_door_open_fails_with_a_clear_reason_and_never_blocks_the_item(): void
    {
        $this->stockIn([101 => 5]);
        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));

        $item = $this->item->fresh();
        $this->assertSame('failed', $item->citybox_submit_status);
        $this->assertStringContainsString('Open Door', $item->citybox_submit_error);
        $this->assertSame((string) OpsJob::STATUS_DELIVERED, (string) $item->status); // still stocked in
        $this->assertCount(0, $this->gw->submits);
    }

    /** Brian, 2026-09-03: the push must not depend on an item-page door open. A door
     *  opened any other way (Settings page here) is the same physical visit — its
     *  session is adopted onto the item and the count goes through. */
    public function test_submit_adopts_the_chillers_latest_door_open_from_any_source(): void
    {
        $session = app(RestockVisitService::class)->openDoor($this->vend, $this->driver, CityboxDoorOpenLog::SOURCE_VEND_SETTINGS);
        $this->assertNull($this->item->fresh()->citybox_msg_id);
        $this->stockIn([101 => 5, 102 => 4]);

        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));

        $item = $this->item->fresh();
        $this->assertSame('ok', $item->citybox_submit_status);
        $this->assertSame($session->msgId, $item->citybox_msg_id);
        $this->assertCount(1, $this->gw->submits);
        $this->assertSame($session->msgId, $this->gw->submits[0]['msgId']);
    }

    // ── undo stock in → revert ───────────────────────────────────────────────

    /** Brian, 2026-09-03: Undo Stock In puts CityBox back to the pre-restock count. */
    public function test_undo_stock_in_restores_the_previous_count_in_citybox(): void
    {
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->stockIn([101 => 5, 102 => 4]); // before: 101=0, 102=1
        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));
        $this->assertSame('ok', $this->item->fresh()->citybox_submit_status);

        Queue::fake();
        $this->actingAs($this->driver);
        app(\App\Http\Controllers\OpsJobController::class)->undoItemStatus($this->item->id);

        $item = $this->item->fresh();
        $this->assertSame((string) OpsJob::STATUS_PICKED, (string) $item->status);
        $this->assertSame('reverting', $item->citybox_submit_status);
        Queue::assertPushed(SubmitCityboxCount::class, fn ($j) => $j->opsJobItemId === $this->item->id && $j->revert === true);

        (new SubmitCityboxCount($this->item->id, true))->handle(app(RestockVisitService::class));

        $item = $this->item->fresh();
        $this->assertSame('reverted', $item->citybox_submit_status);
        $this->assertNotNull($item->citybox_submitted_at);
        $this->assertCount(2, $this->gw->submits);
        $reverted = collect($this->gw->submits[1]['rows'])->pluck('reality_stock', 'product_id')->all();
        $this->assertSame([90338 => 0, 90340 => 1], $reverted);
    }

    public function test_stock_in_again_after_a_revert_pushes_the_fresh_count(): void
    {
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->stockIn([101 => 5, 102 => 4]);
        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));
        $this->actingAs($this->driver);
        app(\App\Http\Controllers\OpsJobController::class)->undoItemStatus($this->item->id);
        (new SubmitCityboxCount($this->item->id, true))->handle(app(RestockVisitService::class));
        $this->assertSame('reverted', $this->item->fresh()->citybox_submit_status);

        $this->item->refresh(); // the undo ran on another instance
        $this->stockIn([101 => 2, 102 => 3]); // observer arms 'pending' again
        $this->assertSame('pending', $this->item->fresh()->citybox_submit_status);
        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));

        $this->assertSame('ok', $this->item->fresh()->citybox_submit_status);
        $this->assertCount(3, $this->gw->submits);
        $fresh = collect($this->gw->submits[2]['rows'])->pluck('reality_stock', 'product_id')->all();
        $this->assertSame([90338 => 2, 90340 => 4], $fresh);
    }

    public function test_revert_job_is_a_no_op_once_the_item_is_stocked_in_again(): void
    {
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->stockIn([101 => 5]);
        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));
        $this->actingAs($this->driver);
        app(\App\Http\Controllers\OpsJobController::class)->undoItemStatus($this->item->id);
        $this->item->refresh();
        $this->stockIn([101 => 3]); // re-stocked before the queued revert ran
        $this->assertSame('pending', $this->item->fresh()->citybox_submit_status);

        (new SubmitCityboxCount($this->item->id, true))->handle(app(RestockVisitService::class));

        $this->assertSame('pending', $this->item->fresh()->citybox_submit_status); // untouched
        $this->assertCount(1, $this->gw->submits);
    }

    // ── before / after refill from CityBox (Brian, 2026-09-03) ──────────────

    public function test_stock_in_files_before_from_the_last_poll_and_after_from_a_fresh_pull(): void
    {
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $lastPoll = \App\Models\CityboxInventoryPoll::where('vend_id', $this->vend->id)->orderByDesc('polled_at')->first();
        $this->assertNotNull($lastPoll); // setUp's syncAll polled: 90338→0, 90340→1
        $this->stockIn([101 => 5, 102 => 4]);

        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));

        $item = $this->item->fresh();
        $this->assertNotNull($item->vend_channel_record_id);
        $rec = VendChannelRecord::find($item->vend_channel_record_id);
        $this->assertSame('B', $rec->before_label);
        $this->assertSame($lastPoll->polled_at->toDateTimeString(), $rec->before_data_created_at->toDateTimeString());
        $this->assertSame('citybox_poll', $rec->before_data_json['source']);
        $this->assertSame('A', $rec->after_label);
        $this->assertNotNull($rec->after_data_created_at);

        $byCode = $item->opsJobItemChannels->keyBy('vend_channel_code');
        $this->assertSame([0, 1], [(int) $byCode[101]->vmc_before_qty, (int) $byCode[102]->vmc_before_qty]);
        // The fake mirrors their behaviour: submitted numbers become the live stock.
        $this->assertSame([5, 5], [(int) $byCode[101]->vmc_after_qty, (int) $byCode[102]->vmc_after_qty]);
    }

    public function test_undo_revert_clears_the_after_refill_but_keeps_before(): void
    {
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->stockIn([101 => 5, 102 => 4]);
        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));
        $this->actingAs($this->driver);
        app(\App\Http\Controllers\OpsJobController::class)->undoItemStatus($this->item->id);
        (new SubmitCityboxCount($this->item->id, true))->handle(app(RestockVisitService::class));

        $item = $this->item->fresh();
        $rec = VendChannelRecord::find($item->vend_channel_record_id);
        $this->assertSame('B', $rec->before_label);
        $this->assertNull($rec->after_data_created_at);
        $this->assertNull($rec->after_data_json);
        $byCode = $item->opsJobItemChannels->keyBy('vend_channel_code');
        $this->assertSame(0, (int) $byCode[101]->vmc_before_qty);
        $this->assertNull($byCode[101]->vmc_after_qty);
    }

    public function test_failed_submit_requeues_with_backoff_until_max_attempts(): void
    {
        Queue::fake();
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->stockIn([101 => 5]);
        $failing = new class extends FakeChillerGateway
        {
            public function submitCount(\App\Services\Citybox\DTO\RestockSession $s, \App\Services\Citybox\DTO\StockCount $c): void
            {
                throw new CityboxApiException('msg_id expired');
            }
        };
        $failing->seedDevice('E1')->seedPar('E1', [['id' => 90338, 'name' => 'S', 'qty' => 5, 'layer' => 1]])->seedStock('E1', [['id' => 90338, 'name' => 'S', 'qty' => 0, 'layer' => 1]]);
        $this->app->instance(ChillerGateway::class, $failing);

        (new SubmitCityboxCount($this->item->id))->handle(app(RestockVisitService::class));

        $this->assertSame('failed', $this->item->fresh()->citybox_submit_status);
        $this->assertSame(1, $this->item->fresh()->citybox_submit_attempts);
        Queue::assertPushed(SubmitCityboxCount::class, fn ($j) => $j->delay !== null); // re-queued
    }

    public function test_poller_skips_channel_writes_while_a_submit_is_pending_but_still_records_the_poll(): void
    {
        $this->stockIn([101 => 5]);                       // observer → pending
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 0, 'layer' => 1]]); // their PRE-restock number
        $before = VendChannel::where('vend_id', $this->vend->id)->where('code', 102)->first()->qty;

        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame($before, VendChannel::where('vend_id', $this->vend->id)->where('code', 102)->first()->qty); // NOT overwritten
        $this->assertSame(2, \App\Models\CityboxInventoryPoll::count()); // poll still logged
    }

    // ── movement classification uses the door-open log ─────────────────────

    public function test_rise_inside_a_visit_window_is_a_restock_carrying_the_item(): void
    {
        $this->assertInstanceOf(\App\Services\Citybox\DoorOpenLogVisitWindowProvider::class, app(VisitWindowProvider::class));
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->travel(2)->minutes();
        $this->gw->seedStock('E1', [['id' => 90340, 'name' => 'Peach', 'qty' => 5, 'layer' => 1], ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1]]);

        app(CityboxOpenapiSync::class)->syncAll();

        $m = CityboxStockMovement::where('citybox_product_id', 90340)->latest('id')->first();
        $this->assertSame(MovementType::Restock, $m->movement_type);
        $this->assertSame($this->item->id, $m->ops_job_item_id);
    }

    // ── HTTP: driver-level access ──────────────────────────────────────────

    public function test_assigned_driver_can_open_door_without_any_permission_and_others_cannot(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertForbidden();

        $this->actingAs($this->driver)->from('/ops-jobs/1')->post("/ops-jobs/items/{$this->item->id}/citybox-open-door", ['source' => 'ops_job_page'])
            ->assertRedirect('/ops-jobs/1')->assertSessionHas('success');
        $this->assertSame('ops_job_page', CityboxDoorOpenLog::sole()->source);

        // ops user (not the driver) with update operations can too
        $ops = User::factory()->create();
        $ops->givePermissionTo('update operations');
        $this->travel(30)->seconds(); // past the per-item rate limit
        $this->actingAs($ops)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertRedirect();
        $this->assertSame(2, CityboxDoorOpenLog::count());
    }

    public function test_double_tap_is_rate_limited(): void
    {
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertSessionHas('success');
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertSessionHasErrors('citybox');
        $this->assertSame(1, CityboxDoorOpenLog::count());
    }

    public function test_offline_refusal_is_translated_for_the_driver(): void
    {
        $this->gw->openRefusals['E1'] = '售货机失联=>20002';
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")
            ->assertSessionHasErrors('citybox');
        $this->assertStringContainsString('offline', session('errors')->first('citybox'));
    }

    public function test_refused_open_does_not_arm_the_double_tap_guard(): void
    {
        $this->gw->openRefusals['E1'] = '售货机失联=>20002';
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertSessionHasErrors('citybox');

        // Chiller comes back online: the very next press must reach CityBox, not the 20 s guard.
        unset($this->gw->openRefusals['E1']);
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertSessionHas('success');
        $this->assertSame(2, CityboxDoorOpenLog::count());
        $this->assertSame(CityboxDoorOpenLog::RESULT_OPENED, CityboxDoorOpenLog::latest('id')->first()->result);
    }

    public function test_open_door_route_is_403_for_a_vending_machine_item(): void
    {
        $vm = Vend::create(['code' => 9602, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1]);
        $vmItem = OpsJobItem::create(['ops_job_id' => $this->job->id, 'vend_id' => $vm->id, 'customer_id' => $this->vend->customer_id, 'status' => 2]);
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$vmItem->id}/citybox-open-door")->assertForbidden();
    }

    public function test_retry_endpoint_requeues_and_door_opens_endpoint_lists_history(): void
    {
        Queue::fake();
        app(RestockVisitService::class)->openDoor($this->item, $this->driver);
        $this->stockIn([101 => 5]);
        $this->item->forceFill(['citybox_submit_status' => 'failed', 'citybox_submit_error' => 'boom'])->saveQuietly();

        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-retry-submit")->assertRedirect()->assertSessionHas('success');
        $this->assertSame('pending', $this->item->fresh()->citybox_submit_status);
        Queue::assertPushed(SubmitCityboxCount::class);

        $this->actingAs($this->driver)->getJson("/ops-jobs/items/{$this->item->id}/citybox-door-opens")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.result', 'opened')->assertJsonPath('0.this_item', true);
    }
}
