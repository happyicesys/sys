<?php

namespace Tests\Feature;

use App\Jobs\Vend\RefreshMachineSalesDay;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use App\Services\Freezer\FreezerSaleAudit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A closed day's sale changes the next morning: Sales Transactions reads the sale, the Ops Dashboard
 * reads vend_records. Both have to agree straight away, not after the 02:00 rollup (50001, 2026-11-03:
 * S$38.80 against S$34.90 after two T05 refunds).
 */
class RefreshMachineSalesDayTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-11-03';

    private Vend $vend;

    private Vend $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->other = Vend::create(['code' => 50002, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sale(Vend $vend, int $amount, string $at = self::DAY.' 19:35:00', int $qty = 1): VendTransaction
    {
        return VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $vend->id, 'vend_channel_id' => 0, 'amount' => $amount, 'qty' => $qty,
            'gst_vat_rate' => 9, 'revenue' => $amount, 'transaction_datetime' => $at, 'operator_id' => 1,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
    }

    /** The row the 00:06 nightly run left behind. */
    private function snapshot(Vend $vend, int $amount, int $count): VendRecord
    {
        return VendRecord::create([
            'vend_id' => $vend->id, 'vend_code' => $vend->code, 'operator_id' => 1, 'customer_id' => null,
            'date' => self::DAY, 'day' => 3, 'month' => 11, 'year' => 2026, 'monthname' => 'November',
            'total_amount' => $amount, 'total_count' => $count, 'all_total_count' => $count,
        ]);
    }

    public function test_the_machines_closed_day_and_dashboard_take_the_new_figures(): void
    {
        $this->sale($this->vend, 880, qty: 2);
        $this->sale($this->vend, 1000, qty: 3);
        $this->snapshot($this->vend, 2270, 6);

        RefreshMachineSalesDay::dispatchSync($this->vend->id, self::DAY);

        $record = VendRecord::where('vend_id', $this->vend->id)->sole();
        $this->assertSame(1880, (int) $record->total_amount);
        $this->assertSame(5, (int) $record->total_count);

        $totals = $this->vend->fresh()->vend_transaction_totals_json;
        $this->assertSame(1880, (int) $totals['yesterday_amount']);
        $this->assertSame(5, (int) $totals['yesterday_count']);
    }

    public function test_no_other_machine_is_touched(): void
    {
        $this->sale($this->vend, 880);
        $this->sale($this->other, 500);
        $this->snapshot($this->other, 700, 2);

        RefreshMachineSalesDay::dispatchSync($this->vend->id, self::DAY);

        $this->assertSame(700, (int) VendRecord::where('vend_id', $this->other->id)->sole()->total_amount);
    }

    public function test_a_day_whose_last_sale_stopped_counting_goes_to_zero(): void
    {
        $this->sale($this->vend, 880)->forceFill(['settlement_status' => VendTransaction::SETTLEMENT_REFUNDED])->save();
        $this->snapshot($this->vend, 880, 1);

        RefreshMachineSalesDay::dispatchSync($this->vend->id, self::DAY);

        $record = VendRecord::where('vend_id', $this->vend->id)->sole();
        $this->assertSame(0, (int) $record->total_amount);
        $this->assertSame(0, (int) $record->total_count);
        $this->assertSame(0, (int) $this->vend->fresh()->vend_transaction_totals_json['yesterday_amount']);
    }

    public function test_a_refund_on_a_closed_day_refreshes_that_machines_day(): void
    {
        Queue::fake();

        app(FreezerSaleAudit::class)->applyRefund($this->sale($this->vend, 1130), 880, ['by' => 'Daniel']);

        Queue::assertPushed(RefreshMachineSalesDay::class, fn ($job) => $job->vendId === $this->vend->id && $job->day === self::DAY);
        Queue::assertNotPushed(SyncVendTransactionTotalsJson::class);
    }

    public function test_a_correction_on_a_closed_day_refreshes_that_machines_day(): void
    {
        Queue::fake();

        app(FreezerSaleAudit::class)->applyCorrection($this->sale($this->vend, 180), [], [], ['by' => ['name' => 'Daniel']]);

        Queue::assertPushed(RefreshMachineSalesDay::class, fn ($job) => $job->vendId === $this->vend->id && $job->day === self::DAY);
    }

    public function test_todays_sale_only_resyncs_the_totals(): void
    {
        Queue::fake();

        app(FreezerSaleAudit::class)->applyRefund($this->sale($this->vend, 1130, '2026-11-04 08:30:00'), 880, ['by' => 'Daniel']);

        Queue::assertPushed(SyncVendTransactionTotalsJson::class);
        Queue::assertNotPushed(RefreshMachineSalesDay::class);
    }
}
