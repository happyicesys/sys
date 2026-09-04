<?php

namespace Tests\Feature;

use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use App\Models\Operator;
use App\Models\RefundPayoutBatch;
use App\Models\RefundTicket;
use App\Services\Commission\CommissionSettlementService;
use App\Services\Refund\RefundSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Paid date on the two settlement Show pages (Refund Settlement, Site Settlement).
 *
 * The bank run and the admin ticking the rows are rarely the same day, so the
 * mark-done bar carries a date picker. The chosen day is the PAYOUT date — it
 * lands on refund_tickets.paid_at / customer_period_summaries.paid_date and the
 * ledger entry_date — while completed_at stays the moment it was recorded.
 * Picking today must keep the wall-clock time rather than snapping to midnight.
 */
class SettlementPaidDateTest extends TestCase
{
    use RefreshDatabase;

    // ---- Refund Settlement ----

    private function makeRefundSettlement(): array
    {
        $settlement = RefundPayoutBatch::create([
            'reference' => 'RST-260904-HIPL-01',
            'is_settlement' => true,
            'settlement_date' => '2026-09-04',
            'operator_id' => 1,
            'sequence' => 1,
            'method' => 'paynow',
            'status' => RefundPayoutBatch::STATUS_OPEN,
        ]);

        $ticket = RefundTicket::create([
            'reference' => 'RF-000001',
            'operator_id' => 1,
            'refund_method' => RefundTicket::METHOD_PAYNOW,
            'payout_destination' => '89844833',
            'contact_email' => 'customer@example.com',
            'final_refund_amount_cents' => 250,
            'status' => RefundTicket::STATUS_SCHEDULED,
            'payout_batch_id' => $settlement->id,
        ]);

        return [$settlement, $ticket];
    }

    public function test_refund_mark_done_stores_the_picked_payout_date()
    {
        Queue::fake();
        [$settlement, $ticket] = $this->makeRefundSettlement();

        app(RefundSettlementService::class)->markDone($settlement, [$ticket->id], null, 'Admin', '2026-08-28');

        $ticket->refresh();
        $this->assertSame('2026-08-28 00:00:00', $ticket->paid_at->toDateTimeString());
        // completed_at is when it was recorded, not the payout date.
        $this->assertTrue($ticket->completed_at->isToday());
    }

    public function test_refund_mark_done_without_a_date_pays_today()
    {
        Queue::fake();
        [$settlement, $ticket] = $this->makeRefundSettlement();

        app(RefundSettlementService::class)->markDone($settlement, [$ticket->id], null, 'Admin');

        $this->assertTrue($ticket->refresh()->paid_at->isToday());
    }

    public function test_refund_mark_done_with_todays_date_keeps_the_wall_clock_time()
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-04 15:30:00');
        [$settlement, $ticket] = $this->makeRefundSettlement();

        app(RefundSettlementService::class)->markDone($settlement, [$ticket->id], null, 'Admin', '2026-09-04');

        $this->assertSame('2026-09-04 15:30:00', $ticket->refresh()->paid_at->toDateTimeString());
        Carbon::setTestNow();
    }

    // ---- Site (commission) Settlement ----

    private function makeSiteSettlement(): array
    {
        $operator = Operator::create(['code' => 'HIPL', 'name' => 'Happy Ice', 'is_active' => true]);
        $customer = Customer::create(['code' => 'C1', 'name' => 'The Inflora Condo', 'is_active' => true, 'operator_id' => $operator->id]);

        $settlement = CommissionSettlement::create([
            'reference' => 'CST-260828-HIPL-01',
            'settlement_date' => '2026-08-28',
            'operator_id' => $operator->id,
            'sequence' => 1,
            'status' => CommissionSettlement::STATUS_OPEN,
            'count' => 1,
            'total_cents' => 4000,
        ]);

        $summary = CustomerPeriodSummary::create([
            'customer_id' => $customer->id,
            'operator_id' => $operator->id,
            'year_month' => '2026-07-01',
            'is_current_month' => false,
            'location_fees_cents' => 4000,
            'external_subsidize_cents' => 0,
            'locked_at' => now(),
            'is_locked' => true,
            'commission_settlement_id' => $settlement->id,
        ]);

        return [$settlement, $summary];
    }

    public function test_site_mark_done_stores_the_picked_payout_date_on_the_row_and_the_ledger()
    {
        [$settlement, $summary] = $this->makeSiteSettlement();

        app(CommissionSettlementService::class)->markDone($settlement, [$summary->id], null, 'Admin', '2026-09-04');

        $summary->refresh();
        $this->assertTrue($summary->is_paid);
        $this->assertSame('2026-09-04', Carbon::parse($summary->paid_date)->toDateString());

        $entry = CustomerSettlement::where('customer_period_summary_id', $summary->id)
            ->where('source', CustomerSettlement::SOURCE_PAID_ACTION)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame('2026-09-04', Carbon::parse($entry->entry_date)->toDateString());
        $this->assertSame(-4000, (int) $entry->amount_cents);
    }

    public function test_site_mark_done_without_a_date_pays_today()
    {
        [$settlement, $summary] = $this->makeSiteSettlement();

        app(CommissionSettlementService::class)->markDone($settlement, [$summary->id], null, 'Admin');

        $this->assertSame(now()->toDateString(), Carbon::parse($summary->refresh()->paid_date)->toDateString());
    }
}
