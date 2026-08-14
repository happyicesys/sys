<?php

namespace Tests\Feature;

use App\Models\Vend;
use App\Models\VendChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/vends/{code}/vend-channels/{channel}/thumbnail when there is no
 * image to serve.
 *
 * The handler used to `return false` on that path, which Laravel renders as
 * "200 OK, Content-Type: text/html, 0 bytes". The terminal (ImageDownloadTask
 * -> Picasso) therefore saw a SUCCESSFUL response carrying an undecodable body
 * and logged "Failed to decode stream" at error level on every settings sync,
 * for every channel that has capacity but no product mapped.
 *
 * A channel with capacity and no product is a normal operational state, not an
 * error, so the endpoint has to say "no image here" in a way an HTTP client can
 * actually act on.
 */
class VendChannelThumbnailNotFoundTest extends TestCase
{
    use RefreshDatabase;

    private function makeChannel(int $vendCode, int $channelCode, ?int $productId): void
    {
        $vend = Vend::forceCreate(['code' => $vendCode]);

        VendChannel::forceCreate([
            'vend_id' => $vend->id,
            'code' => $channelCode,
            'product_id' => $productId,
            'amount' => 200,
            'qty' => 10,
            'capacity' => 10,
            'is_active' => 1,
        ]);
    }

    public function test_channel_with_no_product_returns_404_not_an_empty_200(): void
    {
        $this->makeChannel(9801, 19, null);

        $response = $this->get('/api/vends/9801/vend-channels/19/thumbnail');

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 404);

        // The regression itself: never an empty body served as a success.
        $this->assertNotSame('', $response->getContent());
    }

    public function test_unknown_channel_code_returns_404(): void
    {
        $this->makeChannel(9802, 11, null);

        $this->get('/api/vends/9802/vend-channels/57/thumbnail')
            ->assertStatus(404);
    }

    public function test_unknown_vend_code_returns_404(): void
    {
        $this->get('/api/vends/9899/vend-channels/11/thumbnail')
            ->assertStatus(404);
    }
}
