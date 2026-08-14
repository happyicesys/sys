<?php

namespace Tests\Feature;

use App\Exceptions\CityboxApiException;
use App\Services\Citybox\OpenapiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenapiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'citybox.openapi.enabled' => true,
            'citybox.openapi.base_url' => 'https://openapi.citybox.test',
            'citybox.openapi.app_id' => 'APP123',
            'citybox.openapi.secret' => 'test-secret',
            'citybox.openapi.timeout' => 5,
        ]);
    }

    private function fakeToken(): void
    {
        Http::fake([
            'openapi.citybox.test/api/Openapi/get_access_token' => Http::response([
                'code' => 200,
                'body' => ['access_token' => 'TOK1', 'express_in' => 3600],
            ]),
        ]);
    }

    public function test_box_list_fetches_token_then_calls_signed_and_returns_body(): void
    {
        $this->fakeToken();
        Http::fake([
            'openapi.citybox.test/api/Openapi/box_list' => Http::response([
                'code' => 200,
                'body' => [[
                    'equipment_id' => 'ICB23EHWFC5B',
                    'name' => '测试点位',
                    'status' => 1,
                    'type' => 'visual-2',
                    'equipment_online_status' => 1,
                ]],
            ]),
        ]);

        $body = app(OpenapiClient::class)->boxList(['equipment_status' => 1]);

        $this->assertSame('ICB23EHWFC5B', $body[0]['equipment_id']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'box_list')) {
                return false;
            }

            // Every business call must carry the token + tenant + a signature.
            return $request['access_token'] === 'TOK1'
                && $request['app_id'] === 'APP123'
                && isset($request['timestamp'], $request['sign']);
        });
    }

    public function test_access_token_is_cached_across_calls(): void
    {
        $this->fakeToken();
        Http::fake([
            'openapi.citybox.test/api/Openapi/device_product' => Http::response(['code' => 200, 'body' => ['goods' => []]]),
            'openapi.citybox.test/api/Openapi/box_list' => Http::response(['code' => 200, 'body' => []]),
        ]);

        $client = app(OpenapiClient::class);
        $client->boxList();
        $client->deviceProduct('ICB23EHWFC5B');

        Http::assertSentCount(3); // one token fetch + two business calls, not two token fetches
    }

    public function test_token_cache_honors_reported_express_in_not_a_guessed_ttl(): void
    {
        // Regression: Cache::remember() fixes TTL before the fetch, so a short
        // express_in (here 200s → cached 80s after the 120s buffer) would have
        // been cached for the default ~1h and served dead for ~50 min.
        Http::fake([
            'openapi.citybox.test/api/Openapi/get_access_token' => Http::sequence()
                ->push(['code' => 200, 'body' => ['access_token' => 'TOK1', 'express_in' => 200]])
                ->push(['code' => 200, 'body' => ['access_token' => 'TOK2', 'express_in' => 3600]]),
            'openapi.citybox.test/api/Openapi/box_list' => Http::response(['code' => 200, 'body' => []]),
        ]);

        $client = app(OpenapiClient::class);
        $client->boxList();                    // fetches TOK1, cached 80s
        $this->assertSame('TOK1', $client->accessToken());

        $this->travel(90)->seconds();          // past the 80s TTL
        $client->boxList();                    // must re-fetch → TOK2

        $this->assertSame('TOK2', $client->accessToken());
    }

    public function test_non_200_envelope_throws_with_their_message(): void
    {
        $this->fakeToken();
        Http::fake([
            'openapi.citybox.test/api/Openapi/box_list' => Http::response([
                'code' => 400,
                'body' => ['success' => false, 'message' => '无权限'],
            ]),
        ]);

        $this->expectException(CityboxApiException::class);
        $this->expectExceptionMessage('无权限');

        app(OpenapiClient::class)->boxList();
    }

    public function test_disabled_or_unconfigured_throws_before_any_http(): void
    {
        Http::fake();

        config(['citybox.openapi.enabled' => false]);
        try {
            app(OpenapiClient::class)->boxList();
            $this->fail('expected disabled exception');
        } catch (CityboxApiException) {
        }

        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => null]);
        try {
            app(OpenapiClient::class)->boxList();
            $this->fail('expected unconfigured exception');
        } catch (CityboxApiException) {
        }

        Http::assertNothingSent();
    }
}
