<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-11-26 outage: every thumbnail request downloaded the photo and resized it. When the whole fleet
 * re-read its menus at once the PHP workers fell behind and mark1 went down. The resized JPEG is now kept.
 */
class VendChannelThumbnailCacheTest extends TestCase
{
    use RefreshDatabase;

    private function channelWithPhoto(string $url): void
    {
        $vend = Vend::forceCreate(['code' => 9811]);
        $product = Product::forceCreate(['name' => 'Thumb product', 'code' => 'TH-01']);
        Attachment::forceCreate([
            'local_url' => $url, 'full_url' => $url,
            'modelable_type' => Product::class, 'modelable_id' => $product->id, 'type' => 1,
        ]);
        VendChannel::forceCreate([
            'vend_id' => $vend->id, 'code' => 11, 'product_id' => $product->id,
            'amount' => 200, 'qty' => 10, 'capacity' => 10, 'is_active' => 1,
        ]);
    }

    public function test_the_resized_photo_is_made_once_and_kept(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'thumb').'.png';
        $img = imagecreatetruecolor(600, 400);
        imagepng($img, $path);
        $this->channelWithPhoto($path);

        $first = $this->get('/api/vends/9811/vend-channels/11/thumbnail');
        $first->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        [$w, $h] = getimagesizefromstring($first->getContent());
        $this->assertSame([300, 300], [$w, $h]);

        // The source is gone: a second request must not need it.
        unlink($path);
        $second = $this->get('/api/vends/9811/vend-channels/11/thumbnail');
        $second->assertOk();
        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function test_a_photo_that_cannot_be_read_is_a_404_not_a_500(): void
    {
        $this->channelWithPhoto(sys_get_temp_dir().'/no-such-thumb-'.uniqid().'.jpg');

        $this->get('/api/vends/9811/vend-channels/11/thumbnail')->assertStatus(404);
    }
}
