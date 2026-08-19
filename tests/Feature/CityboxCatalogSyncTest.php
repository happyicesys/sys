<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\SyncCityboxCatalog;
use App\Models\CityboxProduct;
use App\Models\CityboxProductSyncLog;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Vend;
use App\Services\Citybox\CatalogSyncService;
use App\Services\Citybox\CityboxOpenapiSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

class CityboxCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    protected function setUp(): void
    {
        parent::setUp();
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1]);
        (new \Database\Seeders\CityboxOperatorSeeder)->run();
        Storage::fake('public');
        config(['filesystems.default' => 'local']);
        // Any CityBox CDN url answers with a small PNG unless a test overrides it.
        Http::fake(['cdn.icitybox.cn/*' => Http::response($this->png(64, 64), 200, ['Content-Type' => 'image/png'])]);
    }

    /** A real PNG of the given size, noisy so it does not compress to nothing. */
    private function png(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand(1);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                imagesetpixel($im, $x, $y, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        ob_start();
        imagepng($im, null, 0);

        return ob_get_clean();
    }

    private function cbOperatorId(): int
    {
        return Operator::where('code', 'CB')->value('id');
    }

    // ── auto-created mark1 products (Brian 2026-08-19: no mapping step) ─────

    public function test_catalog_run_creates_a_mark1_product_per_sku_and_links_it(): void
    {
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola', 'https://cdn.icitybox.cn/a.png'), $this->catalogRow(90340, 'KSF Peach')];
        $log = app(CatalogSyncService::class)->syncCatalog();

        $this->assertEqualsCanonicalizing([89925, 90340], $log->details_json['products_created']);
        $row = CityboxProduct::where('citybox_product_id', 89925)->first();
        $product = Product::withoutGlobalScopes()->find($row->product_id);
        $this->assertNotNull($product);
        $this->assertSame('89925', (string) $product->code);
        $this->assertSame('Cocacola', $product->name);
        $this->assertSame($this->cbOperatorId(), (int) $product->operator_id);
        $this->assertSame('ledger', $product->warehouse_qty_source);
        $this->assertSame('citybox:https://cdn.icitybox.cn/a.png', $product->thumbnail->desc);
        $this->assertStringContainsString('sys/products/citybox/cb-89925.', $product->thumbnail->local_url);
        Storage::disk('public')->assertExists($product->thumbnail->local_url);
        $this->assertNotNull($row->mapped_at);
        $this->assertNull($row->mapped_by);
    }

    public function test_rerun_never_duplicates_products_and_follows_rename_and_image(): void
    {
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola', 'https://cdn.icitybox.cn/a.png')];
        app(CatalogSyncService::class)->syncCatalog();
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Coca-Cola 330ml', 'https://cdn.icitybox.cn/b.png')];
        $log = app(CatalogSyncService::class)->syncCatalog();
        app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame([], $log->details_json['products_created']);
        $this->assertSame(1, Product::withoutGlobalScopes()->where('code', '89925')->count());
        $product = Product::withoutGlobalScopes()->where('code', '89925')->first();
        $this->assertSame('Coca-Cola 330ml', $product->name);
        $this->assertSame('citybox:https://cdn.icitybox.cn/b.png', $product->fresh()->thumbnail->desc);
        Http::assertSentCount(2); // a.png once, b.png once — the third run re-downloaded nothing
        $this->assertSame($product->id, CityboxProduct::where('citybox_product_id', 89925)->first()->product_id);
    }

    public function test_existing_product_with_that_code_is_reused_not_duplicated(): void
    {
        $mine = Product::create(['code' => '89925', 'name' => 'Coke (pre-made)', 'operator_id' => 1]);
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola')];
        $log = app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame([], $log->details_json['products_created']);
        $this->assertSame(1, Product::withoutGlobalScopes()->where('code', '89925')->count());
        $this->assertSame($mine->id, CityboxProduct::where('citybox_product_id', 89925)->first()->product_id);
        $this->assertSame('ledger', $mine->fresh()->warehouse_qty_source);
    }

    public function test_human_mapping_is_respected_and_its_product_is_not_renamed(): void
    {
        $human = Product::create(['code' => 'MYCOKE', 'name' => 'My Coke', 'operator_id' => 1]);
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola')];
        app(CatalogSyncService::class)->syncCatalog();
        CityboxProduct::where('citybox_product_id', 89925)->update(['product_id' => $human->id]);

        $this->gw->catalogRows = [$this->catalogRow(89925, 'Renamed')];
        app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame($human->id, CityboxProduct::where('citybox_product_id', 89925)->first()->product_id);
        $this->assertSame('My Coke', $human->fresh()->name);
    }

    public function test_oversized_citybox_image_is_shrunk_under_490kb_on_our_storage(): void
    {
        $big = $this->png(1600, 1600);
        $this->assertGreaterThan(490 * 1024, strlen($big));
        Http::fake(['cdn.icitybox.cn/*' => Http::response($big, 200, ['Content-Type' => 'image/png'])]);

        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola', 'https://cdn.icitybox.cn/big.png')];
        app(CatalogSyncService::class)->syncCatalog();

        $thumb = Product::withoutGlobalScopes()->where('code', '89925')->first()->thumbnail;
        $this->assertLessThanOrEqual(490 * 1024, Storage::disk('public')->size($thumb->local_url));
        $this->assertSame('citybox:https://cdn.icitybox.cn/big.png', $thumb->desc);
    }

    public function test_failed_image_download_hotlinks_for_now_and_retries_next_sync(): void
    {
        // First sync: the CDN 500s (twice — the importer retries once); second sync: it recovers.
        Http::fakeSequence('img.icitybox.cn/*')->push('nope', 500)->push('nope', 500)
            ->push($this->png(64, 64), 200, ['Content-Type' => 'image/png']);
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola', 'https://img.icitybox.cn/a.png')];
        app(CatalogSyncService::class)->syncCatalog();
        $thumb = Product::withoutGlobalScopes()->where('code', '89925')->first()->thumbnail;
        $this->assertSame('https://img.icitybox.cn/a.png', $thumb->full_url);
        $this->assertSame('citybox-remote:https://img.icitybox.cn/a.png', $thumb->desc);

        app(CatalogSyncService::class)->syncCatalog();
        $this->assertSame('citybox:https://img.icitybox.cn/a.png', $thumb->fresh()->desc);
    }

    public function test_human_uploaded_thumbnail_is_never_overwritten(): void
    {
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola', 'https://cdn.icitybox.cn/a.png')];
        app(CatalogSyncService::class)->syncCatalog();
        $product = Product::withoutGlobalScopes()->where('code', '89925')->first();
        $product->thumbnail->update(['full_url' => 'https://ours.example/mine.png', 'local_url' => 'sys/products/mine.png', 'desc' => null]);

        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola', 'https://cdn.icitybox.cn/b.png')];
        app(CatalogSyncService::class)->syncCatalog();
        $this->assertSame('https://ours.example/mine.png', $product->fresh()->thumbnail->full_url);
    }

    public function test_device_poll_discovered_sku_also_gets_a_product(): void
    {
        $this->gw->seedDevice('E1');
        $this->gw->seedStock('E1', [['id' => 90999, 'name' => 'New on shelf', 'quantity' => 2, 'par' => 5, 'price' => 200]]);
        app(CatalogSyncService::class)->noteSeenOnDevice($this->gw->deviceStock('E1'));

        $row = CityboxProduct::where('citybox_product_id', 90999)->first();
        $this->assertNotNull($row->product_id);
        $this->assertSame('90999', (string) Product::withoutGlobalScopes()->find($row->product_id)->code);
    }

    /** Literal product_list shape (2026-08-19), incl. the dead product_id=0 SKU field. */
    private function catalogRow(int $id, string $name, string $img = 'https://cdn/x.png'): array
    {
        return ['id' => (string) $id, 'product_id' => '0', 'product_name' => $name, 'img_url' => $img,
            'vision_img' => 'https://cdn/v.png', 'vision_img2' => '', 'vision_img3' => '', 'vision_img4' => ''];
    }

    // ── catalog run: add / update / unchanged / delist ─────────────────────

    public function test_first_catalog_run_adds_every_row_and_logs_it(): void
    {
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola'), $this->catalogRow(90340, 'KSF Peach')];

        $log = app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame(2, CityboxProduct::count());
        $this->assertSame([2, 2, 0, 0, 0], [$log->fetched, $log->added, $log->updated, $log->delisted, $log->unchanged]);
        $this->assertSame([89925, 90340], $log->details_json['added']);
        $this->assertNull(CityboxProduct::where('citybox_product_id', 89925)->first()->sku_code); // "0" ⇒ absent
        $this->assertNotNull(CityboxProduct::first()->product_id); // auto-linked to a created mark1 product (2026-08-19)
    }

    public function test_second_run_is_idempotent_and_classifies_updates(): void
    {
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola'), $this->catalogRow(90340, 'KSF Peach')];
        app(CatalogSyncService::class)->syncCatalog();

        $this->gw->catalogRows = [$this->catalogRow(89925, 'Coca-Cola 330ml'), $this->catalogRow(90340, 'KSF Peach')]; // one rename
        $log = app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame(2, CityboxProduct::count()); // NO duplicate
        $this->assertSame([0, 1, 0, 1], [$log->added, $log->updated, $log->delisted, $log->unchanged]);
        $this->assertSame([89925], $log->details_json['updated']);
        $this->assertSame('Coca-Cola 330ml', CityboxProduct::where('citybox_product_id', 89925)->first()->name);
    }

    public function test_full_run_soft_delists_absent_rows_and_reappearance_relists(): void
    {
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola'), $this->catalogRow(90340, 'KSF Peach')];
        app(CatalogSyncService::class)->syncCatalog();

        $this->gw->catalogRows = [$this->catalogRow(90340, 'KSF Peach')];
        $log = app(CatalogSyncService::class)->syncCatalog();
        $this->assertSame(1, $log->delisted);
        $this->assertTrue(CityboxProduct::where('citybox_product_id', 89925)->first()->is_delisted);
        $this->assertSame(2, CityboxProduct::count()); // soft — row kept

        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola'), $this->catalogRow(90340, 'KSF Peach')];
        app(CatalogSyncService::class)->syncCatalog();
        $this->assertFalse(CityboxProduct::where('citybox_product_id', 89925)->first()->is_delisted);
    }

    public function test_catalog_run_never_touches_a_human_mapping(): void
    {
        $product = Product::create(['code' => 'CB-TEST-1', 'name' => 'Test Coke']);
        $this->gw->catalogRows = [$this->catalogRow(89925, 'Cocacola')];
        app(CatalogSyncService::class)->syncCatalog();
        CityboxProduct::where('citybox_product_id', 89925)->update(['product_id' => $product->id, 'mapped_at' => now()]);

        $this->gw->catalogRows = [$this->catalogRow(89925, 'Renamed')];
        app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame($product->id, CityboxProduct::where('citybox_product_id', 89925)->first()->product_id);
    }

    public function test_api_failure_is_logged_on_the_run_row_and_rethrown(): void
    {
        $this->gw->catalogRows = []; // fine
        // Force a throw via a gateway that fails on catalog():
        $failing = new class extends FakeChillerGateway
        {
            public function catalog(array $filters = []): \Illuminate\Support\Collection
            {
                throw new \App\Exceptions\CityboxApiException('unreachable after retries');
            }
        };
        $this->app->instance(ChillerGateway::class, $failing);

        try {
            app(CatalogSyncService::class)->syncCatalog();
            $this->fail('expected throw');
        } catch (\App\Exceptions\CityboxApiException) {
        }

        $log = CityboxProductSyncLog::sole();
        $this->assertStringContainsString('unreachable', $log->error);
        $this->assertNotNull($log->finished_at);
    }

    // ── device-poll path ───────────────────────────────────────────────────

    public function test_stock_poll_upserts_unknown_products_and_enriches_but_never_delists(): void
    {
        $vend = Vend::create(['code' => 9300, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);
        // Catalog knows 89925 only (no volume/unit); the device shows 89925 + a brand-new 90340.
        CityboxProduct::create(['citybox_product_id' => 89925, 'name' => 'Cocacola', 'first_seen_at' => now()]);
        $this->gw->seedDevice('E1')->seedStock('E1', [
            ['id' => 89925, 'name' => 'Cocacola', 'qty' => 1, 'price' => '0.10'],
            ['id' => 90340, 'name' => 'KSF Peach', 'qty' => 2, 'price' => '0.12'],
        ]);

        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame(2, CityboxProduct::count());
        $coke = CityboxProduct::where('citybox_product_id', 89925)->first();
        $this->assertSame('500ml', $coke->volume);        // enriched from device
        $this->assertSame(10, $coke->last_price_cents);
        $this->assertSame('device', $coke->last_seen_source);
        $new = CityboxProduct::where('citybox_product_id', 90340)->first();
        $this->assertSame('KSF Peach', $new->name);
        $this->assertFalse($new->is_delisted);
        // A device-poll run logged only the ADDED id, and delisted nothing.
        $log = CityboxProductSyncLog::where('source', CityboxProductSyncLog::SOURCE_DEVICE_POLL)->sole();
        $this->assertSame([90340], $log->details_json['added']);
        $this->assertSame(0, $log->delisted);
    }

    public function test_device_poll_does_not_overwrite_catalog_owned_name(): void
    {
        CityboxProduct::create(['citybox_product_id' => 89925, 'name' => 'Coca-Cola 330ml (catalog)', 'first_seen_at' => now()]);
        Vend::create(['code' => 9301, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1]);
        $this->gw->seedDevice('E1')->seedStock('E1', [['id' => 89925, 'name' => 'Cocacola', 'qty' => 1]]);

        app(CityboxOpenapiSync::class)->syncAll();

        $this->assertSame('Coca-Cola 330ml (catalog)', CityboxProduct::first()->name);
    }

    // ── scheduler / command ────────────────────────────────────────────────

    public function test_command_is_noop_when_disabled_and_dispatches_when_enabled(): void
    {
        Queue::fake();
        config(['citybox.openapi.enabled' => false]);
        $this->artisan('citybox:sync-products')->assertSuccessful();
        Queue::assertNothingPushed();

        config(['citybox.openapi.enabled' => true]);
        $this->artisan('citybox:sync-products')->assertSuccessful();
        Queue::assertPushed(SyncCityboxCatalog::class, 1);
    }
}
