<?php

namespace Tests\Unit;

use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use App\Support\SaleFacts;
use App\Support\SaleStatus;
use PHPUnit\Framework\TestCase;

/**
 * Payment and dispense are two facts; the machine's TRADE only carries the
 * second (SErr per channel) and `is_payment_received` is that verdict under a
 * payment name. SaleStatus deduces both labels from SaleFacts — pinned here so
 * the grid, the CSV export and the refund screen cannot drift apart again.
 *
 * Payment is only what a rail CONFIRMED (Brian, 2026-09-02): gateway rows from
 * the gateway API, NETS card rows from the uploaded report, everything else
 * blank. Dispense sits on a single row or on a multiple's item rows.
 */
class SaleStatusTest extends TestCase
{
    private function facts(array $o = []): SaleFacts
    {
        return new SaleFacts(
            isMultiple: $o['isMultiple'] ?? false,
            headerErrorCode: $o['headerErrorCode'] ?? null,
            settlementStatus: $o['settlementStatus'] ?? VendTransaction::SETTLEMENT_SETTLED,
            isFoundInTransaction: $o['isFoundInTransaction'] ?? true,
            isRefunded: $o['isRefunded'] ?? false,
            autoRefundSource: $o['autoRefundSource'] ?? null,
            isRetainedCreditSettlement: $o['isRetainedCreditSettlement'] ?? false,
            paidThroughGateway: $o['paidThroughGateway'] ?? false,
            confirmedBySettlementReport: $o['confirmedBySettlementReport'] ?? false,
        );
    }

    public function test_only_a_rail_that_confirmed_the_money_gets_a_payment_label(): void
    {
        // Cash, or a card sale whose NETS report is not uploaded yet: nobody confirmed it.
        $this->assertSame(SaleStatus::UNCONFIRMED, SaleStatus::payment($this->facts()));
        $this->assertSame('', SaleStatus::payment($this->facts()));

        // Gateway sale (Omise / Midtrans / Fiuu): the API created the row on the paid callback —
        // including a row still SETTLEMENT_PENDING (pending is about dispense).
        $this->assertSame(SaleStatus::PAID, SaleStatus::payment($this->facts(['paidThroughGateway' => true])));
        $this->assertSame(SaleStatus::PAID, SaleStatus::payment($this->facts(['paidThroughGateway' => true, 'settlementStatus' => VendTransaction::SETTLEMENT_PENDING])));

        // NETS card sale matched by the uploaded acquirer report.
        $this->assertSame(SaleStatus::SETTLED, SaleStatus::payment($this->facts(['confirmedBySettlementReport' => true])));
    }

    public function test_refunded_when_the_rail_returned_the_money(): void
    {
        // Omise API/webhook refund, NETS report reversal, manual flag: is_refunded.
        $this->assertSame(SaleStatus::REFUNDED, SaleStatus::payment($this->facts(['isRefunded' => true, 'paidThroughGateway' => true])));
        $this->assertSame(SaleStatus::REFUNDED, SaleStatus::payment($this->facts(['isRefunded' => true, 'autoRefundSource' => AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, 'confirmedBySettlementReport' => true])));
        // A voided gateway row.
        $this->assertSame(SaleStatus::REFUNDED, SaleStatus::payment($this->facts(['settlementStatus' => VendTransaction::SETTLEMENT_REFUNDED, 'paidThroughGateway' => true])));
    }

    public function test_retained_credit_rows_get_their_own_words(): void
    {
        // The sale approved from banked credit: no fresh auth, no money moved on this row.
        $consumer = $this->facts(['isRetainedCreditSettlement' => true]);
        $this->assertSame(SaleStatus::RETAINED_CREDIT, SaleStatus::payment($consumer));
        $this->assertStringContainsString('no fresh card authorisation', SaleStatus::paymentNote($consumer));

        // The failed sale it made whole: goods, not money, went back — not "Refunded".
        $source = $this->facts(['isRefunded' => true, 'autoRefundSource' => AutoRefundSource::RETAINED_CREDIT_REVEND]);
        $this->assertSame(SaleStatus::RE_VENDED, SaleStatus::payment($source));
        $this->assertStringContainsString('no money was returned', SaleStatus::paymentNote($source));

        $this->assertNull(SaleStatus::paymentNote($this->facts(['paidThroughGateway' => true])));
    }

    public function test_a_channel_dispensed_on_codes_0_and_6_or_no_code_at_all(): void
    {
        $this->assertTrue(SaleStatus::itemDispensed(0));
        $this->assertTrue(SaleStatus::itemDispensed('0'));
        $this->assertTrue(SaleStatus::itemDispensed(6));
        $this->assertTrue(SaleStatus::itemDispensed(null));
        $this->assertTrue(SaleStatus::itemDispensed(''));

        foreach ([1, 3, 4, 5, 7, 9, 42, 45, 77, 8] as $fault) {
            $this->assertFalse(SaleStatus::itemDispensed($fault), "code {$fault} is a failed dispense");
        }
        $this->assertFalse(SaleStatus::itemDispensed('garbage'));

        $this->assertSame(SaleStatus::DISPENSED, SaleStatus::itemDispense(6));
        $this->assertSame(SaleStatus::FAILED, SaleStatus::itemDispense(4));
    }

    /** The screenshot row: card terminal, TXN_SRC 0, SErr 4 — money taken, nothing dropped. */
    public function test_single_sale_is_judged_on_the_header_error_code(): void
    {
        $this->assertSame(SaleStatus::FAILED, SaleStatus::dispense($this->facts(['headerErrorCode' => 4])));
        $this->assertSame(SaleStatus::DISPENSED, SaleStatus::dispense($this->facts(['headerErrorCode' => 0])));
        // Legacy single rows store no code at all: the machine reported no fault.
        $this->assertSame(SaleStatus::DISPENSED, SaleStatus::dispense($this->facts(['headerErrorCode' => null])));
        // A voided row still shows what the machine said about the dispense.
        $this->assertSame(SaleStatus::FAILED, SaleStatus::dispense($this->facts(['headerErrorCode' => 7, 'settlementStatus' => VendTransaction::SETTLEMENT_REFUNDED])));
    }

    public function test_multiple_purchase_carries_the_verdict_on_its_item_rows_not_the_parent(): void
    {
        // The header code of a multiple is meaningless (top-level SErr / 0) — the parent is blank,
        // each item row answers for itself through itemDispense().
        $this->assertSame(SaleStatus::ON_ITEMS, SaleStatus::dispense($this->facts(['isMultiple' => true, 'headerErrorCode' => 4])));
        $this->assertSame('', SaleStatus::dispense($this->facts(['isMultiple' => true, 'headerErrorCode' => 0])));
    }

    public function test_gateway_rows_without_a_machine_verdict_are_not_called_dispensed(): void
    {
        // Paid, dispense outcome still open.
        $this->assertSame(SaleStatus::PENDING, SaleStatus::dispense($this->facts(['settlementStatus' => VendTransaction::SETTLEMENT_PENDING, 'paidThroughGateway' => true])));

        // Paid and settled, but the machine never sent a TRADE — a null error code here
        // means "no report", not "no fault" (the trap the refund screen fixed). Multiples too.
        $this->assertSame(SaleStatus::NO_REPORT, SaleStatus::dispense($this->facts(['isFoundInTransaction' => false, 'paidThroughGateway' => true])));
        $this->assertSame(SaleStatus::NO_REPORT, SaleStatus::dispense($this->facts(['isMultiple' => true, 'isFoundInTransaction' => false, 'paidThroughGateway' => true])));
    }

    public function test_facts_are_read_from_a_row_with_the_grid_and_export_aliases(): void
    {
        $row = (object) [
            'is_multiple' => 0, 'vend_channel_error_code' => '4', 'settlement_status' => '2', 'is_found_in_transaction' => 1,
            'is_refunded' => 0, 'auto_refund_source' => null, 'is_retained_credit_settlement' => 0,
            'payment_method_gateway_id' => 2, 'card_settlement_synced_at' => null,
        ];
        $facts = SaleFacts::fromRow($row);

        $this->assertTrue($facts->paidThroughGateway);
        $this->assertFalse($facts->confirmedBySettlementReport);
        $this->assertSame('4', $facts->headerErrorCode);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $facts->settlementStatus);
        $this->assertSame(SaleStatus::PAID, SaleStatus::payment($facts));
        $this->assertSame(SaleStatus::FAILED, SaleStatus::dispense($facts));

        // A row that never selected the rail alias reads as unconfirmed, never as Paid.
        $bare = SaleFacts::fromRow((object) ['is_multiple' => 0, 'settlement_status' => 2]);
        $this->assertFalse($bare->paidThroughGateway);
        $this->assertSame(SaleStatus::UNCONFIRMED, SaleStatus::payment($bare));
    }
}
