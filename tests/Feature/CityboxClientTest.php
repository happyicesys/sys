<?php

namespace Tests\Feature;

use App\Exceptions\CityboxApiException;
use App\Services\Citybox\CityboxClient;
use App\Services\Citybox\CityboxSigner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CityboxClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'citybox.enabled' => true,
            'citybox.base_url' => 'https://citybox.test',
            'citybox.api_secret' => 'test-secret',
            'citybox.timeout' => 5,
        ]);
    }

    public function test_get_equipment_posts_signed_form_and_returns_data(): void
    {
        Http::fake([
            'citybox.test/*' => Http::response([
                'code' => 200,
                'msg' => 'success',
                'data' => ['list' => [['equipment_id' => '08DFF2CEB11']], 'total' => 1],
            ]),
        ]);

        $data = app(CityboxClient::class)->getEquipment(['equipment_status' => '1'], page: 1, limit: 5);

        $this->assertSame(1, $data['total']);
        $this->assertSame('08DFF2CEB11', $data['list'][0]['equipment_id']);

        Http::assertSent(function (Request $request) {
            $body = [];
            parse_str($request->body(), $body);

            return $request->url() === 'https://citybox.test/api/apiThredDetail/getEqiupment'
              && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
              && isset($body['timestamp'], $body['nonce'], $body['sign'])
              && $body['equipment_status'] === '1'
              // The sign must verify against the exact params that were sent.
              && $body['sign'] === CityboxSigner::sign($body, 'test-secret');
        });
    }

    public function test_envelope_error_code_throws_with_api_code(): void
    {
        Http::fake([
            'citybox.test/*' => Http::response(['code' => 401, 'msg' => '签名验证失败']),
        ]);

        try {
            app(CityboxClient::class)->getEquipment();
            $this->fail('Expected CityboxApiException');
        } catch (CityboxApiException $e) {
            $this->assertSame(401, $e->apiCode);
            $this->assertStringContainsString('signature verification failed', $e->getMessage());
        }
    }

    public function test_http_level_failure_throws(): void
    {
        Http::fake(['citybox.test/*' => Http::response('', 500)]);

        $this->expectException(CityboxApiException::class);
        app(CityboxClient::class)->getEquipment();
    }

    public function test_stock_edit_business_failure_inside_code_200_throws(): void
    {
        // Their edit endpoint reports failure as code-200 + success_count 0.
        Http::fake([
            'citybox.test/*' => Http::response([
                'code' => 200,
                'msg' => 'success',
                'data' => ['success_count' => 0, 'msg' => '更新失败，已回滚'],
            ]),
        ]);

        $this->expectException(CityboxApiException::class);
        $this->expectExceptionMessageMatches('/rolled back/');

        app(CityboxClient::class)->editEquipmentProductStock([
            ['equipment_id' => '08DFF2CEB11', 'product_id' => '9315', 'stock' => 10],
        ]);
    }

    public function test_stock_edit_success_returns_counts(): void
    {
        Http::fake([
            'citybox.test/*' => Http::response([
                'code' => 200,
                'msg' => 'success',
                'data' => ['success_count' => 2, 'msg' => '更新成功'],
            ]),
        ]);

        $list = [
            ['equipment_id' => '08DFF2CEB11', 'product_id' => '9315', 'stock' => 10],
            ['equipment_id' => '08DFF2CEB11', 'product_id' => '350', 'stock' => 15],
        ];

        $data = app(CityboxClient::class)->editEquipmentProductStock($list);

        $this->assertSame(2, $data['success_count']);

        // The trap this guards: form-encoding a nested array would send
        // list[0][equipment_id]=… while the JSON form was signed. `list` must
        // travel as ONE JSON string and the signature must verify over the
        // exact body that was sent.
        Http::assertSent(function (Request $request) use ($list) {
            $body = [];
            parse_str($request->body(), $body);

            return is_string($body['list'])
                && json_decode($body['list'], true) === $list
                && $body['sign'] === CityboxSigner::sign($body, 'test-secret');
        });
    }

    public function test_non_json_response_body_throws_citybox_exception(): void
    {
        // e.g. a proxy/maintenance HTML page with HTTP 200 — must surface as
        // CityboxApiException, not a null-array-offset ErrorException.
        Http::fake(['citybox.test/*' => Http::response('<html>maintenance</html>', 200)]);

        $this->expectException(CityboxApiException::class);
        app(CityboxClient::class)->getEquipment();
    }

    public function test_disabled_integration_throws_before_any_http(): void
    {
        config(['citybox.enabled' => false]);
        Http::fake();

        $this->expectException(CityboxApiException::class);
        $this->expectExceptionMessageMatches('/disabled/');

        app(CityboxClient::class)->getEquipment();

        Http::assertNothingSent();
    }

    public function test_missing_config_throws_before_any_http(): void
    {
        config(['citybox.base_url' => null]);
        Http::fake();

        $this->expectException(CityboxApiException::class);
        $this->expectExceptionMessageMatches('/not configured/');

        app(CityboxClient::class)->getEquipment();
    }
}
