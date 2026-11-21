<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendModel;
use App\Services\OtaChannelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The smart-freezer OTA channel is every smart freezer, by machine type (2026-11-21). It used to match the
 * vend model "Smart Vend", which no freezer has (they are "SCF 338"): the APK Releases page's "Push OTA fleet
 * check" reached no freezer, its version spread was empty, and the freezers were counted under vending.
 */
class OtaFreezerFleetTest extends TestCase
{
    use RefreshDatabase;

    private Vend $freezer;

    private Vend $vending;

    protected function setUp(): void
    {
        parent::setUp();
        $scf = VendModel::create(['name' => 'SCF 338']);
        $this->freezer = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => $scf->id]);
        $this->vending = Vend::create(['code' => 2031, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1]);
    }

    private function fleet(string $channel): array
    {
        return app(OtaChannelResolver::class)->scopeFleet(Vend::query(), $channel)->pluck('code')->map(fn ($c) => (int) $c)->sort()->values()->all();
    }

    public function test_the_freezer_channel_is_every_smart_freezer_and_vending_leaves_them_out(): void
    {
        $this->assertSame([50001], $this->fleet('smart_freezer'));
        $this->assertSame([2031], $this->fleet('vending'));
    }

    public function test_a_freezer_resolves_to_its_own_channel_with_or_without_its_package(): void
    {
        $channels = app(OtaChannelResolver::class);
        $this->assertSame('smart_freezer', $channels->forVend($this->freezer));
        $this->assertSame('smart_freezer', $channels->resolve(null, $this->freezer));
        $this->assertSame('smart_freezer', $channels->resolve('sg.mark1.freezer', $this->freezer));
        $this->assertSame('vending', $channels->resolve(null, $this->vending));
        $this->assertSame('vending', $channels->catchAllChannel());
    }

    public function test_push_ota_check_reaches_the_freezers(): void
    {
        Bus::fake([PublishMqtt::class]);
        Permission::findOrCreate('update apk-releases', 'web');
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo('update apk-releases');

        $this->actingAs($user)->post('/apk-releases/push-ota-check', ['channel' => 'smart_freezer'])->assertRedirect();

        Bus::assertDispatched(PublishMqtt::class, fn (PublishMqtt $job) => str_contains(json_encode((array) $job), 'CM50001'));
        Bus::assertNotDispatched(PublishMqtt::class, fn (PublishMqtt $job) => str_contains(json_encode((array) $job), 'CM2031'));
    }
}
