<?php

namespace Tests\Feature;

use App\Jobs\PublishMqtt;
use App\Models\ApkSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\VendPricingSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Is Using Server Price?" (Brian, 2026-08-21).
 *
 * The Site (customers.selling_price_type, RP1–RP5) is the ONLY place a
 * reference price tier is chosen. A machine carries just a switch,
 * vends.is_using_server_price:
 *   No  → the APK sells at the VMC board price;
 *   Yes → the APK sells at mark1 selling prices in the Site's tier.
 *
 * Wire effect pinned here: /parameters emits selectedPricingSource from the
 * MACHINE (not from the shared apk_settings row), /thumbnails carries
 * server_price only while the machine follows its Site, and every write path
 * (Machine Settings save, APK Settings toggle, Site RP change) nudges the
 * terminal to re-read settings + menu.
 */
class VendServerPriceSourceTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create(['code' => 'SP-COLA', 'name' => 'Cola']);
        SellingPrice::create(['product_id' => $this->product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 2.50]);
        SellingPrice::create(['product_id' => $this->product->id, 'type' => SellingPrice::TYPE_3, 'amount' => 3.50]);
    }

    /** A vending machine on a Site at RP3, mapping + channel 11 = the product. */
    private function makeVend(int $code, ?int $siteRp = SellingPrice::TYPE_3, bool $usesServerPrice = false): Vend
    {
        $customer = Customer::create([
            'name' => "Site {$code}", 'code' => $code, 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => $siteRp,
        ]);
        $mapping = ProductMapping::forceCreate(['name' => "Mapping {$code}"]);
        ProductMappingItem::create([
            'product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $this->product->id,
        ]);
        $vend = Vend::forceCreate([
            'code' => $code, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id,
            'is_using_server_price' => $usesServerPrice, 'operator_id' => 1,
        ]);
        VendChannel::forceCreate([
            'vend_id' => $vend->id, 'code' => 11, 'product_id' => $this->product->id,
            'amount' => 200, 'qty' => 5, 'capacity' => 10, 'is_active' => 1,
        ]);

        return $vend;
    }

    private function bindApkSetting(Vend $vend, string $storedPricingSource): void
    {
        $setting = ApkSetting::create([
            'name' => 'Shared profile',
            'settings_parameter_json' => ['selectedPricingSource' => $storedPricingSource],
        ]);
        DB::table('apk_setting_vend')->insert([
            'apk_setting_id' => $setting->id, 'vend_id' => $vend->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_parameters_pricing_source_comes_from_the_machine_not_the_shared_setting(): void
    {
        $vend = $this->makeVend(9911, usesServerPrice: false);
        $this->bindApkSetting($vend, 'server'); // the group says server …

        // … but this machine uses the board price.
        $this->getJson('/api/vends/9911/parameters/301')
            ->assertOk()
            ->assertJsonPath('selectedPricingSource', 'machine');

        $vend->update(['is_using_server_price' => true]);

        $this->getJson('/api/vends/9911/parameters/301')
            ->assertOk()
            ->assertJsonPath('selectedPricingSource', 'server');
    }

    public function test_thumbnails_carry_the_sites_rp_price_only_while_the_machine_follows_it(): void
    {
        $vend = $this->makeVend(9912, siteRp: SellingPrice::TYPE_3, usesServerPrice: true);

        $this->getJson('/api/vends/9912/thumbnails')
            ->assertOk()
            ->assertJsonPath('0.channel_code', 11)
            ->assertJsonPath('0.server_price', 350);

        // The Site moves to RP2: no machine write needed, the tier is the Site's.
        $vend->customer->update(['selling_price_type' => SellingPrice::TYPE_2]);
        $this->getJson('/api/vends/9912/thumbnails')->assertJsonPath('0.server_price', 250);

        // Machine switched back to board price: no server price on the wire.
        $vend->update(['is_using_server_price' => false]);
        $this->getJson('/api/vends/9912/thumbnails')->assertJsonPath('0.server_price', null);

        // Follows the Site, but the Site has no tier: nothing to follow.
        $vend->update(['is_using_server_price' => true]);
        $vend->customer->update(['selling_price_type' => null]);
        $this->getJson('/api/vends/9912/thumbnails')->assertJsonPath('0.server_price', null);
    }

    public function test_channel_server_amount_and_resource_tier_follow_the_site(): void
    {
        $vend = $this->makeVend(9913, siteRp: SellingPrice::TYPE_3, usesServerPrice: true);

        $this->assertSame(350, (int) $vend->vendChannels()->first()->server_amount);
        $this->assertSame(3, $vend->serverPriceType());

        $vend->update(['is_using_server_price' => false]);
        $this->assertNull($vend->vendChannels()->first()->server_amount);
        $this->assertNull($vend->fresh()->serverPriceType());
    }

    public function test_service_only_nudges_the_terminal_on_a_real_change(): void
    {
        Queue::fake();
        $vend = $this->makeVend(9914, usesServerPrice: false);

        $service = app(VendPricingSourceService::class);

        $this->assertTrue($service->setUsingServerPrice($vend, true));
        $this->assertTrue($vend->fresh()->usesServerPrice());
        // settings re-read (TYPESYNCSETTINGSPARAM) + menu re-fetch (TYPESYNCAPICHANNELSLOTLIST)
        Queue::assertPushed(PublishMqtt::class, 2);

        $this->assertFalse($service->setUsingServerPrice($vend->fresh(), true));
        Queue::assertPushed(PublishMqtt::class, 2); // unchanged → silent
    }

    public function test_apk_settings_page_toggle_writes_the_machine_and_nudges_it(): void
    {
        Queue::fake();
        $vend = $this->makeVend(9915, usesServerPrice: false);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/apk-settings/vends/'.$vend->id.'/pricing-source', ['is_using_server_price' => true])
            ->assertRedirect();

        $this->assertTrue($vend->fresh()->usesServerPrice());
        Queue::assertPushed(PublishMqtt::class, 2);

        $this->actingAs($user)
            ->post('/apk-settings/vends/'.$vend->id.'/pricing-source', ['is_using_server_price' => 'nope'])
            ->assertSessionHasErrors('is_using_server_price');
    }

    public function test_machine_settings_save_persists_the_switch_and_nudges_the_terminal(): void
    {
        Queue::fake();
        $vend = $this->makeVend(9916, usesServerPrice: false);
        $user = User::factory()->create();

        $payload = [
            'name' => 'Machine 9916',
            'lcd_monitor_id' => 1,
            'menu_frame_id' => 1,
            'operator_id' => 1,
            'product_mapping_id' => $vend->product_mapping_id,
            'vend_model_id' => 1,
            'vend_prefix_id' => 1,
            'is_using_server_price' => true,
        ];

        $this->actingAs($user)->post('/vends/'.$vend->id.'/update', $payload)->assertSessionHasNoErrors();
        $this->assertTrue($vend->fresh()->usesServerPrice());
        Queue::assertPushed(PublishMqtt::class, 2);

        // Saving again without flipping the switch must not spam the terminal.
        $this->actingAs($user)->post('/vends/'.$vend->id.'/update', $payload)->assertSessionHasNoErrors();
        Queue::assertPushed(PublishMqtt::class, 2);

        $this->actingAs($user)->post('/vends/'.$vend->id.'/update', ['is_using_server_price' => false] + $payload)->assertSessionHasNoErrors();
        $this->assertFalse($vend->fresh()->usesServerPrice());
        Queue::assertPushed(PublishMqtt::class, 4);
    }

    public function test_edit_pages_expose_the_switch_and_the_sites_tier_not_a_machine_tier(): void
    {
        $vend = $this->makeVend(9919, siteRp: SellingPrice::TYPE_3, usesServerPrice: true);
        $this->bindApkSetting($vend, 'server');
        $settingId = DB::table('apk_setting_vend')->where('vend_id', $vend->id)->value('apk_setting_id');

        foreach (['read machine-settings', 'update machine-settings'] as $perm) {
            \Spatie\Permission\Models\Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['read machine-settings', 'update machine-settings']);

        // Machine Settings → Edit: the flag + the Site's RP; no per-machine RP field any more.
        $this->actingAs($user)->get('/settings/vend/'.$vend->id.'/update')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setting/Edit')
                ->where('vend.is_using_server_price', true)
                ->where('vend.selling_price_type', 3)
                ->missing('vend.server_price_type')
            );

        // APK Settings → Edit: per bound machine, the flag and its effective tier.
        $this->actingAs($user)->get('/apk-settings/'.$settingId.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ApkSetting/Edit')
                ->where('apkSetting.data.vends.0.is_using_server_price', true)
                ->where('apkSetting.data.vends.0.server_price_type', 3)
                ->where('apkSetting.data.vends.0.customer.selling_price_type', 3)
            );
    }

    public function test_binding_or_unbinding_a_site_nudges_a_machine_that_follows_it(): void
    {
        Queue::fake();
        $vend = $this->makeVend(9920, usesServerPrice: true);
        $otherSite = Customer::create([
            'name' => 'Other Site', 'code' => 9921, 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);

        // Unbind (VendController::unbindCustomer does exactly this save) → the
        // effective tier is gone, so the terminal must drop server prices.
        $vend->customer_id = null;
        $vend->save();
        Queue::assertPushed(PublishMqtt::class, 2);

        // Rebind to a Site on another tier → re-fetch at the new tier.
        $vend->update(['customer_id' => $otherSite->id]);
        Queue::assertPushed(PublishMqtt::class, 4);
        $this->assertSame(2, $vend->fresh()->serverPriceType());

        // Unrelated saves stay silent on the wire.
        $vend->update(['name' => 'renamed']);
        Queue::assertPushed(PublishMqtt::class, 4);
    }

    public function test_site_rp_change_nudges_every_machine_that_follows_the_site(): void
    {
        Queue::fake();
        $follower = $this->makeVend(9917, usesServerPrice: true);
        // A second follower on the SAME Site (a Site can carry several machines).
        $sibling = Vend::forceCreate([
            'code' => 9922, 'customer_id' => $follower->customer_id, 'operator_id' => 1,
            'product_mapping_id' => $follower->product_mapping_id, 'is_using_server_price' => true,
        ]);
        $boardPriced = $this->makeVend(9918, usesServerPrice: false);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/customers/'.$follower->customer_id.'/update', [
            'customer' => ['selling_price_type' => SellingPrice::TYPE_2],
        ]);
        $this->assertSame(2, (int) $follower->customer->fresh()->selling_price_type);
        // both followers: settings + menu each
        Queue::assertPushed(PublishMqtt::class, 4);
        $this->assertSame(2, $sibling->fresh()->serverPriceType());

        // A board-priced machine's Site changing tier is none of the terminal's business.
        $this->actingAs($user)->post('/customers/'.$boardPriced->customer_id.'/update', [
            'customer' => ['selling_price_type' => SellingPrice::TYPE_2],
        ]);
        Queue::assertPushed(PublishMqtt::class, 4);
    }
}
