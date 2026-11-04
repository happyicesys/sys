<?php

namespace Tests\Feature;

use App\Models\CityboxProduct;
use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\ZijiaLibrarySku;
use App\Models\ZijiaSkuApplication;
use App\Services\Product\ProductAiPhotos;
use App\Services\SmartFreezer\Zijia\ZijiaBarcodeSync;
use App\Services\SmartFreezer\Zijia\ZijiaLibraryImport;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

/**
 * Products index: the Smart Freezer / Smart Chiller AI Photo columns (Brian, 2026-11-04), so an
 * operator sees a SKU that was missed.
 */
class ProductAiPhotosTest extends TestCase
{
    use RefreshDatabase;

    private int $operatorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operatorId = Operator::firstOrCreate(['code' => 'OP1'], ['name' => 'Test Operator'])->id;
    }

    private function product(string $code, ?string $barcode = null): Product
    {
        return Product::withoutGlobalScopes()->forceCreate([
            'code' => $code, 'name' => "Product {$code}", 'operator_id' => $this->operatorId, 'barcode' => $barcode, 'is_active' => true,
        ]);
    }

    private function onPlanogram(Product $product, string $machineType, int $machineCode, bool $active = true): void
    {
        $vendId = DB::table('vends')->insertGetId(['code' => $machineCode, 'machine_type' => $machineType, 'operator_id' => $this->operatorId]);
        DB::table('vend_channels')->insert(['vend_id' => $vendId, 'code' => 11, 'product_id' => $product->id, 'amount' => 100, 'is_active' => $active]);
    }

    private function application(Product $product, string $status, string $photo = 'https://spaces/pack.png', array $pics = ['high' => ['https://spaces/1.png', 'https://spaces/2.png'], 'low' => []]): ZijiaSkuApplication
    {
        return ZijiaSkuApplication::query()->forceCreate([
            'product_id' => $product->id, 'application_no' => uniqid('no-'), 'status' => $status, 'source' => 'mark1',
            'package_image_url' => $photo, 'model_pics' => $pics,
        ]);
    }

    private function catalogue(Product $product, array $fields = []): CityboxProduct
    {
        return CityboxProduct::query()->create($fields + [
            'citybox_product_id' => random_int(1000, 999999), 'product_id' => $product->id, 'name' => $product->name,
            'img_url' => 'https://cdn/main.jpg', 'vision_imgs' => ['https://cdn/a.jpg', 'https://cdn/b.jpg', 'https://cdn/c.jpg'], 'citybox_status' => 1,
        ]);
    }

    private function cells(Product $product): array
    {
        return app(ProductAiPhotos::class)->forProducts(collect([$product->fresh()]))[$product->id];
    }

    public function test_an_approved_listed_product_shows_its_package_photo_and_photo_count(): void
    {
        $product = $this->product('A', '111');
        $this->application($product, 'approved');

        $cell = $this->cells($product)['freezer'];

        $this->assertSame(['has_ai' => true, 'photo_url' => 'https://spaces/pack.png', 'photo_count' => 2, 'note' => null, 'missing' => false], $cell);
    }

    public function test_a_freezer_product_the_ai_does_not_have_is_missing_and_one_on_no_freezer_is_not(): void
    {
        $onFreezer = $this->product('B');
        $this->onPlanogram($onFreezer, Vend::MACHINE_TYPE_SMART_FREEZER, 50001);
        $elsewhere = $this->product('C');

        $this->assertTrue($this->cells($onFreezer)['freezer']['missing']);
        $this->assertFalse($this->cells($elsewhere)['freezer']['missing']);
        $this->assertFalse($this->cells($elsewhere)['freezer']['has_ai']);
        $this->assertFalse($this->cells($onFreezer)['chiller']['missing'], 'a freezer planogram says nothing about chillers');
    }

    public function test_a_retired_channel_does_not_make_a_product_missing(): void
    {
        $product = $this->product('D');
        $this->onPlanogram($product, Vend::MACHINE_TYPE_SMART_CHILLER, 6003, false);

        $this->assertFalse($this->cells($product)['chiller']['missing']);
    }

    public function test_a_barcode_their_library_did_not_list_at_the_last_run_is_not_on_the_list(): void
    {
        $product = $this->product('E', '222');
        $this->onPlanogram($product, Vend::MACHINE_TYPE_SMART_FREEZER, 50001);
        $sync = Mockery::mock(ZijiaBarcodeSync::class);
        $sync->shouldReceive('barcodesNotInLibrary')->andReturn([$product->id]);
        $this->app->instance(ZijiaBarcodeSync::class, $sync);

        $cell = $this->cells($product)['freezer'];

        $this->assertFalse($cell['has_ai']);
        $this->assertTrue($cell['missing']);
    }

    public function test_a_photo_waiting_for_approval_is_not_shown_as_in_the_ai(): void
    {
        $product = $this->product('F');
        $this->application($product, 'submitted');

        $cell = $this->cells($product)['freezer'];

        $this->assertFalse($cell['has_ai']);
        $this->assertSame('submitted', $cell['note']);
        $this->assertSame('https://spaces/pack.png', $cell['photo_url']);
    }

    public function test_a_new_draft_does_not_hide_the_approved_photo(): void
    {
        $product = $this->product('G', '333');
        $this->application($product, 'approved', 'https://spaces/approved.png');
        $this->application($product, 'draft', 'https://spaces/draft.png');

        $cell = $this->cells($product)['freezer'];

        $this->assertSame('https://spaces/approved.png', $cell['photo_url']);
        $this->assertNull($cell['note']);
    }

    public function test_the_chiller_cell_reads_the_linked_catalogue_row(): void
    {
        $product = $this->product('H');
        $this->catalogue($product);

        $this->assertSame(['has_ai' => true, 'photo_url' => 'https://cdn/main.jpg', 'photo_count' => 3, 'note' => null, 'missing' => false], $this->cells($product)['chiller']);
    }

    public function test_a_sku_switched_off_in_citybox_says_so(): void
    {
        $product = $this->product('I');
        $this->catalogue($product, ['citybox_status' => 0]);

        $this->assertSame('disabled', $this->cells($product)['chiller']['note']);
    }

    public function test_a_chiller_product_with_no_catalogue_row_is_missing(): void
    {
        $product = $this->product('J');
        $this->onPlanogram($product, Vend::MACHINE_TYPE_SMART_CHILLER, 6003);

        $cell = $this->cells($product)['chiller'];

        $this->assertTrue($cell['missing']);
        $this->assertNull($cell['photo_url']);
    }

    public function test_the_index_page_carries_the_cells_and_no_other_page_does(): void
    {
        $this->seed(RolePermissionSyncSeeder::class);
        $user = User::factory()->create(['operator_id' => $this->operatorId]);
        $user->assignRole('superadmin');
        $product = $this->product('K', '444');
        $this->application($product, 'approved');

        $this->actingAs($user)->get('/products?operator_id='.$this->operatorId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Product/Index')
                ->where('products.data.0.ai_photos.freezer.photo_url', 'https://spaces/pack.png')
                ->where('products.data.0.ai_photos.chiller.has_ai', false));

        $this->assertArrayNotHasKey('ai_photos', (new \App\Http\Resources\ProductResource($product))->resolve(request()));
    }

    public function test_a_barcoded_product_on_no_freezer_is_imported_when_their_library_lists_it(): void
    {
        $inLibrary = $this->product('L', '555');
        $notInLibrary = $this->product('M', '8888002087005');
        ZijiaLibrarySku::query()->create(['sys_sku_id' => '1789000000000001', 'product_code' => '555', 'sku_name' => 'L', 'name_key' => 'l']);
        $import = Mockery::mock(ZijiaLibraryImport::class);
        $import->shouldReceive('sync')->once()->withArgs(fn (Product $p) => $p->id === $inLibrary->id)->andReturn(ZijiaLibraryImport::OUTCOME_IMPORTED);
        $this->app->instance(ZijiaLibraryImport::class, $import);

        $results = app(ZijiaBarcodeSync::class)->importApproved();

        $this->assertArrayHasKey($inLibrary->id, $results);
        $this->assertArrayNotHasKey($notInLibrary->id, $results);
        $this->assertSame([], app(ZijiaBarcodeSync::class)->barcodesNotInLibrary());
        Cache::flush();
    }
}
