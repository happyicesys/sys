<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\CityboxProductSyncLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class CityboxProductScreenTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    protected function setUp(): void
    {
        parent::setUp();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        foreach (['read products', 'update products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        \App\Models\Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        (new \Database\Seeders\CityboxOperatorSeeder)->run();
    }

    private function editor(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(['read products', 'update products']);

        return $u;
    }

    private function reader(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo('read products');

        return $u;
    }

    public function test_index_requires_read_products(): void
    {
        $this->actingAs(User::factory()->create())->get('/citybox/products')->assertForbidden();
        $this->actingAs($this->reader())->get('/citybox/products')->assertOk();
    }

    public function test_index_shows_the_catalog_with_each_skus_mark1_product(): void
    {
        $product = Product::create(['code' => '89925', 'name' => 'Cocacola', 'operator_id' => 1]);
        CityboxProduct::create(['citybox_product_id' => 89925, 'name' => 'Cocacola', 'first_seen_at' => now(), 'product_id' => $product->id, 'mapped_at' => now()]);
        CityboxProduct::create(['citybox_product_id' => 90340, 'name' => 'Kang Shi Fu, Oolong Tea 康师傅', 'first_seen_at' => now()]);
        CityboxProduct::create(['citybox_product_id' => 90341, 'name' => 'Gone', 'first_seen_at' => now(), 'is_delisted' => true]);

        $this->actingAs($this->reader())->get('/citybox/products')
            ->assertInertia(fn ($page) => $page
                ->component('Citybox/Products')
                ->where('tab', 'catalog')
                ->where('counts.catalog', 2)
                ->where('counts.unlinked', 1)
                ->where('counts.delisted', 1)
                ->where('rows.0.citybox_product_id', 89925)
                ->where('rows.0.product.code', '89925')
                ->where('rows.1.product', null));

        $this->actingAs($this->reader())->get('/citybox/products?tab=delisted')
            ->assertInertia(fn ($page) => $page->where('tab', 'delisted')->where('rows.0.citybox_product_id', 90341));
    }

    public function test_map_requires_update_products_and_links_the_row(): void
    {
        $product = Product::create(['code' => 'CK-330', 'name' => 'Cocacola 330ml']);
        $row = CityboxProduct::create(['citybox_product_id' => 89925, 'name' => 'Cocacola', 'first_seen_at' => now()]);

        $this->actingAs($this->reader())->post("/citybox/products/{$row->id}/map", ['product_id' => $product->id])->assertForbidden();

        $editor = $this->editor();
        $this->actingAs($editor)->from('/citybox/products')->post("/citybox/products/{$row->id}/map", ['product_id' => $product->id])
            ->assertRedirect('/citybox/products')->assertSessionHas('success');

        $row->refresh();
        $this->assertSame($product->id, $row->product_id);
        $this->assertSame($editor->id, $row->mapped_by);
        $this->assertNotNull($row->mapped_at);

        // Unmap
        $this->actingAs($editor)->post("/citybox/products/{$row->id}/map", ['product_id' => null])->assertRedirect();
        $this->assertNull($row->fresh()->product_id);
    }

    public function test_map_rejects_nonexistent_product(): void
    {
        $row = CityboxProduct::create(['citybox_product_id' => 89925, 'name' => 'Cocacola', 'first_seen_at' => now()]);

        $this->actingAs($this->editor())->from('/citybox/products')
            ->post("/citybox/products/{$row->id}/map", ['product_id' => 999999])
            ->assertSessionHasErrors('product_id');
    }

    public function test_sync_now_runs_catalog_and_flashes_counts(): void
    {
        $this->gw->catalogRows = [
            ['id' => '89925', 'product_id' => '0', 'product_name' => 'Cocacola', 'img_url' => 'https://cdn/a.png', 'vision_img' => '', 'vision_img2' => '', 'vision_img3' => '', 'vision_img4' => ''],
        ];

        $this->actingAs($this->editor())->from('/citybox/products')->post('/citybox/products/sync')
            ->assertRedirect('/citybox/products')
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 new') && str_contains($m, '1 ConnectVend product(s) created'));

        $this->assertSame(CityboxProductSyncLog::SOURCE_CATALOG_MANUAL, CityboxProductSyncLog::sole()->source);
    }

    public function test_product_search_returns_matches(): void
    {
        Product::create(['code' => 'CK-330', 'name' => 'Cocacola 330ml']);
        Product::create(['code' => 'HB-1', 'name' => 'Haribo Goldbears']);

        $this->actingAs($this->reader())->getJson('/citybox/products/search?q=coca')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.code', 'CK-330');
    }
}
