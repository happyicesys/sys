<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\ZijiaSkuApplication;
use App\Models\ZijiaSkuNotification;
use App\Services\SmartFreezer\Zijia\ZijiaSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Product → Edit → Smart Freezer AI Training (2026-10-06): a product's modelling application to
 * Zijia (§5 sys.sku.sync.put), their approval callback (§7), and the log of every exchange.
 */
class ZijiaAiTrainingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'app-secret-under-test';

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'), ['url' => 'https://space.test']);
        // The product thumbnail a draft starts from is fetched and squared on the first save.
        Http::preventStrayRequests();
        $thumb = imagecreatetruecolor(300, 200);
        ob_start();
        imagejpeg($thumb);
        Http::fake(['cdn.test/*' => Http::response((string) ob_get_clean(), 200, ['Content-Type' => 'image/jpeg'])]);
        config(['smart_freezer.zijia.algorithm' => [
            'base_url' => 'https://algo.test', 'app_id' => '1789379222883159', 'app_secret' => self::SECRET,
            'model_ids' => [], 'notify_url' => null, 'auto_submit' => false, 'timeout' => 5,
            'callback_verification' => 'enforce', 'timezone' => 'Asia/Shanghai',
        ], 'smart_freezer.zijia.sku_callback_token' => null]);

        $this->user = User::factory()->create(['name' => 'Ops One', 'operator_id' => 1]);
        foreach (['read products', 'update products'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->product = Product::forceCreate([
            'code' => 'CC-02', 'name' => 'Basque Cheesecake - Chocolate', 'operator_id' => 1,
            'measurement_value' => 120, 'measurement_unit' => 'g',
        ]);
        $this->product->thumbnail()->create(['type' => 1, 'full_url' => 'https://cdn.test/sys/products/choc.png', 'local_url' => 'x']);
    }

    private function save(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training", $data);
    }

    private function fullDraft(): ZijiaSkuApplication
    {
        $this->save([
            'sku_name' => 'Basque Cheesecake - Chocolate', 'brand_name' => '其他/其他', 'spec' => '120克',
            'category' => 4, 'package_type' => 3, 'product_code' => '0726436016209',
            'photos' => ['high' => [UploadedFile::fake()->image('top1.jpg', 40, 30), UploadedFile::fake()->image('top2.jpg', 30, 40)]],
        ])->assertSessionHasNoErrors();

        return ZijiaSkuApplication::sole();
    }

    public function test_the_section_starts_from_what_mark1_knows_about_the_product(): void
    {
        $this->actingAs($this->user)->get("/products/{$this->product->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aiTraining.application', null)
                ->where('aiTraining.defaults.sku_name', 'Basque Cheesecake - Chocolate')
                ->where('aiTraining.defaults.spec', '120克')
                ->where('aiTraining.defaults.package_image_url', 'https://cdn.test/sys/products/choc.png')
                ->has('aiTraining.categoryOptions')
                ->has('aiTraining.packageTypeOptions'));
    }

    public function test_a_draft_saves_fields_and_photos_and_logs_both(): void
    {
        $app = $this->fullDraft();

        $this->assertSame(ZijiaSkuApplication::STATUS_DRAFT, $app->status);
        $this->assertSame('0726436016209', $app->product_code);
        $this->assertCount(2, $app->model_pics['high']);
        // photos.squared: the product thumbnail the draft started with, made 1:1.
        $this->assertSame(['draft.created', 'draft.saved', 'photos.squared'], $app->events()->reorder('id')->pluck('event')->all());
        $this->assertNull($this->product->fresh()->barcode, 'the barcode waits for Zijia\'s approval');

        // Removing a photo is logged too.
        $this->save(['remove' => [$app->model_pics['high'][0]]])->assertSessionHasNoErrors();
        $this->assertCount(1, $app->fresh()->model_pics['high']);
    }

    public function test_missing_required_inputs_are_prompted_and_block_submission(): void
    {
        $this->save(['sku_name' => 'Basque Cheesecake - Chocolate'])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training/submit")
            ->assertSessionHasErrors(['package_type', 'product_code', 'model_pics.high']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'algo.test'));

        $this->actingAs($this->user)->get("/products/{$this->product->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('aiTraining.application.missing.package_type'));
    }

    public function test_submit_sends_a_signed_application_with_our_code_and_callback_and_logs_the_exchange(): void
    {
        $app = $this->fullDraft();
        Http::fake(['algo.test/api/algorithm/api' => Http::response(['code' => 0, 'msg' => '提交审核成功', 'data' => ['applicationNo' => '', 'skuId' => 'SKU-77']])]);

        $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training/submit")->assertSessionHasNoErrors();

        Http::assertSent(function (HttpRequest $r) use ($app) {
            $envelope = json_decode($r->body(), true);
            $sku = json_decode($envelope['bizContent'], true)['sku'];

            return $envelope['method'] === 'sys.sku.sync.put'
                && (new ZijiaSigner(self::SECRET))->verify($envelope)
                && $sku['applicationNo'] === $app->application_no
                && $sku['attach'] === 'CC-02'
                && $sku['productCode'] === '0726436016209'
                && $sku['category'] === 4 && $sku['packageType'] === 3
                && str_ends_with($sku['callbackUrl'], '/api/smart-freezer/zijia/sku/notify')
                // The product photo, squared (1:1) into our own storage before it is sent.
                && str_starts_with($sku['packageImageUrl'], "https://space.test/sys/zijia-sku/{$this->product->id}/square/")
                && count($sku['skuModelPic']['high']) === 2;
        });
        $app->refresh();
        $this->assertSame(ZijiaSkuApplication::STATUS_SUBMITTED, $app->status);
        $this->assertSame('SKU-77', $app->zijia_sku_id);
        $this->assertSame($this->user->id, $app->submitted_by);
        $sent = $app->events()->where('event', 'submit.sent')->sole();
        $this->assertSame('sys.sku.sync.put', $sent->detail['envelope']['method'], 'the exact envelope is kept');
        $this->assertSame('Ops One', $sent->user_name);
        $this->assertTrue($app->events()->where('event', 'submit.accepted')->exists());

        // Locked while Zijia reviews it.
        $this->save(['sku_name' => 'changed'])->assertSessionHasErrors('application');
    }

    public function test_a_refused_submission_is_failed_with_their_answer(): void
    {
        $app = $this->fullDraft();
        Http::fake(['algo.test/*' => Http::response(['code' => 500, 'msg' => '商品条形码已存在'])]);

        $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training/submit");

        $app->refresh();
        $this->assertSame(ZijiaSkuApplication::STATUS_FAILED, $app->status);
        $this->assertStringContainsString('商品条形码已存在', $app->last_error);
        $this->assertSame('error', $app->events()->where('event', 'submit.refused')->sole()->level);
    }

    private function submitted(): ZijiaSkuApplication
    {
        $app = $this->fullDraft();
        Http::fake(['algo.test/api/algorithm/api' => Http::response(['code' => 0, 'msg' => '提交审核成功', 'data' => ['skuId' => 'SKU-77']])]);
        $this->actingAs($this->user)->post("/products/{$this->product->id}/ai-training/submit");

        return $app->fresh();
    }

    public function test_their_approval_callback_closes_our_application_and_sets_the_barcode(): void
    {
        $app = $this->submitted();

        $this->postJson('/api/smart-freezer/zijia/sku/notify', ['pass' => true, 'msg' => null, 'sku' => [
            'applicationNo' => $app->application_no, 'productCode' => '0726436016209', 'skuName' => 'Basque Cheesecake - Chocolate',
            'sysSkuId' => 1791006205910842, 'attach' => 'CC-02',
        ]])->assertOk()->assertJson(['status' => 200, 'body' => 'SUCCESS']);

        $app->refresh();
        $this->assertSame(ZijiaSkuApplication::STATUS_APPROVED, $app->status);
        $this->assertSame('1791006205910842', $app->sys_sku_id);
        $this->assertSame('0726436016209', $this->product->fresh()->barcode);
        $this->assertSame(['callback.approved', 'barcode.set'], $app->events()->reorder('id')->whereIn('event', ['callback.approved', 'barcode.set'])->pluck('event')->all());
        $this->assertSame($app->id, ZijiaSkuNotification::sole()->zijia_sku_application_id);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'query_list'));
    }

    public function test_a_rejection_keeps_their_reason_and_a_new_application_copies_it(): void
    {
        $app = $this->submitted();

        $this->postJson('/api/smart-freezer/zijia/sku/notify', ['pass' => false, 'msg' => '申请被拒绝,拒绝原因: 图片不清晰', 'sku' => [
            'applicationNo' => $app->application_no, 'productCode' => '0726436016209',
        ]])->assertOk();

        $app->refresh();
        $this->assertSame(ZijiaSkuApplication::STATUS_REJECTED, $app->status);
        $this->assertSame('申请被拒绝,拒绝原因: 图片不清晰', $app->decision_msg);
        $this->assertNull($this->product->fresh()->barcode);

        $this->save(['start_new' => 1])->assertSessionHasNoErrors();
        $new = ZijiaSkuApplication::query()->latest('id')->first();
        $this->assertNotSame($app->id, $new->id);
        $this->assertNotSame($app->application_no, $new->application_no);
        $this->assertSame(ZijiaSkuApplication::STATUS_DRAFT, $new->status);
        $this->assertSame('0726436016209', $new->product_code);
        $this->assertCount(2, $new->model_pics['high'], 'photos carried over');
    }

    public function test_only_users_who_may_update_products_can_train(): void
    {
        $viewer = User::factory()->create(['operator_id' => 1]);
        $viewer->givePermissionTo(Permission::findOrCreate('read products', 'web'));

        $this->actingAs($viewer)->post("/products/{$this->product->id}/ai-training", ['sku_name' => 'x'])->assertForbidden();
        $this->assertSame(0, ZijiaSkuApplication::count());
    }
}
