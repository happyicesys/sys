<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Enums\Citybox\DeviceState;
use App\Models\User;
use App\Models\Vend;
use App\Services\Citybox\CityboxOpenapiGateway;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\DeviceSyncService;
use App\Services\Citybox\RestockVisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * Step-0 refactor proof: services depend on ChillerGateway, so a fake plugs
 * in via the container and no HTTP is needed. Also proves the real gateway
 * maps arrays → VOs against the live token/box_list shapes.
 */
class CityboxGatewayServicesTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    protected function setUp(): void
    {
        parent::setUp();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'APP', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
    }

    private function chiller(string $eq): Vend
    {
        return Vend::create(['code' => 9200, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => $eq, 'is_active' => 1]);
    }

    public function test_container_binds_the_real_gateway_by_default(): void
    {
        $this->app->forgetInstance(ChillerGateway::class);
        $this->assertInstanceOf(CityboxOpenapiGateway::class, app(ChillerGateway::class));
    }

    public function test_device_sync_writes_status_and_keeps_stock_key_untouched(): void
    {
        $vend = $this->chiller('ICB26F9FUPE7');
        $vend->forceFill(['citybox_status_json' => ['stock' => ['p1' => ['quantity' => 3]]]])->save();
        $this->gw->seedDevice('ICB26F9FUPE7', name: '#1', status: '1');

        $r = app(DeviceSyncService::class)->syncFleet();
        $vend->refresh();

        $this->assertSame(1, $r['matched']);
        $this->assertTrue((bool) $vend->is_online);
        $this->assertSame('#1', $vend->citybox_status_json['name']);
        $this->assertSame(1, $vend->citybox_status_json['equipment_status']);
        $this->assertSame('2026-08-19 01:14:36', $vend->citybox_status_json['heartbeat_last_recovery']);
        $this->assertSame(3, $vend->citybox_status_json['stock']['p1']['quantity']); // status sync never clobbers stock
    }

    public function test_stock_poll_writes_snapshot_in_cents_and_reports_per_device_errors(): void
    {
        $ok = $this->chiller('OK1');
        $bad = Vend::create(['code' => 9201, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'BAD1', 'is_active' => 1]);
        $this->gw->seedDevice('OK1')->seedDevice('BAD1', online: 2);
        $this->gw->seedStock('OK1', [['id' => 90340, 'name' => 'Peach', 'qty' => 2, 'layer' => 1, 'price' => '0.10']]);
        $this->gw->stockErrors['BAD1'] = '此设备没有商品';

        $summary = app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(2, $summary['matched']);
        $this->assertSame(['BAD1' => '此设备没有商品'], $summary['stock_errors']);
        $this->assertSame(2, $ok->fresh()->citybox_status_json['stock']['p90340']['quantity']);
        $this->assertSame(10, $ok->fresh()->citybox_status_json['stock']['p90340']['active_price']);
        $this->assertArrayNotHasKey('stock', $bad->fresh()->citybox_status_json); // nothing invented for the failed one
    }

    public function test_pull_refreshes_one_vend_status_and_stock(): void
    {
        $vend = $this->chiller('ICB26F9FUPE7');
        $this->gw->seedDevice('ICB26F9FUPE7', online: 2)->seedStock('ICB26F9FUPE7', [['id' => 90338, 'name' => 'Suntory', 'qty' => 0]]);

        $out = app(CityboxOpenapiSync::class)->pull($vend);

        $this->assertFalse((bool) $out->is_online);
        $this->assertSame(0, $out->citybox_status_json['stock']['p90338']['quantity']);
    }

    public function test_restock_visit_open_door_records_session_and_attributes_user(): void
    {
        $vend = $this->chiller('ICB26F9FUPE7');
        $this->gw->seedDevice('ICB26F9FUPE7');
        $user = User::factory()->create();

        $session = app(RestockVisitService::class)->openDoor($vend, $user, 'ops_job_page');

        $this->assertSame('sg-fake-1', $session->msgId);
        $this->assertSame('mark1-u'.$user->id, $this->gw->opens[0]['operatorRef']);
        $this->assertSame(DeviceState::Opening, $this->gw->deviceState('ICB26F9FUPE7'));
        $last = $vend->fresh()->citybox_status_json['last_ops_open'];
        $this->assertSame('sg-fake-1', $last['msg_id']);
        $this->assertSame('ops_job_page', $last['source']);
    }

    public function test_restock_visit_open_door_refusal_bubbles_as_citybox_exception(): void
    {
        $vend = $this->chiller('OFF1');
        $this->gw->seedDevice('OFF1', online: 2);
        $this->gw->openRefusals['OFF1'] = '售货机失联=>20002';

        $this->expectException(\App\Exceptions\CityboxApiException::class);
        $this->expectExceptionMessage('售货机失联');
        app(RestockVisitService::class)->openDoor($vend, null);
    }

    public function test_real_gateway_maps_box_list_and_stock_to_value_objects(): void
    {
        $this->app->forgetInstance(ChillerGateway::class);
        config(['citybox.openapi.base_url' => 'https://gw.test']);
        Http::fake([
            'gw.test/api/Openapi/get_access_token' => Http::response(['code' => 200, 'body' => ['access_token' => 'T', 'express_in' => 3600]]),
            'gw.test/api/Openapi/box_list' => Http::response(['code' => 200, 'body' => [['equipment_id' => 'E1', 'name' => 'n', 'status' => '1', 'type' => 'visual-2', 'equipment_online_status' => 1]]]),
            'gw.test/api/Openapi/device_product' => Http::response(['code' => 200, 'body' => ['goods' => [['product_id' => '90340', 'name' => 'x', 'quantity' => '1', 'layer' => '1', 'price' => '0.10', 'active_price' => '0.10']]]]),
            'gw.test/api/Openapi/get_device_status_new' => Http::response(['code' => 200, 'body' => ['code' => 'FREE', 'msg' => '设备空闲状态']]),
        ]);

        $gw = app(ChillerGateway::class);
        $devices = $gw->listDevices();
        $stock = $gw->deviceStock('E1');

        $this->assertSame('E1', $devices->first()->equipmentId);
        $this->assertSame(90340, $stock->first()->cityboxProductId);
        $this->assertSame(10, $stock->first()->activePriceCents);
        $this->assertSame(DeviceState::Free, $gw->deviceState('E1'));
    }

    public function test_real_gateway_treats_device_with_no_products_configured_as_empty_not_error(): void
    {
        $this->app->forgetInstance(ChillerGateway::class);
        config(['citybox.openapi.base_url' => 'https://gw.test']);
        Http::fake([
            'gw.test/api/Openapi/get_access_token' => Http::response(['code' => 200, 'body' => ['access_token' => 'T', 'express_in' => 3600]]),
            'gw.test/api/Openapi/device_product' => Http::response(['code' => 400, 'body' => ['message' => '此设备没有商品']]),
            'gw.test/api/Openapi/shipping_product' => Http::response(['code' => 400, 'body' => ['message' => '此设备没有商品']]),
            'gw.test/api/Openapi/product_list' => Http::response(['code' => 400, 'body' => ['message' => '参数错误']]),
        ]);

        $gw = app(ChillerGateway::class);
        $this->assertCount(0, $gw->deviceStock('E1'));
        $this->assertCount(0, $gw->restockConfig('E1'));
        $this->expectException(\App\Exceptions\CityboxApiException::class); // other 400s still throw
        $gw->catalog();
    }
}
