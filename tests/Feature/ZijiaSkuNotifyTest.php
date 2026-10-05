<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ZijiaSkuNotification;
use App\Services\CardSettlement\CardSettlementHealthCheck;
use App\Services\SmartFreezer\Zijia\ZijiaSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Zijia's product-audit push (requested 2026-10-05): a product approved in their portal gets its
 * barcode in mark1 at once, matched by 商品编码 = products.code — never by name.
 */
class ZijiaSkuNotifyTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/smart-freezer/zijia/sku/notify';

    private const SECRET = 'app-secret-under-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['smart_freezer.zijia.algorithm' => [
            'base_url' => 'https://algo.test', 'app_id' => '1789379222883159', 'app_secret' => self::SECRET,
            'model_ids' => [], 'notify_url' => null, 'auto_submit' => false, 'timeout' => 5,
            'callback_verification' => 'enforce', 'timezone' => 'Asia/Shanghai',
        ]]);
    }

    private function push(array $biz, ?string $secret = self::SECRET): TestResponse
    {
        $envelope = ['appId' => '1789379222883159', 'version' => 'v1', 'signType' => 'md5', 'timestamp' => '2026-10-05 15:00:00',
            'method' => 'cabinet.sku.audit.result', 'bizContent' => json_encode($biz)];
        if ($secret !== null) {
            $envelope['sign'] = (new ZijiaSigner($secret))->sign($envelope);
        }

        return $this->postJson(self::URL, $envelope);
    }

    private function approved(string $code, string $barcode, string $status = 'approved'): array
    {
        return ['merchantGoodsCode' => $code, 'productCode' => $barcode, 'skuName' => 'Basque Cheesecake - Chocolate', 'auditStatus' => $status];
    }

    private function product(string $code, ?string $barcode = null): Product
    {
        return Product::forceCreate(['code' => $code, 'name' => 'P '.$code, 'operator_id' => 1, 'barcode' => $barcode]);
    }

    public function test_an_approval_fills_the_barcode_of_the_product_with_that_code(): void
    {
        $chocolate = $this->product('CC-02');

        $this->push($this->approved('CC-02', '0726436016209'))->assertOk()->assertJson(['status' => 200, 'body' => 'SUCCESS']);

        $this->assertSame('0726436016209', $chocolate->fresh()->barcode);
        $this->assertTrue(DB::table('user_logs')->where('auditable_id', $chocolate->id)->where('source', 'zijia-sku-notify')->exists());
        $this->assertSame('set', ZijiaSkuNotification::sole()->outcome);
    }

    public function test_an_unsigned_push_changes_nothing_but_is_kept(): void
    {
        $chocolate = $this->product('CC-02');

        $this->push($this->approved('CC-02', '0726436016209'), null)->assertOk()->assertJson(['status' => 500]);
        $this->push($this->approved('CC-02', '0726436016209'), 'wrong-secret')->assertJson(['status' => 500]);

        $this->assertNull($chocolate->fresh()->barcode);
        $this->assertSame(['refused_unsigned', 'refused_unsigned'], ZijiaSkuNotification::query()->pluck('outcome')->all());
    }

    public function test_a_different_existing_barcode_is_never_overwritten_and_is_reported(): void
    {
        $chocolate = $this->product('CC-02', '1111111111111');

        $this->push($this->approved('CC-02', '0726436016209'))->assertOk();

        $this->assertSame('1111111111111', $chocolate->fresh()->barcode);
        $section = collect(app(CardSettlementHealthCheck::class)->run())->firstWhere('key', 'zijia_sku_push');
        $this->assertStringContainsString('different barcode', $section['items'][0]['text']);
    }

    public function test_a_withdrawal_clears_only_their_own_barcode(): void
    {
        $theirs = $this->product('CC-02', '0726436016209');
        $other = $this->product('U-01', '8851932115919');

        $this->push($this->approved('CC-02', '0726436016209', '已撤销'))->assertOk();
        $this->push($this->approved('U-01', '9999999999999', 'rejected'))->assertOk();

        $this->assertNull($theirs->fresh()->barcode, 'withdrawn: no longer sent to the AI');
        $this->assertSame('8851932115919', $other->fresh()->barcode, 'not the barcode they withdrew');
    }

    public function test_a_shared_code_resolves_to_the_product_on_a_freezer(): void
    {
        $freezer = new \App\Models\Vend;
        $freezer->forceFill(['code' => 50001, 'machine_type' => \App\Models\Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1])->save();
        $elsewhere = $this->product('U-79');
        $onFreezer = $this->product('U-79');
        DB::table('vend_channels')->insert(['vend_id' => $freezer->id, 'code' => 52, 'product_id' => $onFreezer->id, 'amount' => 460]);

        $this->push($this->approved('U-79', '5555555555555'))->assertOk();

        $this->assertSame('5555555555555', $onFreezer->fresh()->barcode);
        $this->assertNull($elsewhere->fresh()->barcode);
    }

    public function test_unknown_codes_statuses_and_shapes_are_kept_and_reported(): void
    {
        $this->product('DUP');
        $this->product('DUP');

        $this->push($this->approved('NOPE', '1'))->assertOk();
        $this->push($this->approved('DUP', '2'))->assertOk();
        $this->push($this->approved('CC-02', '3', 'pending'))->assertOk();
        $this->push(['skuName' => 'no codes', 'auditStatus' => 'approved'])->assertOk();

        $this->assertSame(['no_product', 'ambiguous_code', 'unknown_status', 'missing_fields'],
            ZijiaSkuNotification::query()->orderBy('id')->pluck('outcome')->all());
        $this->assertCount(4, collect(app(CardSettlementHealthCheck::class)->run())->firstWhere('key', 'zijia_sku_push')['items']);
    }
}
