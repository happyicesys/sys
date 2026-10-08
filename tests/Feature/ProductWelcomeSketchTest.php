<?php

namespace Tests\Feature;

use App\Jobs\GenerateProductWelcomeSketch;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\ProductWelcomeSketch;
use App\Models\User;
use App\Models\Vend;
use App\Services\Products\WelcomeSketch\CutoutMaker;
use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use App\Services\Products\WelcomeSketch\SketchGenerationException;
use App\Services\Products\WelcomeSketch\SketchGenerator;
use Database\Seeders\ProductWelcomeSketchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Freezer welcome sketches (2026-10-08): seeded approved art, automatic drawing on product save,
 * Regenerate / Upload on Product → Edit, the hourly freezer sweep, and `welcome_sketch` on /menu.
 */
class ProductWelcomeSketchTest extends TestCase
{
    use RefreshDatabase;

    private FakeSketchGenerator $generator;

    private FakeCutoutMaker $cutouts;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        Queue::fake();
        $this->generator = new FakeSketchGenerator;
        $this->app->instance(SketchGenerator::class, $this->generator);
        $this->cutouts = new FakeCutoutMaker;
        $this->app->instance(CutoutMaker::class, $this->cutouts);
        Http::fake(fn () => Http::response(self::png(800, 600), 200, ['Content-Type' => 'image/png']));

        $this->user = User::factory()->create(['operator_id' => 1]);
        foreach (['read products', 'update products'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
    }

    /** A PNG with a transparent margin around an opaque block. */
    public static function png(int $w = 1024, int $h = 1024): string
    {
        $image = new Imagick;
        $image->newImage($w, $h, new ImagickPixel('transparent'));
        $image->setImageFormat('png');
        $draw = new \ImagickDraw;
        $draw->setFillColor(new ImagickPixel('#c0392b'));
        $draw->rectangle((int) ($w * 0.3), (int) ($h * 0.1), (int) ($w * 0.7), (int) ($h * 0.9));
        $image->drawImage($draw);

        return $image->getImageBlob();
    }

    private function product(string $code, ?string $photo = 'https://cdn.test/sys/products/p.png'): Product
    {
        $product = Product::forceCreate(['code' => $code, 'name' => "Product {$code}", 'operator_id' => 1]);
        if ($photo !== null) {
            $product->thumbnail()->create(['type' => 1, 'full_url' => $photo, 'local_url' => $photo]);
        }

        return $product;
    }

    private function onFreezer(Product ...$products): Vend
    {
        $mapping = ProductMapping::create([
            'name' => 'Freezer', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_smart' => true, 'is_active' => true, 'operator_id' => 1,
        ]);
        $vend = new Vend;
        $vend->forceFill(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'product_mapping_id' => $mapping->id])->save();
        foreach ($products as $i => $product) {
            ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => (string) (11 + $i * 10), 'product_id' => $product->id]);
            DB::table('vend_channels')->insert(['vend_id' => $vend->id, 'code' => 11 + $i * 10, 'product_id' => $product->id, 'amount' => 100]);
        }

        return $vend;
    }

    private function service(): ProductWelcomeSketchService
    {
        return app(ProductWelcomeSketchService::class);
    }

    private function runQueued(): void
    {
        Queue::assertPushed(GenerateProductWelcomeSketch::class, function (GenerateProductWelcomeSketch $job) {
            $this->service()->generate($job->sketchId);

            return true;
        });
    }

    public function test_the_approved_set_is_stored_on_its_products_once_and_never_over_an_upload(): void
    {
        $cornetto = $this->product('U-01');
        $otherCookies = $this->product('U-79');
        $freezerCookies = $this->product('U-79');
        $this->onFreezer($freezerCookies);

        $this->seed(ProductWelcomeSketchSeeder::class);

        $sketch = ProductWelcomeSketch::query()->where('product_id', $cornetto->id)->sole();
        $this->assertSame([ProductWelcomeSketch::STATUS_READY, ProductWelcomeSketch::SOURCE_SEED], [$sketch->status, $sketch->source]);
        Storage::assertExists($sketch->path);
        // U-79 has two products: the one on a freezer gets the drawing.
        $this->assertNotNull($freezerCookies->welcomeSketch()->first());
        $this->assertNull($otherCookies->welcomeSketch()->first());

        $before = $sketch->updated_at;
        $this->travel(5)->minutes();
        $this->seed(ProductWelcomeSketchSeeder::class);
        $this->assertTrue($sketch->fresh()->updated_at->equalTo($before), 'an unchanged file is not stored again');

        $this->service()->storeUpload($cornetto, UploadedFile::fake()->createWithContent('mine.png', self::png(300, 300)), $this->user->id);
        $this->seed(ProductWelcomeSketchSeeder::class);
        $this->assertSame(ProductWelcomeSketch::SOURCE_UPLOAD, $sketch->fresh()->source);
        $this->assertSame(0, $this->generator->calls, 'seeding never calls the image service');
    }

    public function test_saving_a_new_photo_draws_a_trimmed_transparent_sketch_once(): void
    {
        $product = $this->product('NEW-1');

        $sketch = $this->service()->requestAfterSave($product, $this->user->id, 'created');
        $this->assertSame(ProductWelcomeSketch::STATUS_PENDING, $sketch->status);
        $this->runQueued();

        $sketch->refresh();
        $this->assertSame([ProductWelcomeSketch::STATUS_READY, ProductWelcomeSketch::SOURCE_GENERATED, 'fake-model'],
            [$sketch->status, $sketch->source, $sketch->model]);
        $this->assertSame('https://cdn.test/sys/products/p.png', $sketch->source_photo_url);
        $image = new Imagick;
        $image->readImageBlob(Storage::get($sketch->path));
        $this->assertSame('WEBP', $image->getImageFormat());
        $this->assertLessThanOrEqual(512, max($image->getImageWidth(), $image->getImageHeight()));
        $this->assertLessThan($image->getImageHeight(), $image->getImageWidth(), 'the transparent margin was trimmed');
        $this->assertSame(3, $this->generator->lastReferences, 'approved sketches sent as the style');

        // A redelivered job and a second save of the same photo cost nothing.
        $this->service()->generate($sketch->id);
        $this->assertNull($this->service()->requestAfterSave($product, null));
        $this->assertSame(1, $this->generator->calls);
    }

    public function test_a_failure_keeps_the_previous_drawing_and_is_not_retried_for_the_same_photo(): void
    {
        $product = $this->product('NEW-2');
        $this->service()->requestAfterSave($product, null);
        $this->runQueued();
        $url = $product->welcomeSketch()->first()->url;

        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);
        $this->generator->fail = 'content policy';
        $sketch = $this->service()->requestAfterSave($product->fresh(), null, 'photo_saved');
        $this->service()->generate($sketch->id);

        $sketch->refresh();
        $this->assertSame(ProductWelcomeSketch::STATUS_FAILED, $sketch->status);
        $this->assertStringContainsString('content policy', $sketch->last_error);
        $this->assertSame($url, $sketch->url, 'the freezer keeps the previous drawing');
        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null), 'the failed photo is not re-paid on every save');
    }

    public function test_approved_and_uploaded_art_is_never_redrawn_automatically_but_regenerate_does(): void
    {
        $product = $this->product('U-01');
        $this->service()->seed($product, self::png(400, 400));
        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);

        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null));
        Queue::assertNothingPushed();

        $this->service()->regenerate($product->fresh(), $this->user->id);
        $this->runQueued();
        $this->assertSame(ProductWelcomeSketch::SOURCE_GENERATED, $product->welcomeSketch()->first()->source);
    }

    public function test_nothing_is_queued_without_an_image_service_or_a_photo(): void
    {
        $this->generator->configured = false;
        $this->assertNull($this->service()->requestAfterSave($this->product('NEW-3'), null));
        $this->generator->configured = true;
        $this->assertNull($this->service()->requestAfterSave($this->product('NEW-4', null), null));
        Queue::assertNothingPushed();

        $this->generator->configured = false;
        $this->expectException(SketchGenerationException::class);
        $this->service()->regenerate($this->product('NEW-5'), null);
    }

    public function test_an_abandoned_drawing_does_not_block_a_new_request(): void
    {
        $product = $this->product('NEW-6');
        $sketch = $this->service()->requestAfterSave($product, null);
        ProductWelcomeSketch::query()->whereKey($sketch->id)->update(['status' => ProductWelcomeSketch::STATUS_GENERATING]);
        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);

        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null), 'still being drawn');
        $this->travel(16)->minutes();
        $this->assertNotNull($this->service()->requestAfterSave($product->fresh(), null), 'a worker killed mid-call is not waited on forever');
    }

    public function test_product_edit_queues_a_drawing_only_for_a_new_photo_or_a_freezer_product_without_one(): void
    {
        $vending = $this->product('V-1');
        $this->actingAs($this->user)->post("/products/{$vending->id}/update", ['code' => 'V-1', 'name' => 'Vending', 'operator_id' => 1]);
        Queue::assertNothingPushed();

        $freezer = $this->product('F-1');
        $this->onFreezer($freezer);
        $this->actingAs($this->user)->post("/products/{$freezer->id}/update", ['code' => 'F-1', 'name' => 'Freezer item', 'operator_id' => 1]);
        Queue::assertPushed(GenerateProductWelcomeSketch::class, 1);
        $this->assertSame('freezer_product', $freezer->welcomeSketch()->first()->reason);
    }

    public function test_regenerate_and_upload_from_product_edit(): void
    {
        $product = $this->product('E-1');

        $this->actingAs($this->user)->post("/products/{$product->id}/welcome-sketch/regenerate")->assertSessionHasNoErrors();
        Queue::assertPushed(GenerateProductWelcomeSketch::class, 1);

        $this->actingAs($this->user)->post("/products/{$product->id}/welcome-sketch", [
            'welcome_sketch' => UploadedFile::fake()->createWithContent('mine.png', self::png(300, 300)),
        ])->assertSessionHasNoErrors();
        $this->assertSame(ProductWelcomeSketch::SOURCE_UPLOAD, $product->welcomeSketch()->first()->source);

        $this->actingAs($this->user)->post("/products/{$product->id}/welcome-sketch", [
            'welcome_sketch' => UploadedFile::fake()->create('mine.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('welcome_sketch');

        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo(Permission::findOrCreate('read products', 'web'));
        $this->actingAs($viewer)->post("/products/{$product->id}/welcome-sketch/regenerate")->assertForbidden();
    }

    public function test_the_hourly_sweep_draws_freezer_products_that_have_none(): void
    {
        $drawn = $this->product('S-1');
        $this->service()->seed($drawn, self::png(300, 300));
        $missing = $this->product('S-2');
        $noPhoto = $this->product('S-3', null);
        $vendingOnly = $this->product('S-4');
        $this->onFreezer($drawn, $missing, $noPhoto);

        $this->artisan('products:welcome-sketches --missing-freezer')->expectsOutputToContain("Queued: {$missing->id}")->assertSuccessful();
        Queue::assertPushed(GenerateProductWelcomeSketch::class, 1);
        $this->assertNull($vendingOnly->welcomeSketch()->first());
    }

    public function test_without_an_image_model_a_photo_cut_out_sticker_is_made_and_upgraded_later(): void
    {
        $this->generator->configured = false;
        $this->cutouts->available = true;
        $product = $this->product('C-1');

        $this->service()->requestAfterSave($product, null, 'created');
        $this->runQueued();

        $sketch = $product->welcomeSketch()->first();
        $this->assertSame([ProductWelcomeSketch::STATUS_READY, ProductWelcomeSketch::SOURCE_CUTOUT, 'rembg:fake', null],
            [$sketch->status, $sketch->source, $sketch->model, $sketch->last_error]);
        $this->assertSame(0, $this->generator->calls);
        $image = new Imagick;
        $image->readImageBlob(Storage::get($sketch->path));
        $this->assertSame('WEBP', $image->getImageFormat());
        // The white sticker outline sits just outside the product's edge.
        $this->assertTrue(self::hasWhiteOutline($image), 'finished with a white outline');

        // Same photo, still no model: nothing more to do.
        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null));
        // A key is added: the cut-out is upgraded to a real sketch on the next save / sweep.
        $this->generator->configured = true;
        $this->artisan('products:welcome-sketches --missing-freezer')->assertSuccessful(); // not on a freezer: untouched
        $this->assertNotNull($this->service()->requestAfterSave($product->fresh(), null));
        $this->service()->generate($sketch->id);
        $this->assertSame(ProductWelcomeSketch::SOURCE_GENERATED, $sketch->fresh()->source);
    }

    public function test_a_failed_drawing_falls_back_to_the_cut_out_and_says_why(): void
    {
        $this->cutouts->available = true;
        $this->generator->fail = 'rate limited';
        $product = $this->product('C-2');

        $this->service()->requestAfterSave($product, null);
        $this->runQueued();

        $sketch = $product->welcomeSketch()->first();
        $this->assertSame([ProductWelcomeSketch::STATUS_READY, ProductWelcomeSketch::SOURCE_CUTOUT], [$sketch->status, $sketch->source]);
        $this->assertStringContainsString('rate limited', $sketch->last_error);
        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null), 'a model failure on this photo is not re-paid on every save');
    }

    private static function hasWhiteOutline(Imagick $image): bool
    {
        $w = $image->getImageWidth();
        $h = $image->getImageHeight();
        // Walk in from the left edge on the middle row: the first opaque pixel is the white outline.
        for ($x = 0; $x < $w; $x++) {
            $px = $image->getImagePixelColor($x, intdiv($h, 2));
            if ($px->getColorValue(Imagick::COLOR_ALPHA) > 0.9) {
                return $px->getColorValue(Imagick::COLOR_RED) > 0.94
                    && $px->getColorValue(Imagick::COLOR_GREEN) > 0.94
                    && $px->getColorValue(Imagick::COLOR_BLUE) > 0.94;
            }
        }

        return false;
    }

    public function test_the_freezer_menu_carries_the_sketch_and_vending_menus_are_unchanged(): void
    {
        $drawn = $this->product('M-1');
        $plain = $this->product('M-2');
        $this->service()->seed($drawn, self::png(300, 300));
        $vend = $this->onFreezer($drawn, $plain);

        $rows = collect($this->getJson("/api/vends/{$vend->code}/menu")->assertOk()->json())->keyBy('product_code');
        $this->assertStringContainsString('/welcome-sketch/', $rows['M-1']['welcome_sketch']);
        $this->assertStringContainsString('?v=', $rows['M-1']['welcome_sketch'], 'versioned so a redraw busts the freezer cache');
        $this->assertNull($rows['M-2']['welcome_sketch'], 'no drawing: the freezer drops the photo');

        ProductMapping::query()->update(['is_smart' => false]);
        $row = $this->getJson("/api/vends/{$vend->code}/menu")->json()[0];
        $this->assertArrayNotHasKey('welcome_sketch', $row);
    }
}

class FakeSketchGenerator implements SketchGenerator
{
    public bool $configured = true;

    public int $calls = 0;

    public int $lastReferences = 0;

    public ?string $fail = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function generate(string $photo, string $productName, array $styleReferences): string
    {
        $this->calls++;
        $this->lastReferences = count($styleReferences);
        if ($this->fail !== null) {
            throw new SketchGenerationException($this->fail);
        }

        return ProductWelcomeSketchTest::png();
    }
}

class FakeCutoutMaker implements CutoutMaker
{
    public bool $available = false;

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function name(): string
    {
        return 'rembg:fake';
    }

    public function cutout(string $photo): string
    {
        return ProductWelcomeSketchTest::png(600, 800);
    }
}
