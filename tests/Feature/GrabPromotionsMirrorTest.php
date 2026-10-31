<?php

namespace Tests\Feature;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformCampaignItemVend;
use App\Models\DeliveryPlatformCampaignRead;
use App\Models\DeliveryPlatformLiveCampaign;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Promotions mirror (plan §9.3, 2026-10-30): what Grab has live per store, read
 * from Grab, never written to it; the old campaign pages' campaigns they believe ended show as
 * leftovers.
 */
class GrabPromotionsMirrorTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const MERCHANT = '4-C7W2RE5APADUGN';

    private $binding;

    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->item = $this->grabItem('U-49', 11, 280);
        Queue::fake();
        $this->binding = $this->bindGrabStore(4754, self::MERCHANT, [11 => ['qty' => 5, 'capacity' => 10]]);
    }

    private function campaign(string $id, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $id,
            'createdBy' => 'Merchant',
            'merchantID' => self::MERCHANT,
            'name' => 'Any 2 for $5',
            'quotas' => ['totalCount' => 0, 'totalCountPerUser' => 0],
            'conditions' => [
                'startTime' => '2026-10-30T02:00:00Z',
                'endTime' => '2026-11-30T15:59:00Z',
                'eaterType' => 'all',
                'minBasketAmount' => 0,
                'bundleQuantity' => 2,
            ],
            'discount' => [
                'type' => 'bundleDiffFixPrice',
                'value' => 5,
                'scope' => ['type' => 'items', 'objectIDs' => [(string) $this->item->id]],
            ],
        ], $overrides);
    }

    private function ourItemVend(string $grabId, array $attributes = []): DeliveryPlatformCampaignItemVend
    {
        return DeliveryPlatformCampaignItemVend::forceCreate($attributes + [
            'delivery_platform_campaign_id' => 7,
            'delivery_platform_campaign_item_id' => 1,
            'delivery_product_mapping_vend_id' => $this->binding->id,
            'platform_ref_id' => $grabId,
            'is_active' => true,
            'is_submitted' => true,
            'datetime_to' => now()->addDays(10),
            'vend_code' => 4754,
        ]);
    }

    // What Grab answers next. Http::fake stubs stack (the first match wins), so the
    // fake is set once and reads this.
    private $answer = null;

    private function fakeList(array $ongoing, array $upcoming = []): void
    {
        $this->answer(Http::response(['ongoing' => $ongoing, 'upcoming' => $upcoming], 200));
    }

    private function answer($response): void
    {
        $first = $this->answer === null;
        $this->answer = $response;
        if ($first) {
            Http::fake(['*/partner/v1/campaigns*' => fn () => $this->answer]);
        }
    }

    public function test_a_stores_live_and_upcoming_campaigns_are_mirrored_and_ours_are_recognised(): void
    {
        $this->ourItemVend('C-OURS');
        $this->fakeList(
            [$this->campaign('C-OURS', ['createdBy' => 'Partner']), $this->campaign('C-APP')],
            [$this->campaign('C-SOON', ['createdBy' => 'Grab', 'conditions' => ['startTime' => '2026-11-05T01:00:00Z']])],
        );

        $this->artisan('grab:mirror-campaigns')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/partner/v1/campaigns?merchantID='.self::MERCHANT)
            && $request->header('merchantID')[0] === self::MERCHANT);
        Http::assertSentCount(1);

        $rows = DeliveryPlatformLiveCampaign::query()->with('deliveryPlatformCampaignItemVend.deliveryProductMappingVend')->get()->keyBy('grab_campaign_id');
        $this->assertCount(3, $rows);
        $this->assertSame('ongoing', $rows['C-OURS']->state);
        $this->assertSame('connectvend', $rows['C-OURS']->source());
        $this->assertSame('merchant_app', $rows['C-APP']->source());
        $this->assertSame('upcoming', $rows['C-SOON']->state);
        $this->assertSame('grab_team', $rows['C-SOON']->source());
        // Grab's UTC, kept in Singapore time like every other column.
        $this->assertSame('2026-11-05 09:00:00', $rows['C-SOON']->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('bundleDiffFixPrice', $rows['C-APP']->discount_type);
        $this->assertSame($this->binding->delivery_platform_ref_number_id, $rows['C-APP']->delivery_platform_ref_number_id);

        $read = DeliveryPlatformCampaignRead::sole();
        $this->assertSame(2, $read->ongoing_count);
        $this->assertSame(1, $read->upcoming_count);
        $this->assertNotNull($read->last_ok_at);
    }

    public function test_a_campaign_mark1_believes_ended_but_grab_still_runs_is_a_leftover(): void
    {
        $this->ourItemVend('C-ENDED', ['is_active' => false, 'datetime_to' => now()->subDays(60)]);
        $this->ourItemVend('C-MOVED');
        $this->binding->forceFill(['end_date' => now()->subDay()])->save();
        $this->fakeList([$this->campaign('C-ENDED', ['createdBy' => 'Partner']), $this->campaign('C-MOVED', ['createdBy' => 'Partner'])]);

        $this->artisan('grab:mirror-campaigns', ['--store' => self::MERCHANT])->assertSuccessful();

        $rows = DeliveryPlatformLiveCampaign::query()->get()->keyBy('grab_campaign_id');
        $this->assertSame('leftover', $rows['C-ENDED']->source());
        $this->assertSame('leftover', $rows['C-MOVED']->source());
    }

    public function test_a_campaign_no_longer_listed_is_gone_and_a_failed_read_changes_nothing(): void
    {
        $this->fakeList([$this->campaign('C-1'), $this->campaign('C-2')]);
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();

        $this->fakeList([$this->campaign('C-2')]);
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        $this->assertNotNull(DeliveryPlatformLiveCampaign::query()->where('grab_campaign_id', 'C-1')->value('gone_at'));
        $this->assertNull(DeliveryPlatformLiveCampaign::query()->where('grab_campaign_id', 'C-2')->value('gone_at'));

        // Grab fails: what we last saw stays, and the read says it failed.
        $this->answer(Http::response(['reason' => 'internal_error'], 500));
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        $this->assertNull(DeliveryPlatformLiveCampaign::query()->where('grab_campaign_id', 'C-2')->value('gone_at'));
        $read = DeliveryPlatformCampaignRead::sole();
        $this->assertSame(500, $read->last_status);
        $this->assertNotNull($read->last_message);
        $this->assertSame(1, $read->ongoing_count, 'the count is the last good read');
        $this->assertSame('campaign_list', DeliveryPlatformApiError::sole()->action);

        // An empty answer (Grab documents 204) means nothing is live.
        $this->answer(Http::response('', 204));
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        $this->assertSame(0, DeliveryPlatformLiveCampaign::query()->live()->count());
    }

    public function test_a_store_grab_refuses_us_is_not_asked(): void
    {
        DeliveryPlatformApiError::create([
            'direction' => 'outgoing', 'action' => 'store_status', 'status' => 401, 'merchant_id' => self::MERCHANT,
        ]);
        Http::fake();

        $this->artisan('grab:mirror-campaigns')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_merchant_grab_does_not_know_is_asked_once_a_day(): void
    {
        $this->answer(Http::response(['reason' => 'not_found', 'message' => 'Merchant not found'], 404));

        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        Http::assertSentCount(1);

        $this->travel(25)->hours();
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_sandbox_stores_are_not_read(): void
    {
        $this->grabOperator->forceFill(['type' => 'sandbox'])->save();
        DeliveryPlatformCampaignRead::create([
            'merchant_id' => self::MERCHANT, 'last_read_at' => now(), 'last_status' => 401, 'last_message' => 'listCampaigns Unauthorized',
        ]);
        Http::fake();

        $this->artisan('grab:mirror-campaigns')->assertSuccessful();

        Http::assertNothingSent();
        // Its old failed read leaves the page's "could not be read" box.
        $this->assertSame(0, DeliveryPlatformCampaignRead::query()->count());
    }

    public function test_a_rate_limit_stops_the_run(): void
    {
        $this->bindGrabStore(4755, '4-SECONDSTORE', [11 => ['qty' => 5, 'capacity' => 10]]);
        Http::fake(['*/partner/v1/campaigns*' => Http::response(['message' => 'Too many'], 429)]);

        $this->artisan('grab:mirror-campaigns')->expectsOutputToContain('rate limit')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_ended_rows_are_kept_30_days(): void
    {
        $this->fakeList([$this->campaign('C-OLD'), $this->campaign('C-RECENT')]);
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();
        DeliveryPlatformLiveCampaign::query()->where('grab_campaign_id', 'C-OLD')->update(['gone_at' => now()->subDays(31)]);
        DeliveryPlatformLiveCampaign::query()->where('grab_campaign_id', 'C-RECENT')->update(['gone_at' => now()->subDays(29)]);

        $this->fakeList([]);
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();

        $this->assertSame(['C-RECENT'], DeliveryPlatformLiveCampaign::query()->pluck('grab_campaign_id')->all());
    }

    public function test_the_page_lists_what_grab_has_and_a_client_sees_only_its_own_stores(): void
    {
        $this->ourItemVend('C-LEFT', ['is_active' => false]);
        $this->fakeList([$this->campaign('C-LEFT', ['createdBy' => 'Partner']), $this->campaign('C-APP')]);
        $this->artisan('grab:mirror-campaigns')->assertSuccessful();

        Permission::findOrCreate('read delivery-platform-campaigns', 'web');
        Permission::findOrCreate('read delivery-platform-vends', 'web');
        $hipl = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $hipl->givePermissionTo(['read delivery-platform-campaigns', 'read delivery-platform-vends']);

        $this->actingAs($hipl)->get('/grab-promotions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('GrabPromotion/Index')
                ->has('promotions.data', 2)
                ->where('totals.leftover', 1)
                ->where('totals.merchant_app', 1)
                ->where('promotions.data.0.stores.0.vend_code', 4754)
                ->where('promotions.data.0.objects.0.name', 'U-49 Product U-49'));

        $this->actingAs($hipl)->get('/grab-promotions?source=leftover')
            ->assertInertia(fn ($page) => $page->has('promotions.data', 1)
                ->where('promotions.data.0.stores.0.grab_campaign_id', 'C-LEFT')
                ->where('promotions.data.0.leftover_vend_code', '4754'));

        // Grab Stores counts them on the store's row.
        $this->actingAs($hipl)->get('/grab-stores')
            ->assertInertia(fn ($page) => $page->where('stores.data.0.promotions_live', 2));

        $client = Operator::forceCreate([
            'name' => 'Kumoyo', 'code' => 'KMY', 'country_id' => $this->grabMapping->operator->country_id, 'created_by' => 1, 'gst_vat_rate' => 9,
        ]);
        $clientUser = User::factory()->create(['operator_id' => $client->id]);
        $clientUser->givePermissionTo('read delivery-platform-campaigns');
        $this->actingAs($clientUser)->get('/grab-promotions')
            ->assertInertia(fn ($page) => $page->has('promotions.data', 0));

        // Nor may it read another operator's store from Grab.
        $ref = DeliveryPlatformRefNumber::query()->where('ref_number', self::MERCHANT)->first();
        $this->actingAs($clientUser)->post("/grab-promotions/stores/{$ref->id}/refresh")->assertForbidden();
    }

    public function test_read_again_asks_grab_for_one_store_and_changes_nothing_there(): void
    {
        Permission::findOrCreate('read delivery-platform-campaigns', 'web');
        $hipl = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $hipl->givePermissionTo('read delivery-platform-campaigns');
        $ref = DeliveryPlatformRefNumber::query()->where('ref_number', self::MERCHANT)->first();
        $this->fakeList([$this->campaign('C-APP')]);

        $this->actingAs($hipl)->post("/grab-promotions/stores/{$ref->id}/refresh")
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
        $this->assertSame(1, DeliveryPlatformLiveCampaign::query()->live()->count());
    }
}
