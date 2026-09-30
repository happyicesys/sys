<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobItemChannel;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Services\Freezer\FreezerStockLedger;
use App\Services\VendTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * A Smart Freezer's stock is mark1's own ledger: Stock In adds each SKU's refill, Undo takes
 * the refill back, each dispensed unit sold takes one off — once, whatever the TRADE replays
 * (50001, 2026-09-30: stocked 3 in job 58533 and still read 0).
 */
class FreezerStockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    private Product $cheesecake;

    private VendChannel $channel;

    private OpsJobItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Fault (7)']);

        $this->cheesecake = Product::create(['code' => 'CC-01', 'name' => 'Basque Cheesecake', 'freezer_slot_qty' => 3]);
        $mapping = ProductMapping::create(['name' => 'Freezer', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_smart' => true, 'is_active' => true, 'operator_id' => 1]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '41', 'product_id' => $this->cheesecake->id]);
        $customer = Customer::create(['name' => 'Freezer Site', 'code' => 'FS1', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->vend = Vend::create(['code' => 50091, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id]);
        $this->channel = VendChannel::create(['vend_id' => $this->vend->id, 'code' => 41, 'product_id' => $this->cheesecake->id, 'qty' => 0, 'capacity' => 3, 'amount' => 430, 'is_active' => 1]);

        $job = OpsJob::create(['code' => 900300, 'date' => now()->toDateString(), 'status' => 1, 'operator_id' => 1]);
        $this->item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $this->vend->id, 'customer_id' => $customer->id, 'status' => OpsJob::STATUS_PICKED]);
        OpsJobItemChannel::create(['ops_job_id' => $job->id, 'ops_job_item_id' => $this->item->id, 'vend_channel_id' => $this->channel->id,
            'vend_channel_code' => 41, 'vend_code' => $this->vend->code, 'product_id' => $this->cheesecake->id, 'qty' => 0, 'actual_qty' => 3, 'capacity' => 3, 'picked_qty' => 3]);
    }

    /** One freezer TRADE through the real ingest: one transf_info entry per unit. */
    private function trade(string $orderId, array $units): void
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => $orderId, 'PAY_TYPE' => 0, 'TIME' => now()->format('Y-m-d H:i:s'), 'SErr' => $units[0], 'SId' => 41, 'Price' => 430, 'TXN_SRC' => 0,
            'transf_info' => array_map(fn ($err) => ['SId' => 41, 'SErr' => $err, 'Price' => 430, 'goods_id' => $this->cheesecake->id], $units),
        ]);
    }

    public function test_stock_in_sets_count_plus_refill_and_undo_takes_the_refill_back(): void
    {
        app(FreezerStockLedger::class)->stockIn($this->item->fresh());
        $this->assertSame(3, (int) $this->channel->fresh()->qty);

        app(FreezerStockLedger::class)->undoStockIn($this->item->fresh());
        $this->assertSame(0, (int) $this->channel->fresh()->qty);
    }

    public function test_a_sale_made_while_the_page_was_open_stays_deducted(): void
    {
        // The page loaded qty 3 and sends it back; one sold before Confirm, so the ledger is at 2.
        $this->channel->update(['qty' => 2]);
        $this->item->opsJobItemChannels()->update(['qty' => 3, 'actual_qty' => 1]);

        app(FreezerStockLedger::class)->stockIn($this->item->fresh());

        $this->assertSame(3, (int) $this->channel->fresh()->qty, '2 left + 1 loaded — the sale is not put back');
    }

    public function test_undo_leaves_a_retired_sku_alone(): void
    {
        // After a mapping swap the leaving SKU was returned (refill -2) and its row retired.
        $this->channel->update(['qty' => 0, 'is_active' => false]);
        $this->item->opsJobItemChannels()->update(['qty' => 2, 'actual_qty' => -2]);

        app(FreezerStockLedger::class)->undoStockIn($this->item->fresh());

        $this->assertSame(0, (int) $this->channel->fresh()->qty, 'returned stock does not come back');
    }

    public function test_the_stock_in_screen_writes_the_ledger(): void
    {
        $user = User::factory()->create();
        foreach (['read operations', 'update operations'] as $permission) {
            $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($permission, 'web'));
        }
        $line = $this->item->opsJobItemChannels()->first();

        $this->actingAs($user)->post("/ops-jobs/items/{$this->item->id}/confirm", [
            'channels' => [['id' => $line->id, 'qty' => 0, 'refill' => 3, 'capacity' => 3]],
            'cash_amount' => 0, 'cashless_amount' => 0, 'temp_cash_amount_from_vmc' => 0,
        ])->assertRedirect();

        $this->assertSame(OpsJob::STATUS_DELIVERED, (string) $this->item->fresh()->status);
        $this->assertSame(3, (int) $this->channel->fresh()->qty);
    }

    public function test_a_sale_takes_off_each_dispensed_unit_once_even_when_replayed(): void
    {
        $this->channel->update(['qty' => 3]);

        $this->trade('26093013280250001', [0]);
        $this->assertSame(2, (int) $this->channel->fresh()->qty);

        $this->trade('26093013280250001', [0]); // the APK replays its outbox
        $this->assertSame(2, (int) $this->channel->fresh()->qty);

        $this->trade('26093013300250001', [0, 7]); // two units, one failed to dispense
        $this->assertSame(1, (int) $this->channel->fresh()->qty);
    }

    public function test_never_below_zero(): void
    {
        $this->trade('26093013280250002', [0]);
        $this->assertSame(0, (int) $this->channel->fresh()->qty);
    }

    public function test_a_vending_machine_is_untouched(): void
    {
        $this->vend->forceFill(['machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE])->save();
        $this->channel->update(['qty' => 5]);

        app(FreezerStockLedger::class)->stockIn($this->item->fresh());
        $this->trade('26093013280250003', [0]);

        $this->assertSame(5, (int) $this->channel->fresh()->qty);
    }

    public function test_rebuild_recomputes_from_the_last_stock_in_minus_sales_since(): void
    {
        $this->item->update(['status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now()->subMinutes(10)]);
        $this->trade('26093013280250004', [0]); // qty stays 0 (never below zero): the pre-ledger state

        $this->artisan('freezer:rebuild-stock', ['vend' => 50091])->assertSuccessful();
        $this->assertSame(0, (int) $this->channel->fresh()->qty, 'dry run writes nothing');

        $this->artisan('freezer:rebuild-stock', ['vend' => 50091, '--apply' => true])->assertSuccessful();
        $this->assertSame(2, (int) $this->channel->fresh()->qty);
    }
}
