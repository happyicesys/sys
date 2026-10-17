<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Audit 2026-10-17:
 * - STK-11: every product-mapping route passed on `read product-mappings`, including the item
 *   routes that now push stock to CityBox. Each verb is gated on its own permission.
 * - CORE-02: update() filled the mapping from the whole request while `operator_id` is fillable,
 *   so an operator user could hand a mapping to another operator or make it global. Only an
 *   unrestricted (HIPL) viewer may move a mapping between operators.
 */
class ProductMappingWriteGateTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $other;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $this->other = Operator::withoutGlobalScopes()->create(['code' => 'OTH', 'name' => 'OTH', 'is_active' => true]);
    }

    /** @param  list<string>  $verbs */
    private function userFor(Operator $operator, array $verbs): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        foreach ($verbs as $verb) {
            $user->givePermissionTo(Permission::findOrCreate("{$verb} product-mappings", 'web'));
        }

        return $user;
    }

    private function mapping(Operator $owner, string $name = 'Mapping'): ProductMapping
    {
        return ProductMapping::withoutGlobalScopes()->create(['name' => $name, 'operator_id' => $owner->id, 'is_active' => true]);
    }

    public function test_read_alone_cannot_write_a_mapping_or_its_items(): void
    {
        $mapping = $this->mapping($this->hipl);
        $itemId = DB::table('product_mapping_items')->insertGetId(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'sequence' => 1]);
        $this->actingAs($this->userFor($this->hipl, ['read']));

        $this->post('/product-mappings/create', ['name' => 'New'])->assertForbidden();
        $this->post('/product-mappings/replicate', ['id' => $mapping->id])->assertForbidden();
        $this->post("/product-mappings/{$mapping->id}/update", ['name' => 'Renamed'])->assertForbidden();
        $this->post("/product-mappings/{$mapping->id}/toggle-smart")->assertForbidden();
        $this->post("/product-mappings/{$mapping->id}/toggle-activate-deactivate")->assertForbidden();
        $this->post("/product-mappings/{$mapping->id}/update/vends", ['vends' => []])->assertForbidden();
        $this->post("/product-mappings/{$mapping->id}/items/create", ['channel_code' => '12'])->assertForbidden();
        $this->post("/product-mappings/{$mapping->id}/baskets/reorder", [])->assertForbidden();
        $this->post("/product-mappings/items/{$itemId}/update", ['channel_code' => '13'])->assertForbidden();
        $this->post("/product-mappings/items/{$itemId}/sequence", ['sequence' => 2])->assertForbidden();
        $this->delete("/product-mappings/items/{$itemId}")->assertForbidden();
        $this->delete("/product-mappings/{$mapping->id}")->assertForbidden();

        $this->assertSame('Mapping', $mapping->fresh()->name);
        $this->assertSame(1, ProductMapping::withoutGlobalScopes()->count());
        $this->assertSame(1, DB::table('product_mapping_items')->count());
    }

    public function test_update_needs_update_and_delete_needs_delete(): void
    {
        $mapping = $this->mapping($this->hipl);

        $this->actingAs($this->userFor($this->hipl, ['read', 'update']))
            ->post("/product-mappings/{$mapping->id}/update", ['name' => 'Renamed'])->assertRedirect();
        $this->assertSame('Renamed', $mapping->fresh()->name);

        $this->actingAs($this->userFor($this->hipl, ['read', 'update']))
            ->delete("/product-mappings/{$mapping->id}")->assertForbidden();
    }

    public function test_an_operator_user_cannot_move_their_mapping_to_another_operator_or_make_it_global(): void
    {
        $mapping = $this->mapping($this->other);
        $user = $this->userFor($this->other, ['read', 'update']);

        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/update", ['name' => 'Mine', 'operator_id' => ''])->assertRedirect();
        $this->assertSame($this->other->id, (int) $mapping->fresh()->operator_id);

        $this->actingAs($user)->post("/product-mappings/{$mapping->id}/update", ['name' => 'Mine', 'operator_id' => $this->hipl->id])->assertRedirect();
        $this->assertSame($this->other->id, (int) $mapping->fresh()->operator_id);
        $this->assertSame('Mine', $mapping->fresh()->name);
    }

    public function test_hipl_still_moves_a_mapping_between_operators(): void
    {
        $mapping = $this->mapping($this->hipl);

        $this->actingAs($this->userFor($this->hipl, ['read', 'update']))
            ->post("/product-mappings/{$mapping->id}/update", ['name' => 'Moved', 'operator_id' => $this->other->id])->assertRedirect();

        $this->assertSame($this->other->id, (int) ProductMapping::withoutGlobalScopes()->find($mapping->id)->operator_id);
    }

    public function test_create_takes_the_operator_from_the_user_not_the_request(): void
    {
        $this->actingAs($this->userFor($this->other, ['read', 'create']))
            ->post('/product-mappings/create', ['name' => 'Fresh', 'operator_id' => $this->hipl->id])->assertRedirect();

        $this->assertSame($this->other->id, (int) ProductMapping::withoutGlobalScopes()->where('name', 'Fresh')->value('operator_id'));
    }
}
