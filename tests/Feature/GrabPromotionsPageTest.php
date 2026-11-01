<?php

namespace Tests\Feature;

use App\Jobs\SendGrabPromotion;
use App\Models\Customer;
use App\Models\DeliveryPlatformCampaignSend;
use App\Models\DeliveryPlatformLiveCampaign;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryProductMappingVend;
use App\Models\User;
use App\Services\GrabPromotionService;
use App\Services\GrabStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\Grab\BuildsGrabStore;
use Tests\TestCase;

/**
 * Grab Promotions, one page (Brian, 2026-10-31: "single source of truth … from Grab API
 * only"): a promotion is made, changed and ended on Grab store by store, the list is
 * what Grab answers, and ConnectVend keeps only the log of what it asked. A promotion
 * takes every item on each store's Grab menu (Brian, 2026-11-01: no picking SKUs).
 */
class GrabPromotionsPageTest extends TestCase
{
    use BuildsGrabStore;
    use RefreshDatabase;

    private const STORE_A = '4-C7AAAAAAAAAAAA';

    private const STORE_B = '4-C7BBBBBBBBBBBB';

    private User $user;

    private $itemA;

    private $itemB;

    // Grab's campaign list per store, and what it answers a create / update / delete.
    private array $listed = [];

    private $writeAnswer = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['grab.webhook_auth' => 'off', 'grab.store_api_spacing_ms' => 0]);
        $this->buildGrabOperator();
        $this->itemA = $this->grabItem('U-49', 11, 280);
        $this->itemB = $this->grabItem('U-50', 12, 300);
        Queue::fake();
        $this->bindGrabStore(4754, self::STORE_A, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 5, 'capacity' => 10]]);
        $this->bindGrabStore(4755, self::STORE_B, [11 => ['qty' => 5, 'capacity' => 10], 12 => ['qty' => 5, 'capacity' => 10]]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/partner/v1/campaigns')) {
                $merchant = $request->header('merchantID')[0] ?? '';

                return Http::response(['ongoing' => $this->listed[$merchant] ?? [], 'upcoming' => []], 200);
            }
            if (str_contains($request->url(), '/partner/v1/campaigns')) {
                return $this->writeAnswer ?? Http::response(['id' => 'NEW-'.($request['merchantID'] ?? 'X')], 200);
            }

            return Http::response([], 200);
        });

        foreach (['read', 'create', 'update', 'delete'] as $verb) {
            Permission::findOrCreate("{$verb} delivery-platform-campaigns", 'web');
        }
        $this->user = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $this->user->givePermissionTo(['read delivery-platform-campaigns', 'create delivery-platform-campaigns', 'update delivery-platform-campaigns', 'delete delivery-platform-campaigns']);
    }

    private function promotion(array $overrides = []): array
    {
        return $overrides + [
            'ref_ids' => $this->refIds(),
            'type' => 'bundleDiffFixPrice',
            'scope' => 'items',
            'eater_type' => 'all',
            'qty' => 2,
            'value' => '5.90',
            'name' => 'Any 2 for $5.90',
            'starts_at' => now()->addHour()->format('Y-m-d H:i'),
            'ends_at' => now()->addMonth()->format('Y-m-d H:i'),
        ];
    }

    private function refIds(): array
    {
        return DeliveryPlatformRefNumber::query()->orderBy('id')->pluck('id')->all();
    }

    // Take one item off a store's machine: its Grab menu no longer sells it.
    private function dropFromMenu(string $merchant, $item): void
    {
        $binding = DeliveryProductMappingVend::query()->withoutGlobalScopes()->where('platform_ref_id', $merchant)->whereNull('end_date')->sole();
        DB::table('delivery_product_mapping_vend_channels')
            ->where('delivery_product_mapping_vend_id', $binding->id)
            ->when($item, fn ($query) => $query->where('delivery_product_mapping_item_id', $item->id))
            ->delete();
    }

    private function runQueuedSends(): void
    {
        foreach (DeliveryPlatformCampaignSend::query()->orderBy('id')->pluck('id') as $id) {
            (new SendGrabPromotion($id))->handle(app(GrabPromotionService::class));
        }
    }

    // What Grab lists for a store after a create: the body we sent, with Grab's id.
    private function grabLists(string $merchant, string $id, array $body): void
    {
        $this->listed[$merchant] = [array_merge($body, ['id' => $id, 'createdBy' => 'Partner'])];
    }

    public function test_a_new_promotion_is_sent_to_each_store_and_the_list_is_what_grab_answers(): void
    {
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion())->assertRedirect()->assertSessionHasNoErrors();

        Queue::assertPushed(SendGrabPromotion::class, 2);
        $sends = DeliveryPlatformCampaignSend::query()->orderBy('id')->get();
        $this->assertCount(2, $sends);
        $this->assertSame(1, $sends->pluck('batch')->unique()->count(), 'one press, one batch');
        $body = $sends[0]->request_json;
        $this->assertSame(self::STORE_A, $body['merchantID']);
        $this->assertSame(5.9, $body['discount']['value'], 'Grab takes dollars; ConnectVend validated cents');
        $this->assertSame(2, $body['conditions']['bundleQuantity']);
        $this->assertSame([(string) $this->itemA->id, (string) $this->itemB->id], $body['discount']['scope']['objectIDs'], 'every item on the machine, none picked');
        $this->assertStringEndsWith('Z', $body['conditions']['startTime']);
        $this->assertArrayNotHasKey('quotas', $body, 'no limit = no quota sent');

        $this->grabLists(self::STORE_A, 'NEW-'.self::STORE_A, $sends[0]->request_json);
        $this->grabLists(self::STORE_B, 'NEW-'.self::STORE_B, $sends[1]->request_json);
        $this->runQueuedSends();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/partner/v1/campaigns') && $request['merchantID'] === self::STORE_B);
        $this->assertSame(['ok', 'ok'], DeliveryPlatformCampaignSend::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame('NEW-'.self::STORE_A, DeliveryPlatformCampaignSend::query()->orderBy('id')->value('grab_campaign_id'));

        // Each store is read again at once: the mirror holds Grab's answer, ours by the send.
        $live = DeliveryPlatformLiveCampaign::query()->with('createSend')->get();
        $this->assertCount(2, $live);
        $this->assertSame(['connectvend', 'connectvend'], $live->map->source()->all());

        // One promotion on two stores is one row.
        $this->actingAs($this->user)->get('/grab-promotions')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('GrabPromotion/Index')
            ->has('promotions.data', 1)
            ->has('promotions.data.0.stores', 2)
            ->where('promotions.data.0.source', 'connectvend')
            ->where('promotions.data.0.made_by_user', $this->user->name)
            ->where('promotions.data.0.editable', true)
            ->where('promotions.data.0.all_menu_items', true)
            ->where('sends.0.ok', 2)
            ->where('can.create', true));
    }

    public function test_a_refused_send_keeps_grabs_reason_and_is_never_resent(): void
    {
        $this->writeAnswer = Http::response(['message' => 'item 11 is already in another campaign'], 400);
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['ref_ids' => [$this->refIds()[0]]]))->assertRedirect();

        $this->runQueuedSends();
        $send = DeliveryPlatformCampaignSend::sole();
        $this->assertSame('failed', $send->status);
        $this->assertSame(400, $send->response_code);
        $this->assertStringContainsString('already in another campaign', $send->response_message);

        $this->runQueuedSends();
        Http::assertSentCount(2); // one create + one read of the store, never a second create
        $this->assertSame(0, DeliveryPlatformLiveCampaign::count());
    }

    public function test_the_form_is_checked_against_grabs_rules_and_offers_no_item_picking(): void
    {
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['type' => 'bundleDiffPercentage', 'value' => 150]))->assertSessionHasErrors('value');
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['qty' => 1]))->assertSessionHasErrors('qty');
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['ends_at' => now()->subDay()->format('Y-m-d H:i')]))->assertSessionHasErrors('ends_at');
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['ref_ids' => [999999]]))->assertSessionHasErrors('ref_ids');
        // Deals that need one item or a category picked are not offered.
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['type' => 'freeItem', 'value' => null]))->assertSessionHasErrors('type');
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['type' => 'bundleSameFixPrice']))->assertSessionHasErrors('type');
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['type' => 'net', 'scope' => 'category', 'value' => '1']))->assertSessionHasErrors('type');
        $this->assertSame(0, DeliveryPlatformCampaignSend::count());

        // A whole-order deal sends no items, with its min. spend.
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['type' => 'net', 'scope' => 'order', 'eater_type' => 'new', 'value' => '2', 'min_basket' => '15']))->assertSessionHasNoErrors();
        $order = DeliveryPlatformCampaignSend::query()->first()->request_json;
        $this->assertSame(['type' => 'order'], $order['discount']['scope']);
        $this->assertSame(15, $order['conditions']['minBasketAmount']);
        $this->assertSame('new', $order['conditions']['eaterType']);

        $reader = User::factory()->create(['operator_id' => $this->grabMapping->operator_id]);
        $reader->givePermissionTo('read delivery-platform-campaigns');
        $this->actingAs($reader)->post('/grab-promotions', $this->promotion())->assertForbidden();
    }

    public function test_each_store_gets_its_own_machines_items_and_an_empty_menu_is_refused(): void
    {
        $this->dropFromMenu(self::STORE_B, $this->itemB);
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion())->assertSessionHasNoErrors();
        $sends = DeliveryPlatformCampaignSend::query()->orderBy('id')->get();
        $this->assertSame([(string) $this->itemA->id, (string) $this->itemB->id], $sends[0]->request_json['discount']['scope']['objectIDs']);
        $this->assertSame([(string) $this->itemA->id], $sends[1]->request_json['discount']['scope']['objectIDs']);

        // Nothing on a store's menu: an item deal is refused, naming the machine; a whole-order one goes.
        $this->dropFromMenu(self::STORE_B, null);
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion())->assertSessionHasErrors(['ref_ids' => 'Nothing is on the Grab menu of 4755; pick a whole-order deal or leave those stores out.']);
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['type' => 'delivery', 'scope' => 'order', 'value' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(4, DeliveryPlatformCampaignSend::count());
    }

    public function test_edit_and_end_go_to_every_store_of_the_promotion_and_wait_for_grab(): void
    {
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion());
        $sends = DeliveryPlatformCampaignSend::query()->orderBy('id')->get();
        $this->grabLists(self::STORE_A, 'NEW-'.self::STORE_A, $sends[0]->request_json);
        $this->grabLists(self::STORE_B, 'NEW-'.self::STORE_B, $sends[1]->request_json);
        $this->runQueuedSends();
        $ids = DeliveryPlatformLiveCampaign::query()->orderBy('id')->pluck('id')->all();

        // A change takes each store's menu as it is now.
        $this->dropFromMenu(self::STORE_B, $this->itemB);
        $this->actingAs($this->user)->post('/grab-promotions/update', $this->promotion(['ids' => $ids, 'value' => '4.90']))->assertSessionHasNoErrors();
        $updates = DeliveryPlatformCampaignSend::query()->where('action', 'update')->orderBy('id')->get();
        $this->assertCount(2, $updates);
        $this->assertSame(4.9, $updates[0]->request_json['discount']['value']);
        $this->assertSame([(string) $this->itemA->id], $updates[1]->request_json['discount']['scope']['objectIDs']);

        // Nothing else may go to a campaign while a change is on its way.
        $this->actingAs($this->user)->post('/grab-promotions/end', ['ids' => $ids])->assertSessionHasErrors('ids');

        $this->runQueuedSends();
        Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/partner/v1/campaigns/NEW-'.self::STORE_A));

        $this->actingAs($this->user)->post('/grab-promotions/end', ['ids' => [$ids[1]]])->assertSessionHasNoErrors();
        $this->listed[self::STORE_B] = [];
        $this->runQueuedSends();
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/partner/v1/campaigns/NEW-'.self::STORE_B));
        $this->assertNotNull(DeliveryPlatformLiveCampaign::find($ids[1])->gone_at, 'Grab no longer lists it');
        $this->assertNull(DeliveryPlatformLiveCampaign::find($ids[0])->gone_at);
    }

    public function test_a_store_leaving_its_machine_ends_only_what_connectvend_made_on_it(): void
    {
        $this->actingAs($this->user)->post('/grab-promotions', $this->promotion(['ref_ids' => [$this->refIds()[0]]]));
        $this->grabLists(self::STORE_A, 'NEW-'.self::STORE_A, DeliveryPlatformCampaignSend::sole()->request_json);
        $this->listed[self::STORE_A][] = ['id' => 'APP-1', 'createdBy' => 'Merchant', 'name' => 'Their own', 'discount' => ['type' => 'net', 'value' => 1, 'scope' => ['type' => 'order']], 'conditions' => []];
        $this->runQueuedSends();
        $this->assertSame(2, DeliveryPlatformLiveCampaign::count());

        $binding = DeliveryProductMappingVend::query()->withoutGlobalScopes()->where('platform_ref_id', self::STORE_A)->sole();
        app(GrabStoreService::class)->unbind($binding, $this->user->id, 'removed');

        $ends = DeliveryPlatformCampaignSend::query()->where('action', 'end')->get();
        $this->assertSame(['NEW-'.self::STORE_A], $ends->pluck('grab_campaign_id')->all());
    }

    public function test_the_old_campaign_page_address_lands_on_grab_promotions(): void
    {
        $this->actingAs($this->user)->get('/delivery-platform-campaigns')->assertRedirect('/grab-promotions');
    }

    public function test_the_form_lists_the_stores_on_machines_and_only_the_deals_that_need_nothing_picked(): void
    {
        // The site shows beside the machine, so a store can be found by its site name.
        $site = Customer::create(['name' => 'Carissa Park Condo', 'code' => '15653', 'operator_id' => $this->grabMapping->operator_id, 'status_id' => Customer::STATUS_ACTIVE]);
        DeliveryProductMappingVend::query()->withoutGlobalScopes()->where('platform_ref_id', self::STORE_A)->update(['customer_id' => $site->id]);

        $this->actingAs($this->user)->get('/grab-promotions')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('formStores', 2)
            ->where('formStores.0.vend_code', 4754)
            ->where('formStores.0.merchant_id', self::STORE_A)
            ->where('formStores.0.site', '15653 Carissa Park Condo')
            ->where('formStores.1.site', null)
            ->where('formStores.0.items', 2)
            ->where('formStores.0.has_account', true)
            ->has('deals', 8)
            ->where('deals.0.key', 'bundleDiffFixPrice:items')
            ->where('deals.7.key', 'delivery:order')
            ->where('deals.5.eater_types', ['all', 'new']));
    }
}
