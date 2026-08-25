<?php

namespace Tests\Feature;

use App\Http\Controllers\RefundController;
use App\Models\RefundTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refund ticket "pending match" deep link (2026-08-25): unmatched tickets get a
 * txn_link into Sales Transactions built from the machine code + the customer's
 * Day Chosen (resolved against the submission date), so Ops can eyeball the
 * day's sales before a transaction is matched. Tickets without a machine or a
 * usable day keep txn_link null, and the Show page falls back to plain text.
 */
class RefundPendingMatchTxnLinkTest extends TestCase
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

    private function makeTicket(array $attrs): RefundTicket
    {
        return RefundTicket::create(array_merge([
            'reference' => 'RF-TEST'.random_int(1000, 9999),
            'status' => RefundTicket::STATUS_SUBMITTED,
            'is_manual' => true,
            'claimed_amount_cents' => 0,
        ], $attrs));
    }

    public function test_unmatched_ticket_links_to_the_chosen_days_sales(): void
    {
        $t = $this->makeTicket(['vend_code' => '2870', 'entered_day' => 'today']);

        $row = $this->rowFor($t);
        $day = $t->created_at->toDateString();

        $this->assertFalse($row['matched']);
        $this->assertSame(
            '/vends/transactions?'.http_build_query([
                'codes' => '2870',
                'date_from' => $day.' 00:00:00',
                'date_to' => $day.' 23:59:59',
            ]),
            $row['txn_link']
        );
    }

    public function test_yesterday_and_custom_dates_anchor_the_window(): void
    {
        $yesterday = $this->rowFor($this->makeTicket(['vend_code' => '2870', 'entered_day' => 'yesterday']));
        $this->assertStringContainsString(
            'date_from='.urlencode(now()->subDay()->toDateString().' 00:00:00'),
            $yesterday['txn_link']
        );

        $custom = $this->rowFor($this->makeTicket(['vend_code' => '2870', 'entered_day' => '2026-08-20']));
        $this->assertStringContainsString('date_from='.urlencode('2026-08-20 00:00:00'), $custom['txn_link']);
        $this->assertStringContainsString('date_to='.urlencode('2026-08-20 23:59:59'), $custom['txn_link']);
    }

    public function test_no_machine_or_no_day_means_no_link(): void
    {
        $noDay = $this->rowFor($this->makeTicket(['vend_code' => '2870']));
        $this->assertNull($noDay['txn_link']);

        $noMachine = $this->rowFor($this->makeTicket(['entered_day' => 'today']));
        $this->assertNull($noMachine['txn_link']);
    }
}
