<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\SyncCityboxCatalog;
use App\Models\CityboxProduct;
use App\Models\CityboxProductSyncLog;
use App\Models\Product;
use App\Models\Vend;
use App\Services\Citybox\CatalogSyncService;
use App\Services\Citybox\CityboxOpenapiSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        $this->assertNull(CityboxProduct::first()->product_id); // never auto-linked
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
