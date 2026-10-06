<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vend;
use App\Models\ZijiaSkuApplication;
use App\Services\SmartFreezer\Zijia\ZijiaLibraryImport;
use App\Services\SmartFreezer\Zijia\ZijiaSkuApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a product was approved with in vms4 is mirrored into mark1 from Zijia's library — details
 * and photos copied to our storage — and kept in step when changed there (2026-10-06).
 */
class ZijiaLibraryImportTest extends TestCase
{
    use RefreshDatabase;

    private Product $chocolate;

    private array $entry;

    /** @var list<string> photo URLs the fake storage refuses */
    private array $failing = [];

    private int $downloads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        config(['smart_freezer.zijia.algorithm.base_url' => 'https://algo.test']);

        $vend = new Vend;
        $vend->forceFill(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1])->save();
        $this->chocolate = Product::forceCreate(['code' => 'CC-02', 'name' => 'Basque Cheesecake - Chocolate', 'operator_id' => 1, 'barcode' => '0726436016209']);
        DB::table('vend_channels')->insert(['vend_id' => $vend->id, 'code' => 22, 'product_id' => $this->chocolate->id, 'amount' => 22]);

        $this->entry = [
            'sysSkuId' => 1791006205910842, 'skuName' => 'Basque Cheesecake - Chocolate', 'brandName' => '其他/其他', 'spec' => '120克',
            'category' => 4, 'packageType' => 2, 'productCode' => '0726436016209', 'updatedTime' => '2026-10-03 14:38:59',
            'packageImageUrl' => 'https://oss.test/Goods/pack.jpg',
            'sysDemoPic' => json_encode(['horizontal' => [], 'low' => [], 'high' => [
                'https://oss.test/id/top1.png' => ['url' => 'https://oss.test/id/top1.png'],
                'https://oss.test/id/top2.png' => ['url' => 'https://oss.test/id/top2.png'],
            ]]),
        ];
        $this->fakeZijia();
    }

    private function fakeZijia(): void
    {
        Http::fake(function (HttpRequest $r) {
            if (str_contains($r->url(), 'query_list')) {
                $code = $r->data()['productCode'] ?? null;

                return Http::response(['list' => $code === $this->entry['productCode'] ? [$this->entry] : [], 'totalPage' => 1, 'currentPage' => 1, 'totalCount' => 1]);
            }
            $this->downloads++;
            if (in_array($r->url(), $this->failing, true)) {
                return Http::response('', 404);
            }

            return Http::response('image-bytes-of-'.basename($r->url()), 200, ['Content-Type' => 'image/png']);
        });
    }

    private function import(): string
    {
        return app(ZijiaLibraryImport::class)->sync($this->chocolate->fresh());
    }

    public function test_an_approved_product_is_mirrored_with_its_photos_copied_to_our_storage(): void
    {
        $this->assertSame('imported', $this->import());

        $app = ZijiaSkuApplication::sole();
        $this->assertSame([ZijiaSkuApplication::STATUS_APPROVED, ZijiaSkuApplication::SOURCE_VMS4], [$app->status, $app->source]);
        $this->assertSame(['Basque Cheesecake - Chocolate', '其他/其他', '120克', 4, 2, '0726436016209', '1791006205910842'],
            [$app->sku_name, $app->brand_name, $app->spec, $app->category, $app->package_type, $app->product_code, $app->sys_sku_id]);
        $this->assertCount(2, $app->model_pics['high']);
        foreach ([...$app->model_pics['high'], $app->package_image_url] as $url) {
            $this->assertStringNotContainsString('oss.test', $url, 'our copy, not their link');
        }
        $this->assertCount(3, Storage::allFiles("sys/zijia-sku/{$this->chocolate->id}/vms4"));
        $this->assertSame('vms4.imported', $app->events()->sole()->event);
    }

    public function test_nothing_happens_while_vms4_has_not_changed_and_a_change_there_is_picked_up(): void
    {
        $this->import();
        $this->downloads = 0;
        $this->assertSame('unchanged', $this->import());
        $this->assertSame(0, $this->downloads, 'nothing is downloaded on an unchanged run');

        // A new top-view photo and spec added in vms4.
        $demo = json_decode($this->entry['sysDemoPic'], true);
        $demo['high']['https://oss.test/id/top3.png'] = ['url' => 'https://oss.test/id/top3.png'];
        $this->entry = ['sysDemoPic' => json_encode($demo), 'spec' => '125克', 'updatedTime' => '2026-10-06 10:00:00'] + $this->entry;

        $this->assertSame('updated', $this->import());
        $app = ZijiaSkuApplication::sole();
        $this->assertSame('125克', $app->spec);
        $this->assertCount(3, $app->model_pics['high']);
        $this->assertSame(1, $this->downloads, 'only the NEW photo is downloaded');
        $this->assertSame(['spec', 'model_pics'], $app->events()->where('event', 'vms4.updated')->sole()->detail['changed']);
    }

    public function test_a_product_approved_through_mark1_is_not_mirrored_twice(): void
    {
        ZijiaSkuApplication::query()->create(['product_id' => $this->chocolate->id, 'application_no' => '1', 'status' => 'approved',
            'source' => 'mark1', 'product_code' => '0726436016209']);

        $this->assertSame('approved_in_mark1', $this->import());
        $this->assertSame(1, ZijiaSkuApplication::count());
    }

    public function test_a_draft_being_worked_on_stays_the_current_application(): void
    {
        $draft = ZijiaSkuApplication::query()->create(['product_id' => $this->chocolate->id, 'application_no' => '1', 'status' => 'draft', 'source' => 'mark1']);
        $this->import();

        $this->assertSame($draft->id, app(ZijiaSkuApplicationService::class)->current($this->chocolate)->id);
    }

    public function test_a_photo_that_cannot_be_copied_keeps_their_link_and_is_logged(): void
    {
        $this->failing = ['https://oss.test/id/top2.png'];

        $this->import();

        $app = ZijiaSkuApplication::sole();
        $this->assertContains('https://oss.test/id/top2.png', $app->model_pics['high']);
        $event = $app->events()->sole();
        $this->assertSame('warning', $event->level);
        $this->assertSame(['https://oss.test/id/top2.png'], $event->detail['photos_not_copied']);
    }

    public function test_the_three_minute_sync_mirrors_every_barcoded_freezer_product(): void
    {
        $this->artisan('smart-freezer:zijia-barcode-sync')->expectsOutputToContain('vms4: imported')->assertSuccessful();
        $this->artisan('smart-freezer:zijia-barcode-sync')->doesntExpectOutputToContain('vms4: imported')->assertSuccessful();

        $this->assertSame(1, ZijiaSkuApplication::count());
    }
}
