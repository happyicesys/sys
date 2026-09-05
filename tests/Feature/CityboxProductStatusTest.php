<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxProduct;
use App\Models\Operator;
use App\Models\Product;
use App\Services\Citybox\CatalogSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * CityBox's undocumented `status` on product_list — the only signal that a SKU
 * has been retired, since their catalog returns disabled rows forever
 * (confirmed live 2026-09-05: 24 of 53 disabled, none ever absent).
 *
 * 0 = disabled, 1 = enabled, anything else unconfirmed and read as NOT enabled.
 * A row with NO status field must change nothing at all — their API omitted it
 * entirely until this month, and silence must never read as "all disabled".
 */
class CityboxProductStatusTest extends TestCase
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
        Http::fake(['*' => Http::response('', 200)]);
    }

    private function row(int $id, string $name, ?int $status = null): array
    {
        $r = ['id' => (string) $id, 'product_id' => '0', 'product_name' => $name, 'img_url' => '',
            'vision_img' => '', 'vision_img2' => '', 'vision_img3' => '', 'vision_img4' => ''];
        if ($status !== null) {
            $r['status'] = (string) $status;
        }

        return $r;
    }

    private function productFor(int $cityboxId): Product
    {
        $row = CityboxProduct::where('citybox_product_id', $cityboxId)->firstOrFail();

        return Product::withoutGlobalScopes()->findOrFail($row->product_id);
    }

    // ── the flag itself ───────────────────────────────────────────────────

    public function test_status_is_mirrored_onto_the_citybox_row(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 0), $this->row(90363, 'KSF Cup Noodle new', 1)];

        app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame(0, CityboxProduct::where('citybox_product_id', 90332)->value('citybox_status'));
        $this->assertSame(1, CityboxProduct::where('citybox_product_id', 90363)->value('citybox_status'));
        $this->assertNotNull(CityboxProduct::where('citybox_product_id', 90332)->value('citybox_status_at'));
    }

    public function test_disabled_sku_deactivates_its_mark1_product(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        app(CatalogSyncService::class)->syncCatalog();
        $this->assertTrue((bool) $this->productFor(90332)->is_active);

        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 0)];
        $r = app(CatalogSyncService::class)->syncStatuses();

        $this->assertFalse((bool) $this->productFor(90332)->is_active);
        $this->assertSame(1, $r['deactivated']);
        $this->assertSame(0, $r['reactivated']);
    }

    public function test_reenabling_in_citybox_reactivates_the_product(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 0)];
        app(CatalogSyncService::class)->syncCatalog();
        $this->assertFalse((bool) $this->productFor(90332)->is_active);

        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        $r = app(CatalogSyncService::class)->syncStatuses();

        $this->assertTrue((bool) $this->productFor(90332)->is_active);
        $this->assertSame(1, $r['reactivated']);
    }

    public function test_a_sku_created_while_already_disabled_is_born_inactive(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 0)];

        app(CatalogSyncService::class)->syncCatalog();

        $this->assertFalse((bool) $this->productFor(90332)->is_active);
    }

    // ── the safety rails ──────────────────────────────────────────────────

    public function test_a_payload_without_status_never_deactivates_anything(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        app(CatalogSyncService::class)->syncCatalog();

        // Their API carried no `status` at all before 2026-09-05. If it ever
        // stops again, silence must not read as "every SKU is disabled".
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle')];
        $r = app(CatalogSyncService::class)->syncStatuses();

        $this->assertTrue((bool) $this->productFor(90332)->is_active);
        $this->assertSame(0, $r['deactivated']);
        $this->assertSame(1, CityboxProduct::where('citybox_product_id', 90332)->value('citybox_status'));
    }

    public function test_an_unrecognised_status_is_treated_as_not_enabled_and_logged(): void
    {
        Log::spy();
        $this->gw->catalogRows = [$this->row(90348, 'Lalune Cheese Bread', 99)];

        app(CatalogSyncService::class)->syncCatalog();

        $this->assertFalse((bool) $this->productFor(90348)->is_active);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c = []) => str_contains($m, 'unrecognised product status')
            && ($c['status_by_citybox_product_id'][90348] ?? null) === 99)->once();
    }

    public function test_a_human_mapping_is_never_flipped_by_their_status(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        app(CatalogSyncService::class)->syncCatalog();

        // Re-point the mirror at a product a human owns: its code is OURS, not their id.
        $ours = Product::withoutGlobalScopes()->create([
            'code' => 'U-79', 'name' => 'Our own SKU', 'operator_id' => Operator::where('code', 'HIPL')->value('id'),
            'is_inventory' => true, 'is_active' => true, 'is_available' => true, 'measurement_count' => 1,
        ]);
        CityboxProduct::where('citybox_product_id', 90332)->update(['product_id' => $ours->id]);

        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 0)];
        $r = app(CatalogSyncService::class)->syncStatuses();

        $this->assertTrue((bool) Product::withoutGlobalScopes()->find($ours->id)->is_active);
        $this->assertSame(0, $r['deactivated']);
    }

    // ── the light path stays light ────────────────────────────────────────

    public function test_status_sync_does_not_create_products_or_delist(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        app(CatalogSyncService::class)->syncCatalog();

        // A SKU the hourly run has not seen yet must be ignored, not created.
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1), $this->row(90999, 'Brand new', 1)];
        app(CatalogSyncService::class)->syncStatuses();

        $this->assertNull(CityboxProduct::where('citybox_product_id', 90999)->first());
        // And a SKU absent from this payload is NOT delisted — only the full run may.
        $this->gw->catalogRows = [$this->row(90999, 'Brand new', 1)];
        app(CatalogSyncService::class)->syncStatuses();
        $this->assertFalse((bool) CityboxProduct::where('citybox_product_id', 90332)->value('is_delisted'));
    }

    public function test_unchanged_status_is_not_rewritten_every_run(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        app(CatalogSyncService::class)->syncCatalog();
        $touched = CityboxProduct::where('citybox_product_id', 90332)->value('citybox_status_at');

        $r = app(CatalogSyncService::class)->syncStatuses();

        $this->assertSame(0, $r['changed']);
        $this->assertEquals($touched, CityboxProduct::where('citybox_product_id', 90332)->value('citybox_status_at'));
    }

    // ── one product per CityBox SKU, however often we run ─────────────────

    public function test_repeated_runs_never_create_a_second_product_for_one_sku(): void
    {
        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];

        app(CatalogSyncService::class)->syncCatalog();
        app(CatalogSyncService::class)->syncCatalog();
        app(CatalogSyncService::class)->syncStatuses();

        $this->assertSame(1, Product::withoutGlobalScopes()->where('code', '90332')->count());
    }

    public function test_an_existing_product_with_that_code_is_reused_not_duplicated(): void
    {
        // Someone already created it by hand under another operator.
        $existing = Product::withoutGlobalScopes()->create([
            'code' => '90332', 'name' => 'Pre-existing', 'operator_id' => Operator::where('code', 'HIPL')->value('id'),
            'is_inventory' => true, 'is_active' => true, 'is_available' => true, 'measurement_count' => 1,
        ]);

        $this->gw->catalogRows = [$this->row(90332, 'KSF Cup Noodle', 1)];
        app(CatalogSyncService::class)->syncCatalog();

        $this->assertSame(1, Product::withoutGlobalScopes()->where('code', '90332')->count());
        $this->assertSame($existing->id, CityboxProduct::where('citybox_product_id', 90332)->value('product_id'));
    }
}
