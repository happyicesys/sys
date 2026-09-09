<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Support\AutoRefundSource;
use App\Support\OperatorScope;
use App\Support\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sales Transactions grid: "Payment Status" and "Dispense Status" are two
 * columns (2026-09-02). The old single column read `is_payment_received`, which
 * VendTransactionService::processMapping derives FROM the dispense error code,
 * so a card sale with a motor fault showed "Payment Status: Unsuccessful" and
 * users could not tell whether the money or the product had failed. This drives
 * the real page (select + resource) and pins both labels per row and per item.
 */
class TransactionIndexStatusColumnsTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private int $vendId;

    private int $okErrorId;

    private int $faultErrorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Kent office', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 2003, 'name' => 'Machine 2003', 'operator_id' => $this->operator->id, 'customer_id' => $customerId,
            'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->okErrorId = VendChannelError::create(['code' => 0, 'desc' => 'No Malfunction (0)'])->id;
        VendChannelError::create(['code' => 6, 'desc' => 'Microswitch pressed over time (6)']);
        $this->faultErrorId = VendChannelError::create(['code' => 4, 'desc' => 'Open circuit, motor not detected (4)'])->id;
    }

    private function txn(string $orderId, array $attrs): VendTransaction
    {
        return VendTransaction::create(array_merge([
            'order_id' => $orderId,
            'vend_id' => $this->vendId,
            'operator_id' => $this->operator->id,
            'transaction_datetime' => now(),
            'amount' => 20,
            'qty' => 1,
            'vend_channel_code' => 11,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'interface_type' => 0,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ], $attrs));
    }

    private function item(VendTransaction $txn, int $channelCode, int $errorCode): void
    {
        VendTransactionItem::create([
            'vend_transaction_id' => $txn->id,
            'vend_channel_code' => $channelCode,
            'vend_channel_error_code' => $errorCode,
            'vend_channel_error_id' => VendChannelError::where('code', $errorCode)->value('id'),
        ]);
    }

    /** @return array<string, array> grid rows keyed by order id */
    private function gridRows(): array
    {
        Permission::findOrCreate('read transactions', 'web');
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo('read transactions');
        OperatorScope::flush();

        $rows = [];
        $this->actingAs($user)
            ->get('/vends/transactions?'.http_build_query([
                'date_from' => now()->subDay()->toDateTimeString(),
                'date_to' => now()->addDay()->toDateTimeString(),
                'operators' => [$this->operator->id],
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$rows) {
                $page->component('Vend/Transaction');
                foreach ($page->toArray()['props']['vendTransactions']['data'] as $row) {
                    $rows[$row['order_id']] = $row;
                }
            });

        return $rows;
    }

    public function test_payment_and_dispense_are_reported_as_separate_facts(): void
    {
        // Payment rails: a NETS card terminal (no gateway) and an Omise QR method (gateway 2).
        $card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        $cash = PaymentMethod::create(['code' => 0, 'name' => 'Cash', 'is_active' => true]);
        $omise = PaymentMethod::create(['code' => 201, 'name' => 'Omise (Paynow)', 'is_active' => true, 'payment_gateway_id' => 2]);

        // The screenshot: card terminal, VMC keypad frame, SErr 4. Money taken, nothing dropped —
        // and no report synced yet, so Payment is BLANK (nobody confirmed it), Dispense Failed.
        $this->txn('CARD-FAULT', [
            'payment_method_id' => $card->id,
            'vend_channel_error_id' => $this->faultErrorId,
            'is_payment_received' => false,
        ]);
        // Cash: no rail ever confirms it.
        $this->txn('CASH-OK', [
            'payment_method_id' => $cash->id,
            'vend_channel_error_id' => $this->okErrorId,
            'is_payment_received' => true,
        ]);
        // NETS report matched this sale (Card Settlement › Sync stamps it with forceFill — not fillable).
        $this->txn('CARD-SETTLED', [
            'payment_method_id' => $card->id,
            'vend_channel_error_id' => $this->okErrorId,
        ])->forceFill(['card_settlement_synced_at' => now()])->save();
        // NETS report carried the reversal line for this failed vend.
        // (The refund / retained-credit columns are written by services with forceFill, as here.)
        $this->txn('CARD-REVERSED', [
            'payment_method_id' => $card->id,
            'vend_channel_error_id' => $this->faultErrorId,
            'is_payment_received' => false,
        ])->forceFill(['is_refunded' => true, 'auto_refund_source' => AutoRefundSource::SETTLEMENT_REPORT_REVERSAL])->save();
        // Retained-credit pair: the earlier failed sale the reader banked, and the sale that consumed it.
        $this->txn('CARD-REVENDED', [
            'payment_method_id' => $card->id,
            'vend_channel_error_id' => $this->faultErrorId,
        ])->forceFill(['is_refunded' => true, 'auto_refund_source' => AutoRefundSource::RETAINED_CREDIT_REVEND])->save();
        $this->txn('CARD-RETAINED', [
            'payment_method_id' => $card->id,
            'vend_channel_error_id' => $this->okErrorId,
        ])->forceFill(['is_retained_credit_settlement' => true])->save();
        // QR gateway sale: paid callback pre-created the row, TRADE never came.
        $this->txn('QR-SILENT', [
            'payment_method_id' => $omise->id,
            'is_payment_received' => true,
            'interface_type' => 1,
            'is_found_in_transaction' => false,
        ]);
        $basket = $this->txn('MULTI-PARTIAL', [
            'payment_method_id' => $omise->id,
            'is_multiple' => true,
            'qty' => 2,
            'is_payment_received' => true,
            'interface_type' => 1,
            'vend_channel_error_id' => $this->okErrorId, // header code of a multiple means nothing
        ]);
        $this->item($basket, 11, 0);
        $this->item($basket, 12, 4);

        $rows = $this->gridRows();
        $this->assertCount(8, $rows);

        $this->assertSame(SaleStatus::UNCONFIRMED, $rows['CARD-FAULT']['payment_status']);
        $this->assertSame(SaleStatus::FAILED, $rows['CARD-FAULT']['dispense_status']);
        $this->assertNull($rows['CARD-FAULT']['payment_note']);

        $this->assertSame(SaleStatus::UNCONFIRMED, $rows['CASH-OK']['payment_status']);
        $this->assertSame(SaleStatus::DISPENSED, $rows['CASH-OK']['dispense_status']);

        $this->assertSame(SaleStatus::SETTLED, $rows['CARD-SETTLED']['payment_status']);
        $this->assertSame(SaleStatus::DISPENSED, $rows['CARD-SETTLED']['dispense_status']);

        $this->assertSame(SaleStatus::REFUNDED, $rows['CARD-REVERSED']['payment_status']);
        $this->assertSame(SaleStatus::FAILED, $rows['CARD-REVERSED']['dispense_status']);

        $this->assertSame(SaleStatus::RE_VENDED, $rows['CARD-REVENDED']['payment_status']);
        $this->assertNotNull($rows['CARD-REVENDED']['payment_note']);
        $this->assertSame(SaleStatus::RETAINED_CREDIT, $rows['CARD-RETAINED']['payment_status']);
        $this->assertNotNull($rows['CARD-RETAINED']['payment_note']);

        $this->assertSame(SaleStatus::PAID, $rows['QR-SILENT']['payment_status']);
        $this->assertSame(SaleStatus::NO_TRADE, $rows['QR-SILENT']['dispense_status']);

        // Multiple: payment on the parent, dispense on each item row, parent dispense blank.
        $multi = $rows['MULTI-PARTIAL'];
        $this->assertSame(SaleStatus::PAID, $multi['payment_status']);
        $this->assertSame(SaleStatus::ON_ITEMS, $multi['dispense_status']);

        $items = collect($multi['vendTransactionItems'])->keyBy('vend_channel_code');
        $this->assertSame(SaleStatus::DISPENSED, $items[11]['dispense_status']);
        $this->assertSame(SaleStatus::FAILED, $items[12]['dispense_status']);
    }

    /**
     * "Settle Sync" is decided in the Vue from five fields, and between
     * 2026-09-01 and 2026-09-09 the resource emitted none of them: every row
     * fell through `payment_method_gateway_id !== null` (undefined) into the
     * gateway branch and drew a grey cross, whatever the rail or the report
     * said. The ints are compared with === in the cell, so their TYPE is part
     * of the contract, not just their presence.
     */
    public function test_the_settle_sync_column_is_given_the_fields_it_renders_from(): void
    {
        $card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        $cash = PaymentMethod::create(['code' => 0, 'name' => 'Cash', 'is_active' => true]);
        $omise = PaymentMethod::create(['code' => 201, 'name' => 'Omise (Paynow)', 'is_active' => true, 'payment_gateway_id' => 2]);

        $this->txn('CARD-SYNCED', ['payment_method_id' => $card->id, 'vend_channel_error_id' => $this->okErrorId])
            ->forceFill(['card_settlement_synced_at' => now()])->save();
        $this->txn('CARD-WAITING', ['payment_method_id' => $card->id, 'vend_channel_error_id' => $this->okErrorId]);
        $this->txn('CASH-OK', ['payment_method_id' => $cash->id, 'vend_channel_error_id' => $this->okErrorId]);
        $this->txn('QR-APPROVED', ['payment_method_id' => $omise->id, 'payment_gateway_log_id' => $this->gatewayLog('QR-APPROVED', 2)]);
        $this->txn('QR-DECLINED', ['payment_method_id' => $omise->id, 'payment_gateway_log_id' => $this->gatewayLog('QR-DECLINED', 99)]);

        $rows = $this->gridRows();

        // Card terminal: gateway id null + a non-zero method code selects the card branch.
        $synced = $rows['CARD-SYNCED'];
        $this->assertNull($synced['payment_method_gateway_id']);
        $this->assertSame(1, $synced['payment_method_code']);
        $this->assertNotNull($synced['card_settlement_synced_at'], 'a synced card sale must show the tick');

        $this->assertNull($rows['CARD-WAITING']['card_settlement_synced_at'], 'no report line yet: cross');
        $this->assertNull($rows['CARD-WAITING']['payment_method_gateway_id']);

        // Cash takes neither branch — the cell stays blank.
        $this->assertNull($rows['CASH-OK']['payment_method_gateway_id']);
        $this->assertSame(0, $rows['CASH-OK']['payment_method_code']);

        // Gateway: the approve callback is the confirmation, compared with === 2 / 98 / 99.
        $this->assertSame(2, $rows['QR-APPROVED']['payment_method_gateway_id']);
        $this->assertSame(2, $rows['QR-APPROVED']['payment_gateway_log_status']);
        $this->assertNotNull($rows['QR-APPROVED']['payment_gateway_approved_at']);
        $this->assertSame(99, $rows['QR-DECLINED']['payment_gateway_log_status']);
    }

    /**
     * "Will refund?" under the Payment Method cell: the flag of the terminal
     * that was on the machine ON THE DAY OF THE SALE, so a historical row shows
     * the terminal that actually took the money rather than today's.
     */
    public function test_the_payment_method_cell_gets_the_terminal_flag_effective_on_the_sale_date(): void
    {
        $card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        $cash = PaymentMethod::create(['code' => 0, 'name' => 'Cash', 'is_active' => true]);
        $nets = DB::table('card_terminals')->insertGetId(['name' => 'Nets', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['TID-YES', 1], ['TID-NO', 0]] as [$tid, $flag]) {
            DB::table('card_terminal_units')->insert([
                'terminal_id' => $tid, 'card_terminal_id' => $nets, 'batch' => 'Nets #3 (50x)',
                'is_will_auto_refund' => $flag, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // The machine ran TID-NO until yesterday and carries TID-YES from today.
        DB::table('card_terminal_bindings')->insert([
            ['provider' => 'nets', 'terminal_id' => 'TID-NO', 'vend_id' => $this->vendId,
                'bound_from' => now()->subMonth()->toDateString(), 'bound_until' => now()->subDay()->toDateString(),
                'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'nets', 'terminal_id' => 'TID-YES', 'vend_id' => $this->vendId,
                'bound_from' => now()->toDateString(), 'bound_until' => null,
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->txn('CARD-TODAY', ['payment_method_id' => $card->id, 'vend_channel_error_id' => $this->okErrorId]);
        $this->txn('CARD-YESTERDAY', [
            'payment_method_id' => $card->id, 'vend_channel_error_id' => $this->okErrorId,
            'transaction_datetime' => now()->subDay(),
        ]);
        $this->txn('CASH-TODAY', ['payment_method_id' => $cash->id, 'vend_channel_error_id' => $this->okErrorId]);

        $rows = $this->gridRows();

        $this->assertTrue($rows['CARD-TODAY']['card_terminal_will_auto_refund']);
        $this->assertSame('TID-YES', $rows['CARD-TODAY']['card_terminal_unit_id']);
        $this->assertSame('Nets #3 (50x)', $rows['CARD-TODAY']['card_terminal_batch']);

        $this->assertFalse($rows['CARD-YESTERDAY']['card_terminal_will_auto_refund'], 'the terminal fitted that day, not today\'s');
        $this->assertSame('TID-NO', $rows['CARD-YESTERDAY']['card_terminal_unit_id']);

        // Not a card sale: no terminal, no badge.
        $this->assertNull($rows['CASH-TODAY']['card_terminal_will_auto_refund']);
        $this->assertNull($rows['CASH-TODAY']['card_terminal_unit_id']);
    }

    private function gatewayLog(string $orderId, int $status): int
    {
        return DB::table('payment_gateway_logs')->insertGetId([
            'vend_code' => 2003, 'vend_id' => $this->vendId, 'order_id' => $orderId,
            'operator_payment_gateway_id' => 1, 'amount' => 0.2, 'status' => $status,
            'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
