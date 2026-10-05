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

    /** The documented 商品审批回调 (§7): plain JSON, unsigned. */
    private function documented(bool $pass, array $sku, string $query = ''): TestResponse
    {
        return $this->postJson(self::URL.$query, ['pass' => $pass, 'msg' => $pass ? null : '申请被拒绝,拒绝原因: 图片不清', 'sku' => $sku + [
            'brandName' => '其他/其他', 'category' => 4, 'packageType' => 5, 'spec' => '120克', 'status' => 1, 'version' => 1,
            'sysSkuId' => 1791006205910842, 'standardProduct' => true,
        ]]);
    }

    private function library(array $codes): void
    {
        \Illuminate\Support\Facades\Http::fake(['algo.test/*' => fn ($r) => \Illuminate\Support\Facades\Http::response([
            'list' => array_values(array_map(fn ($c) => ['productCode' => $c, 'skuName' => 'x'],
                array_filter($codes, fn ($c) => $c === ($r->data()['productCode'] ?? null)))),
            'totalPage' => 1, 'currentPage' => 1, 'totalCount' => 1,
        ])]);
    }

    private function onFreezer(Product $product): void
    {
        $freezer = \App\Models\Vend::query()->where('code', 50001)->first() ?? tap(new \App\Models\Vend, fn ($v) => $v->forceFill(['code' => 50001, 'machine_type' => \App\Models\Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1])->save());
        DB::table('vend_channels')->insert(['vend_id' => $freezer->id, 'code' => random_int(11, 69), 'product_id' => $product->id, 'amount' => 22]);
    }

    public function test_the_documented_callback_matches_our_code_in_attach_after_a_library_check(): void
    {
        $this->library(['0726436016209']);
        $chocolate = $this->product('CC-02');

        $this->documented(true, ['productCode' => '0726436016209', 'skuName' => 'Basque Cheesecake - Chocolate', 'attach' => 'CC-02'])
            ->assertOk()->assertJson(['status' => 200, 'body' => 'SUCCESS']);

        $this->assertSame('0726436016209', $chocolate->fresh()->barcode);
    }

    public function test_a_portal_application_without_our_code_matches_a_unique_freezer_product_by_name(): void
    {
        $this->library(['0726436016209']);
        $chocolate = $this->product('CC-02');
        $chocolate->forceFill(['name' => 'Basque Cheesecake - Chocolate'])->save();
        $this->onFreezer($chocolate);

        $this->documented(true, ['productCode' => '0726436016209', 'skuName' => 'basque cheesecake chocolate', 'applicationNo' => '1766037363852'])->assertOk();

        $this->assertSame('0726436016209', $chocolate->fresh()->barcode);
    }

    public function test_an_unsigned_callback_cannot_plant_a_barcode_their_library_does_not_have(): void
    {
        $this->library([]);
        $chocolate = $this->product('CC-02');

        $this->documented(true, ['productCode' => '1234567890123', 'attach' => 'CC-02'])->assertOk();

        $this->assertNull($chocolate->fresh()->barcode);
        $this->assertSame('not_in_library', ZijiaSkuNotification::sole()->outcome);
    }

    public function test_once_a_token_is_set_the_callback_must_carry_it(): void
    {
        config(['smart_freezer.zijia.sku_callback_token' => 'sku-token']);
        $this->library(['0726436016209']);
        $chocolate = $this->product('CC-02');

        $this->documented(true, ['productCode' => '0726436016209', 'attach' => 'CC-02'])->assertJson(['status' => 500]);
        $this->assertNull($chocolate->fresh()->barcode);

        $this->documented(true, ['productCode' => '0726436016209', 'attach' => 'CC-02'], '?token=sku-token')->assertJson(['status' => 200]);
        $this->assertSame('0726436016209', $chocolate->fresh()->barcode);
    }

    public function test_a_rejection_clears_only_the_barcode_it_names(): void
    {
        $chocolate = $this->product('CC-02', '0726436016209');

        $this->documented(false, ['productCode' => '0726436016209', 'attach' => 'CC-02'])->assertOk();

        $this->assertNull($chocolate->fresh()->barcode);
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
