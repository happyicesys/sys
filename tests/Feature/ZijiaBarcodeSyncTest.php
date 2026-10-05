<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vend;
use App\Services\CardSettlement\CardSettlementHealthCheck;
use App\Services\SmartFreezer\Zijia\ZijiaBarcodeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Freezer products with no barcode get one from Zijia's SKU library — only when exactly one
 * library product has exactly the same name (Brian, 2026-10-05).
 */
class ZijiaBarcodeSyncTest extends TestCase
{
    use RefreshDatabase;

    private Vend $freezer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['smart_freezer.zijia.algorithm.base_url' => 'https://algo.test']);
        $vend = new Vend;
        $vend->forceFill(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1])->save();
        $this->freezer = $vend;

        // Their library, searched by name (substring), as the live API answers.
        $library = [
            ['skuName' => 'Basque Cheesecake - Chocolate', 'productCode' => '0726436016209'],
            ['skuName' => 'Cat, Basque Cheesecake Original', 'productCode' => '9726436016148'],
            ['skuName' => 'Cornetto Classic Chocolate', 'productCode' => '8851932434300'],
            ['skuName' => 'Cornetto Classic Chocolate', 'productCode' => '1111111111111'],
        ];
        Http::fake(['algo.test/*' => function ($request) use ($library) {
            $name = mb_strtolower((string) ($request->data()['skuName'] ?? ''));

            return Http::response(['list' => array_values(array_filter($library, fn ($s) => str_contains(mb_strtolower($s['skuName']), $name))),
                'totalPage' => 1, 'currentPage' => 1, 'totalCount' => 1]);
        }]);
    }

    private function onFreezer(string $name, ?string $barcode = null, ?Vend $vend = null): Product
    {
        $product = Product::forceCreate(['code' => 'P'.uniqid(), 'name' => $name, 'operator_id' => 1, 'barcode' => $barcode]);
        DB::table('vend_channels')->insert(['vend_id' => ($vend ?? $this->freezer)->id, 'code' => random_int(11, 69), 'product_id' => $product->id, 'amount' => 100]);

        return $product;
    }

    public function test_a_unique_exact_name_match_fills_the_barcode_and_is_audited(): void
    {
        $chocolate = $this->onFreezer('Basque Cheesecake - Chocolate');

        $this->artisan('smart-freezer:zijia-barcode-sync')->assertSuccessful();

        $this->assertSame('0726436016209', $chocolate->fresh()->barcode);
        $this->assertTrue(DB::table('user_logs')->where('auditable_id', $chocolate->id)->where('source', 'zijia-sync')->exists());
    }

    public function test_a_near_name_is_not_a_match(): void
    {
        // Their entry is "Cat, Basque Cheesecake Original": close, but not our name.
        $original = $this->onFreezer('Basque Cheesecake Original');

        app(ZijiaBarcodeSync::class)->run();

        $this->assertNull($original->fresh()->barcode);
    }

    public function test_two_library_products_with_our_name_are_left_for_a_person_and_reported(): void
    {
        $classic = $this->onFreezer('Cornetto Classic Chocolate');

        app(ZijiaBarcodeSync::class)->run();

        $this->assertNull($classic->fresh()->barcode);
        $section = collect(app(CardSettlementHealthCheck::class)->run())->firstWhere('key', 'freezer_no_barcode');
        $this->assertStringContainsString('several products with this name', $section['items'][0]['text']);
    }

    public function test_an_existing_barcode_is_never_overwritten_and_other_machines_are_ignored(): void
    {
        $set = $this->onFreezer('Basque Cheesecake - Chocolate', '0000000000001');
        $vending = new Vend;
        $vending->forceFill(['code' => 2031, 'machine_type' => 'vending', 'is_active' => 1, 'operator_id' => 1, 'vend_model_id' => 1])->save();
        $onVending = $this->onFreezer('Basque Cheesecake - Chocolate', null, $vending);

        app(ZijiaBarcodeSync::class)->run();

        $this->assertSame('0000000000001', $set->fresh()->barcode);
        $this->assertNull($onVending->fresh()->barcode, 'only smart-freezer planogram products are synced');
    }

    public function test_names_compare_without_case_or_punctuation(): void
    {
        $this->assertSame(ZijiaBarcodeSync::normalise('Basque Cheesecake - Chocolate'), ZijiaBarcodeSync::normalise('basque cheesecake chocolate'));
        $this->assertNotSame(ZijiaBarcodeSync::normalise('Basque Cheesecake Original'), ZijiaBarcodeSync::normalise('Cat, Basque Cheesecake Original'));
    }
}
