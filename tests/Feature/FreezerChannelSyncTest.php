<?php

namespace Tests\Feature;

use App\Jobs\Vend\SyncVendChannels;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\SellingPrice;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Services\Freezer\FreezerChannelSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A Smart Freezer sends no CHANNEL frame, so mark1 writes its channels from the planogram — one
 * entry per SKU since 2026-09-22 (the row is the product; the code is its position). The qty is our
 * own ledger (ops-job topup in, sale out) and must survive every re-sync.
 */
class FreezerChannelSyncTest extends TestCase
{
    use RefreshDatabase;

    private function freezer(): Vend
    {
        $product = Product::create(['code' => 'U-01', 'name' => 'Cornetto', 'freezer_slot_qty' => 24]);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_1, 'amount' => 2.70]);
        SellingPrice::create(['product_id' => $product->id, 'type' => SellingPrice::TYPE_2, 'amount' => 3.00]);
        $mapping = ProductMapping::create([
            'name' => 'Freezer planogram', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => 1,
        ]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '11', 'product_id' => $product->id]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '21', 'product_id' => $product->id]);
        $customer = Customer::create([
            'name' => 'Freezer Site', 'code' => 'FS1', 'operator_id' => 1,
            'status_id' => Customer::STATUS_ACTIVE, 'selling_price_type' => SellingPrice::TYPE_2,
        ]);

        return Vend::create([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'vend_model_id' => 1, 'customer_id' => $customer->id,
            'product_mapping_id' => $mapping->id, 'is_using_server_price' => true,
        ]);
    }

    /** @return array<int,array<string,int>> the pushed frame, keyed by channel code */
    private function pushedFrame(): array
    {
        $frame = null;
        Queue::assertPushed(SyncVendChannels::class, function (SyncVendChannels $job) use (&$frame) {
            $frame = (fn () => $this->input)->call($job);

            return true;
        });

        return collect($frame['channels'])->keyBy('channel_code')->all();
    }

    public function test_it_pushes_one_entry_per_sku_at_the_sites_price(): void
    {
        Queue::fake();
        $vend = $this->freezer();

        // The fixture puts ONE product on 11 and 21: one SKU, one entry, labelled by its first code.
        $this->assertSame(1, app(FreezerChannelSync::class)->sync($vend));

        $channels = $this->pushedFrame();
        $this->assertSame([11], array_keys($channels));
        $this->assertSame(Product::where('code', 'U-01')->value('id'), $channels[11]['product_id'], 'the row is the SKU');
        $this->assertSame(300, $channels[11]['amount'], 'RP2 = the Site tier, in cents');
        $this->assertSame(0, $channels[11]['qty'], 'a new SKU starts empty');
        $this->assertSame(48, $channels[11]['capacity'], "the SKU's own measured par, once per code it sits on");
    }

    public function test_it_keeps_our_ledger_and_retires_a_sku_that_left_the_planogram(): void
    {
        Queue::fake([SyncVendChannels::class => false, \App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        $vend = $this->freezer();
        $product = Product::where('code', 'U-01')->first();
        $other = Product::create(['code' => 'U-02', 'name' => 'Old flavour', 'freezer_slot_qty' => 12]);
        VendChannel::forceCreate(['vend_id' => $vend->id, 'code' => 11, 'product_id' => $product->id, 'qty' => 7, 'capacity' => 12, 'amount' => 300, 'is_active' => 1]);
        VendChannel::forceCreate(['vend_id' => $vend->id, 'code' => 61, 'product_id' => $other->id, 'qty' => 3, 'capacity' => 12, 'amount' => 200, 'is_active' => 1]);

        app(FreezerChannelSync::class)->sync($vend);

        $kept = VendChannel::where('vend_id', $vend->id)->where('product_id', $product->id)->first();
        $this->assertSame(7, (int) $kept->qty, 'topup ledger is never reset by a re-sync');
        $this->assertSame(48, (int) $kept->capacity, 'the par follows the product, not the stale row');
        $gone = VendChannel::where('vend_id', $vend->id)->where('product_id', $other->id)->first();
        $this->assertFalse((bool) $gone->is_active, 'a SKU off the planogram is retired, not left sold out');
        $this->assertSame(3, (int) $gone->qty, 'with its qty kept for the return');
    }

    public function test_an_unmeasured_product_leaves_the_par_blank_rather_than_inventing_one(): void
    {
        Queue::fake();
        $vend = $this->freezer();
        Product::query()->update(['freezer_slot_qty' => null]);

        app(FreezerChannelSync::class)->sync($vend);

        $this->assertSame(0, $this->pushedFrame()[11]['capacity'], '0 reads as "-" on the dashboard');
    }

    public function test_a_vending_machine_is_not_touched(): void
    {
        Queue::fake();
        $vend = Vend::create(['code' => 2052, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1]);

        $this->assertSame(0, app(FreezerChannelSync::class)->sync($vend));
        Queue::assertNotPushed(SyncVendChannels::class);
    }
}
