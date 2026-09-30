<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Products index → "Default Capacity" and "Shelf Life (days)" columns (Brian, 2026-09-30): pieces per
 * smart-freezer slot / smart-chiller channel, and the CityBox shelf life, all set on Product → Edit.
 */
class ProductIndexDefaultCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_payload_carries_both_default_capacities(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('read products', 'web'));
        Product::create(['code' => 'CAP-1', 'name' => 'Both', 'freezer_slot_qty' => 24, 'chiller_slot_qty' => 6, 'is_active' => true]);
        Product::create(['code' => 'CAP-2', 'name' => 'Neither', 'is_active' => true]);

        $this->actingAs($user)->get('/products?code=CAP-&numberPerPage=100')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Product/Index')
                ->where('products.data', fn ($rows) => collect($rows)->keyBy('code')->map(
                    fn ($r) => [$r['freezer_slot_qty'], $r['chiller_slot_qty']]
                )->only(['CAP-1', 'CAP-2'])->all() === ['CAP-1' => [24, 6], 'CAP-2' => [null, null]]));
    }

    public function test_shelf_life_is_saved_cleared_and_validated_and_reaches_the_index(): void
    {
        \Illuminate\Database\Eloquent\Model::reguard(); // other suite files unguard and never reguard
        $operator = Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1, 'timezone' => 'Asia/Singapore', 'is_active' => true]);
        $user = User::factory()->create(['operator_id' => $operator->id]);
        foreach (['read products', 'update products'] as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $product = Product::create(['code' => '90271', 'name' => 'MetaVita Tea', 'operator_id' => $operator->id, 'is_active' => true]);
        $save = fn ($days) => $this->actingAs($user)->from("/products/{$product->id}/edit")
            ->post("/products/{$product->id}/update", ['code' => $product->code, 'name' => $product->name, 'operator_id' => $operator->id, 'shelf_life_days' => $days]);

        $save(180)->assertSessionHasNoErrors();
        $this->assertSame(180, (int) $product->fresh()->shelf_life_days);

        $this->actingAs($user)->get('/products?code=90271')
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.shelf_life_days', 180));

        $save(0)->assertSessionHasErrors('shelf_life_days');
        $this->assertSame(180, (int) $product->fresh()->shelf_life_days, 'a refused save changes nothing');

        $save('')->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->shelf_life_days, 'blank clears it');
    }
}
