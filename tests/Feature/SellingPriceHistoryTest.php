<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\SellingPrice;
use App\Models\User;
use Database\Seeders\SellingPriceAuthorBackfillSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Product → Edit → Selling Price(s): when each RP was set and by whom, and the price history, all
 * derived from user_logs (SellingPriceHistory). A change on the page is a delete plus an add of
 * the same RP, and reads as one change, old → new.
 */
class SellingPriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Model::reguard(); // see ProductEditCityboxPricingTest
        Queue::fake();
        foreach (['read products', 'update products'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->operator = Operator::create(['code' => 'HIPL', 'name' => 'HI SG', 'country_id' => 1, 'timezone' => 'Asia/Singapore', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function editor(string $name): User
    {
        $u = User::factory()->create(['name' => $name, 'operator_id' => $this->operator->id]);
        $u->givePermissionTo(['read products', 'update products']);

        return $u;
    }

    /** @param  list<array{amount: string, type: int}>  $prices  dollars, as the page posts them */
    private function save(User $user, Product $product, array $prices): void
    {
        $this->actingAs($user)->from("/products/{$product->id}/edit")
            ->post("/products/{$product->id}/update", [
                'code' => $product->code, 'name' => $product->name, 'operator_id' => $this->operator->id,
                'sellingPrices' => $prices,
            ])->assertRedirect();
    }

    public function test_the_edit_page_shows_who_set_each_price_and_the_history_of_changes(): void
    {
        $product = Product::create(['code' => 'U-12', 'name' => 'Solero', 'operator_id' => $this->operator->id]);
        $untouched = Product::create(['code' => 'X-1', 'name' => 'Other', 'operator_id' => $this->operator->id]);
        $alice = $this->editor('Alice');
        $daniel = $this->editor('Daniel');

        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->save($alice, $product, [['amount' => '1.90', 'type' => SellingPrice::TYPE_1], ['amount' => '2.20', 'type' => SellingPrice::TYPE_2]]);
        $this->save($alice, $untouched, [['amount' => '9.90', 'type' => SellingPrice::TYPE_2]]);

        // Daniel changes RP2 the way the page does: delete the row, then add the new price and save.
        Carbon::setTestNow('2026-10-12 16:23:13');
        $rp2 = $product->sellingPrices()->where('type', SellingPrice::TYPE_2)->firstOrFail();
        $this->actingAs($daniel)->from("/products/{$product->id}/edit")->delete("/products/selling-prices/{$rp2->id}")->assertRedirect();
        Carbon::setTestNow('2026-10-12 16:23:40');
        $this->save($daniel, $product, [['amount' => '2.50', 'type' => SellingPrice::TYPE_2]]);

        // ...and removes RP1 for good.
        Carbon::setTestNow('2026-10-12 16:25:00');
        $rp1 = $product->sellingPrices()->where('type', SellingPrice::TYPE_1)->firstOrFail();
        $this->actingAs($daniel)->from("/products/{$product->id}/edit")->delete("/products/selling-prices/{$rp1->id}")->assertRedirect();

        $newRp2 = $product->sellingPrices()->where('type', SellingPrice::TYPE_2)->firstOrFail();
        $this->assertSame([$daniel->id, 220], [(int) $newRp2->created_by, $newRp2->previous_amount], 'stored on the row at save');
        $this->actingAs($daniel)->get("/products/{$product->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.data.sellingPrices.0.set_by', 'Daniel')
                ->where('product.data.sellingPrices.0.previous_amount', 220)
                ->where('product.data.sellingPrices.0.set_at', Carbon::parse('2026-10-12 16:23:40')->toIso8601String())
                ->where('sellingPriceHistory', fn ($history) => collect($history)
                    ->map(fn ($h) => [$h['kind'], $h['type_name'], $h['from'], $h['to'], $h['by']])->all() === [
                        ['removed', 'RP1', 190, null, 'Daniel'],
                        ['changed', 'RP2', 220, 250, 'Daniel'],
                        ['added', 'RP2', null, 220, 'Alice'],
                        ['added', 'RP1', null, 190, 'Alice'],
                    ]));
    }

    public function test_a_price_set_before_the_audit_log_shows_its_date_and_no_author(): void
    {
        $product = Product::create(['code' => 'OLD', 'name' => 'Legacy', 'operator_id' => $this->operator->id]);
        Carbon::setTestNow('2024-06-04 15:43:54');
        $price = $product->sellingPrices()->create(['amount' => 1.5, 'type' => SellingPrice::TYPE_1]); // no web user: not logged
        Carbon::setTestNow('2026-10-12 12:00:00');

        $this->actingAs($this->editor('Daniel'))->get("/products/{$product->id}/edit")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.data.sellingPrices.0.id', $price->id)
                ->where('product.data.sellingPrices.0.set_by', null)
                ->where('product.data.sellingPrices.0.set_at', Carbon::parse('2024-06-04 15:43:54')->toIso8601String())
                ->where('sellingPriceHistory', []));
    }

    public function test_the_backfill_fills_author_and_previous_price_from_the_log_and_never_overwrites(): void
    {
        $product = Product::create(['code' => 'U-12', 'name' => 'Solero', 'operator_id' => $this->operator->id]);
        $alice = $this->editor('Alice');
        $daniel = $this->editor('Daniel');
        $this->save($alice, $product, [['amount' => '2.20', 'type' => SellingPrice::TYPE_2], ['amount' => '1.90', 'type' => SellingPrice::TYPE_1]]);
        $rp2 = $product->sellingPrices()->where('type', SellingPrice::TYPE_2)->firstOrFail();
        $this->actingAs($daniel)->from("/products/{$product->id}/edit")->delete("/products/selling-prices/{$rp2->id}");
        $this->save($daniel, $product, [['amount' => '2.50', 'type' => SellingPrice::TYPE_2]]);

        // As the rows looked before the columns existed; RP1 was later corrected by hand to someone else.
        DB::table('selling_prices')->update(['created_by' => null, 'previous_amount' => null]);
        $rp1 = $product->sellingPrices()->where('type', SellingPrice::TYPE_1)->firstOrFail();
        DB::table('selling_prices')->where('id', $rp1->id)->update(['created_by' => $daniel->id]);

        $this->seed(SellingPriceAuthorBackfillSeeder::class);
        $this->seed(SellingPriceAuthorBackfillSeeder::class); // re-run is harmless

        $newRp2 = $product->sellingPrices()->where('type', SellingPrice::TYPE_2)->firstOrFail();
        $this->assertSame([$daniel->id, 220], [(int) $newRp2->created_by, $newRp2->previous_amount]);
        $this->assertSame([$daniel->id, null], [(int) $rp1->fresh()->created_by, $rp1->fresh()->previous_amount], 'a set value is never overwritten');
    }
}
