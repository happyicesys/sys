<?php

namespace Tests\Feature;

use App\Jobs\GenerateProductWelcomeSketch;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\ProductWelcomeSketch;
use App\Models\ProductWelcomeSketchVersion;
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
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Freezer welcome sketches (2026-10-08) and their album (2026-10-12): seeded approved art, automatic
 * drawing on product save, Draw / Use / Star / Delete / Upload on Product → Edit, the hourly freezer
 * sweep, recovering earlier drawings from storage, and `welcome_sketch` on /menu.
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
            $this->service()->generate($job->versionId);

            return true;
        });
    }

    private function live(Product $product): ?ProductWelcomeSketch
    {
        return ProductWelcomeSketch::query()->where('product_id', $product->id)->first();
    }

    public function test_the_product_index_shows_the_chosen_sketch_as_artwork(): void
    {
        $drawn = $this->product('ART-1');
        $plain = $this->product('ART-2');
        Product::whereIn('id', [$drawn->id, $plain->id])->update(['is_active' => true, 'is_inventory' => true]);
        ProductWelcomeSketch::create([
            'product_id' => $drawn->id, 'status' => ProductWelcomeSketch::STATUS_READY, 'source' => ProductWelcomeSketch::SOURCE_UPLOAD,
            'url' => 'https://cdn.test/sys/products/1/welcome-sketch/a.webp', 'generated_at' => now(),
        ]);
        $expected = ProductWelcomeSketchService::menuUrl($this->live($drawn));

        $this->actingAs($this->user)->get('/products?code=ART&operator_id=all')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Product/Index')
                ->where('products.data', fn ($rows) => collect($rows)->pluck('welcome_sketch_url', 'code')->sortKeys()->all() === [
                    'ART-1' => $expected,
                    'ART-2' => null,
                ]));
        $this->assertStringContainsString('?v=', $expected);
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
        $this->assertSame($sketch->path, ProductWelcomeSketchVersion::query()->findOrFail($sketch->version_id)->path, 'the seed is in the album too');
        // U-79 has two products: the one on a freezer gets the drawing.
        $this->assertNotNull($freezerCookies->welcomeSketch()->first());
        $this->assertNull($otherCookies->welcomeSketch()->first());

        $before = $sketch->updated_at;
        $this->travel(5)->minutes();
        $this->seed(ProductWelcomeSketchSeeder::class);
        $this->assertTrue($sketch->fresh()->updated_at->equalTo($before), 'an unchanged file is not stored again');
        $this->assertSame(1, ProductWelcomeSketchVersion::query()->where('product_id', $cornetto->id)->count());

        $this->service()->storeUpload($cornetto, UploadedFile::fake()->createWithContent('mine.png', self::png(300, 300)), $this->user->id);
        $this->seed(ProductWelcomeSketchSeeder::class);
        $this->assertSame(ProductWelcomeSketch::SOURCE_UPLOAD, $sketch->fresh()->source);
        $this->assertSame(0, $this->generator->calls, 'seeding never calls the image service');
    }

    public function test_saving_a_new_photo_draws_a_trimmed_transparent_sketch_once_and_puts_it_live(): void
    {
        $product = $this->product('NEW-1');

        $version = $this->service()->requestAfterSave($product, $this->user->id, 'created');
        $this->assertSame(ProductWelcomeSketch::STATUS_PENDING, $version->status);
        $this->runQueued();

        $version->refresh();
        $this->assertSame([ProductWelcomeSketch::STATUS_READY, ProductWelcomeSketch::SOURCE_GENERATED, 'fake-model'],
            [$version->status, $version->source, $version->model]);
        $this->assertSame('https://cdn.test/sys/products/p.png', $version->source_photo_url);
        $live = $this->live($product);
        $this->assertSame([$version->id, $version->path, null], [(int) $live->version_id, $live->path, $live->selected_by], 'the first drawing goes live by itself');
        $image = new Imagick;
        $image->readImageBlob(Storage::get($version->path));
        $this->assertSame('WEBP', $image->getImageFormat());
        $this->assertLessThanOrEqual(512, max($image->getImageWidth(), $image->getImageHeight()));
        $this->assertLessThan($image->getImageHeight(), $image->getImageWidth(), 'the transparent margin was trimmed');
        $this->assertSame(3, $this->generator->lastReferences, 'approved sketches sent as the style');

        // A redelivered job and a second save of the same photo cost nothing.
        $this->service()->generate($version->id);
        $this->assertNull($this->service()->requestAfterSave($product, null));
        $this->assertSame(1, $this->generator->calls);
    }

    public function test_a_failure_keeps_the_live_drawing_and_is_not_retried_for_the_same_photo(): void
    {
        $product = $this->product('NEW-2');
        $this->service()->requestAfterSave($product, null);
        $this->runQueued();
        $url = $this->live($product)->url;

        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);
        $this->generator->fail = 'content policy';
        $version = $this->service()->requestAfterSave($product->fresh(), null, 'photo_saved');
        $this->service()->generate($version->id);

        $version->refresh();
        $this->assertSame(ProductWelcomeSketch::STATUS_FAILED, $version->status);
        $this->assertStringContainsString('content policy', $version->last_error);
        $this->assertSame($url, $this->live($product)->url, 'the freezer keeps the live drawing');
        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null), 'the failed photo is not re-paid on every save');
    }

    public function test_a_new_photo_redraws_an_automatic_live_drawing(): void
    {
        $product = $this->product('NEW-7');
        $this->service()->requestAfterSave($product, null);
        $this->runQueued();
        $first = $this->live($product)->version_id;

        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);
        $this->generator->image = self::png(900, 700);
        $version = $this->service()->requestAfterSave($product->fresh(), null, 'photo_saved');
        $this->service()->generate($version->id);

        $this->assertSame($version->id, (int) $this->live($product)->version_id, 'nobody picked the old one, so the new photo replaces it');
        $this->assertNotSame($first, $version->id);
        $this->assertSame(2, ProductWelcomeSketchVersion::query()->where('product_id', $product->id)->count(), 'the old drawing stays in the album');
    }

    public function test_draw_waits_in_the_album_until_a_person_uses_one(): void
    {
        $product = $this->product('A-1');
        $vend = $this->onFreezer($product);
        $this->service()->requestAfterSave($product, null, 'freezer_product');
        $this->runQueued();
        $live = $this->live($product);

        $this->generator->image = self::png(700, 900);
        $drawn = $this->service()->regenerate($product, $this->user->id, 2);
        $this->assertCount(2, $drawn);
        foreach ($drawn as $version) {
            $this->service()->generate($version->id);
        }
        $this->assertSame($live->version_id, $this->live($product)->version_id, 'Draw never replaces the live drawing by itself');
        $this->assertSame(3, ProductWelcomeSketchVersion::query()->where('product_id', $product->id)->where('status', 'ready')->count());

        Queue::fake();
        \Illuminate\Support\Facades\Cache::flush(); // past the first drawing's 90 s nudge debounce
        $picked = $drawn[1]->fresh();
        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/versions/{$picked->id}/select")
            ->assertOk()->assertJsonPath('live.version_id', $picked->id)->assertJsonPath('live.selected_by', $this->user->name);
        $now = $this->live($product);
        $this->assertSame([$picked->path, $this->user->id], [$now->path, $now->selected_by]);
        Queue::assertPushed(\App\Jobs\NudgeFreezerMenu::class, fn ($job) => $job->vendId === $vend->id);
        $row = collect($this->getJson("/api/vends/{$vend->code}/menu")->json())->firstWhere('product_code', 'A-1');
        $this->assertStringContainsString($picked->path, $row['welcome_sketch'], 'the freezer gets the picked drawing');

        // A person's pick is never replaced automatically, even by a new photo.
        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);
        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null, 'photo_saved'));
    }

    public function test_draw_from_an_empty_album_goes_live_when_it_finishes(): void
    {
        $product = $this->product('A-2');
        [$version] = $this->service()->regenerate($product, $this->user->id);
        $this->service()->generate($version->id);

        $this->assertSame($version->id, (int) $this->live($product)->version_id, 'a product with no drawing gets the first one at once');
        $this->assertNull($this->live($product)->selected_by);
    }

    public function test_a_draw_carries_a_persons_instructions_to_the_model_and_keeps_them_on_the_album_entry(): void
    {
        $product = $this->product('I-1');

        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/regenerate", [
            'count' => 2, 'instructions' => '  Text exactly ICE BALL, nothing else. No bite.  ',
        ])->assertOk()
            ->assertJsonPath('last_instructions', 'Text exactly ICE BALL, nothing else. No bite.')
            ->assertJsonPath('versions.0.instructions', 'Text exactly ICE BALL, nothing else. No bite.');
        $this->runQueued();
        $this->assertSame('Text exactly ICE BALL, nothing else. No bite.', $this->generator->lastInstructions);

        // Blank = none; too long is refused; an automatic redraw never carries any.
        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/regenerate", ['instructions' => str_repeat('x', 1001)])
            ->assertStatus(422)->assertJsonValidationErrors('instructions');
        Queue::fake();
        [$plain] = $this->service()->regenerate($product, $this->user->id, 1, '   ');
        $this->assertNull($plain->instructions);
        $this->service()->generate($plain->id);
        $this->assertNull($this->generator->lastInstructions);

        $freezerItem = $this->product('I-2');
        $this->onFreezer($freezerItem);
        $auto = $this->service()->requestAfterSave($freezerItem, null, 'freezer_product');
        $this->assertNull($auto->instructions);
    }

    public function test_the_openai_prompt_ranks_a_persons_instructions_above_the_fixed_rules(): void
    {
        config(['services.openai.api_key' => 'sk-test']);
        Http::swap(new \Illuminate\Http\Client\Factory); // past setUp's catch-all photo fake
        Http::fake(['*/images/edits' => Http::response(['data' => [['b64_json' => base64_encode(self::png())]]])]);
        $generator = new \App\Services\Products\WelcomeSketch\OpenAiSketchGenerator;

        $generator->generate(self::png(), 'Ice Ball Red Grape', [], 'Keep the wooden stick.');
        $generator->generate(self::png(), 'Ice Ball Red Grape', []);

        $prompts = collect(Http::recorded())->map(fn ($pair) => collect($pair[0]->data())->firstWhere('name', 'prompt')['contents'] ?? null)->values();
        $this->assertStringContainsString("they win:\nKeep the wooden stick.\nWhat the product is", $prompts[0]);
        $this->assertStringNotContainsString('Extra instructions', $prompts[1]);
    }

    public function test_approved_and_uploaded_art_is_never_redrawn_automatically(): void
    {
        $product = $this->product('U-01');
        $this->service()->seed($product, self::png(400, 400));
        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);

        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null));
        Queue::assertNothingPushed();

        [$version] = $this->service()->regenerate($product->fresh(), $this->user->id);
        $this->runQueued();
        $this->assertSame(ProductWelcomeSketch::SOURCE_SEED, $this->live($product)->source, 'the drawing waits in the album');
        $this->service()->select($product, $version->id, $this->user->id);
        $this->assertSame(ProductWelcomeSketch::SOURCE_GENERATED, $this->live($product)->source);
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
        $version = $this->service()->requestAfterSave($product, null);
        ProductWelcomeSketchVersion::query()->whereKey($version->id)->update(['status' => ProductWelcomeSketch::STATUS_GENERATING]);
        $product->thumbnail()->update(['full_url' => 'https://cdn.test/sys/products/new.png']);

        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null), 'still being drawn');
        $this->travel(16)->minutes();
        $this->assertNotNull($this->service()->requestAfterSave($product->fresh(), null), 'a worker killed mid-call is not waited on forever');
        $this->actingAs($this->user)->getJson("/products/{$product->id}/welcome-sketch")
            ->assertOk()->assertJsonPath('versions.1.status', 'abandoned')->assertJsonPath('in_flight', 1);
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
        $this->assertSame('freezer_product', ProductWelcomeSketchVersion::query()->where('product_id', $freezer->id)->sole()->reason);
    }

    public function test_draw_and_upload_from_product_edit(): void
    {
        $product = $this->product('E-1');

        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/regenerate", ['count' => 3])
            ->assertOk()->assertJsonPath('in_flight', 3)->assertJsonCount(3, 'versions');
        Queue::assertPushed(GenerateProductWelcomeSketch::class, 3);
        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/regenerate", ['count' => 2])
            ->assertStatus(422)->assertJsonPath('message', 'Already drawing 3 — wait for them to finish (at most 4 at once).');
        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/regenerate", ['count' => 9])
            ->assertStatus(422)->assertJsonValidationErrors('count');

        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch", [
            'welcome_sketch' => UploadedFile::fake()->createWithContent('mine.png', self::png(300, 300)),
        ])->assertOk()->assertJsonPath('live.source', 'upload');
        $this->assertSame(ProductWelcomeSketch::SOURCE_UPLOAD, $this->live($product)->source);
        $this->assertSame($this->user->id, $this->live($product)->selected_by);

        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch", [
            'welcome_sketch' => UploadedFile::fake()->create('mine.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('welcome_sketch');

        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo(Permission::findOrCreate('read products', 'web'));
        $this->actingAs($viewer)->getJson("/products/{$product->id}/welcome-sketch")->assertOk()->assertJsonPath('live.source', 'upload');
        $this->actingAs($viewer)->postJson("/products/{$product->id}/welcome-sketch/regenerate")->assertForbidden();
        $version = ProductWelcomeSketchVersion::query()->where('product_id', $product->id)->first();
        $this->actingAs($viewer)->postJson("/products/{$product->id}/welcome-sketch/versions/{$version->id}/select")->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/products/{$product->id}/welcome-sketch/versions/{$version->id}")->assertForbidden();
    }

    public function test_star_and_delete_in_the_album(): void
    {
        $product = $this->product('D-1');
        $other = $this->product('D-2');
        $this->service()->requestAfterSave($product, null);
        $this->runQueued();
        $liveId = (int) $this->live($product)->version_id;
        $this->generator->image = self::png(600, 900);
        [$extra] = $this->service()->regenerate($product, null);
        $this->service()->generate($extra->id);
        $extra->refresh();
        // Same image twice (paths are content hashes): deleting one keeps the file for the other.
        $twin = ProductWelcomeSketchVersion::query()->create($extra->only(['product_id', 'status', 'source', 'path', 'url']));

        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/versions/{$extra->id}/shortlist", ['shortlisted' => true])
            ->assertOk();
        $this->assertNotNull($extra->fresh()->shortlisted_at);
        $this->actingAs($this->user)->postJson("/products/{$product->id}/welcome-sketch/versions/{$extra->id}/shortlist", ['shortlisted' => false]);
        $this->assertNull($extra->fresh()->shortlisted_at);

        $this->actingAs($this->user)->deleteJson("/products/{$product->id}/welcome-sketch/versions/{$liveId}")
            ->assertStatus(422)->assertJsonPath('message', 'This is the drawing on the freezers — use another one first.');
        $this->actingAs($this->user)->deleteJson("/products/{$other->id}/welcome-sketch/versions/{$extra->id}")->assertNotFound();

        $this->actingAs($this->user)->deleteJson("/products/{$product->id}/welcome-sketch/versions/{$twin->id}")->assertOk();
        Storage::assertExists($extra->path);
        $this->actingAs($this->user)->deleteJson("/products/{$product->id}/welcome-sketch/versions/{$extra->id}")
            ->assertOk()->assertJsonCount(1, 'versions');
        Storage::assertMissing($extra->path);
        Storage::assertExists($this->live($product)->path);
    }

    public function test_earlier_drawings_left_in_storage_are_recovered_into_the_album(): void
    {
        $product = $this->product('U-01');
        $this->service()->requestAfterSave($product, null);
        $this->runQueued();
        $dir = "sys/products/{$product->id}/welcome-sketch";
        Storage::put("{$dir}/aaaaaaaaaaaaaaaa.webp", 'old drawing');
        Storage::put("{$dir}/notes.txt", 'not a drawing');
        // The approved seed art for U-01, stored as the seeder would have.
        $seedWebp = ProductWelcomeSketchService::normalise((string) file_get_contents(ProductWelcomeSketchService::seedDirectory().'/U-01.webp'), 512);
        $seedPath = "{$dir}/".substr(hash('sha256', $seedWebp), 0, 16).'.webp';
        Storage::put($seedPath, $seedWebp);

        $this->artisan('products:welcome-sketches --recover --dry-run')->expectsOutputToContain('Would recover: 2')->assertSuccessful();
        $this->assertSame(1, ProductWelcomeSketchVersion::query()->count());
        $this->artisan('products:welcome-sketches --recover')->expectsOutputToContain('Recovered: 2')->assertSuccessful();
        $this->artisan('products:welcome-sketches --recover')->expectsOutputToContain('Recovered: 0')->assertSuccessful();

        $recovered = ProductWelcomeSketchVersion::query()->where('reason', ProductWelcomeSketchVersion::REASON_RECOVERED)->get()->keyBy('path');
        $this->assertSame(['generated', 'seed'], [$recovered["{$dir}/aaaaaaaaaaaaaaaa.webp"]->source, $recovered[$seedPath]->source]);
        $this->assertSame(ProductWelcomeSketch::SOURCE_GENERATED, $this->live($product)->source, 'recovering never changes the live drawing');
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

        $sketch = $this->live($product);
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
        $upgrade = $this->service()->requestAfterSave($product->fresh(), null);
        $this->assertNotNull($upgrade);
        $this->service()->generate($upgrade->id);
        $this->assertSame(ProductWelcomeSketch::SOURCE_GENERATED, $sketch->fresh()->source);
        $this->assertNull($this->service()->requestAfterSave($product->fresh(), null), 'upgraded once');
    }

    public function test_a_failed_drawing_falls_back_to_the_cut_out_and_says_why(): void
    {
        $this->cutouts->available = true;
        $this->generator->fail = 'rate limited';
        $product = $this->product('C-2');

        $this->service()->requestAfterSave($product, null);
        $this->runQueued();

        $sketch = $this->live($product);
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

    public function test_a_new_sketch_nudges_its_freezers_once_to_re_read_the_menu(): void
    {
        $a = $this->product('N-1');
        $b = $this->product('N-2');
        $vendingOnly = $this->product('N-3');
        $vend = $this->onFreezer($a, $b);

        $this->service()->seed($a, self::png(300, 300));
        $this->service()->seed($b, self::png(320, 300));
        $this->service()->seed($vendingOnly, self::png(300, 300));

        // Two sketches on the same freezer within the window: one nudge, delayed.
        Queue::assertPushed(\App\Jobs\NudgeFreezerMenu::class, 1);
        Queue::assertPushed(\App\Jobs\NudgeFreezerMenu::class, fn ($job) => $job->vendId === $vend->id && $job->delay !== null);
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

    public ?string $lastInstructions = null;

    public ?string $fail = null;

    /** What the next drawing returns (a different image = a different stored file). */
    public ?string $image = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function generate(string $photo, string $productName, array $styleReferences, ?string $instructions = null): string
    {
        $this->calls++;
        $this->lastReferences = count($styleReferences);
        $this->lastInstructions = $instructions;
        if ($this->fail !== null) {
            throw new SketchGenerationException($this->fail);
        }

        return $this->image ?? ProductWelcomeSketchTest::png();
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
