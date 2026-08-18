<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Product-mapping names must be unique among the mappings the caller can see.
 *
 * Origin: 2026-08-15, two mappings both named
 * "~CEC_(Mindef)_2608_Peche_BStick_Durian_Jelly" (#601 and #621). The wrong
 * twin was preset as the upcoming mapping of #588, so 8 machines showed the
 * correct upcoming NAME in the ops job with the previous changeover's remarks.
 * Names are the only handle in every dropdown; a duplicate is never intended.
 *
 * Covers create, update (rename onto a taken name; saving your own name is
 * fine), replicate (auto-suffixes so a second copy does not collide) and the
 * operator boundary (another operator's private name is not a clash).
 *
 * Run: php artisan test --filter=ProductMappingUniqueNameTest
 */
class ProductMappingUniqueNameTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $other;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $this->other = Operator::withoutGlobalScopes()->create(['code' => 'OTH', 'name' => 'OTH', 'is_active' => true]);
    }

    private function userFor(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo(Permission::findOrCreate('read product-mappings', 'web'));

        return $user;
    }

    private function mapping(Operator $owner, string $name, bool $active = true): ProductMapping
    {
        return ProductMapping::withoutGlobalScopes()->create([
            'name' => $name,
            'operator_id' => $owner->id,
            'is_active' => $active,
        ]);
    }

    // ------------------------------------------------------------------ create

    public function test_create_rejects_a_name_that_already_exists(): void
    {
        $this->mapping($this->hipl, 'CEC_2608_Jelly');

        $this->actingAs($this->userFor($this->hipl))
            ->from('/product-mappings')
            ->post('/product-mappings/create', ['name' => 'CEC_2608_Jelly'])
            ->assertRedirect('/product-mappings')
            ->assertSessionHasErrors('name');

        $this->assertSame(1, ProductMapping::withoutGlobalScopes()->where('name', 'CEC_2608_Jelly')->count());
    }

    public function test_create_ignores_case_and_surrounding_whitespace(): void
    {
        $this->mapping($this->hipl, 'CEC_2608_Jelly');

        $this->actingAs($this->userFor($this->hipl))
            ->from('/product-mappings')
            ->post('/product-mappings/create', ['name' => '  cec_2608_jelly '])
            ->assertSessionHasErrors('name');
    }

    public function test_create_rejects_a_clash_with_an_inactive_mapping(): void
    {
        $this->mapping($this->hipl, 'CEC_2608_Jelly', active: false);

        $this->actingAs($this->userFor($this->hipl))
            ->from('/product-mappings')
            ->post('/product-mappings/create', ['name' => 'CEC_2608_Jelly'])
            ->assertSessionHasErrors('name');
    }

    public function test_create_accepts_a_fresh_name(): void
    {
        $this->actingAs($this->userFor($this->hipl))
            ->post('/product-mappings/create', ['name' => 'CEC_2609_Fresh'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_mappings', ['name' => 'CEC_2609_Fresh']);
    }

    // ------------------------------------------------------------------ update

    public function test_update_rejects_renaming_onto_another_mappings_name(): void
    {
        $this->mapping($this->hipl, 'CEC_2607_Durian');
        $target = $this->mapping($this->hipl, 'CEC_2608_Jelly');

        $this->actingAs($this->userFor($this->hipl))
            ->from("/product-mappings/{$target->id}/edit")
            ->post("/product-mappings/{$target->id}/update", ['name' => 'CEC_2607_Durian'])
            ->assertSessionHasErrors('name');

        $this->assertSame('CEC_2608_Jelly', $target->fresh()->name);
    }

    public function test_update_allows_saving_with_the_mappings_own_name(): void
    {
        $target = $this->mapping($this->hipl, 'CEC_2608_Jelly');

        $this->actingAs($this->userFor($this->hipl))
            ->post("/product-mappings/{$target->id}/update", ['name' => 'CEC_2608_Jelly', 'remarks' => 'edited'])
            ->assertSessionHasNoErrors();

        $this->assertSame('edited', $target->fresh()->remarks);
    }

    // --------------------------------------------------------------- replicate

    public function test_replicate_twice_yields_two_distinct_names(): void
    {
        $source = $this->mapping($this->hipl, 'CEC_2608_Jelly');
        $user = $this->userFor($this->hipl);

        $this->actingAs($user)->post('/product-mappings/replicate', ['id' => $source->id]);
        $this->actingAs($user)->post('/product-mappings/replicate', ['id' => $source->id]);

        $names = ProductMapping::withoutGlobalScopes()->orderBy('id')->pluck('name')->all();

        $this->assertSame(
            ['CEC_2608_Jelly', 'CEC_2608_Jelly-replicated', 'CEC_2608_Jelly-replicated-2'],
            $names
        );
    }

    // ------------------------------------------------------- operator boundary

    public function test_another_operators_private_name_is_not_a_clash(): void
    {
        // Operator OTH cannot see HIPL-less private mappings of a third party;
        // the rule follows the same visibility scope, so a name it cannot see
        // is not blocked. (Happy Ice sees everything, so for it every name is.)
        $third = Operator::withoutGlobalScopes()->create(['code' => 'THD', 'name' => 'THD', 'is_active' => true]);
        $this->mapping($third, 'CEC_2608_Jelly');

        $this->actingAs($this->userFor($this->other))
            ->post('/product-mappings/create', ['name' => 'CEC_2608_Jelly'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ProductMapping::withoutGlobalScopes()->where('name', 'CEC_2608_Jelly')->count());
    }

    public function test_happy_ice_sees_every_operators_name_as_a_clash(): void
    {
        $this->mapping($this->other, 'CEC_2608_Jelly');

        $this->actingAs($this->userFor($this->hipl))
            ->from('/product-mappings')
            ->post('/product-mappings/create', ['name' => 'CEC_2608_Jelly'])
            ->assertSessionHasErrors('name');
    }
}
