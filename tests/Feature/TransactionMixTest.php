<?php

namespace Tests\Feature;

use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\TransactionMix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Touch screen and cashless rates (Brian, 2026-10-22/23) by TRANSACTION count, not pieces — a touch screen basket
 * holds several. Pinned: TXN_SRC 1 is the touch screen but Grab Mart is not, every method but cash is cashless and a
 * sale with no method on record is left out of that share, a failed or refunded sale is no transaction, each window
 * has its own edges, and the totals job stores them for vending machines only.
 */
class TransactionMixTest extends TestCase
{
    use RefreshDatabase;

    private array $pay = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 15:00:00');
        foreach (['cash' => [0, 'Cash'], 'card' => [1, 'Card Terminal'], 'grab' => [209, 'Grab Mart'], 'qr' => [201, 'Omise (Paynow)']] as $key => [$code, $name]) {
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
    private function sale(Vend $vend, string $at, int $src, ?string $pay, int $qty = 1, ?int $pieces = null, int $settlement = VendTransaction::SETTLEMENT_SETTLED): void
    {
        DB::table('vend_transactions')->insert([
            'vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'operator_id' => 1,
            'order_id' => uniqid('T'), 'amount' => 300 * $qty, 'qty' => $qty, 'success_qty' => $pieces ?? $qty,
            'is_multiple' => $qty > 1 ? 1 : 0, 'interface_type' => $src, 'payment_method_id' => $pay ? $this->pay[$pay] : null,
            'settlement_status' => $settlement, 'transaction_datetime' => $at, 'created_at' => $at, 'updated_at' => $at,
            // A single sale that dispensed nothing carries the machine's fault, as the ingest writes it.
            'vend_channel_error_id' => ($pieces === 0 && $qty === 1)
                ? VendChannelError::firstOrCreate(['code' => 7], ['desc' => 'Sensor error (7)'])->id : null,
        ]);
    }

    public function test_each_window_counts_transactions_not_pieces(): void
    {
        $vend = $this->vend(4713);
        $this->sale($vend, '2026-10-22 10:00:00', 1, 'card');            // today, touch, card
        $this->sale($vend, '2026-10-22 11:00:00', 1, 'card', 3, 2);      // today, touch basket of 3: ONE transaction
        $this->sale($vend, '2026-10-22 12:00:00', 0, 'cash');            // today, keypad, cash
        $this->sale($vend, '2026-10-22 12:30:00', 1, 'grab');            // today, Grab Mart: online, not touch; cashless
        $this->sale($vend, '2026-10-22 12:40:00', 50, 'qr');             // today, keypad QR: not touch; cashless
        $this->sale($vend, '2026-10-22 12:50:00', 0, null);              // today, no payment method on record
        $this->sale($vend, '2026-10-22 13:00:00', 1, 'card', 1, 0);      // today, failed: not a transaction
        $this->sale($vend, '2026-10-22 13:30:00', 1, 'card', 1, 1, VendTransaction::SETTLEMENT_REFUNDED); // refunded
        $this->sale($vend, '2026-10-21 18:00:00', 1, 'card');            // yesterday, touch
        $this->sale($vend, '2026-10-21 19:00:00', 0, 'card');            // yesterday, keypad
        $this->sale($vend, '2026-10-17 09:00:00', 0, 'cash', 2);         // 5 days ago, keypad, cash
        $this->sale($vend, '2026-10-05 09:00:00', 1, 'card');            // 17 days ago, touch
        $this->sale($vend, '2026-09-20 09:00:00', 1, 'card');            // 32 days ago: outside every window

        $mix = TransactionMix::forVend($vend->id);

        $this->assertSame(['touch' => 2, 'total' => 6], $mix['touch_usage']['today']);
        $this->assertSame(['touch' => 1, 'total' => 2], $mix['touch_usage']['yesterday']);
        $this->assertSame(['touch' => 3, 'total' => 9], $mix['touch_usage']['seven_days']);
        $this->assertSame(['touch' => 4, 'total' => 10], $mix['touch_usage']['thirty_days']);

        $this->assertSame(['cashless' => 4, 'total' => 5], $mix['cashless_usage']['today'], 'the sale with no method is left out');
        $this->assertSame(['cashless' => 2, 'total' => 2], $mix['cashless_usage']['yesterday']);
        $this->assertSame(['cashless' => 6, 'total' => 8], $mix['cashless_usage']['seven_days']);
        $this->assertSame(['cashless' => 7, 'total' => 9], $mix['cashless_usage']['thirty_days']);
    }

    public function test_the_totals_job_stores_it_for_vending_machines_only(): void
    {
        $vend = $this->vend(4714);
        $this->sale($vend, '2026-10-22 10:00:00', 1, 'card');
        $freezer = $this->vend(50009, Vend::MACHINE_TYPE_SMART_FREEZER);
        $this->sale($freezer, '2026-10-22 10:00:00', 1, 'card');

        app()->call([new SyncVendTransactionTotalsJson($vend), 'handle']);
        app()->call([new SyncVendTransactionTotalsJson($freezer), 'handle']);

        $this->assertEquals(['touch' => 1, 'total' => 1], $vend->fresh()->vend_transaction_totals_json['touch_usage']['today']);
        $siteTotals = json_decode((string) DB::table('customers')->where('id', $vend->customer_id)->value('totals_json'), true);
        $this->assertEquals(['touch' => 1, 'total' => 1], $siteTotals['touch_usage']['today'] ?? null, 'the Site totals carry it too');
        $this->assertEquals(['cashless' => 1, 'total' => 1], $vend->fresh()->vend_transaction_totals_json['cashless_usage']['today']);
        $this->assertNull($vend->fresh()->vend_transaction_totals_json['happy_hour'], 'no Happy Hour slot in 30 days');
        $freezerTotals = $freezer->fresh()->vend_transaction_totals_json;
        $this->assertNull($freezerTotals['touch_usage'], 'a freezer has no keypad to compare with');
        $this->assertNull($freezerTotals['cashless_usage']);
        $this->assertNull($freezerTotals['happy_hour']);
    }
}
