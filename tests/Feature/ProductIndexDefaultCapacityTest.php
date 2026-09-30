<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Products index → "Default Capacity" column (Brian, 2026-09-30): pieces per smart-freezer slot and
 * per smart-chiller channel, as set on Product → Edit. The page reads both off the index payload.
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
}
