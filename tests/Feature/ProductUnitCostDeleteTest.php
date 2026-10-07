<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\SellingPrice;
use App\Models\UnitCost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Product -> Edit -> Unit Cost "remove" posted to a route that did not exist,
 * so every click 404'd (EATZ, 2026-10-07: rows 306/307, a price typed into
 * Unit Cost by mistake). The route now exists, and the delete is fenced:
 * own products only, never a cost a recorded sale already carries, and the
 * product keeps a current cost when the current one goes.
 */
class ProductUnitCostDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();
        Model::reguard();
        Permission::findOrCreate('read products', 'web');

        // HappyIce is unrestricted by id (1), and auto-increment does not
        // reset between tests, so pin it.
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->hipl = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $this->opA = Operator::withoutGlobalScopes()->create([
            'code' => 'OPA', 'name' => 'OPA', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => true,
        ]);
        $this->opB = Operator::withoutGlobalScopes()->create([
            'code' => 'OPB', 'name' => 'OPB', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => true,
        ]);
    }

    private function userFor(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo('read products');

        return $user;
    }

    private function product(Operator $operator, string $code): Product
    {
        return Product::withoutGlobalScopes()->create([
            'code' => $code, 'name' => $code, 'operator_id' => $operator->id,
        ]);
    }

    private function cost(Product $product, float $cost, string $dateFrom, bool $current, string $createdAt): UnitCost
    {
        $row = UnitCost::create([
            'product_id' => $product->id,
            'cost' => $cost,
            'date_from' => $dateFrom,
            'is_current' => $current,
        ]);
        DB::table('unit_costs')->where('id', $row->id)->update(['created_at' => $createdAt]);

        return $row;
    }

    public function test_owner_deletes_their_unit_cost_and_the_previous_one_becomes_current(): void
    {
        $product = $this->product($this->opA, 'A-001');
        $old = $this->cost($product, 3.10, now()->subMonth()->toDateString(), false, now()->subMonth()->toDateTimeString());
        $mistake = $this->cost($product, 7.90, now()->toDateString(), true, now()->toDateTimeString());

        $this->actingAs($this->userFor($this->opA))
            ->delete("/products/unit-costs/{$mistake->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseMissing('unit_costs', ['id' => $mistake->id]);
        $this->assertTrue((bool) UnitCost::find($old->id)->is_current);
    }

    public function test_deleting_the_only_cost_leaves_none(): void
    {
        $product = $this->product($this->opA, 'A-001');
        $only = $this->cost($product, 7.90, now()->toDateString(), true, now()->toDateTimeString());

        $this->actingAs($this->userFor($this->opA))
            ->delete("/products/unit-costs/{$only->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame(0, UnitCost::where('product_id', $product->id)->count());
    }

    public function test_a_cost_already_on_a_sale_is_kept(): void
    {
        $product = $this->product($this->opA, 'A-001');
        $cost = $this->cost($product, 3.10, now()->toDateString(), true, now()->toDateTimeString());
        DB::table('vend_transactions')->insert([
            'product_id' => $product->id,
            'unit_cost_id' => $cost->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->userFor($this->opA))
            ->delete("/products/unit-costs/{$cost->id}")
            ->assertSessionHasErrors('unit_cost');

        $this->assertDatabaseHas('unit_costs', ['id' => $cost->id]);
    }

    public function test_operator_cannot_delete_another_operators_unit_cost(): void
    {
        $product = $this->product($this->opB, 'B-001');
        $cost = $this->cost($product, 3.10, now()->toDateString(), true, now()->toDateTimeString());

        $this->actingAs($this->userFor($this->opA))
            ->delete("/products/unit-costs/{$cost->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('unit_costs', ['id' => $cost->id]);
    }

    public function test_operator_cannot_delete_another_operators_selling_price(): void
    {
        $product = $this->product($this->opB, 'B-001');
        $price = SellingPrice::create(['product_id' => $product->id, 'amount' => 500, 'type' => 2]);

        $this->actingAs($this->userFor($this->opA))
            ->delete("/products/selling-prices/{$price->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('selling_prices', ['id' => $price->id]);
    }

    public function test_happyice_can_delete_any_unit_cost(): void
    {
        $product = $this->product($this->opB, 'B-001');
        $cost = $this->cost($product, 3.10, now()->toDateString(), true, now()->toDateTimeString());

        $this->actingAs($this->userFor($this->hipl))
            ->delete("/products/unit-costs/{$cost->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('unit_costs', ['id' => $cost->id]);
    }
}
