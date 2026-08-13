<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Operator;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Campaign Settings (/campaigns) operator isolation.
 *
 * The rule mirrors Vend/CustomerIndex: HIPL staff see the HIPL sibling group
 * (HIPL, HIMD, LEA, HIESG, UL-ST); everyone else sees only their own operator.
 * These tests exist because the page previously applied NO server-side filter
 * at all - every operator saw every campaign.
 */
class CampaignOperatorScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OperatorScope::flush();
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    private function operator(string $code, string $name): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function userFor(Operator $operator, array $permissions = []): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        OperatorScope::flush();

        return $user;
    }

    /** Authenticate for direct model/helper assertions (no HTTP round trip). */
    private function loginAs(Operator $operator): User
    {
        $user = $this->userFor($operator);
        Auth::guard('web')->setUser($user);
        OperatorScope::flush();

        return $user;
    }

    private function campaignFor(Operator $operator, string $name): Campaign
    {
        return Campaign::create([
            'name' => $name,
            'operator_id' => $operator->id,
            'slug' => Str::slug($name),
            'promo_type' => Campaign::TYPE_AMOUNT,
            'is_using_qty' => 'qty',
            'is_active' => true,
        ]);
    }

    public function test_non_hipl_operator_sees_only_its_own_campaigns(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $mine = $this->campaignFor($other, 'Kumoyo 2 for 5');
        $theirs = $this->campaignFor($hipl, 'HIPL Any 2 Cornetto 6');

        $this->loginAs($other);

        $visible = Campaign::visibleTo()->pluck('id')->all();

        $this->assertContains($mine->id, $visible);
        $this->assertNotContains($theirs->id, $visible, 'a non-HIPL operator must not see HIPL campaigns');
    }

    public function test_hipl_staff_see_the_whole_sibling_group_but_not_outsiders(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $mindef = $this->operator('HIMD', 'HI Mindef');
        $bulla = $this->operator('LEA', 'Bulla');
        $outsider = $this->operator('KMY', 'Kumoyo');

        $a = $this->campaignFor($hipl, 'HIPL One');
        $b = $this->campaignFor($mindef, 'Any 2 Cornettos MINDEF');
        $c = $this->campaignFor($bulla, 'Bulla One');
        $d = $this->campaignFor($outsider, 'Kumoyo One');

        $this->loginAs($hipl);

        $visible = Campaign::visibleTo()->pluck('id')->all();

        $this->assertContains($a->id, $visible);
        $this->assertContains($b->id, $visible);
        $this->assertContains($c->id, $visible);
        $this->assertNotContains($d->id, $visible, 'HIPL must not see operators outside its sibling group');
    }

    public function test_requested_operator_filter_cannot_widen_the_ceiling(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $this->campaignFor($hipl, 'HIPL One');
        $mine = $this->campaignFor($other, 'Kumoyo One');

        $this->loginAs($other);

        // Simulate ?operators[]=<hipl id> typed straight into the URL.
        $narrowed = OperatorScope::narrow([$hipl->id, $other->id]);
        $this->assertSame([$other->id], $narrowed);

        $visible = Campaign::visibleTo()->whereIn('operator_id', $narrowed)->pluck('id')->all();
        $this->assertSame([$mine->id], $visible);
    }

    public function test_narrow_collapses_all_and_empty_selections_to_the_ceiling(): void
    {
        $other = $this->operator('KMY', 'Kumoyo');
        $this->loginAs($other);

        $this->assertSame([$other->id], OperatorScope::narrow('all'));
        $this->assertSame([$other->id], OperatorScope::narrow(null));
        $this->assertSame([$other->id], OperatorScope::narrow([]));
        $this->assertSame([$other->id], OperatorScope::narrow(['all']));
    }

    public function test_user_without_an_operator_sees_nothing_rather_than_everything(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $this->campaignFor($hipl, 'HIPL One');

        $user = User::factory()->create(['operator_id' => null]);
        Auth::guard('web')->setUser($user);
        OperatorScope::flush();

        $this->assertSame([], OperatorScope::current());
        $this->assertSame([], Campaign::visibleTo()->pluck('id')->all());
    }

    public function test_editing_another_operators_campaign_is_forbidden(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $theirs = $this->campaignFor($hipl, 'HIPL One');
        $mine = $this->campaignFor($other, 'Kumoyo One');
        $user = $this->userFor($other, ['read campaigns', 'update campaigns']);

        // Control: the permission really did attach, so the 403 below is the
        // controller's operator guard firing and not the `can:` middleware.
        $this->actingAs($user)
            ->get("/campaigns/{$mine->id}/edit")
            ->assertOk();

        $this->actingAs($user)
            ->get("/campaigns/{$theirs->id}/edit")
            ->assertForbidden();
    }

    public function test_deleting_another_operators_campaign_is_forbidden(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $theirs = $this->campaignFor($hipl, 'HIPL One');
        $mine = $this->campaignFor($other, 'Kumoyo One');
        $user = $this->userFor($other, ['read campaigns', 'delete campaigns']);

        $this->actingAs($user)
            ->delete("/campaigns/{$theirs->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('campaigns', ['id' => $theirs->id]);

        // Control: the same request against an in-scope campaign succeeds.
        $this->actingAs($user)
            ->delete("/campaigns/{$mine->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('campaigns', ['id' => $mine->id]);
    }

    public function test_creating_a_campaign_for_another_operator_is_forbidden(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $user = $this->userFor($other, ['read campaigns', 'create campaigns']);

        $this->actingAs($user)
            ->post('/campaigns/create', [
                'name' => 'Sneaky',
                'operator_id' => $hipl->id,
                'slug' => 'sneaky',
                'promo_type' => Campaign::TYPE_AMOUNT,
                'is_using_qty' => 'qty',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('campaigns', ['name' => 'Sneaky']);
    }

    public function test_out_of_scope_operator_filter_yields_zero_rows_not_everything(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $this->campaignFor($hipl, 'HIPL One');
        $this->campaignFor($other, 'Kumoyo One');

        // A HIPL admin narrowing to an operator outside the sibling group:
        // narrow() returns [], and [] must mean "no rows", not "all rows".
        // The previous `when($request->operators, ...)` treated [] as falsy.
        $user = $this->userFor($hipl, ['read campaigns', 'admin-access vend-customers']);

        $this->actingAs($user)
            ->get('/campaigns?operators[]='.$other->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Campaign/Index')
                ->has('campaigns.data', 0)
            );
    }

    public function test_index_lists_only_in_scope_campaigns(): void
    {
        $hipl = $this->operator('HIPL', 'HI SG');
        $other = $this->operator('KMY', 'Kumoyo');

        $this->campaignFor($hipl, 'HIPL Only');
        $mine = $this->campaignFor($other, 'Kumoyo Only');

        $user = $this->userFor($other, ['read campaigns']);

        $this->actingAs($user)
            ->get('/campaigns')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Campaign/Index')
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $mine->id)
            );
    }
}
