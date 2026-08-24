<?php

namespace Tests\Feature;

use App\Http\Controllers\RefundController;
use App\Models\RefundTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refund Requests list — manual / pending-match tickets show what the customer
 * KEYED IN on the manual form (2026-08-24): the Channel column falls back to
 * manual_items_summary (parsed per item), Paid Amt to entered_amount +
 * manual_pay_method, Refund Amt to the entered (claimed) amount. Matched
 * tickets are unchanged: their columns come from the transaction.
 */
class RefundIndexManualKeyedColumnsTest extends TestCase
{
    use RefreshDatabase;

    /** buildRows() output for one ticket. */
    private function rowFor(RefundTicket $t): array
    {
        $controller = app(RefundController::class);
        $method = (new \ReflectionClass($controller))->getMethod('buildRows');
        $method->setAccessible(true);

        return $method->invoke($controller, collect([$t]))[$t->id];
    }

    public function test_manual_ticket_row_carries_the_customer_keyed_values(): void
    {
        $t = RefundTicket::create([
            'reference' => 'RF-TEST001',
            'vend_code' => '2870',
            'status' => RefundTicket::STATUS_SUBMITTED,
            'is_manual' => true,
            'claimed_amount_cents' => 0,
            'entered_amount_cents' => 430,
            'manual_pay_method' => 'PayNow / QR code',
            'manual_items_summary' => 'Blind Stick (Channel #11) × 2; Not sure / not listed',
        ]);

        $row = $this->rowFor($t);

        $this->assertFalse($row['matched']);
        $this->assertNull($row['paid_amount']);
        $this->assertSame('4.30', $row['entered_amount']);
        $this->assertSame('PayNow / QR code', $row['manual_pay_method']);
        $this->assertSame([
            ['channel' => '11', 'product_name' => 'Blind Stick × 2'],
            ['channel' => null, 'product_name' => 'Not sure / not listed'],
        ], $row['manual_affected_items']);
    }

    public function test_ticket_without_manual_fields_has_empty_fallbacks(): void
    {
        $t = RefundTicket::create([
            'reference' => 'RF-TEST002',
            'vend_code' => '2870',
            'status' => RefundTicket::STATUS_SUBMITTED,
        ]);

        $row = $this->rowFor($t);

        $this->assertSame([], $row['manual_affected_items']);
        $this->assertNull($row['manual_pay_method']);
        $this->assertNull($row['entered_amount']);
    }
}
