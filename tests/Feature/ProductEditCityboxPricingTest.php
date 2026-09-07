<?php

namespace Tests\Feature;

use App\Models\CityboxProduct;
use App\Models\Operator;
use App\Models\Product;
use App\Models\SellingPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A CityBox-owned SKU is priced by their portal: the chiller's channel amounts
 * come from their API (ChillerPlanogram / ChannelFrameAdapter), so a mark1
 * selling price on such a product would be a number nothing reads. The edit page
 * hides that block off `is_citybox_owned`, and the update endpoint refuses the
 * write. Unit cost is untouched by all of this — it is ours, and it drives GP.
 */
class ProductEditCityboxPricingTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        // Several suite files call Model::unguard() and never reguard; that static
        // leaks into whatever runs next, and here it would let the controller's
        // fill() write the request's `sellingPrices` / `unitCosts` arrays straight
        // onto products columns that do not exist. Production is guarded, so are we.
        Model::reguard();
        foreach (['read products', 'update products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->operator = Operator::create([
            'code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1,
            'timezone' => 'Asia/Singapore', 'is_active' => true,
        ]);
        (new \Database\Seeders\CityboxOperatorSeeder)->run();
    }

    private function editor(): User
    {
        // Unit-cost writes resolve the operator's timezone (GetUserTimezone).
        $u = User::factory()->create(['operator_id' => $this->operator->id]);
        $u->givePermissionTo(['read products', 'update products']);

        return $u;
    }

    /** Their catalog created it: products.code IS the citybox_product_id. */
    private function cityboxOwned(): Product
    {
        $product = Product::create(['code' => '90271', 'name' => 'MetaVita Tea', 'operator_id' => $this->operator->id]);
        CityboxProduct::create([
            'citybox_product_id' => 90271, 'name' => 'MetaVita Tea',
            'first_seen_at' => now(), 'product_id' => $product->id, 'mapped_at' => now(),
        ]);

        return $product;
    }

    public function test_edit_flags_a_citybox_owned_product(): void
    {
        $product = $this->cityboxOwned();

        $this->actingAs($this->editor())->get("/products/{$product->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->component('Product/Edit')
                ->where('product.data.is_citybox_owned', true));
    }

    public function test_edit_does_not_flag_a_human_mapping_or_an_ordinary_product(): void
    {
        // Hand-mapped to a CityBox SKU but keeping its own mark1 code — it may
        // also sell in vending machines, where the RP tier is real.
        $mapped = Product::create(['code' => 'CK-330', 'name' => 'Cocacola 330ml', 'operator_id' => $this->operator->id]);
        CityboxProduct::create([
            'citybox_product_id' => 89925, 'name' => 'Cocacola',
            'first_seen_at' => now(), 'product_id' => $mapped->id, 'mapped_at' => now(),
        ]);
        $plain = Product::create(['code' => 'MI-500', 'name' => 'Milo 500ml', 'operator_id' => $this->operator->id]);

        $editor = $this->editor();
        foreach ([$mapped, $plain] as $product) {
            $this->actingAs($editor)->get("/products/{$product->id}/edit")
                ->assertInertia(fn ($page) => $page->where('product.data.is_citybox_owned', false));
        }
    }

    public function test_update_refuses_a_selling_price_for_a_citybox_owned_product(): void
    {
        $product = $this->cityboxOwned();

        $this->actingAs($this->editor())->from("/products/{$product->id}/edit")
            ->post("/products/{$product->id}/update", [
                'code' => $product->code, 'name' => $product->name, 'operator_id' => $this->operator->id,
                'sellingPrices' => [['amount' => 250, 'type' => SellingPrice::TYPE_1]],
            ])->assertRedirect();

        $this->assertSame(0, $product->sellingPrices()->count());
    }

    public function test_update_still_accepts_a_selling_price_for_an_ordinary_product(): void
    {
        $product = Product::create(['code' => 'MI-500', 'name' => 'Milo 500ml', 'operator_id' => $this->operator->id]);

        $this->actingAs($this->editor())->from("/products/{$product->id}/edit")
            ->post("/products/{$product->id}/update", [
                'code' => $product->code, 'name' => $product->name, 'operator_id' => $this->operator->id,
                'sellingPrices' => [['amount' => 250, 'type' => SellingPrice::TYPE_1]],
            ])->assertRedirect();

        $this->assertSame(1, $product->sellingPrices()->count());
    }

    public function test_update_still_records_a_unit_cost_for_a_citybox_owned_product(): void
    {
        $product = $this->cityboxOwned();

        $this->actingAs($this->editor())->from("/products/{$product->id}/edit")
            ->post("/products/{$product->id}/update", [
                'code' => $product->code, 'name' => $product->name, 'operator_id' => $this->operator->id,
                'unitCosts' => [['cost' => 120, 'date_from' => now()->toDateString()]],
            ])->assertRedirect();

        $this->assertSame(1, $product->unitCosts()->count());
    }
}
