<?php

namespace Tests\Feature;

use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\TouchScreenUsage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Touch screen usage rate (Brian, 2026-10-22): pieces sold via the touch screen ÷ all pieces sold, today /
 * yesterday / last 7 / last 30 days. Pinned: TXN_SRC 1 is the touch screen but a Grab Mart order is not, pieces
 * are counted like Sales(qty), each window has its own edges, and the totals job stores it for vending only.
 */
class TouchScreenUsageTest extends TestCase
{
    use RefreshDatabase;

    private array $pay = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 15:00:00');
        foreach (['cash' => [0, 'Cash'], 'card' => [1, 'Card Terminal'], 'grab' => [209, 'Grab Mart']] as $key => [$code, $name]) {
            $this->pay[$key] = PaymentMethod::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true])->id;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(int $code, string $type = Vend::MACHINE_TYPE_VENDING_MACHINE): Vend
    {
        $customer = Customer::create(['name' => "Site {$code}", 'code' => "S{$code}", 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend = new Vend;
        $vend->forceFill(['code' => $code, 'machine_type' => $type, 'is_active' => 1, 'operator_id' => 1, 'customer_id' => $customer->id])->save();

        return $vend->fresh();
    }

    /** One sale: $pieces sold of $qty, from the touch screen (src 1) or the keypad (src 0). */
    private function sale(Vend $vend, string $at, int $src, string $pay, int $qty = 1, ?int $pieces = null, int $settlement = VendTransaction::SETTLEMENT_SETTLED): void
    {
        DB::table('vend_transactions')->insert([
            'vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'operator_id' => 1,
            'order_id' => uniqid('T'), 'amount' => 300 * $qty, 'qty' => $qty, 'success_qty' => $pieces ?? $qty,
            'is_multiple' => $qty > 1 ? 1 : 0, 'interface_type' => $src, 'payment_method_id' => $this->pay[$pay],
            'settlement_status' => $settlement, 'transaction_datetime' => $at, 'created_at' => $at, 'updated_at' => $at,
            // A single sale that dispensed nothing carries the machine's fault, as the ingest writes it.
            'vend_channel_error_id' => ($pieces === 0 && $qty === 1)
                ? VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)'])->id : null,
        ]);
    }

    public function test_each_window_counts_touch_screen_pieces_over_all_pieces(): void
    {
        $vend = $this->vend(4713);
        $this->sale($vend, '2026-10-22 10:00:00', 1, 'card');            // today, touch, 1 pc
        $this->sale($vend, '2026-10-22 11:00:00', 1, 'card', 3, 2);      // today, touch basket, 2 of 3 dispensed
        $this->sale($vend, '2026-10-22 12:00:00', 0, 'cash', 1);         // today, keypad
        $this->sale($vend, '2026-10-22 12:30:00', 1, 'grab');            // today, Grab Mart: placed online, not touch
        $this->sale($vend, '2026-10-22 13:00:00', 1, 'card', 1, 0);      // today, touch but failed: no piece
        $this->sale($vend, '2026-10-22 13:30:00', 1, 'card', 1, 1, VendTransaction::SETTLEMENT_REFUNDED); // refunded: not a sale
        $this->sale($vend, '2026-10-21 18:00:00', 1, 'card');            // yesterday, touch
        $this->sale($vend, '2026-10-21 19:00:00', 0, 'card');            // yesterday, keypad
        $this->sale($vend, '2026-10-17 09:00:00', 0, 'cash', 2);         // 5 days ago, keypad, 2 pcs
        $this->sale($vend, '2026-10-05 09:00:00', 1, 'card');            // 17 days ago, touch
        $this->sale($vend, '2026-09-20 09:00:00', 1, 'card');            // 32 days ago: outside every window

        $usage = TouchScreenUsage::forVend($vend->id);

        $this->assertSame(['touch' => 3, 'total' => 5], $usage['today']);
        $this->assertSame(['touch' => 1, 'total' => 2], $usage['yesterday']);
        $this->assertSame(['touch' => 4, 'total' => 9], $usage['seven_days']);
        $this->assertSame(['touch' => 5, 'total' => 10], $usage['thirty_days']);
    }

    public function test_the_totals_job_stores_it_for_vending_machines_only(): void
    {
        $vend = $this->vend(4714);
        $this->sale($vend, '2026-10-22 10:00:00', 1, 'card');
        $freezer = $this->vend(50009, Vend::MACHINE_TYPE_SMART_FREEZER);
        $this->sale($freezer, '2026-10-22 10:00:00', 1, 'card');

        (new SyncVendTransactionTotalsJson($vend))->handle();
        (new SyncVendTransactionTotalsJson($freezer))->handle();

        $this->assertEquals(['touch' => 1, 'total' => 1], $vend->fresh()->vend_transaction_totals_json['touch_usage']['today']);
        $siteTotals = json_decode((string) DB::table('customers')->where('id', $vend->customer_id)->value('totals_json'), true);
        $this->assertEquals(['touch' => 1, 'total' => 1], $siteTotals['touch_usage']['today'] ?? null, 'the Site totals carry it too');
        $this->assertNull($freezer->fresh()->vend_transaction_totals_json['touch_usage'] ?? null, 'a freezer has no keypad to compare with');
    }
}
