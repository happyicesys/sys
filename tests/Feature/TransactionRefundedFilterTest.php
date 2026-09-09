<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Auto Refunded?" filter on Sales Transactions
 * (VendTransaction::scopeApplyRefundedFilter), which the index and the report
 * export both use.
 *
 * A ticket links to its sale by vend_transaction_id OR by order_id. Both keys
 * used to sit inside ONE correlated EXISTS, which MySQL cannot serve from an
 * index — on prod (2026-09-09) "Refunded (any)" over five days of card sales
 * scanned every refund ticket for every sale and the page never loaded. The
 * rewrite is one EXISTS per key; these cases pin the behaviour that must
 * survive it, including both link paths.
 */
class TransactionRefundedFilterTest extends TestCase
{
    use RefreshDatabase;

    private const VEND = 1320;

    private function sale(string $orderId, array $attrs = []): VendTransaction
    {
        $card = PaymentMethod::firstOrCreate(['code' => 1], ['name' => 'Card Terminal', 'is_active' => true]);

        return VendTransaction::create(array_merge([
            'order_id' => $orderId, 'vend_id' => self::VEND, 'transaction_datetime' => '2026-09-03 10:00:00',
            'amount' => 240, 'qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0, 'payment_method_id' => $card->id,
        ], $attrs));
    }

    private function ticket(array $attrs): RefundTicket
    {
        return RefundTicket::create(array_merge([
            'reference' => 'RF-'.uniqid(), 'vend_code' => '2542', 'vend_id' => self::VEND,
            'claimed_amount_cents' => 240, 'status' => RefundTicket::STATUS_APPROVED,
            // Every payable ticket on prod carries a payout method (paynow 984,
            // paypal 103, none without one), and "manual" is defined as "not the
            // auto-refund channel", so the fixture carries one too.
            'refund_method' => RefundTicket::METHOD_PAYNOW,
        ], $attrs));
    }

    /** @return string[] order ids the filter returns, sorted */
    private function filtered(string $value): array
    {
        return VendTransaction::withoutGlobalScopes()
            ->applyRefundedFilter($value)
            ->orderBy('order_id')
            ->pluck('order_id')
            ->all();
    }

    public function test_each_value_selects_the_right_sales_through_both_ticket_link_paths(): void
    {
        $flagged = $this->sale('FLAGGED')->forceFill(['is_refunded' => true])->save();
        $this->sale('PLAIN');
        $byTxnId = $this->sale('BY-TXN-ID');
        $byOrderId = $this->sale('BY-ORDER-ID');
        $rejected = $this->sale('REJECTED-TICKET');
        $manualTicket = $this->sale('MANUAL');
        $autoTicket = $this->sale('AUTO-TICKET');

        // Linked by the numeric key…
        $this->ticket(['vend_transaction_id' => $byTxnId->id]);
        // …and by the order id, the path a ticket raised before the sale landed takes.
        $this->ticket(['order_id' => $byOrderId->order_id]);
        // A rejected ticket is not a refund.
        $this->ticket(['vend_transaction_id' => $rejected->id, 'status' => RefundTicket::STATUS_REJECTED]);
        // Paid out by hand (PayNow): manual, not auto.
        $this->ticket(['vend_transaction_id' => $manualTicket->id, 'status' => RefundTicket::STATUS_COMPLETED]);
        // Auto-refund channel: auto, never manual.
        $this->ticket(['vend_transaction_id' => $autoTicket->id, 'refund_method' => RefundTicket::METHOD_NAYAX_AUTO]);

        unset($flagged);

        $this->assertSame(
            ['AUTO-TICKET', 'BY-ORDER-ID', 'BY-TXN-ID', 'FLAGGED', 'MANUAL'],
            $this->filtered('true'),
            'refunded = the gateway flag or an active ticket on either link key'
        );

        $this->assertSame(['PLAIN', 'REJECTED-TICKET'], $this->filtered('false'));

        $this->assertSame(['AUTO-TICKET', 'FLAGGED'], $this->filtered('auto'));

        $this->assertSame(['BY-ORDER-ID', 'BY-TXN-ID', 'MANUAL'], $this->filtered('manual'));

        // 'all' and an empty value never touch the query.
        $this->assertCount(7, $this->filtered('all'));
    }

    public function test_each_ticket_link_key_is_correlated_in_its_own_exists(): void
    {
        // The performance contract, not a style rule: one EXISTS per key is what
        // lets MySQL use refund_tickets' own indexes. A single EXISTS holding
        // `vend_transaction_id = … OR order_id = …` is the shape that hung.
        $sql = strtolower(VendTransaction::withoutGlobalScopes()->applyRefundedFilter('true')->toSql());

        $this->assertSame(2, substr_count($sql, 'exists ('), 'one EXISTS per link key');
        $this->assertStringNotContainsString(
            'refund_tickets`.`vend_transaction_id` = `vend_transactions`.`id` or `refund_tickets`.`order_id`',
            $sql,
            'the two keys must not share one correlated subquery'
        );
    }
}
