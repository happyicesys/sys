<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\Sales\OpsJobItemSalesWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An ops-job stop's "Transaction S$ (Qty)" (acc_total_*) = the machine's sales since
 * its previous completed stop. A chiller's sales are CityBox orders pulled after the
 * fact, so they are placed by transaction_datetime (pay time), never created_at (pull
 * time) — every chiller stop read $0 on the Operation Dashboard until 2026-10-14.
 */
class OpsJobItemSalesWindowTest extends TestCase
{
    use RefreshDatabase;

    private int $jobCode = 900300;

    private function machine(string $type): Vend
    {
        $site = Customer::create(['name' => 'Site '.$type, 'code' => 10000 + $this->jobCode, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);

        return Vend::create(['code' => 6000 + ($this->jobCode % 100), 'machine_type' => $type, 'is_active' => 1, 'operator_id' => 1, 'customer_id' => $site->id]);
    }

    private function stop(Vend $vend, string $completedAt): OpsJobItem
    {
        $job = OpsJob::create(['code' => $this->jobCode++, 'date' => substr($completedAt, 0, 10), 'status' => 1, 'operator_id' => 1]);

        return OpsJobItem::create([
            'ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $vend->customer_id,
            'status' => OpsJob::STATUS_DELIVERED, 'completed_at' => Carbon::parse($completedAt),
        ]);
    }

    private function sale(Vend $vend, int $cents, string $paidAt, string $createdAt): void
    {
        $t = VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $vend->id, 'customer_id' => $vend->customer_id, 'vend_channel_id' => 0,
            'amount' => $cents, 'transaction_datetime' => Carbon::parse($paidAt), 'gst_vat_rate' => 0, 'operator_id' => 1,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
        VendTransaction::whereKey($t->id)->update(['created_at' => Carbon::parse($createdAt)]);
    }

    public function test_a_chiller_stop_counts_its_orders_by_pay_time_not_pull_time(): void
    {
        $chiller = $this->machine(Vend::MACHINE_TYPE_SMART_CHILLER);
        $this->stop($chiller, '2026-09-16 19:59:34');
        $second = $this->stop($chiller, '2026-10-08 15:33:21');

        // Paid inside the window, pulled by the 2026-10-13 backfill.
        $this->sale($chiller, 290, '2026-09-17 12:00:00', '2026-10-13 13:00:00');
        $this->sale($chiller, 1240, '2026-10-05 09:00:00', '2026-10-13 13:00:00');
        // Paid after the stop — the NEXT stop's sale.
        $this->sale($chiller, 520, '2026-10-11 09:00:00', '2026-10-13 13:00:00');

        $this->assertTrue(app(OpsJobItemSalesWindow::class)->apply($second));

        $second->refresh();
        $this->assertSame(1530, (int) $second->acc_total_amount);
        $this->assertSame(2, (int) $second->acc_total_count);
    }

    public function test_a_vending_stop_still_counts_by_arrival(): void
    {
        $vm = $this->machine(Vend::MACHINE_TYPE_VENDING_MACHINE);
        $this->stop($vm, '2026-10-01 10:00:00');
        $second = $this->stop($vm, '2026-10-08 10:00:00');

        // Board clock says September, but it reached us inside the window: counted, as before.
        $this->sale($vm, 150, '2026-09-20 10:00:00', '2026-10-03 10:00:00');

        app(OpsJobItemSalesWindow::class)->apply($second);

        $this->assertSame(150, (int) $second->refresh()->acc_total_amount);
    }

    public function test_the_first_stop_has_no_window(): void
    {
        $chiller = $this->machine(Vend::MACHINE_TYPE_SMART_CHILLER);
        $first = $this->stop($chiller, '2026-09-16 19:59:34');

        $this->assertFalse(app(OpsJobItemSalesWindow::class)->apply($first));
    }

    public function test_the_nightly_command_recounts_chiller_stops_only(): void
    {
        $chiller = $this->machine(Vend::MACHINE_TYPE_SMART_CHILLER);
        $this->stop($chiller, now()->subDays(10)->toDateTimeString());
        $chillerStop = $this->stop($chiller, now()->subDays(2)->toDateTimeString());
        $this->sale($chiller, 860, now()->subDays(5)->toDateTimeString(), now()->toDateTimeString());

        $vm = $this->machine(Vend::MACHINE_TYPE_VENDING_MACHINE);
        $this->stop($vm, now()->subDays(10)->toDateTimeString());
        $vmStop = $this->stop($vm, now()->subDays(2)->toDateTimeString());
        $this->sale($vm, 100, now()->subDays(5)->toDateTimeString(), now()->subDays(5)->toDateTimeString());

        $this->artisan('ops-jobs:recompute-sales-window --dry-run')->assertSuccessful();
        $this->assertSame(0, (int) $chillerStop->refresh()->acc_total_amount, 'a dry run writes nothing');

        $this->artisan('ops-jobs:recompute-sales-window')->assertSuccessful();
        $this->assertSame(860, (int) $chillerStop->refresh()->acc_total_amount);
        $this->assertSame(0, (int) $vmStop->refresh()->acc_total_amount, 'a vending stop is left to its completion-time figure');
    }
}
