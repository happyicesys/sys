<?php

namespace Tests\Feature;

use App\Models\SmartFreezerVideo;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZijiaVideoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/smart-freezer/zijia/videos';

    protected function setUp(): void
    {
        parent::setUp();
        config(['smart_freezer.zijia.video_webhook_token' => 'secret-token']);
    }

    public function test_it_is_inert_until_a_token_is_configured(): void
    {
        config(['smart_freezer.zijia.video_webhook_token' => null]);

        $this->postJson(self::URL, ['videoUrl' => 'https://x/a.mp4'])->assertStatus(503);
        $this->assertSame(0, SmartFreezerVideo::count());
    }

    public function test_it_refuses_a_missing_or_wrong_token(): void
    {
        $this->postJson(self::URL, ['videoUrl' => 'https://x/a.mp4'])->assertStatus(401);
        $this->postJson(self::URL, ['videoUrl' => 'https://x/a.mp4'], ['Authorization' => 'Bearer nope'])->assertStatus(401);
        $this->assertSame(0, SmartFreezerVideo::count());
    }

    public function test_it_stores_a_push_and_links_our_order_number_to_the_vend(): void
    {
        $vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $signed = 'https://oss.example.cn/order_video_v3/SF-50001-1789000000-3/cam1.mp4?Expires=1789&Signature='.str_repeat('a', 300);
        $body = [
            'deviceId' => '861232069524632',
            'orderNo' => 'SF-50001-1789000000-3',
            'videos' => [['camera' => 1, 'url' => $signed], ['camera' => 2, 'url' => 'https://oss.example.cn/cam2.mp4']],
        ];

        $this->postJson(self::URL, $body, ['Authorization' => 'Bearer secret-token'])
            ->assertOk()
            ->assertJson(['code' => 0, 'message' => 'ok']);

        $video = SmartFreezerVideo::sole();
        $this->assertSame($vend->id, $video->vend_id);
        $this->assertSame('SF-50001-1789000000-3', $video->order_no);
        $this->assertSame('861232069524632', $video->device_id);
        $this->assertSame([$signed, 'https://oss.example.cn/cam2.mp4'], $video->video_urls);
        $this->assertEquals($body, $video->payload);
        $this->assertSame(json_encode($body), $video->raw_body);
    }

    public function test_an_unrecognised_shape_is_still_kept_and_acknowledged(): void
    {
        $this->post(self::URL.'?token=secret-token', ['foo' => 'bar'], ['X-Requested-With' => 'x'])
            ->assertOk();

        $video = SmartFreezerVideo::sole();
        $this->assertNull($video->vend_id);
        $this->assertSame(['foo' => 'bar'], $video->payload);
        $this->assertSame([], $video->video_urls);
    }

    public function test_x_api_key_header_is_accepted_and_empty_body_rejected(): void
    {
        $this->postJson(self::URL, [], ['X-Api-Key' => 'secret-token'])->assertStatus(422);
        $this->postJson(self::URL, ['url' => 'https://x/a.mp4'], ['X-Api-Key' => 'secret-token'])->assertOk();
    }
}
