<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Services\CustomerSummaryAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An INACTIVE site stops accruing flat location fees from its Inactive Date
 * (termination_date), the same way a Removed site stops at its Removed Date
 * (Brian, 2026-09-15). Before this, three sites set Inactive instead of Removed
 * — Penang Lane Ji Hotel among them — kept billing rent every month and Finance
 * waived each month by hand.
 *
 * Forward-only: the stop is never earlier than INACTIVE_FEE_STOP_FROM
 * (2026-09-01), so every month generated before it keeps its fee.
 *
 * The rule is gated on the CURRENT status: an Active site can carry a stale
 * termination_date (machine unbind stamps it, a legacy import stamped
 * 2024-02-07) and must keep paying.
 */
class InactiveSiteStopsLocationFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function site(string $name, array $attrs): Customer
    {
        $c = new Customer;
        $c->forceFill(array_merge([
            'name' => $name,
            'code' => $name,
            'operator_id' => 1,
            'begin_date' => '2026-03-29 08:00:00',
            'active_date' => '2026-03-30',
            'contract_commission_type' => CustomerSummaryAggregator::CONTRACT_TYPE_RENTAL,
            'contract_commission_value' => 200,
        ], $attrs))->save();

        return $c;
    }

    private function feeFor(Customer $c, string $yearMonth): ?int
    {
        $row = CustomerPeriodSummary::query()
            ->where('customer_id', $c->id)
            ->where('year_month', $yearMonth)
            ->first();

        return $row ? (int) $row->location_fees_cents : null;
    }

    public function test_fee_end_date_follows_status(): void
    {
        $row = fn (int $status, ?string $removed, ?string $termination) => (object) [
            'status_id' => $status, 'removed_date' => $removed, 'termination_date' => $termination,
        ];

        // Inactive → the Inactive Date ends the window.
        $this->assertSame('2026-09-10', CustomerSummaryAggregator::feeEndDate(
            $row(Customer::STATUS_INACTIVE, null, '2026-09-10 02:26:19')
        ));
        // …but never before the forward-only floor: older months are left alone.
        $this->assertSame('2026-09-01', CustomerSummaryAggregator::feeEndDate(
            $row(Customer::STATUS_INACTIVE, null, '2026-07-01 02:26:19')
        ));
        // Inactive with an earlier Removed Date keeps the earlier one.
        $this->assertEquals('2026-06-15', CustomerSummaryAggregator::feeEndDate(
            $row(Customer::STATUS_INACTIVE, '2026-06-15', '2026-07-01 02:26:19')
        ));
        // Active with a stale termination_date is NOT capped.
        $this->assertNull(CustomerSummaryAggregator::feeEndDate(
            $row(Customer::STATUS_ACTIVE, null, '2024-02-07 00:00:00')
        ));
        // Removed keeps its Removed Date, termination_date ignored.
        $this->assertEquals('2026-08-16', CustomerSummaryAggregator::feeEndDate(
            $row(Customer::STATUS_REMOVED, '2026-08-16', '2026-07-01 00:00:00')
        ));
        // Inactive with no Inactive Date → nothing to cap on.
        $this->assertNull(CustomerSummaryAggregator::feeEndDate(
            $row(Customer::STATUS_INACTIVE, null, null)
        ));
    }

    public function test_inactive_site_stops_accruing_after_its_inactive_date(): void
    {
        $jiHotel = $this->site('Ji Hotel', [
            'status_id' => Customer::STATUS_INACTIVE,
            'termination_date' => '2026-07-01 02:26:19',
        ]);
        $midMonth = $this->site('Gnosis', [
            'status_id' => Customer::STATUS_INACTIVE,
            'termination_date' => '2026-08-11 09:00:00',
        ]);
        $staleActive = $this->site('Still trading', [
            'status_id' => Customer::STATUS_ACTIVE,
            'termination_date' => '2024-02-07 00:00:00',
        ]);
        $removed = $this->site('Removed mid-month', [
            'status_id' => Customer::STATUS_REMOVED,
            'removed_date' => '2026-08-16',
        ]);
        $inactiveInSeptember = $this->site('MINDEF', [
            'status_id' => Customer::STATUS_INACTIVE,
            'termination_date' => '2026-09-10 10:08:29',
        ]);

        CustomerSummaryAggregator::persistMonth(Carbon::parse('2026-08-01'));

        // Months before 2026-09 are left as they were generated: full fee.
        $this->assertSame(20000, $this->feeFor($jiHotel, '2026-08-01'));
        $this->assertSame(20000, $this->feeFor($midMonth, '2026-08-01'));
        // Active with a leftover Inactive Date: full month.
        $this->assertSame(20000, $this->feeFor($staleActive, '2026-08-01'));
        // Removed behaviour unchanged: 15 of 31 days.
        $this->assertSame((int) round(20000 * 15 / 31), $this->feeFor($removed, '2026-08-01'));

        // The in-progress month: the Inactive site stays out of September too.
        CustomerSummaryAggregator::persistMonth(Carbon::parse('2026-09-01'));
        $this->assertNull($this->feeFor($jiHotel, '2026-09-01'));
        $this->assertNull($this->feeFor($midMonth, '2026-09-01'));
        $this->assertSame(20000, $this->feeFor($staleActive, '2026-09-01'));
        // Inactive on 10 Sep (exclusive, like Removed): 9 of 30 days.
        $this->assertSame(6000, $this->feeFor($inactiveInSeptember, '2026-09-01'));
    }

    /**
     * The Summary page re-derives unlocked rows live (row resource, headline
     * totals, Accumulate column, previous-month arrow) from raw customer rows
     * that must now select status_id + termination_date. Render it over an
     * Inactive site so a missing column fails here, not on prod.
     */
    public function test_summary_page_renders_live_fee_for_an_inactive_site(): void
    {
        $site = $this->site('Gnosis', [
            'status_id' => Customer::STATUS_INACTIVE,
            'termination_date' => '2026-08-11 09:00:00',
        ]);
        CustomerSummaryAggregator::persistMonth(Carbon::parse('2026-08-01'));
        // A stale current-month row as the nightly run left it before the fix.
        DB::table('customer_period_summaries')->insert([
            'customer_id' => $site->id, 'operator_id' => 1, 'year_month' => '2026-09-01',
            'period_start' => '2026-09-01', 'period_end' => '2026-09-14', 'is_current_month' => 1,
            'as_of_date' => '2026-09-14', 'sales_cents' => 0, 'gross_earning_cents' => 0,
            'location_fees_cents' => 20000, 'location_earning_cents' => -20000,
        ]);

        $user = \App\Models\User::factory()->create();
        $res = $this->actingAs($user)->get('/customers/summary?period_report=last_1_month&searched=1&status[]=1');

        $res->assertOk();
        $props = $res->viewData('page')['props'];
        $rows = collect($props['summaries']['data'])->keyBy('year_month');

        // Current month re-derived live: $0 despite the stale stored 20000.
        $this->assertSame(0, $rows['2026-09-01']['location_fees_cents']);
        // August (before the floor) keeps its full fee everywhere it shows.
        $this->assertSame(20000, $rows['2026-09-01']['prev_month']['location_fees_cents']);
        $this->assertSame(-20000, $rows['2026-09-01']['accumulate_vending_earning_cents']);
        $this->assertSame(20000, $rows['2026-08-01']['location_fees_cents']);
        $this->assertSame(20000, $props['totals']['location_fees_cents']);
    }

    public function test_inactive_then_active_again_skips_the_inactive_days(): void
    {
        $site = $this->site('Paused', [
            'status_id' => Customer::STATUS_ACTIVE,
            'active_date' => '2026-09-20',
            'termination_date' => '2026-09-05 10:00:00',
        ]);
        $this->logs($site, [
            [Customer::STATUS_ACTIVE, '2026-03-30'],
            [Customer::STATUS_INACTIVE, '2026-09-05'],
            [Customer::STATUS_ACTIVE, '2026-09-20'],
        ]);

        $this->assertContains($site->id, CustomerSummaryAggregator::reactivatedCustomerIds());

        CustomerSummaryAggregator::persistMonth(Carbon::parse('2026-09-01'));

        // Active 1–4 Sep and 20–30 Sep = 15 of 30 days.
        $this->assertSame(10000, $this->feeFor($site, '2026-09-01'));
    }

    public function test_an_inactive_spell_before_the_floor_is_not_a_reactivation(): void
    {
        $site = $this->site('Paused long ago', [
            'status_id' => Customer::STATUS_ACTIVE,
            'termination_date' => '2026-08-05 10:00:00',
        ]);
        $this->logs($site, [
            [Customer::STATUS_ACTIVE, '2026-03-30'],
            [Customer::STATUS_INACTIVE, '2026-08-05'],
            [Customer::STATUS_ACTIVE, '2026-08-20'],
        ]);

        $this->assertNotContains($site->id, CustomerSummaryAggregator::reactivatedCustomerIds());

        CustomerSummaryAggregator::persistMonth(Carbon::parse('2026-08-01'));
        CustomerSummaryAggregator::persistMonth(Carbon::parse('2026-09-01'));

        // Old rule for August (Inactive never gated it), full fee from September.
        $this->assertSame(20000, $this->feeFor($site, '2026-08-01'));
        $this->assertSame(20000, $this->feeFor($site, '2026-09-01'));
    }

    private function logs(Customer $site, array $events): void
    {
        foreach ($events as [$status, $date]) {
            DB::table('customer_status_logs')->insert([
                'customer_id' => $site->id, 'status_id' => $status, 'status_date' => $date, 'source' => 'user',
            ]);
        }
    }
}
