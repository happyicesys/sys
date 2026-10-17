<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Audit SF-01 (2026-10-17): the welcome-sketch and AI-training routes reach a product by id, so they
 * must load it through Product's global scopes like ProductController does. A user restricted to
 * another operator gets 404 and nothing is drawn, uploaded or submitted.
 */
class ProductSketchTrainingOperatorScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $otherOperatorUser;

    private Product $hiplProduct;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        Queue::fake();
        Http::preventStrayRequests();

        $this->otherOperatorUser = User::factory()->create(['operator_id' => 2]);
        foreach (['read products', 'update products'] as $p) {
            $this->otherOperatorUser->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->hiplProduct = Product::forceCreate(['code' => 'CC-01', 'name' => 'HIPL Cheesecake', 'operator_id' => 1]);
        $this->hiplProduct->thumbnail()->create(['type' => 1, 'full_url' => 'https://cdn.test/p.png', 'local_url' => 'x']);
    }

    public function test_another_operators_user_cannot_reach_a_hipl_products_welcome_sketch(): void
    {
        $id = $this->hiplProduct->id;
        $this->actingAs($this->otherOperatorUser);

        $this->getJson("/products/{$id}/welcome-sketch")->assertNotFound();
        $this->postJson("/products/{$id}/welcome-sketch/regenerate", ['count' => 2])->assertNotFound();
        $this->postJson("/products/{$id}/welcome-sketch/versions/1/select")->assertNotFound();
        $this->deleteJson("/products/{$id}/welcome-sketch/versions/1")->assertNotFound();

        $this->assertSame(0, DB::table('product_welcome_sketch_versions')->count());
        $this->assertSame(0, DB::table('product_welcome_sketches')->count());
    }

    public function test_another_operators_user_cannot_draft_or_submit_a_hipl_products_ai_training(): void
    {
        $id = $this->hiplProduct->id;
        $this->actingAs($this->otherOperatorUser);

        $this->post("/products/{$id}/ai-training", ['sku_name' => 'Hijacked'])->assertNotFound();
        $this->post("/products/{$id}/ai-training/submit")->assertNotFound();

        $this->assertSame(0, DB::table('zijia_sku_applications')->count());
    }

    public function test_the_products_own_operator_still_reaches_it(): void
    {
        $own = User::factory()->create(['operator_id' => 1]);
        foreach (['read products', 'update products'] as $p) {
            $own->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        $this->actingAs($own)->getJson("/products/{$this->hiplProduct->id}/welcome-sketch")->assertOk();
    }
}
