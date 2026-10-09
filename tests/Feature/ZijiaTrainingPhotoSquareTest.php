<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\ZijiaSkuApplication;
use App\Services\SmartFreezer\Zijia\ZijiaTrainingPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Zijia requires every AI-training photo to be 1:1 (2026-10-09). mark1 PADS — never crops or
 * stretches — before anything is sent (ZijiaTrainingPhoto), and submit refuses a photo that is not.
 */
class ZijiaTrainingPhotoSquareTest extends TestCase
{
    use RefreshDatabase;

    private const SPACE = 'https://space.test';

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'), ['url' => self::SPACE]);
        Http::preventStrayRequests();
        config(['smart_freezer.zijia.algorithm' => [
            'base_url' => 'https://algo.test', 'app_id' => '1789379222883159', 'app_secret' => 'secret',
            'model_ids' => [], 'notify_url' => null, 'auto_submit' => false, 'timeout' => 5,
            'callback_verification' => 'enforce', 'timezone' => 'Asia/Shanghai',
        ], 'smart_freezer.zijia.sku_callback_token' => null]);

        $this->user = User::factory()->create(['operator_id' => 1]);
        foreach (['read products', 'update products'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->product = Product::forceCreate(['code' => 'CC-03', 'name' => 'Mango Bar', 'operator_id' => 1]);
    }

    private function save(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training", $data);
    }

    /** A JPEG/PNG whose left half is red and right half blue, so a crop, stretch or turn shows. */
    private static function halves(int $w, int $h, string $format = 'jpg', bool $alpha = false): string
    {
        $im = imagecreatetruecolor($w, $h);
        if ($alpha) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
            imagefilledrectangle($im, (int) ($w / 4), (int) ($h / 4), (int) ($w * 3 / 4), (int) ($h * 3 / 4), imagecolorallocatealpha($im, 255, 0, 0, 0));
        } else {
            imagefilledrectangle($im, 0, 0, intdiv($w, 2) - 1, $h - 1, imagecolorallocate($im, 255, 0, 0));
            imagefilledrectangle($im, intdiv($w, 2), 0, $w - 1, $h - 1, imagecolorallocate($im, 0, 0, 255));
        }
        ob_start();
        $format === 'png' ? imagepng($im) : imagejpeg($im, null, 95);

        return (string) ob_get_clean();
    }

    /** The same JPEG carrying EXIF Orientation = 6 ("turn 90° clockwise to view"), as phones save portraits. */
    private static function withExifOrientation6(string $jpeg): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"."\x00\x00\x00\x00";
        $payload = "Exif\x00\x00".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }

    private static function upload(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zsq');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function stored(string $url): ImageInterface
    {
        $this->assertStringStartsWith(self::SPACE.'/', $url);

        return Image::read(Storage::get(substr($url, strlen(self::SPACE) + 1)));
    }

    private static function rgb(ImageInterface $image, int $x, int $y): array
    {
        $c = $image->pickColor($x, $y);

        return [$c->red()->value(), $c->green()->value(), $c->blue()->value(), $c->alpha()->value()];
    }

    private function assertNear(array $expected, array $actual, string $where): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->assertEqualsWithDelta($expected[$i], $actual[$i], 40, "{$where}: got rgb(".implode(',', array_slice($actual, 0, 3)).')');
        }
    }

    public function test_an_upload_is_padded_to_a_square_with_white_never_cropped_or_stretched(): void
    {
        $this->save(['photos' => ['high' => [self::upload(self::halves(400, 300), 'wide.jpg')]]])->assertSessionHasNoErrors();

        $app = ZijiaSkuApplication::sole();
        $url = $app->model_pics['high'][0];
        $this->assertMatchesRegularExpression("#/sys/zijia-sku/{$this->product->id}/square/[0-9a-f]{40}\\.jpg$#", $url);
        $img = $this->stored($url);
        $this->assertSame([400, 400], [$img->width(), $img->height()]);

        // 50 px of white above and below; the photo itself untouched between.
        $this->assertNear([255, 255, 255], self::rgb($img, 200, 20), 'top border');
        $this->assertNear([255, 255, 255], self::rgb($img, 200, 380), 'bottom border');
        $this->assertNear([255, 0, 0], self::rgb($img, 5, 60), 'left edge kept (not cropped)');
        $this->assertNear([0, 0, 255], self::rgb($img, 395, 340), 'right edge kept (not cropped)');
        $this->assertNear([255, 0, 0], self::rgb($img, 190, 200), 'halves meet at the centre (not stretched)');
        $this->assertNear([0, 0, 255], self::rgb($img, 210, 200), 'halves meet at the centre (not stretched)');

        // The original is kept for a later re-process, and the save is logged with both sizes.
        $added = $app->events()->where('event', 'draft.saved')->sole()->detail['photos_added'][0];
        $this->assertSame(['400×300', '400×400'], [$added['from'], $added['to']]);
        $this->assertStringContainsString("/sys/zijia-sku/{$this->product->id}/original/", $added['original_url']);
        $this->assertSame(self::halves(400, 300), Storage::get(substr($added['original_url'], strlen(self::SPACE) + 1)));
    }

    public function test_a_large_photo_is_capped_at_the_max_edge_and_a_tall_one_is_padded_at_the_sides(): void
    {
        $this->save(['photos' => [
            'high' => [self::upload(self::halves(3000, 1500), 'big.jpg')],
            'low' => [self::upload(self::halves(300, 600), 'tall.jpg')],
        ]])->assertSessionHasNoErrors();

        $pics = ZijiaSkuApplication::sole()->model_pics;
        $big = $this->stored($pics['high'][0]);
        $this->assertSame([ZijiaTrainingPhoto::MAX_EDGE, ZijiaTrainingPhoto::MAX_EDGE], [$big->width(), $big->height()]);

        $tall = $this->stored($pics['low'][0]);
        $this->assertSame([600, 600], [$tall->width(), $tall->height()], 'never enlarged');
        $this->assertNear([255, 255, 255], self::rgb($tall, 50, 300), 'left border');
        $this->assertNear([255, 0, 0], self::rgb($tall, 160, 300), 'photo starts at x=150');
    }

    public function test_a_cut_out_stays_a_png_with_transparent_padding(): void
    {
        $this->save(['photos' => ['high' => [self::upload(self::halves(400, 200, 'png', alpha: true), 'cutout.png')]]])->assertSessionHasNoErrors();

        $url = ZijiaSkuApplication::sole()->model_pics['high'][0];
        $this->assertStringEndsWith('.png', $url);
        $img = $this->stored($url);
        $this->assertSame([400, 400], [$img->width(), $img->height()]);
        $corner = self::rgb($img, 3, 3);
        $this->assertSame(0, $corner[3], 'padding is transparent on a cut-out');
        $this->assertNear([255, 255, 255], $corner, 'its colour is white, so a reader that drops alpha sees white');
        $this->assertNear([255, 0, 0], self::rgb($img, 200, 200), 'the product');
    }

    public function test_a_phone_portrait_is_turned_upright_before_it_is_squared(): void
    {
        // Stored 400×300 with Orientation 6: upright it is 300×400, red on top, blue below.
        $this->save(['photos' => ['high' => [self::upload(self::withExifOrientation6(self::halves(400, 300)), 'portrait.jpg')]]])->assertSessionHasNoErrors();

        $img = $this->stored(ZijiaSkuApplication::sole()->model_pics['high'][0]);
        $this->assertSame([400, 400], [$img->width(), $img->height()]);
        $this->assertNear([255, 255, 255], self::rgb($img, 20, 200), 'white at the sides, not top and bottom');
        $this->assertNear([255, 0, 0], self::rgb($img, 300, 100), 'top half red');
        $this->assertNear([0, 0, 255], self::rgb($img, 100, 300), 'bottom half blue');
    }

    public function test_the_product_thumbnail_a_draft_starts_with_is_squared_on_the_first_save(): void
    {
        $this->product->thumbnail()->create(['type' => 1, 'full_url' => 'https://cdn.test/sys/products/mango.jpg', 'local_url' => 'x']);
        Http::fake(['cdn.test/*' => Http::response(self::halves(300, 200), 200, ['Content-Type' => 'image/jpeg'])]);

        $this->save(['sku_name' => 'Mango Bar'])->assertSessionHasNoErrors();

        $app = ZijiaSkuApplication::sole();
        $this->assertTrue(app(ZijiaTrainingPhoto::class)->isSquare($app->package_image_url));
        $img = $this->stored($app->package_image_url);
        $this->assertSame([300, 300], [$img->width(), $img->height()]);
        $squared = $app->events()->where('event', 'photos.squared')->sole();
        $this->assertSame('https://cdn.test/sys/products/mango.jpg', $squared->detail['squared'][0]['from_url']);
        $this->assertSame('info', $squared->level);
    }

    public function test_photos_copied_from_a_vms4_application_are_squared_from_our_own_storage(): void
    {
        Storage::put("sys/zijia-sku/{$this->product->id}/vms4/abc.png", self::halves(500, 250, 'png'));
        Storage::put("sys/zijia-sku/{$this->product->id}/vms4/pkg.jpg", self::halves(250, 500));
        $own = fn (string $f) => self::SPACE."/sys/zijia-sku/{$this->product->id}/vms4/{$f}";
        ZijiaSkuApplication::query()->create([
            'product_id' => $this->product->id, 'application_no' => '1', 'status' => ZijiaSkuApplication::STATUS_APPROVED,
            'source' => ZijiaSkuApplication::SOURCE_VMS4, 'sku_name' => 'Mango Bar', 'brand_name' => '其他/其他',
            'category' => 4, 'package_type' => 3, 'product_code' => '9555000000017',
            'package_image_url' => $own('pkg.jpg'), 'model_pics' => ['high' => [$own('abc.png')], 'horizontal' => [], 'low' => []],
        ]);
        Http::fake();

        $this->save(['start_new' => 1])->assertSessionHasNoErrors();

        $draft = ZijiaSkuApplication::query()->where('status', 'draft')->sole();
        $photos = app(ZijiaTrainingPhoto::class);
        $this->assertTrue($photos->isSquare($draft->package_image_url));
        $this->assertTrue($photos->isSquare($draft->model_pics['high'][0]));
        Http::assertNothingSent();
        // The approved application keeps what Zijia approved.
        $this->assertSame($own('abc.png'), ZijiaSkuApplication::query()->where('source', 'vms4')->sole()->model_pics['high'][0]);
    }

    public function test_a_photo_that_cannot_be_squared_blocks_the_submit(): void
    {
        $this->product->thumbnail()->create(['type' => 1, 'full_url' => 'https://cdn.test/sys/products/gone.jpg', 'local_url' => 'x']);
        Http::fake(['cdn.test/*' => Http::response('not found', 404), 'algo.test/*' => Http::response(['code' => 0])]);

        $this->save([
            'sku_name' => 'Mango Bar', 'brand_name' => '其他/其他', 'category' => 4, 'package_type' => 3, 'product_code' => '9555000000017',
            'photos' => ['high' => [self::upload(self::halves(200, 200), 'top.jpg')]],
        ])->assertSessionHasNoErrors();
        $app = ZijiaSkuApplication::sole();
        $this->assertSame('https://cdn.test/sys/products/gone.jpg', $app->package_image_url, 'link kept, not lost');
        $this->assertSame('warning', $app->events()->where('event', 'photos.squared')->sole()->level);

        $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training/submit")
            ->assertSessionHasErrors('photos.square');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'algo.test'));

        // Replacing it clears the block.
        $this->save(['package_image' => self::upload(self::halves(200, 100), 'pack.jpg')])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('photos.square', app(\App\Services\SmartFreezer\Zijia\ZijiaSkuApplicationService::class)->missing($app->fresh()));
    }

    public function test_submit_squares_a_draft_saved_before_squaring_existed(): void
    {
        Storage::put('sys/zijia-sku/old/top.jpg', self::halves(640, 480));
        Storage::put('sys/zijia-sku/old/pkg.jpg', self::halves(480, 640));
        $app = ZijiaSkuApplication::query()->create([
            'product_id' => $this->product->id, 'application_no' => '2', 'status' => ZijiaSkuApplication::STATUS_DRAFT,
            'sku_name' => 'Mango Bar', 'brand_name' => '其他/其他', 'category' => 4, 'package_type' => 3, 'product_code' => '9555000000017',
            'package_image_url' => self::SPACE.'/sys/zijia-sku/old/pkg.jpg',
            'model_pics' => ['high' => [self::SPACE.'/sys/zijia-sku/old/top.jpg'], 'horizontal' => [], 'low' => []],
        ]);
        Http::fake(['algo.test/*' => Http::response(['code' => 0, 'msg' => 'ok', 'data' => ['skuId' => 'S1']])]);

        $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training/submit")->assertSessionHasNoErrors();

        $photos = app(ZijiaTrainingPhoto::class);
        Http::assertSent(function (HttpRequest $r) use ($photos) {
            $sku = json_decode(json_decode($r->body(), true)['bizContent'], true)['sku'];

            return $photos->isSquare($sku['packageImageUrl']) && $photos->isSquare($sku['skuModelPic']['high'][0]);
        });
        $this->assertSame(ZijiaSkuApplication::STATUS_SUBMITTED, $app->fresh()->status);
    }

    public function test_the_same_photo_twice_is_stored_once_and_a_non_image_is_refused_by_name(): void
    {
        $bytes = self::halves(300, 200);
        $this->save(['photos' => ['high' => [self::upload($bytes, 'a.jpg'), self::upload($bytes, 'b.jpg')]]])->assertSessionHasNoErrors();
        $this->assertCount(1, ZijiaSkuApplication::sole()->model_pics['high']);

        $this->save(['photos' => ['high' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]]])
            ->assertSessionHasErrors('photos.high.0');
    }

    public function test_the_page_is_told_the_per_save_file_limit(): void
    {
        $this->actingAs($this->user)->get("/products/{$this->product->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('aiTraining.max_files_per_save', max(1, (int) ini_get('max_file_uploads')))
                ->where('aiTraining.square_edge', ZijiaTrainingPhoto::MAX_EDGE));
    }
}
