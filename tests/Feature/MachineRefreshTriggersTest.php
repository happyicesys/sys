<?php

namespace Tests\Feature;

use App\Jobs\Vend\PushApkSettingSync;
use App\Jobs\Vend\PushMappingMenuSync;
use App\Jobs\Vend\PushVendSettingsSync;
use App\Models\ApkSetting;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Category;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Tag;
use App\Models\Vend;
use App\Services\VendJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Edits outside a UI Setting or a mapping that change what a machine shows reach it at once
 * (Brian, 2026-10-30) - MachineRefreshTriggers: campaign edits, label / category renames, Grab bindings.
 */
class MachineRefreshTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::table('operators')->insertOrIgnore([
            'id' => 1, 'code' => 'HIPL', 'name' => 'HIPL', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function vend(int $code, array $attrs = []): Vend
    {
        return Vend::forceCreate(array_merge([
            'code' => $code, 'operator_id' => 1, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1,
        ], $attrs));
    }

    private function apkSetting(Vend ...$vends): ApkSetting
    {
        $setting = ApkSetting::withoutGlobalScopes()->forceCreate(['name' => 'UI '.uniqid(), 'operator_id' => 1]);
        $setting->vends()->attach(collect($vends)->pluck('id')->all());

        return $setting;
    }

    /** @return list<int> apk_setting ids a push was queued for */
    private function settingPushes(): array
    {
        return Queue::pushed(PushApkSettingSync::class)
            ->map(fn ($job) => (fn () => $this->apkSettingId)->call($job))->sort()->values()->all();
    }

    /** @return list<int> vend ids a per-machine settings push was queued for */
    private function vendPushes(): array
    {
        return Queue::pushed(PushVendSettingsSync::class)
            ->map(fn ($job) => (fn () => $this->vendId)->call($job))->sort()->values()->all();
    }

    /** @return array<int, bool> mapping id => text only, for each queued menu push */
    private function menuPushes(): array
    {
        return Queue::pushed(PushMappingMenuSync::class)
            ->mapWithKeys(fn ($job) => (fn () => [$this->productMappingId => $this->textOnly])->call($job))->all();
    }

    private function mappedProduct(string $code): array
    {
        $mapping = ProductMapping::create(['name' => 'Map '.$code, 'operator_id' => 1, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE]);
        $product = Product::create(['code' => $code, 'name' => 'Product '.$code, 'operator_id' => 1, 'is_active' => true]);
        $item = new ProductMappingItem;
        $item->product_mapping_id = $mapping->id;
        $item->product_id = $product->id;
        $item->channel_code = '11';
        $item->save();

        return [$mapping, $product];
    }

    // ------------------------------------------------------------- campaigns

    public function test_a_campaign_edit_pushes_every_ui_setting_it_is_bound_to(): void
    {
        $campaign = Campaign::forceCreate(['name' => 'B1F1', 'slug' => 'b1f1', 'operator_id' => 1, 'promo_type' => 'Item', 'is_using_qty' => 'qty', 'is_active' => true]);
        $bound = $this->apkSetting($this->vend(2003));
        $this->apkSetting($this->vend(2004));
        $bound->campaigns()->attach($campaign->id);
        Queue::fake();

        $campaign->update(['value' => 20]);

        $this->assertSame([$bound->id], $this->settingPushes());
    }

    // ------------------------------------------------------------- labels and categories

    public function test_a_label_rename_pushes_the_menu_words_only_and_the_ui_settings_naming_it(): void
    {
        [$mapping, $product] = $this->mappedProduct('P1');
        $this->mappedProduct('P2');
        $tag = Tag::create(['name' => 'healthy', 'classname' => Product::class]);
        DB::table('tag_bindings')->insert(['tag_id' => $tag->id, 'modelable_id' => $product->id, 'modelable_type' => (new Product)->getMorphClass(), 'created_at' => now(), 'updated_at' => now()]);
        $setting = $this->apkSetting($this->vend(2003));
        $item = CampaignItem::forceCreate(['apk_setting_id' => $setting->id, 'qty' => 2, 'promo_type' => array_key_first(CampaignItem::PROMO_TYPE_MAPPINGS), 'value' => 10]);
        DB::table('tag_bindings')->insert(['tag_id' => $tag->id, 'modelable_id' => $item->id, 'modelable_type' => (new CampaignItem)->getMorphClass(), 'created_at' => now(), 'updated_at' => now()]);
        Queue::fake();

        $tag->update(['name' => 'healthier']);

        $this->assertSame([$mapping->id => true], $this->menuPushes());
        $this->assertSame([$setting->id], $this->settingPushes());
    }

    public function test_saving_a_label_unchanged_pushes_nothing(): void
    {
        [, $product] = $this->mappedProduct('P1');
        $tag = Tag::create(['name' => 'healthy', 'classname' => Product::class]);
        DB::table('tag_bindings')->insert(['tag_id' => $tag->id, 'modelable_id' => $product->id, 'modelable_type' => (new Product)->getMorphClass(), 'created_at' => now(), 'updated_at' => now()]);
        Queue::fake();

        $tag->update(['name' => 'healthy']);

        Queue::assertNotPushed(PushMappingMenuSync::class);
    }

    public function test_a_category_rename_or_delete_pushes_the_menu_words_only(): void
    {
        [$mapping, $product] = $this->mappedProduct('P1');
        $category = Category::create(['name' => 'Ice cream', 'classname' => Product::class]);
        $product->update(['category_id' => $category->id]);
        Queue::fake();

        $category->update(['name' => 'Ice Cream']);
        $this->assertSame([$mapping->id => true], $this->menuPushes());

        Cache::flush();
        Queue::fake();
        $category->delete();
        $this->assertSame([$mapping->id => true], $this->menuPushes());
    }

    public function test_a_words_only_push_sends_happyhour_to_315_plus_big_boards_and_the_full_frame_to_others(): void
    {
        [$mapping] = $this->mappedProduct('P1');
        $this->vend(2003, ['product_mapping_id' => $mapping->id, 'apk_ver_json' => ['apkver' => '320', 'deviceType' => 'ZC-328']]);
        $this->vend(2004, ['product_mapping_id' => $mapping->id, 'apk_ver_json' => ['apkver' => '305', 'deviceType' => 'ZC-328']]);

        $light = $full = [];
        $service = $this->createMock(VendJobService::class);
        $service->method('nudgeHappyHourToVend')->willReturnCallback(function ($vend) use (&$light) {
            $light[] = (int) $vend->code;

            return true;
        });
        $service->method('syncChannelSlotListToVend')->willReturnCallback(function ($vend) use (&$full) {
            $full[] = (int) $vend->code;

            return true;
        });

        (new PushMappingMenuSync($mapping->id, false, true))->handle($service);

        $this->assertSame([2003], $light);
        $this->assertSame([2004], $full);
    }

    // ------------------------------------------------------------- Grab

    private function grabStore(): DeliveryPlatformRefNumber
    {
        return DeliveryPlatformRefNumber::forceCreate([
            'ref_number' => 'GRAB-'.uniqid(), 'operator_id' => 1, 'status' => DeliveryPlatformRefNumber::STATUS_ACTIVE,
        ]);
    }

    public function test_a_grab_binding_opened_paused_or_moved_pushes_the_machines_settings(): void
    {
        $a = $this->vend(2003);
        $b = $this->vend(2004);
        $ref = $this->grabStore();
        Queue::fake();

        $binding = DeliveryProductMappingVend::withoutGlobalScopes()->forceCreate([
            'vend_id' => $a->id, 'delivery_platform_ref_number_id' => $ref->id, 'is_active' => true,
        ]);
        $this->assertSame([$a->id], $this->vendPushes());

        Cache::flush();
        Queue::fake();
        $binding->update(['is_active' => false]);
        $this->assertSame([$a->id], $this->vendPushes());

        Cache::flush();
        Queue::fake();
        $binding->update(['vend_id' => $b->id]);
        $this->assertSame([$a->id, $b->id], $this->vendPushes());

        Cache::flush();
        Queue::fake();
        $binding->update(['last_menu_json' => ['x' => 1]]);
        Queue::assertNotPushed(PushVendSettingsSync::class);
    }

    public function test_a_grab_store_switched_off_pushes_its_machines(): void
    {
        $a = $this->vend(2003);
        $ref = $this->grabStore();
        DeliveryProductMappingVend::withoutGlobalScopes()->forceCreate([
            'vend_id' => $a->id, 'delivery_platform_ref_number_id' => $ref->id, 'is_active' => true,
        ]);
        Cache::flush();
        Queue::fake();

        $ref->update(['status' => DeliveryPlatformRefNumber::STATUS_INACTIVE]);

        $this->assertSame([$a->id], $this->vendPushes());
    }

    public function test_the_settings_push_reaches_only_a_vending_machine_parameters_answers_for(): void
    {
        $bound = $this->vend(2003);
        $this->apkSetting($bound);
        $unbound = $this->vend(2004);
        $freezer = $this->vend(50001, ['machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER]);
        $this->apkSetting($freezer);

        $sent = [];
        $service = $this->createMock(VendJobService::class);
        $service->method('syncSettingsToVend')->willReturnCallback(function ($vend) use (&$sent) {
            $sent[] = (int) $vend->code;

            return null;
        });

        foreach ([$bound, $unbound, $freezer] as $vend) {
            (new PushVendSettingsSync($vend->id))->handle($service);
        }

        $this->assertSame([2003], $sent);
    }
}
