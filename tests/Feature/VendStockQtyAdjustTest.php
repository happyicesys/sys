<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\Vend\SaveVendChannelsJson;
use App\Models\CityboxDoorOpenLog;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelQtyAdjustment;
use App\Services\Citybox\CityboxOpenapiSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\ChillerMapping;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * Setting/Edit "Stock Qty" (2026-09-30): ops overwrite a chiller's or freezer's on-hand qty by
 * hand, and every overwrite records who, when and before → after. A freezer's qty is ours, so it
 * is set directly; a chiller's is CityBox's, so it goes to CityBox and is read back; a vending
 * machine's is its VMC's, so it is refused.
 */
class VendStockQtyAdjustTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake([SaveVendChannelsJson::class, \App\Jobs\Vend\SyncVendChannelErrorLog::class]);
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
    }

    private function user(bool $canUpdate = true): User
    {
        $perms = $canUpdate ? ['read machine-settings', 'update machine-settings'] : ['read machine-settings'];
        foreach (['read machine-settings', 'update machine-settings'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create(['name' => 'brian']);
        $user->givePermissionTo($perms);

        return $user;
    }

    private function freezer(int $qty = 3): VendChannel
    {
        $vend = Vend::create(['code' => 50091, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $product = Product::create(['code' => 'CC-01', 'name' => 'Basque Cheesecake']);

        return VendChannel::create(['vend_id' => $vend->id, 'code' => 41, 'product_id' => $product->id, 'qty' => $qty, 'capacity' => 6, 'amount' => 430, 'is_active' => 1]);
    }

    /** A chiller with Suntory (4) and Lemon (3) on its mapping, plus a real off-planogram leftover (5). */
    private function chiller(bool $doorSession = true): Vend
    {
        $vend = Vend::create([
            'code' => 6002, 'code_prefix' => 'C', 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => 1,
        ]);
        $this->gw->seedDevice('E1');
        $this->gw->seedPar('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 5, 'layer' => 1],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 5, 'layer' => 1],
            ['id' => 90332, 'name' => 'Cup Noodle', 'qty' => 5, 'layer' => 1],
        ]);
        ChillerMapping::bind($vend, [101 => [90338, 5], 102 => [90339, 5]]);
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 3, 'layer' => 1],
            ['id' => 90332, 'name' => 'Cup Noodle', 'qty' => 5, 'layer' => 1],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();
        if ($doorSession) {
            CityboxDoorOpenLog::create([
                'vend_id' => $vend->id, 'citybox_equipment_id' => 'E1', 'result' => CityboxDoorOpenLog::RESULT_OPENED,
                'msg_id' => 'sg-old-session', 'requested_at' => now()->subHours(5), 'source' => 'vend_settings',
            ]);
        }

        return $vend->fresh();
    }

    private function row(Vend $vend, int $cityboxId): VendChannel
    {
        $productId = Product::withoutGlobalScopes()->where('code', (string) $cityboxId)->value('id');

        return VendChannel::where('vend_id', $vend->id)->where('product_id', $productId)->firstOrFail();
    }

    private function liveQty(int $cityboxId): int
    {
        return (int) collect($this->gw->stock['E1'])->firstWhere('product_id', (string) $cityboxId)['quantity'];
    }

    public function test_a_freezer_qty_is_overwritten_and_the_change_is_logged_with_who_and_before_after(): void
    {
        $channel = $this->freezer(3);
        $user = $this->user();

        $this->actingAs($user)
            ->postJson("/vends/{$channel->vend_id}/stock-qty/{$channel->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertOk()
            ->assertJsonPath('adjustment.who', 'brian')
            ->assertJsonPath('adjustment.from', 3)
            ->assertJsonPath('adjustment.to', 7);

        $this->assertSame(7, (int) $channel->fresh()->qty);
        $log = VendChannelQtyAdjustment::sole();
        $this->assertSame([$user->id, 3, 7, '41'], [$log->user_id, $log->qty_before, $log->qty_after, $log->channel_label]);
        Queue::assertPushed(SaveVendChannelsJson::class);

        $this->actingAs($user)->getJson("/vends/{$channel->vend_id}/stock-qty")
            ->assertOk()
            ->assertJsonPath('refusal', null)
            ->assertJsonPath('channels.0.code', '41') // the planogram cell it is drawn in
            ->assertJsonPath('channels.0.qty', 7)
            ->assertJsonPath('channels.0.history.0.who', 'brian')
            ->assertJsonPath('channels.0.history.0.from', 3)
            ->assertJsonPath('channels.0.history.0.to', 7);
    }

    public function test_a_freezer_overwrite_is_refused_when_a_sale_moved_the_qty_since_the_page_loaded(): void
    {
        $channel = $this->freezer(3);
        $channel->update(['qty' => 2]); // a sale landed while the page was open

        $this->actingAs($this->user())
            ->postJson("/vends/{$channel->vend_id}/stock-qty/{$channel->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'now shows 2, not 3'));

        $this->assertSame(2, (int) $channel->fresh()->qty);
        $this->assertSame(0, VendChannelQtyAdjustment::count());
    }

    public function test_it_needs_update_machine_settings(): void
    {
        $channel = $this->freezer(3);

        $this->actingAs($this->user(canUpdate: false))
            ->postJson("/vends/{$channel->vend_id}/stock-qty/{$channel->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertForbidden();

        $this->assertSame(3, (int) $channel->fresh()->qty);
    }

    public function test_a_vending_machine_is_refused_because_its_vmc_owns_the_qty(): void
    {
        $vend = Vend::create(['code' => 1234, 'is_active' => 1, 'operator_id' => 1]);
        $product = Product::create(['code' => 'P1', 'name' => 'Coke']);
        $channel = VendChannel::create(['vend_id' => $vend->id, 'code' => 11, 'product_id' => $product->id, 'qty' => 3, 'capacity' => 8, 'is_active' => 1]);

        $this->actingAs($this->user())
            ->postJson("/vends/{$vend->id}/stock-qty/{$channel->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'VMC'));

        $this->assertSame(3, (int) $channel->fresh()->qty);
    }

    public function test_a_chiller_overwrite_goes_to_citybox_as_the_full_list_and_is_read_back(): void
    {
        $vend = $this->chiller();
        $lemon = $this->row($vend, 90339);
        $this->assertSame(3, (int) $lemon->qty, 'BEFORE: our row mirrors CityBox');

        $this->actingAs($this->user())
            ->postJson("/vends/{$vend->id}/stock-qty/{$lemon->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertOk()
            ->assertJsonPath('adjustment.to', 7)
            ->assertJsonPath('adjustment.supplier_to', 7);

        $submit = collect($this->gw->submits)->sole();
        $this->assertSame('sg-old-session', $submit['msgId'], 'the machine\'s last door-open session is reused');
        $this->assertEqualsCanonicalizing([
            ['product_id' => 90338, 'reality_stock' => 4],
            ['product_id' => 90339, 'reality_stock' => 7],
            ['product_id' => 90332, 'reality_stock' => 5],
        ], $submit['rows'], 'every other SKU is sent at its live qty, the off-planogram leftover included');

        // AFTER, on their side and ours.
        $this->assertSame(7, $this->liveQty(90339));
        $this->assertSame(4, $this->liveQty(90338));
        $this->assertSame(5, $this->liveQty(90332));
        $this->assertSame(7, (int) $lemon->fresh()->qty);
        $log = VendChannelQtyAdjustment::sole();
        $this->assertSame([3, 7, 7, 'sg-old-session'], [$log->qty_before, $log->qty_after, $log->supplier_qty_after, $log->supplier_msg_id]);
    }

    public function test_a_chiller_with_no_door_open_session_is_refused_and_nothing_is_sent(): void
    {
        $vend = $this->chiller(doorSession: false);
        $lemon = $this->row($vend, 90339);

        $this->actingAs($this->user())
            ->postJson("/vends/{$vend->id}/stock-qty/{$lemon->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Open Door'));

        $this->assertSame([], $this->gw->submits);
        $this->assertSame(0, VendChannelQtyAdjustment::count());
    }

    public function test_a_chiller_overwrite_is_refused_when_citybox_moved_since_the_page_loaded(): void
    {
        $vend = $this->chiller();
        $lemon = $this->row($vend, 90339);
        // A customer took one; the minute poll has not mirrored it yet.
        $this->gw->seedStock('E1', [
            ['id' => 90338, 'name' => 'Suntory', 'qty' => 4, 'layer' => 1],
            ['id' => 90339, 'name' => 'Lemon', 'qty' => 2, 'layer' => 1],
        ]);

        $this->actingAs($this->user())
            ->postJson("/vends/{$vend->id}/stock-qty/{$lemon->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'CityBox now shows 2, not 3'));

        $this->assertSame([], $this->gw->submits);
    }

    public function test_a_chiller_refusal_from_citybox_writes_nothing(): void
    {
        $vend = $this->chiller();
        $lemon = $this->row($vend, 90339);
        $this->gw->failSubmit = 'msg_id expired';

        $this->actingAs($this->user())
            ->postJson("/vends/{$vend->id}/stock-qty/{$lemon->id}", ['qty' => 7, 'expected_qty' => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'msg_id expired'));

        $this->assertSame(3, (int) $lemon->fresh()->qty);
        $this->assertSame(0, VendChannelQtyAdjustment::count());
    }
}
