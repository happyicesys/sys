<?php

namespace Tests\Feature;

use App\Models\ApkSetting;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * UI Setting (/apk-settings) operator isolation.
 *
 * 2026-10-07: EATZ (operator_admin) created six settings in five minutes and
 * hit a 404 every time - store() redirects to edit, and the scope only showed
 * a non-HappyIce viewer settings already bound to their machines, which a new
 * setting never is. Rule now: a setting the viewer OWNS, or one bound to one
 * of their machines. The write endpoints the Edit page reaches are pinned too,
 * because fixing the 404 is what puts operators on that page.
 */
class ApkSettingOperatorScopeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        OperatorScope::flush();

        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;
        DB::table('operators')->insert([
            'id' => $id,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail($id);
        $this->opA = $this->operator('OPA');
        $this->opB = $this->operator('OPB');
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function operator(string $code): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function userFor(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);

        OperatorScope::flush();

        return $user;
    }

    private function makeVend(Operator $operator, int $code): int
    {
        return DB::table('vends')->insertGetId([
            'code' => $code,
            'name' => "Machine {$code}",
            'operator_id' => $operator->id,
            'is_active' => 1,
            'is_testing' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeSetting(?Operator $owner, string $name): int
    {
        return DB::table('apk_settings')->insertGetId([
            'name' => $name,
            'operator_id' => $owner?->id,
            'settings_parameter_json' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bind(int $settingId, int $vendId): void
    {
        DB::table('apk_setting_vend')->insert([
            'apk_setting_id' => $settingId,
            'vend_id' => $vendId,
        ]);
    }

    private function makeCampaign(Operator $operator, string $name): int
    {
        return Campaign::create([
            'name' => $name,
            'operator_id' => $operator->id,
        ])->id;
    }

    /** @return string[] setting names visible to this user */
    private function visibleTo(?User $user): array
    {
        if ($user) {
            $this->actingAs($user);
        }

        return ApkSetting::query()->orderBy('name')->pluck('name')->all();
    }

    // ------------------------------------------------------------ visibility

    /** The reported bug, end to end: create, follow the redirect, see the page. */
    public function test_operator_can_open_the_setting_they_just_created(): void
    {
        $user = $this->userFor($this->opA);

        $response = $this->actingAs($user)->post('/apk-settings/store', ['name' => 'Eatz']);

        $setting = ApkSetting::withoutGlobalScopes()->where('name', 'Eatz')->sole();
        $this->assertSame($this->opA->id, (int) $setting->operator_id);
        $response->assertRedirect(route('apk-settings.edit', $setting->id));

        $this->actingAs($user)->get(route('apk-settings.edit', $setting->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ApkSetting/Edit')
                ->where('apkSetting.data.id', $setting->id));

        $this->actingAs($user)->get('/apk-settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ApkSetting/Index')
                ->has('apkSettings.data', 1));
    }

    public function test_owner_comes_from_the_creator_not_the_request(): void
    {
        $this->actingAs($this->userFor($this->opA))
            ->post('/apk-settings/store', ['name' => 'Spoof', 'operator_id' => $this->opB->id]);

        $this->assertSame(
            $this->opA->id,
            (int) ApkSetting::withoutGlobalScopes()->where('name', 'Spoof')->value('operator_id')
        );
    }

    public function test_operator_sees_owned_and_bound_settings_only(): void
    {
        $this->makeSetting($this->opA, 'A-OWNED-UNBOUND');
        $this->bind($this->makeSetting($this->hipl, 'HIPL-OWNED-ON-A'), $this->makeVend($this->opA, 2001));
        $this->bind($this->makeSetting(null, 'LEGACY-ON-A'), $this->makeVend($this->opA, 2002));
        $this->makeSetting($this->opB, 'B-OWNED');
        $this->bind($this->makeSetting($this->hipl, 'HIPL-OWNED-ON-B'), $this->makeVend($this->opB, 3001));
        $this->makeSetting(null, 'LEGACY-UNBOUND');

        $this->assertSame(
            ['A-OWNED-UNBOUND', 'HIPL-OWNED-ON-A', 'LEGACY-ON-A'],
            $this->visibleTo($this->userFor($this->opA))
        );
    }

    public function test_happyice_and_unauthenticated_see_every_setting(): void
    {
        $this->makeSetting($this->opA, 'A');
        $this->makeSetting($this->opB, 'B');
        $this->makeSetting(null, 'LEGACY');

        $this->assertSame(['A', 'B', 'LEGACY'], $this->visibleTo(null));
        $this->assertSame(['A', 'B', 'LEGACY'], $this->visibleTo($this->userFor($this->hipl)));
    }

    public function test_operator_gets_404_on_another_operators_setting(): void
    {
        $settingId = $this->makeSetting($this->opB, 'B-OWNED');

        $this->actingAs($this->userFor($this->opA))
            ->get(route('apk-settings.edit', $settingId))
            ->assertNotFound();
    }

    // --------------------------------------------------- writes from the Edit page

    public function test_update_cannot_bind_another_operators_machine(): void
    {
        $settingId = $this->makeSetting($this->opA, 'A-OWNED');
        $own = $this->makeVend($this->opA, 2001);
        $foreign = $this->makeVend($this->opB, 3001);

        $this->actingAs($this->userFor($this->opA))
            ->post("/apk-settings/{$settingId}/update", ['name' => 'A-OWNED', 'vends' => [$own, $foreign]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$own],
            DB::table('apk_setting_vend')->where('apk_setting_id', $settingId)->pluck('vend_id')->all()
        );
    }

    /** HappyIce binds across operators, as it always has. */
    public function test_happyice_update_still_binds_any_machine(): void
    {
        $settingId = $this->makeSetting($this->hipl, 'HIPL-OWNED');
        $a = $this->makeVend($this->opA, 2001);
        $b = $this->makeVend($this->opB, 3001);

        $this->actingAs($this->userFor($this->hipl))
            ->post("/apk-settings/{$settingId}/update", ['name' => 'HIPL-OWNED', 'vends' => [$a, $b]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$a, $b],
            DB::table('apk_setting_vend')->where('apk_setting_id', $settingId)->pluck('vend_id')->all()
        );
    }

    public function test_edit_offers_only_own_campaigns_and_bind_rejects_foreign_ones(): void
    {
        $settingId = $this->makeSetting($this->opA, 'A-OWNED');
        $own = $this->makeCampaign($this->opA, 'A-PROMO');
        $foreign = $this->makeCampaign($this->opB, 'B-PROMO');
        $user = $this->userFor($this->opA);

        $this->actingAs($user)->get(route('apk-settings.edit', $settingId))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('campaignOptions.data', 1)
                ->where('campaignOptions.data.0.id', $own));

        $this->actingAs($user)->post("/apk-settings/{$settingId}/campaigns/bind", [
            'campaign_ids' => [$own, $foreign],
        ]);

        $this->assertSame(
            [$own],
            DB::table('apk_setting_campaign')->where('apk_setting_id', $settingId)->pluck('campaign_id')->all()
        );
    }

    public function test_operator_cannot_delete_a_campaign_item_on_another_operators_setting(): void
    {
        $settingId = $this->makeSetting($this->opB, 'B-OWNED');
        $item = CampaignItem::create(['apk_setting_id' => $settingId, 'qty' => 1, 'value' => 10]);

        $this->actingAs($this->userFor($this->opA))
            ->delete("/apk-settings/campaign-items/{$item->id}/delete-campaign-item")
            ->assertNotFound();

        $this->assertNotNull(CampaignItem::find($item->id));
    }

    public function test_a_setting_bound_only_to_other_operators_machines_cannot_be_deleted(): void
    {
        $settingId = $this->makeSetting($this->opA, 'A-OWNED');
        $this->bind($settingId, $this->makeVend($this->opB, 3001));

        $this->actingAs($this->userFor($this->opA))->delete("/apk-settings/{$settingId}");

        $this->assertDatabaseHas('apk_settings', ['id' => $settingId]);
    }
}
