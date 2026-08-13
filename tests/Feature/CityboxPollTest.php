<?php

namespace Tests\Feature;

use App\Jobs\PollCityboxEquipment;
use App\Models\Vend;
use App\Services\Citybox\CityboxEquipmentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CityboxPollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'citybox.enabled' => true,
            'citybox.base_url' => 'https://citybox.test',
            'citybox.api_secret' => 'test-secret',
        ]);
    }

    private function makeChiller(string $equipmentId, array $overrides = []): Vend
    {
        return Vend::create(array_merge([
            'code' => 9001,
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => $equipmentId,
            'is_active' => 1,
        ], $overrides));
    }

    // ── scheduler guard ────────────────────────────────────────────────────

    public function test_poll_is_a_noop_when_no_vend_is_linked(): void
    {
        Queue::fake();
        Http::fake();

        $this->artisan('citybox:poll')->assertSuccessful();

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_poll_is_a_noop_when_integration_disabled_even_with_linked_vend(): void
    {
        config(['citybox.enabled' => false]);
        $this->makeChiller('08DFF2CEB11');
        Queue::fake();

        $this->artisan('citybox:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_poll_dispatches_the_sweep_once_a_vend_is_linked(): void
    {
        $this->makeChiller('08DFF2CEB11');
        Queue::fake();

        $this->artisan('citybox:poll')->assertSuccessful();

        Queue::assertPushed(PollCityboxEquipment::class, 1);
    }

    // ── sync behaviour ─────────────────────────────────────────────────────

    private function fakeCityboxApi(array $equipmentList, array $productList = []): void
    {
        Http::fake([
            'citybox.test/api/apiThredDetail/getEqiupment' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => ['list' => $equipmentList, 'total' => count($equipmentList), 'page' => 1, 'last_page' => 1],
            ]),
            'citybox.test/api/apiThredDetail/getEqiupmentProduct' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => ['list' => $productList, 'total' => count($productList), 'page' => 1, 'last_page' => 1],
            ]),
        ]);
    }

    public function test_sync_maps_status_online_and_stock_onto_the_vend(): void
    {
        $vend = $this->makeChiller('08DFF2CEB11');

        $this->fakeCityboxApi(
            [[
                'equipment_id' => '08DFF2CEB11',
                'equipment_name' => 'A座1楼01号柜',
                'equipment_status' => 1,
                'equipment_status_str' => '启运',
                'equipment_online_status' => 1,
                'heartbeat_last_offline' => '2026-08-06 10:00:00',
                'heartbeat_last_recovery' => '2026-08-06 10:05:00',
            ]],
            [[
                'equipment_id' => '08DFF2CEB11',
                'name' => 'A座1楼01号柜',
                'product_name' => '农夫山泉550ml',
                'id' => 1234,
                'stock' => 10,
                'pre_qty' => 5,
            ]],
        );

        $summary = app(CityboxEquipmentSync::class)->syncAll();
        $vend->refresh();

        $this->assertSame(1, $summary['matched']);
        $this->assertTrue((bool) $vend->is_online);
        $this->assertNotNull($vend->citybox_synced_at);
        $this->assertNotNull($vend->last_updated_at); // keeps the 5-min offline sweeper at bay
        $this->assertSame(1, $vend->citybox_status_json['equipment_status']);
        $this->assertSame(10, $vend->citybox_status_json['stock'][1234]['stock']);
        $this->assertSame(5, $vend->citybox_status_json['stock'][1234]['pre_qty']);
        $this->assertNull($vend->citybox_status_json['stock_changes']); // first poll — no previous snapshot
    }

    public function test_second_sync_records_stock_changes_as_provisional_signal(): void
    {
        $vend = $this->makeChiller('08DFF2CEB11', [
            'citybox_status_json' => [
                'stock' => [1234 => ['product_name' => '农夫山泉550ml', 'stock' => 10, 'pre_qty' => 0]],
            ],
        ]);

        $this->fakeCityboxApi(
            [['equipment_id' => '08DFF2CEB11', 'equipment_status' => 1, 'equipment_online_status' => 1]],
            [['equipment_id' => '08DFF2CEB11', 'product_name' => '农夫山泉550ml', 'id' => 1234, 'stock' => 8, 'pre_qty' => 0]],
        );

        app(CityboxEquipmentSync::class)->syncAll();
        $vend->refresh();

        $changes = $vend->citybox_status_json['stock_changes'];
        $this->assertCount(1, $changes);
        $this->assertSame(10, $changes[0]['from']);
        $this->assertSame(8, $changes[0]['to']);
    }

    public function test_offline_and_removed_status_do_not_deactivate_the_vend(): void
    {
        $vend = $this->makeChiller('08DFF2CEB11');

        $this->fakeCityboxApi(
            [['equipment_id' => '08DFF2CEB11', 'equipment_status' => 99, 'equipment_status_str' => '已撤机', 'equipment_online_status' => 2]],
        );

        app(CityboxEquipmentSync::class)->syncAll();
        $vend->refresh();

        $this->assertFalse((bool) $vend->is_online);
        $this->assertTrue((bool) $vend->is_active); // recorded + logged, never auto-flipped
        $this->assertSame(99, $vend->citybox_status_json['equipment_status']);
    }

    public function test_sync_reports_unknown_and_missing_equipment_without_creating_vends(): void
    {
        $this->makeChiller('OUR-ONLY-1');

        $this->fakeCityboxApi(
            [['equipment_id' => 'THEIRS-ONLY-9', 'equipment_status' => 1, 'equipment_online_status' => 1]],
        );

        $summary = app(CityboxEquipmentSync::class)->syncAll();

        $this->assertSame(['THEIRS-ONLY-9'], $summary['unknown']);
        $this->assertSame(['OUR-ONLY-1'], $summary['missing']);
        $this->assertSame(0, $summary['matched']);
        $this->assertNull(Vend::where('citybox_equipment_id', 'THEIRS-ONLY-9')->first());
    }

    public function test_pull_refreshes_a_single_vend_using_exact_equipment_id_match(): void
    {
        // Seed previous stock — the empty product list from the targeted pull
        // must CLEAR it (empty means "no products"), not preserve it.
        $vend = $this->makeChiller('08DFF2CEB11', [
            'citybox_status_json' => [
                'stock' => [1234 => ['product_name' => '农夫山泉550ml', 'stock' => 10, 'pre_qty' => 0]],
            ],
        ]);

        // Their filter is fuzzy — response includes a near-miss that must be ignored.
        Http::fake([
            'citybox.test/api/apiThredDetail/getEqiupment' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => ['list' => [
                    ['equipment_id' => '08DFF2CEB110', 'equipment_status' => 0, 'equipment_online_status' => 2],
                    ['equipment_id' => '08DFF2CEB11', 'equipment_status' => 1, 'equipment_online_status' => 1],
                ], 'total' => 2, 'page' => 1, 'last_page' => 1],
            ]),
            'citybox.test/api/apiThredDetail/getEqiupmentProduct' => Http::response([
                'code' => 200, 'msg' => 'success',
                'data' => ['list' => [], 'total' => 0, 'page' => 1, 'last_page' => 1],
            ]),
        ]);

        $vend = app(CityboxEquipmentSync::class)->pull($vend);

        $this->assertTrue((bool) $vend->is_online);
        $this->assertSame(1, $vend->citybox_status_json['equipment_status']);
        $this->assertSame([], $vend->citybox_status_json['stock']);
    }

    public function test_repeated_non_running_status_warns_once_not_every_poll(): void
    {
        $vend = $this->makeChiller('08DFF2CEB11');

        $this->fakeCityboxApi(
            [['equipment_id' => '08DFF2CEB11', 'equipment_status' => 99, 'equipment_status_str' => '已撤机', 'equipment_online_status' => 2]],
        );

        \Illuminate\Support\Facades\Log::spy();

        $sync = app(CityboxEquipmentSync::class);
        $sync->syncAll(); // transition → warn
        $sync->syncAll(); // still 99 → silent

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->with('Citybox reports non-running status for active vend', \Mockery::type('array'))
            ->once();
    }

    public function test_unknown_equipment_is_logged_on_change_not_every_sweep(): void
    {
        $this->makeChiller('OUR-ONLY-1');

        $this->fakeCityboxApi(
            [['equipment_id' => 'THEIRS-ONLY-9', 'equipment_status' => 1, 'equipment_online_status' => 1]],
        );

        \Illuminate\Support\Facades\Log::spy();

        (new PollCityboxEquipment)->handle(app(CityboxEquipmentSync::class));
        (new PollCityboxEquipment)->handle(app(CityboxEquipmentSync::class)); // same sets → silent

        \Illuminate\Support\Facades\Log::shouldHaveReceived('notice')
            ->with('Citybox poll: unimported equipment seen', ['equipment_ids' => ['THEIRS-ONLY-9']])
            ->once();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->with('Citybox poll: linked vends missing from supplier fleet list', ['equipment_ids' => ['OUR-ONLY-1']])
            ->once();
    }

    public function test_unique_citybox_equipment_id_blocks_duplicate_import(): void
    {
        $this->makeChiller('08DFF2CEB11');

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeChiller('08DFF2CEB11', ['code' => 9002]);
    }
}
