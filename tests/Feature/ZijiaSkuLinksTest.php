<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Vend;
use App\Models\ZijiaLibrarySku;
use App\Services\CardSettlement\CardSettlementHealthCheck;
use App\Services\OpsDashboard\CriticalAttention;
use App\Services\SmartFreezer\FreezerRecognitionService;
use App\Services\SmartFreezer\Zijia\ZijiaBarcodeSync;
use App\Services\SmartFreezer\Zijia\ZijiaLibraryMirror;
use App\Services\SmartFreezer\Zijia\ZijiaSkuLinks;
use App\Services\SmartFreezer\Zijia\ZijiaSkuMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A SKU registered in Zijia's portal reaches the AI (Brian, 2026-11-03): exact names link by
 * themselves, near names wait for a person, and every gap is shown with its real reason.
 */
class ZijiaSkuLinksTest extends TestCase
{
    use RefreshDatabase;

    private Vend $freezer;

    /** @var list<array<string, mixed>> Zijia's library, oldest first, as the live API lists it */
    private array $library = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['smart_freezer.zijia.algorithm.base_url' => 'https://algo.test']);
        $this->freezer = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        Http::fake(['algo.test/*' => fn ($request) => $this->answer($request->data())]);
    }

    /** Pages like the live API; name and code filter as a plain substring / prefix of what is sent. */
    private function answer(array $data)
    {
        $rows = array_values(array_filter($this->library, function ($s) use ($data) {
            return (! isset($data['skuName']) || str_contains(mb_strtolower($s['skuName']), mb_strtolower($data['skuName'])))
                && (! isset($data['productCode']) || str_starts_with($s['productCode'], $data['productCode']));
        }));
        $size = (int) ($data['pageSize'] ?? 50);
        $current = (int) ($data['current'] ?? 1);

        return Http::response([
            'list' => array_slice($rows, ($current - 1) * $size, $size),
            'totalCount' => count($rows),
            'totalPage' => (int) ceil(count($rows) / $size),
            'currentPage' => $current,
        ]);
    }

    private function registered(string $name, string $code, ?int $sysSkuId = null): void
    {
        static $next = 1793000000000000;
        $this->library[] = ['skuName' => $name, 'productCode' => $code, 'sysSkuId' => $sysSkuId ?? $next++, 'spec' => '50克', 'updatedTime' => '2026-11-03 20:09:46'];
    }

    private function product(string $name, ?string $barcode = null, string $code = ''): Product
    {
        return Product::forceCreate(['code' => $code ?: 'P'.uniqid(), 'name' => $name, 'operator_id' => 1, 'barcode' => $barcode]);
    }

    private function onFreezer(string $name, ?string $barcode = null, string $code = '', bool $active = true): Product
    {
        $product = $this->product($name, $barcode, $code);
        DB::table('vend_channels')->insert(['vend_id' => $this->freezer->id, 'code' => random_int(11, 69), 'product_id' => $product->id, 'amount' => 100, 'is_active' => $active]);

        return $product;
    }

    private function user(array $permissions, int $operatorId = 1): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    public function test_a_punctuation_difference_their_search_cannot_find_now_links(): void
    {
        $product = $this->onFreezer('Mao Shan Wang Durian - family pack');
        $this->registered('Mao Shan Wang Durian-family pack', '202610062');

        app(ZijiaBarcodeSync::class)->run();

        $this->assertSame('202610062', $product->fresh()->barcode);
    }

    public function test_a_near_name_never_links_by_itself_but_is_offered_to_a_person(): void
    {
        $cup = $this->onFreezer('Milk & Musang King Durian Ice Cream cup', code: 'OK-01');
        $this->registered('Milk & MusangKing IceCreamcup', '202610051');

        app(ZijiaBarcodeSync::class)->run();

        $this->assertNull($cup->fresh()->barcode);
        $missing = app(ZijiaSkuLinks::class)->coverage()[$this->freezer->id]['missing'][0];
        $this->assertSame(ZijiaSkuLinks::REASON_POSSIBLE_MATCH, $missing['reason']);
        $this->assertSame('202610051', $missing['suggestions'][0]['product_code']);

        $section = collect(app(CardSettlementHealthCheck::class)->run())->firstWhere('key', 'freezer_no_barcode');
        $this->assertStringContainsString('possible match', $section['items'][0]['text']);
        $this->assertStringNotContainsString('not registered', $section['items'][0]['text']);
    }

    public function test_the_real_near_names_score_above_the_line_and_a_stranger_below(): void
    {
        $this->assertGreaterThanOrEqual(ZijiaSkuMatcher::MIN_SCORE, ZijiaSkuMatcher::score('Milk & Musang King Durian Ice Cream cup', 'Milk & MusangKing IceCreamcup'));
        $this->assertGreaterThanOrEqual(ZijiaSkuMatcher::MIN_SCORE, ZijiaSkuMatcher::score('24K Gold Classic Chocolate Eclair', '24K Gold Classic Choc Eclair'));
        $this->assertGreaterThanOrEqual(ZijiaSkuMatcher::MIN_SCORE, ZijiaSkuMatcher::score("Ben & Jerry's Chocolate Fudge Brownie Mini Cup", 'B&J Choc Fudge Brownie Cup'));
        $this->assertLessThan(ZijiaSkuMatcher::MIN_SCORE, ZijiaSkuMatcher::score('Magnum Mini CAW Variety', 'Paddle Pop Rainbow'));
        $this->assertSame(0, ZijiaSkuMatcher::score('Anything', '红球'));
    }

    public function test_two_library_entries_with_our_name_wait_for_a_person(): void
    {
        $pineapple = $this->onFreezer('Pineapple Chunks');
        $this->registered('Pineapple Chunks', '202610052');
        $this->registered('Pineapple Chunks', '2026110302');

        $results = app(ZijiaBarcodeSync::class)->run();

        $this->assertNull($pineapple->fresh()->barcode);
        $this->assertSame('ambiguous', $results[$pineapple->id]['outcome']);
    }

    public function test_a_product_on_no_freezer_links_only_when_its_name_is_unique(): void
    {
        $this->onFreezer('Anything on a freezer', '111');
        $single = $this->product('Melona Melon');
        $twinA = $this->product('Magnum Almond');
        $twinB = $this->product('Magnum Almond');
        $this->registered('Melona Melon', '8801104123471');
        $this->registered('Magnum Almond', '8711327614109');

        app(ZijiaBarcodeSync::class)->run();

        $this->assertSame('8801104123471', $single->fresh()->barcode);
        $this->assertNull($twinA->fresh()->barcode);
        $this->assertNull($twinB->fresh()->barcode);
    }

    public function test_a_server_with_no_freezer_never_calls_zijia(): void
    {
        $this->freezer->forceFill(['machine_type' => 'vending'])->save();
        $this->registered('Melona Melon', '8801104123471');
        $melona = $this->product('Melona Melon');

        app(ZijiaBarcodeSync::class)->run();

        Http::assertNothingSent();
        $this->assertNull($melona->fresh()->barcode);
    }

    public function test_the_mirror_walks_back_to_the_recent_end_then_stops_early_and_drops_removed_entries(): void
    {
        for ($i = 0; $i < 450; $i++) {
            $this->library[] = ['skuName' => "旧 {$i}", 'productCode' => "old{$i}", 'sysSkuId' => 1641000000000000 + $i];
        }
        for ($i = 0; $i < 450; $i++) {
            $this->registered("New {$i}", "new{$i}", 1793000000000000 + $i);
        }

        $first = app(ZijiaLibraryMirror::class)->refresh();

        $this->assertSame(450, $first['new']);
        $this->assertSame(450, ZijiaLibrarySku::query()->count());
        $this->assertFalse(ZijiaLibrarySku::query()->where('product_code', 'old1')->exists());

        $quiet = app(ZijiaLibraryMirror::class)->refresh();
        $this->assertSame(2, $quiet['pages'], 'a quiet run stops after two pages');

        array_splice($this->library, 460, 1);
        Cache::forget('zijia-library-mirror:walked-back');
        app(ZijiaLibraryMirror::class)->refresh();
        $this->assertSame(449, ZijiaLibrarySku::query()->count(), 'the daily full walk drops what they removed');
    }

    public function test_coverage_counts_active_products_only_and_matches_what_is_sent_to_the_ai(): void
    {
        $this->onFreezer('Listed', '8851932028103');
        $this->onFreezer('Not registered', code: 'U-50');
        $this->onFreezer('Retired', code: 'U-31', active: false);

        $coverage = app(ZijiaSkuLinks::class)->coverage()[$this->freezer->id];

        $this->assertSame(2, $coverage['total']);
        $this->assertSame(1, $coverage['on_list']);
        $this->assertSame(['U-50'], array_column($coverage['missing'], 'code'));
        $this->assertSame(ZijiaSkuLinks::REASON_NOT_REGISTERED, $coverage['missing'][0]['reason']);
        $this->assertCount($coverage['on_list'], app(FreezerRecognitionService::class)->candidateCodes($this->freezer));
    }

    public function test_a_barcode_their_library_dropped_is_reported_as_the_worse_failure(): void
    {
        $gone = $this->onFreezer('Withdrawn', '9999999999999');

        $sync = app(ZijiaBarcodeSync::class);
        $sync->run();
        $sync->importApproved();

        $this->assertSame([$gone->id], $sync->barcodesNotInLibrary());
        $this->assertSame(ZijiaSkuLinks::REASON_BARCODE_UNKNOWN, app(ZijiaSkuLinks::class)->coverage()[$this->freezer->id]['missing'][0]['reason']);
    }

    public function test_needs_attention_lists_a_freezer_with_a_gap_and_only_then(): void
    {
        $this->onFreezer('Not registered', code: 'U-50');
        $this->onFreezer('Listed', '111');

        $groups = collect(app(CriticalAttention::class)->forVendIds([$this->freezer->id])['groups'])->keyBy('key');

        $machines = $groups[CriticalAttention::GROUP_AI_LIST]['machines'];
        $this->assertCount(1, $machines);
        $this->assertStringContainsString('U-50', $machines[0]['detail']);

        DB::table('products')->where('code', 'U-50')->update(['barcode' => '222']);
        $groups = collect(app(CriticalAttention::class)->forVendIds([$this->freezer->id])['groups'])->keyBy('key');
        $this->assertSame(0, $groups[CriticalAttention::GROUP_AI_LIST]['count']);
    }

    public function test_a_person_links_a_near_match_and_the_change_is_audited(): void
    {
        $cup = $this->onFreezer('Milk & Musang King Durian Ice Cream cup', code: 'OK-01');
        $this->registered('Milk & MusangKing IceCreamcup', '202610051');
        app(ZijiaLibraryMirror::class)->refresh();
        $entry = ZijiaLibrarySku::query()->where('product_code', '202610051')->firstOrFail();

        $this->actingAs($this->user(['read ai-recognition', 'update products']))
            ->post('/ai-recognition/sku-links/link', ['product_id' => $cup->id, 'library_sku_id' => $entry->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('202610051', $cup->fresh()->barcode);
        $this->assertTrue(DB::table('user_logs')->where('auditable_id', $cup->id)->where('auditable_type', Product::class)->where('changes', 'like', '%202610051%')->exists());
    }

    public function test_a_link_is_refused_when_it_could_break_a_session(): void
    {
        $cup = $this->onFreezer('Cup', code: 'OK-01');
        $set = $this->onFreezer('Already set', '111', 'U-05');
        $this->registered('Cup', 'taken');
        app(ZijiaLibraryMirror::class)->refresh();
        $entry = ZijiaLibrarySku::query()->where('product_code', 'taken')->firstOrFail();
        $user = $this->user(['read ai-recognition', 'update products']);

        // A barcode already there is never replaced.
        $this->actingAs($user)->post('/ai-recognition/sku-links/link', ['product_id' => $set->id, 'library_sku_id' => $entry->id])->assertSessionHasErrors('link');
        $this->assertSame('111', $set->fresh()->barcode);

        // A code they no longer list would make them refuse every session.
        $this->library = [];
        $this->actingAs($user)->post('/ai-recognition/sku-links/link', ['product_id' => $cup->id, 'library_sku_id' => $entry->id])->assertSessionHasErrors('link');
        $this->assertNull($cup->fresh()->barcode);

        // One code, one product: the verdict maps a code back to one product.
        $this->registered('Cup', 'taken');
        $this->product('Holder', 'taken', 'U-99');
        $this->actingAs($user)->post('/ai-recognition/sku-links/link', ['product_id' => $cup->id, 'library_sku_id' => $entry->id])->assertSessionHasErrors('link');
        $this->assertNull($cup->fresh()->barcode);
    }

    public function test_the_page_needs_its_permission_and_linking_needs_update_products(): void
    {
        $this->actingAs($this->user([]))->get('/ai-recognition/sku-links')->assertForbidden();

        $cup = $this->onFreezer('Cup');
        $entry = ZijiaLibrarySku::query()->create(['sys_sku_id' => '1', 'product_code' => '5', 'sku_name' => 'Cup', 'name_key' => 'cup']);
        $reader = $this->user(['read ai-recognition']);
        $this->actingAs($reader)->post('/ai-recognition/sku-links/link', ['product_id' => $cup->id, 'library_sku_id' => $entry->id])->assertForbidden();
        $this->actingAs($reader)->post("/ai-recognition/sku-links/library/{$entry->id}/dismiss")->assertForbidden();
    }

    public function test_a_restricted_viewer_sees_only_its_own_freezers_and_not_the_shared_library(): void
    {
        $this->onFreezer('Theirs', code: 'U-50');
        $own = Vend::create(['code' => 50009, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 7]);
        $mine = $this->product('Mine', code: 'M-1');
        DB::table('vend_channels')->insert(['vend_id' => $own->id, 'code' => 11, 'product_id' => $mine->id, 'amount' => 100, 'is_active' => true]);
        ZijiaLibrarySku::query()->create(['sys_sku_id' => '1', 'product_code' => '5', 'sku_name' => 'Somebody Else', 'name_key' => 'somebody else']);

        $this->actingAs($this->user(['read ai-recognition'], 7))->get('/ai-recognition/sku-links')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('AiRecognition/SkuLinks')
                ->where('seesLibrary', false)
                ->has('library', 0)
                ->has('coverage', 1)
                ->where('coverage.0.vend_id', $own->id));
    }

    public function test_a_dismissed_entry_leaves_the_review_list_and_links_nothing(): void
    {
        $this->onFreezer('Anything', '111');
        $this->registered('Coke', '8888002087005');
        app(ZijiaLibraryMirror::class)->refresh();
        $entry = ZijiaLibrarySku::query()->where('product_code', '8888002087005')->firstOrFail();
        $user = $this->user(['read ai-recognition', 'update products']);

        $this->actingAs($user)->post("/ai-recognition/sku-links/library/{$entry->id}/dismiss")->assertSessionHasNoErrors();

        $links = app(ZijiaSkuLinks::class);
        $this->assertSame([], $links->unlinkedLibrary());
        $this->assertCount(1, $links->unlinkedLibrary(true));

        $coke = $this->product('Coke');
        app(ZijiaBarcodeSync::class)->run();
        $this->assertNull($coke->fresh()->barcode);

        $this->actingAs($user)->post("/ai-recognition/sku-links/library/{$entry->id}/restore");
        $this->assertCount(1, $links->unlinkedLibrary());
    }

    public function test_an_entry_named_in_chinese_is_not_offered_for_review(): void
    {
        $this->onFreezer('Anything', '111');
        $this->registered('肉松面包', '6975714060005');
        app(ZijiaLibraryMirror::class)->refresh();

        $this->assertSame([], app(ZijiaSkuLinks::class)->unlinkedLibrary());
    }
}
