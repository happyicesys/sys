<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxPriceGroup;
use App\Models\CityboxProduct;
use App\Models\Operator;
use App\Models\Product;
use App\Models\SellingPrice;
use App\Models\User;
use App\Services\Citybox\CityboxPriceGroupSync;
use App\Services\Product\SellingPriceHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * CityBox price groups (price_group_list) are mirrored, and a group named RP1–RP5 becomes that RP
 * on each CityBox-owned product — read-only in mark1 (Brian, 2026-10-13).
 */
class CityboxPriceGroupSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private Product $owned;

    private Product $handMapped;

    protected function setUp(): void
    {
        parent::setUp();
        Model::reguard();
        config(['citybox.openapi.enabled' => true]);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        $operator = Operator::create(['code' => 'CB', 'name' => 'Citybox', 'timezone' => 'Asia/Singapore', 'is_active' => true]);

        // Their catalog created it: code IS their id.
        $this->owned = Product::create(['code' => '90271', 'name' => 'MetaVita Tea', 'operator_id' => $operator->id]);
        CityboxProduct::create(['citybox_product_id' => 90271, 'name' => 'MetaVita Tea', 'first_seen_at' => now(), 'product_id' => $this->owned->id]);
        // A person mapped our own product to their SKU: keeps its own prices.
        $this->handMapped = Product::create(['code' => 'HI-RB', 'name' => 'Red Bull', 'operator_id' => $operator->id]);
        CityboxProduct::create(['citybox_product_id' => 90420, 'name' => 'Red Bull can', 'first_seen_at' => now(), 'product_id' => $this->handMapped->id]);
        DB::table('selling_prices')->insert(['product_id' => $this->handMapped->id, 'type' => 1, 'amount' => 250, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function group(int $id, string $name, int $product, string $price): array
    {
        return ['group_id' => (string) $id, 'group_name' => $name, 'price' => $price, 'status' => '1', 'product_id' => (string) $product, 'product_name' => 'P', 'img_url' => 'https://cdn/x.jpg'];
    }

    private function rp(Product $product, int $type): ?int
    {
        $v = DB::table('selling_prices')->where('product_id', $product->id)->where('type', $type)->value('amount');

        return $v === null ? null : (int) $v;
    }

    public function test_rp_groups_become_selling_prices_on_owned_products_only(): void
    {
        $this->gw->priceGroupRows = [
            $this->group(4, 'RP1', 90271, '1.70'),
            $this->group(4, 'RP1', 90420, '0.10'),
            $this->group(7, 'Promo Oct', 90271, '1.20'),
        ];

        $stats = app(CityboxPriceGroupSync::class)->sync();

        $this->assertSame(3, CityboxPriceGroup::count(), 'every row mirrored, whatever the group is called');
        $this->assertSame(170, $this->rp($this->owned, 1));
        $this->assertNull($this->rp($this->owned, 2), 'a group not named RP1–RP5 is no tier');
        $this->assertSame(250, $this->rp($this->handMapped, 1), 'a hand-mapped product keeps its own price');
        $this->assertSame(1, $stats['added']);
        $row = SellingPrice::where('product_id', $this->owned->id)->sole();
        $this->assertNull($row->created_by);
    }

    public function test_a_price_change_is_a_delete_and_add_with_history(): void
    {
        $this->gw->priceGroupRows = [$this->group(4, 'RP1', 90271, '1.70')];
        app(CityboxPriceGroupSync::class)->sync();
        $this->gw->priceGroupRows = [$this->group(4, 'RP1', 90271, '1.90')];
        $stats = app(CityboxPriceGroupSync::class)->sync();

        $this->assertSame(1, $stats['changed']);
        $row = SellingPrice::where('product_id', $this->owned->id)->sole();
        $this->assertSame([190, 170], [(int) $row->getRawOriginal('amount'), (int) $row->previous_amount]);
        $history = app(SellingPriceHistory::class)->forProduct($this->owned);
        $this->assertSame(['changed', 170, 190, 'CityBox price group'], [$history[0]['kind'], $history[0]['from'], $history[0]['to'], $history[0]['by']]);

        // Unchanged → nothing written.
        $this->assertSame(0, app(CityboxPriceGroupSync::class)->sync()['changed']);
    }

    public function test_a_product_leaving_the_group_loses_the_rp_and_an_empty_answer_changes_nothing(): void
    {
        $this->gw->priceGroupRows = [$this->group(4, 'RP1', 90271, '1.70')];
        app(CityboxPriceGroupSync::class)->sync();

        $this->gw->priceGroupRows = [];
        app(CityboxPriceGroupSync::class)->sync();
        $this->assertSame(170, $this->rp($this->owned, 1), 'an empty list is never read as "all groups gone"');

        $this->gw->priceGroupRows = [$this->group(9, 'RP2', 90420, '2.00')];
        app(CityboxPriceGroupSync::class)->sync();
        $this->assertNull($this->rp($this->owned, 1));
        $this->assertNotNull(CityboxPriceGroup::where('group_id', 4)->value('removed_at'));
    }

    public function test_rp_name_variants(): void
    {
        $this->assertSame(1, CityboxPriceGroupSync::rpType('RP1'));
        $this->assertSame(3, CityboxPriceGroupSync::rpType(' rp-3 '));
        $this->assertNull(CityboxPriceGroupSync::rpType('RP6'));
        $this->assertNull(CityboxPriceGroupSync::rpType('$0.10'));
    }

    public function test_the_edit_page_shows_the_groups_and_a_synced_rp_cannot_be_deleted_by_hand(): void
    {
        foreach (['read products', 'update products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['read products', 'update products']);
        $this->gw->priceGroupRows = [$this->group(4, 'RP1', 90271, '1.70'), $this->group(7, 'Promo Oct', 90271, '1.20')];
        app(CityboxPriceGroupSync::class)->sync();

        $this->actingAs($user)->get("/products/{$this->owned->id}/edit")
            ->assertInertia(fn ($page) => $page->has('cityboxPriceGroups', 2)
                ->where('cityboxPriceGroups.0.group_name', 'Promo Oct')
                ->where('cityboxPriceGroups.1.type', 1));

        $row = SellingPrice::where('product_id', $this->owned->id)->sole();
        $this->actingAs($user)->delete("/products/selling-prices/{$row->id}")->assertSessionHasErrors('selling_price');
        $this->assertNotNull(SellingPrice::find($row->id));
    }
}
