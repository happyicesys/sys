<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxDoorOpenLog;
use App\Models\Product;
use App\Models\ProductMappingItem;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\DepartedSkuSync;
use App\Services\ProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Citybox\ChillerMapping;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * Switching a chiller's planogram must tell CityBox that the dropped SKUs are
 * gone (Brian, 2026-09-23). Before this, only an ops-job Stock In ever pushed a
 * zero, so a mapping changed from the back office left their count wrong until
 * a driver happened to visit.
 */
class CityboxDepartedSkuSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake([\App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        $this->vend = Vend::create([
            'code' => 6002, 'code_prefix' => 'C', 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => 1,
        ]);
        $this->gw->seedDevice('E1');
        $this->gw->seedPar('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1, 'price' => '0.11'],
        ]);
    }

    /** A door-open session on the machine, as any source would leave behind. */
    private function doorSession(string $msgId = 'sg-fake-1', ?string $at = null): void
    {
        CityboxDoorOpenLog::create([
            'vend_id' => $this->vend->id, 'citybox_equipment_id' => 'E1',
            'result' => CityboxDoorOpenLog::RESULT_OPENED, 'msg_id' => $msgId,
            'requested_at' => $at ?? now()->subHours(3), 'source' => 'vend_settings',
        ]);
    }

    /** Bind layout A (Suntory on 101, Lemon on 102) and let a poll fill the rows. */
    private function bindLayoutA(): void
    {
        ChillerMapping::bind($this->vend, [101 => [90338, 5], 102 => [90339, 5]]);
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 3, 'layer' => 1, 'price' => '0.11'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->vend->refresh();
    }

    /** Switch to layout B: Suntory stays on 101, Lemon is dropped. */
    private function switchToLayoutB(): void
    {
        ProductMappingItem::where('product_mapping_id', $this->vend->product_mapping_id)
            ->where('channel_code', '102')->delete();
        app(ProductMappingService::class)->syncChannels($this->vend->product_mapping_id);
        $this->vend->refresh();
    }

    private function rowFor(int $cityboxId): ?VendChannel
    {
        $productId = Product::withoutGlobalScopes()->where('code', (string) $cityboxId)->value('id');

        return VendChannel::where('vend_id', $this->vend->id)->where('product_id', $productId)->first();
    }

    public function test_a_mapping_switch_pushes_zero_for_the_dropped_sku_and_keeps_the_one_carried_over(): void
    {
        $this->doorSession();
        $this->bindLayoutA();
        $this->assertSame(3, (int) $this->rowFor(90339)->qty, 'BEFORE: CityBox says 3 Lemon are in the cabinet');
        $this->assertSame(4, (int) $this->rowFor(90338)->qty);

        $this->switchToLayoutB();

        // AFTER, on their side: Lemon zeroed, Suntory untouched.
        $submitted = collect($this->gw->submits)->last();
        $this->assertNotNull($submitted, 'the switch must reach CityBox');
        $this->assertSame([['product_id' => 90339, 'reality_stock' => 0]], $submitted['rows']);
        $this->assertSame('sg-fake-1', $submitted['msgId'], 'it reuses the last door-open session');
        $this->assertSame('0', (string) collect($this->gw->stock['E1'])->firstWhere('product_id', 90339)['quantity']);
        $this->assertSame('4', (string) collect($this->gw->stock['E1'])->firstWhere('product_id', 90338)['quantity']);

        // AFTER, on ours: the carried-over SKU keeps its qty, the dropped one settles at 0.
        $this->assertSame(4, (int) $this->rowFor(90338)->qty, 'a SKU carried over retains its qty');
        $this->assertTrue((bool) $this->rowFor(90338)->is_active);
        $this->assertSame(0, (int) $this->rowFor(90339)->qty);
        $this->assertFalse((bool) $this->rowFor(90339)->is_active);
    }

    public function test_it_never_zeroes_stock_that_is_merely_off_planogram(): void
    {
        // C6005, 2026-09-12: five real units of a SKU our mapping never carried.
        // Those are physically inside the cabinet and must not be wiped.
        $this->doorSession();
        $this->bindLayoutA();
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 3, 'layer' => 1, 'price' => '0.11'],
            ['id' => 90332, 'name' => 'Cup Noodle', 'qty' => 5, 'layer' => 1, 'price' => '0.23'],
        ]);

        $this->switchToLayoutB();

        $pushed = collect($this->gw->submits)->last()['rows'];
        $this->assertSame([90339], array_column($pushed, 'product_id'), 'only the SKU that LEFT our planogram');
        $this->assertSame('5', (string) collect($this->gw->stock['E1'])->firstWhere('product_id', 90332)['quantity']);
    }

    public function test_with_no_door_open_session_it_defers_and_keeps_the_qty_for_a_retry(): void
    {
        $this->bindLayoutA(); // no session seeded
        $this->switchToLayoutB();

        $this->assertSame([], $this->gw->submits, 'nothing can be written without a session');
        $this->assertSame(3, (int) $this->rowFor(90339)->qty, 'the qty is kept so the next attempt still knows');
        $this->assertSame('deferred', app(DepartedSkuSync::class)->sync($this->vend->fresh())['status']);
    }

    public function test_a_refused_push_is_deferred_and_settled_by_the_next_stock_submit(): void
    {
        $this->doorSession();
        $this->bindLayoutA();
        $this->gw->failSubmit = 'msg_id expired';

        $this->switchToLayoutB();
        $this->assertSame(3, (int) $this->rowFor(90339)->qty, 'a refusal must not clear our record of it');

        // The machine is visited: a live session, and the catch-up settles it.
        $this->gw->failSubmit = null;
        $report = app(DepartedSkuSync::class)->sync($this->vend->fresh(), force: true);

        $this->assertSame('pushed', $report['status']);
        $this->assertSame(3, $report['skus'][0]['qty_before']);
        $this->assertSame(0, $report['skus'][0]['qty_after'], 'validated by reading CityBox back, not assumed');
        $this->assertSame(0, (int) $this->rowFor(90339)->qty);
    }

    public function test_it_does_nothing_when_citybox_already_shows_the_sku_as_empty(): void
    {
        $this->doorSession();
        $this->bindLayoutA();
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1, 'price' => '0.12'],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 0, 'layer' => 1, 'price' => '0.11'],
        ]);

        $this->switchToLayoutB();

        $this->assertSame([], $this->gw->submits, 'no write when their count is already 0');
        $this->assertSame(0, (int) $this->rowFor(90339)->qty, 'our stale row is settled locally');
    }

    public function test_a_vending_machine_is_never_touched(): void
    {
        $vending = Vend::create(['code' => 2031, 'is_active' => 1, 'operator_id' => 1]);

        $this->assertSame('skipped', app(DepartedSkuSync::class)->sync($vending)['status']);
        $this->assertSame([], $this->gw->submits);
    }
}
