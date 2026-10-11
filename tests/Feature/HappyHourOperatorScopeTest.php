<?php

namespace Tests\Feature;

use App\Models\HappyHourCampaign;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Happy Hour operator isolation (OperatorHappyHourCampaignScope): an operator-restricted admin sees,
 * edits, previews and deletes only its own operator's campaigns, never unbinds another operator's
 * freezer, and never sees another operator's machines or products; HIPL sees all.
 */
class HappyHourOperatorScopeTest extends TestCase
{
    use RefreshDatabase;

    private const OTHER = 23;

    private int $hiplVend;

    private int $otherVend;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->hiplVend = $this->freezer(50001, 1, 'HIPL-ONLY');
        $this->otherVend = $this->freezer(2009, self::OTHER, 'OTHER-ONLY');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function freezer(int $code, int $operatorId, string $productCode): int
    {
        $vendId = DB::table('vends')->insertGetId([
            'code' => $code, 'operator_id' => $operatorId, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'apk_version_code' => 30, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $product = Product::forceCreate(['code' => $productCode, 'name' => "Treat {$productCode}", 'operator_id' => $operatorId]);
        DB::table('vend_channels')->insert(['vend_id' => $vendId, 'code' => 11, 'product_id' => $product->id, 'amount' => 400, 'qty' => 5, 'capacity' => 10, 'is_active' => 1]);

        return $vendId;
    }

    private function admin(int $operatorId): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }

        return $user;
    }

    /** @param  array<string, mixed>  $overrides */
    private function form(array $vendIds, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Clear slow movers', 'is_active' => true, 'starts_on' => null, 'ends_on' => null,
            'days_mask' => HappyHourCampaign::EVERY_DAY, 'window_start' => '14:00', 'window_end' => '18:00',
            'slot_minutes' => 60, 'sku_count' => 2, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT,
            'lookback_days' => 14, 'discount_pct' => 30, 'min_balance_pct' => 15, 'min_qty' => 2,
            'price_step_cents' => 10, 'allow_below_cost' => false, 'excluded_product_ids' => [], 'vend_ids' => $vendIds,
        ], $overrides);
    }

    public function test_a_restricted_admin_cannot_see_edit_preview_or_delete_another_operators_campaign(): void
    {
        $hipl = $this->admin(1);
        $other = $this->admin(self::OTHER);

        // HIPL's campaign spans both operators' freezers.
        $this->actingAs($hipl)->post('/happy-hour-campaigns', $this->form([$this->hiplVend, $this->otherVend]))->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::firstOrFail();
        $this->assertSame(1, (int) $campaign->operator_id);

        $this->actingAs($other)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->has('campaigns', 0)
                ->where('machines', fn ($machines) => collect($machines)->pluck('id')->all() === [$this->otherVend])
                ->where('products', fn ($products) => collect($products)->pluck('name')->all() === ['OTHER-ONLY Treat OTHER-ONLY']));
        $this->actingAs($other)->post("/happy-hour-campaigns/{$campaign->id}", $this->form([]))->assertNotFound();
        $this->actingAs($other)->getJson("/happy-hour-campaigns/{$campaign->id}/preview")->assertNotFound();
        $this->actingAs($other)->delete("/happy-hour-campaigns/{$campaign->id}")->assertNotFound();
        $this->assertEqualsCanonicalizing([$this->hiplVend, $this->otherVend], $campaign->vends()->pluck('vends.id')->all());
        $this->assertNotSoftDeleted($campaign);

        // Its own campaign on its own freezer is refused while HIPL's (which it cannot see) holds that freezer.
        $this->actingAs($other)->post('/happy-hour-campaigns', $this->form([$this->otherVend], ['name' => 'Mine']))
            ->assertSessionHasErrors('vend_ids');
        // ...and another operator's freezer cannot be added at all.
        $this->actingAs($other)->post('/happy-hour-campaigns', $this->form([$this->hiplVend], ['name' => 'Mine', 'window_start' => '19:00', 'window_end' => '20:00']))
            ->assertSessionHasErrors('vend_ids');

        $this->actingAs($other)->post('/happy-hour-campaigns', $this->form([$this->otherVend], ['name' => 'Mine', 'window_start' => '19:00', 'window_end' => '20:00']))
            ->assertSessionHasNoErrors();
        $mine = HappyHourCampaign::where('name', 'Mine')->firstOrFail();
        $this->assertSame(self::OTHER, (int) $mine->operator_id);
        $this->actingAs($other)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->has('campaigns', 1)->where('campaigns.0.id', $mine->id));

        // HIPL sees both.
        $this->actingAs($hipl)->get('/happy-hour-campaigns')->assertOk()->assertInertia(fn ($page) => $page->has('campaigns', 2));
    }

    public function test_an_operators_save_never_unbinds_a_freezer_it_cannot_see(): void
    {
        $other = $this->admin(self::OTHER);
        $this->actingAs($other)->post('/happy-hour-campaigns', $this->form([$this->otherVend]))->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::firstOrFail();

        // HIPL adds its own freezer to the operator's campaign.
        $this->actingAs($this->admin(1))->post("/happy-hour-campaigns/{$campaign->id}", $this->form([$this->otherVend, $this->hiplVend]))
            ->assertSessionHasNoErrors();

        // The operator sees only its freezer, in the list and in the lineup...
        $this->actingAs($other)->get('/happy-hour-campaigns')->assertOk()
            ->assertInertia(fn ($page) => $page->where('campaigns.0.vends', fn ($vends) => collect($vends)->pluck('id')->all() === [$this->otherVend]));
        $this->actingAs($other)->getJson("/happy-hour-campaigns/{$campaign->id}/preview?vend_id={$this->hiplVend}")->assertOk()
            ->assertJsonCount(0, 'machines');

        // ...and saving with its freezer taken out leaves HIPL's in place.
        $this->actingAs($other)->post("/happy-hour-campaigns/{$campaign->id}", $this->form([]))->assertSessionHasNoErrors();
        $this->assertSame([$this->hiplVend], $campaign->vends()->pluck('vends.id')->map(fn ($id) => (int) $id)->all());
    }
}
