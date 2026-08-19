<?php

namespace Tests\Feature;

use App\Jobs\PollCityboxOpenapi;
use App\Models\User;
use App\Models\Vend;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\OpenapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Fixtures mirror REAL responses captured from api.cityboxai.com on
 * 2026-08-17 (test unit ICB26F9FUPE7), so field names/types are the live
 * ones, not the PDF's.
 */
class CityboxOpenapiSyncTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'openapi.citybox.test';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'citybox.openapi.enabled' => true,
            'citybox.openapi.base_url' => 'https://'.self::HOST,
            'citybox.openapi.app_id' => 'APP',
            'citybox.openapi.secret' => 'SECRET',
        ]);
    }

    private function chiller(string $eq, array $o = []): Vend
    {
        return Vend::create(array_merge([
            'code' => 9100, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => $eq, 'is_active' => 1,
        ], $o));
    }

    private function fakeApi(array $boxes, array $goodsByDevice = [], array $extra = []): void
    {
        $routes = [
            self::HOST.'/api/Openapi/get_access_token' => Http::response(['code' => 200, 'body' => ['access_token' => 'T', 'express_in' => 3600]]),
            self::HOST.'/api/Openapi/box_list' => Http::response(['code' => 200, 'body' => $boxes]),
            // A bare closure is a fake HANDLER (per-request); wrapping it in
            // Http::response() would make the closure the response BODY.
            self::HOST.'/api/Openapi/device_product' => function ($req) use ($goodsByDevice) {
                $d = $req['device_id'];
                if (isset($goodsByDevice[$d]) && $goodsByDevice[$d] === 'ERROR') {
                    return Http::response(['code' => 400, 'body' => ['success' => false, 'message' => '设备号不能为空']]);
                }

                return Http::response(['code' => 200, 'body' => ['goods' => $goodsByDevice[$d] ?? []]]);
            },
        ];
        Http::fake(array_merge($routes, $extra));
    }

    private function box(string $eq, int $online = 1, int $status = 1): array
    {
        return ['equipment_id' => $eq, 'name' => '#1', 'status' => $status, 'type' => 'visual-2',
            'equipment_online_status' => $online, 'equipment_online_status_str' => $online === 1 ? '在线' : '离线',
            'equipment_status_str' => '启运'];
    }

    private function goods(): array
    {
        return [
            ['thumbnailPic' => 'https://cdn/x.png', 'quantity' => '0', 'name' => 'Suntory Oolong', 'product_id' => '90338',
                'price' => '0.12', 'active_price' => '0.12', 'volume' => '500ml', 'unit' => 'Bottle', 'layer' => 1],
            ['thumbnailPic' => 'https://cdn/y.png', 'quantity' => '1', 'name' => 'KSF Peach Oolong', 'product_id' => '90340',
                'price' => '0.10', 'active_price' => '0.10', 'volume' => '500ml', 'unit' => 'Bottle', 'layer' => 1],
        ];
    }

    // ── sync ───────────────────────────────────────────────────────────────

    public function test_sync_maps_online_status_type_and_stock_with_layer(): void
    {
        $vend = $this->chiller('ICB26F9FUPE7');
        $this->fakeApi([$this->box('ICB26F9FUPE7')], ['ICB26F9FUPE7' => $this->goods()]);

        $summary = app(CityboxOpenapiSync::class)->syncAll();
        $vend->refresh();

        $this->assertSame(1, $summary['matched']);
        $this->assertTrue((bool) $vend->is_online);
        $this->assertNotNull($vend->citybox_synced_at);
        $s = $vend->citybox_status_json;
        $this->assertSame('openapi', $s['source']);
        $this->assertSame('visual-2', $s['device_type']);
        $this->assertSame(1, $s['stock']['p90340']['quantity']);
        $this->assertSame(1, $s['stock']['p90340']['layer']);
        // Integer cents (estate invariant) — the VO converts their "0.10" string at the boundary.
        $this->assertSame(10, $s['stock']['p90340']['active_price']);
    }

    public function test_sync_only_touches_smart_chiller_vends(): void
    {
        // A vending machine with the same serial must be ignored entirely.
        Vend::create(['code' => 9101, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'citybox_equipment_id' => 'ICB26F9FUPE7', 'is_active' => 1]);
        $this->fakeApi([$this->box('ICB26F9FUPE7')]);

        $summary = app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(0, $summary['matched']);
        Http::assertNothingSent(); // hasLinked short-circuits before any HTTP
    }

    public function test_stock_call_failure_keeps_previous_snapshot_and_reports(): void
    {
        $vend = $this->chiller('ICB26F9FUPE7', ['citybox_status_json' => ['stock' => ['p90340' => ['quantity' => 7]]]]);
        $this->fakeApi([$this->box('ICB26F9FUPE7')], ['ICB26F9FUPE7' => 'ERROR']);

        $summary = app(CityboxOpenapiSync::class)->syncAll();
        $vend->refresh();

        $this->assertArrayHasKey('ICB26F9FUPE7', $summary['stock_errors']);
        $this->assertSame(7, $vend->citybox_status_json['stock']['p90340']['quantity']); // kept
        $this->assertTrue((bool) $vend->is_online); // status still applied
    }

    public function test_offline_and_removed_status_do_not_deactivate(): void
    {
        $vend = $this->chiller('ICB26F9FUPE7');
        $this->fakeApi([$this->box('ICB26F9FUPE7', online: 2, status: 99)]);

        app(CityboxOpenapiSync::class)->syncAll();
        $vend->refresh();

        $this->assertFalse((bool) $vend->is_online);
        $this->assertTrue((bool) $vend->is_active); // never auto-deactivated
    }

    public function test_unknown_and_missing_reported_never_created(): void
    {
        $this->chiller('OURS-ONLY');
        $this->fakeApi([$this->box('THEIRS-ONLY')]);

        $summary = app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(['THEIRS-ONLY'], $summary['unknown']);
        $this->assertSame(['OURS-ONLY'], $summary['missing']);
        $this->assertSame(1, Vend::count());
    }

    // ── scheduler + job ────────────────────────────────────────────────────

    public function test_poll_command_noop_without_linked_chiller_and_dispatches_with_one(): void
    {
        Queue::fake();
        $this->artisan('citybox:openapi-poll')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->chiller('ICB26F9FUPE7');
        $this->artisan('citybox:openapi-poll')->assertSuccessful();
        Queue::assertPushed(PollCityboxOpenapi::class, 1);
    }

    // ── write endpoints (client) ───────────────────────────────────────────

    public function test_open_door_returns_msg_id_and_rejects_non_succ(): void
    {
        $this->fakeApi([], [], [
            self::HOST.'/api/Openapi/zyy_ls_open_door' => Http::sequence()
                ->push(['code' => 200, 'body' => ['status' => 'succ', 'message' => '开门成功', 'msg_id' => 'sg-abc', 'open_log_id' => '78', 'device_id' => 'ICB26F9FUPE7']])
                ->push(['code' => 200, 'body' => ['status' => 'fail', 'message' => '设备正在使用中', 'code' => 'BUSY']]),
        ]);
        $c = app(OpenapiClient::class);

        $ok = $c->zyyLsOpenDoor('ICB26F9FUPE7', 'mark1-u1');
        $this->assertSame('sg-abc', $ok['msg_id']);

        $this->expectException(\App\Exceptions\CityboxApiException::class);
        $c->zyyLsOpenDoor('ICB26F9FUPE7', 'mark1-u1');
    }

    public function test_stock_submit_sends_json_string_data_and_accepts_int_status(): void
    {
        $this->fakeApi([], [], [
            self::HOST.'/api/Openapi/device_stock_submit' => Http::response(['code' => 200, 'body' => ['status' => 1, 'message' => '提交成功']]),
        ]);

        $r = app(OpenapiClient::class)->deviceStockSubmit('ICB26F9FUPE7', 'sg-abc', [
            ['product_id' => 90340, 'reality_stock' => 5],
        ]);

        $this->assertSame('提交成功', $r['message']);
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'device_stock_submit')) {
                return false;
            }
            $data = json_decode($req['data'], true); // must be a JSON STRING field

            return $req['msg_id'] === 'sg-abc' && $data[0]['reality_stock'] === 5;
        });
    }

    // ── controller gate ────────────────────────────────────────────────────

    public function test_open_door_route_is_403_for_non_chiller_and_works_for_chiller(): void
    {
        $user = User::factory()->create();
        $vm = Vend::create(['code' => 9102, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1]);
        $chiller = $this->chiller('ICB26F9FUPE7');
        $this->fakeApi([], [], [
            self::HOST.'/api/Openapi/zyy_ls_open_door' => Http::response(['code' => 200, 'body' => ['status' => 'succ', 'msg_id' => 'sg-abc', 'open_log_id' => '78']]),
        ]);

        $this->actingAs($user)->post("/vends/{$vm->id}/citybox-open-door")->assertForbidden();

        $this->actingAs($user)->from('/settings/vend/'.$chiller->id.'/update')
            ->post("/vends/{$chiller->id}/citybox-open-door")
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('sg-abc', $chiller->fresh()->citybox_status_json['last_ops_open']['msg_id']);
    }
}
