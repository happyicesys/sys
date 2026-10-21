<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\PullCityboxOrders;
use App\Models\CityboxOrder;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\UnitCost;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Services\Citybox\ChillerStockLedger;
use App\Services\Citybox\CityboxOpenapiSync;
use App\Services\Citybox\CityboxOrderSync;
use App\Services\Citybox\CityboxPayMethod;
use App\Services\Sales\DirtyDayRegistry;
use App\Support\AutoRefundSource;
use App\Support\SaleFacts;
use App\Support\SaleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Citybox\ChillerMapping;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * CityBox orders (order_list, pulled) become chiller sales in vend_transactions and move OUR stock
 * ledger, once (Brian, 2026-10-13: their transactions are the source of truth; their live count
 * only checks ours).
 */
class CityboxOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private Vend $vend;

    private Customer $site;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-10-13 12:00:00');
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        Queue::fake([\App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class, \App\Jobs\Vend\SyncVendTransactionTotalsJson::class]);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);

        $operator = Operator::create(['code' => 'CB', 'name' => 'Citybox', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->site = Customer::create(['name' => 'NUS Hall', 'code' => 10001, 'operator_id' => $operator->id, 'status_id' => Customer::STATUS_ACTIVE]);
        $this->vend = Vend::create(['code' => 6001, 'code_prefix' => 'SGC', 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => $operator->id, 'customer_id' => $this->site->id]);
        $this->gw->seedDevice('E1');
        ChillerMapping::bind($this->vend, [101 => [90343, 10], 102 => [90404, 10]]);
        foreach ([90343, 90404] as $id) {
            Product::withoutGlobalScopes()->where('code', (string) $id)->update(['operator_id' => $operator->id]);
        }
        UnitCost::create(['product_id' => $this->product(90343)->id, 'cost' => 0.80, 'is_current' => true]);
        $this->gw->seedStock('E1', [
            ['id' => 90343, 'name' => 'Lalune', 'qty' => 9, 'layer' => 1, 'price' => '1.70'],
            ['id' => 90404, 'name' => 'Soda', 'qty' => 6, 'layer' => 1, 'price' => '1.50'],
        ]);
        // The chiller's first read seeds the ledger at 11:00.
        Carbon::setTestNow('2026-10-13 11:00:00');
        app(CityboxOpenapiSync::class)->syncAll();
        Carbon::setTestNow('2026-10-13 12:00:00');
        $this->vend->refresh();
    }

    private function product(int $cityboxId): Product
    {
        return Product::withoutGlobalScopes()->where('code', (string) $cityboxId)->firstOrFail();
    }

    private function qty(int $cityboxId): int
    {
        return (int) VendChannel::where('vend_id', $this->vend->id)->where('product_id', $this->product($cityboxId)->id)->value('qty');
    }

    private function pull(string ...$dates): array
    {
        return app(CityboxOrderSync::class)->syncDays($dates);
    }

    public function test_first_read_seeds_the_ledger_baseline(): void
    {
        $this->assertSame([9, 6], [$this->qty(90343), $this->qty(90404)]);
        $this->assertSame('2026-10-13 11:00:00', ChillerStockLedger::baselineAt($this->vend)->toDateTimeString());
    }

    public function test_a_basket_becomes_one_sale_with_an_item_per_unit_and_takes_stock_once(): void
    {
        $this->gw->seedOrder('2026-10-13', 133, 'E1', [[90343, 1, '1.70'], [90404, 2, '1.50']], time: '11:30:00', pay: 'APPLEPAY');

        $stats = $this->pull('2026-10-13');
        $this->assertSame([1, 1, 1], [$stats['changed'], $stats['projected'], $stats['stock_moved']]);

        $txn = VendTransaction::withoutGlobalScopes()->sole();
        $this->assertSame(['VI133', 470, 3, true], [$txn->order_id, (int) $txn->amount, (int) $txn->qty, (bool) $txn->is_multiple]);
        $this->assertSame([$this->vend->id, $this->site->id], [(int) $txn->vend_id, (int) $txn->customer_id]);
        $this->assertSame(CityboxPayMethod::APPLE_PAY, (int) PaymentMethod::find($txn->payment_method_id)->code);
        $this->assertSame('Citybox - Apple Pay', PaymentMethod::find($txn->payment_method_id)->name);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $txn->settlement_status);
        $items = $txn->vendTransactionItems()->orderBy('id')->get();
        $this->assertSame([170, 150, 150], $items->pluck('unit_price_amount')->map(fn ($v) => (int) $v)->all(), 'one row per unit');
        $this->assertSame(80.0, (float) $items[0]->unit_cost, 'the product\'s current unit cost, in cents');
        $this->assertEqualsWithDelta(470 / 1.09 - 80, (float) $txn->gross_profit, 1, 'revenue ex 9% GST less unit cost (whole cents)');
        $this->assertSame([8, 4], [$this->qty(90343), $this->qty(90404)], 'the order took its units off the ledger');

        // The same order again — nothing moves, nothing doubles.
        $stats = $this->pull('2026-10-13');
        $this->assertSame([0, 0, 0], [$stats['changed'], $stats['projected'], $stats['stock_moved']]);
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame([8, 4], [$this->qty(90343), $this->qty(90404)]);
    }

    public function test_a_single_unit_order_carries_its_product_on_the_row(): void
    {
        $this->gw->seedOrder('2026-10-13', 140, 'E1', [[90343, 1, '1.70']], time: '11:45:00');
        $this->pull('2026-10-13');

        $txn = VendTransaction::withoutGlobalScopes()->sole();
        $this->assertFalse((bool) $txn->is_multiple);
        $this->assertSame($this->product(90343)->id, (int) $txn->product_id);
        $this->assertSame(101, (int) $txn->vend_channel_code);
        $this->assertSame(0, $txn->vendTransactionItems()->count());
        $this->assertSame(CityboxPayMethod::CARD, (int) PaymentMethod::find($txn->payment_method_id)->code);
    }

    public function test_an_order_taken_before_the_ledger_baseline_is_a_sale_but_moves_no_stock(): void
    {
        // Taken 10:30; their 11:00 count already holds it.
        $this->gw->seedOrder('2026-10-13', 120, 'E1', [[90343, 2, '1.70']], time: '10:30:00');
        $this->pull('2026-10-13');

        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame(9, $this->qty(90343));
        $this->assertSame('in_baseline_count', CityboxOrder::sole()->stock_note);
    }

    public function test_pay_later_books_the_sale_on_the_pay_day_and_dirties_that_past_day(): void
    {
        // Taken 09-16, paid 09-30 (prod order 68).
        $this->gw->seedOrder('2026-09-16', 68, 'E1', [[90404, 1, '1.50']], time: '11:55:02', paidAt: '2026-09-30 18:00:14');
        $this->pull('2026-09-16');

        $txn = VendTransaction::withoutGlobalScopes()->sole();
        $this->assertSame('2026-09-30 18:00:14', $txn->transaction_datetime->toDateTimeString());
        $this->assertContains('2026-09-30', app(DirtyDayRegistry::class)->days());
        $this->assertSame(6, $this->qty(90404), 'taken weeks before our ledger began: inside the baseline count');
    }

    public function test_a_refund_marks_the_sale_refunded_and_leaves_the_stock_alone(): void
    {
        $this->gw->seedOrder('2026-10-13', 150, 'E1', [[90343, 1, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        $this->assertSame(8, $this->qty(90343));

        $this->gw->seedOrder('2026-10-13', 150, 'E1', [[90343, 1, '1.70']], status: 3, time: '11:50:00');
        $this->pull('2026-10-13');
        $txn = VendTransaction::withoutGlobalScopes()->sole();
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $txn->settlement_status, 'refund applied is still a sale');
        $this->assertSame('applied', $txn->meta_json['citybox']['refund']);

        $this->gw->seedOrder('2026-10-13', 150, 'E1', [[90343, 1, '1.70']], status: 4, time: '11:50:00');
        $this->pull('2026-10-13');
        $txn->refresh();
        $this->assertTrue((bool) $txn->is_refunded);
        $this->assertSame(AutoRefundSource::CITYBOX_REFUNDED, $txn->auto_refund_source);
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $txn->settlement_status);
        $this->assertSame(8, $this->qty(90343), 'a refund returns money, not goods');
        $this->assertNotNull(CityboxOrder::sole()->status_changed_at);
    }

    public function test_a_corrected_order_moves_stock_by_the_difference_only(): void
    {
        $this->gw->seedOrder('2026-10-13', 160, 'E1', [[90343, 3, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        $this->assertSame(6, $this->qty(90343));

        // Their AI re-judged the session: 1 unit, not 3.
        $this->gw->seedOrder('2026-10-13', 160, 'E1', [[90343, 1, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        $this->assertSame(8, $this->qty(90343), 'two units given back, never a second deduction');
        $txn = VendTransaction::withoutGlobalScopes()->sole();
        $this->assertSame([170, 1, false], [(int) $txn->amount, (int) $txn->qty, (bool) $txn->is_multiple]);
    }

    public function test_a_correction_after_a_newer_count_moves_nothing(): void
    {
        $this->gw->seedOrder('2026-10-13', 161, 'E1', [[90343, 3, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        // A Stock In count at 12:30 replaces the ledger.
        ChillerStockLedger::markBaseline($this->vend, Carbon::parse('2026-10-13 12:30:00'), 'frame A');
        Carbon::setTestNow('2026-10-13 13:00:00');
        $this->gw->seedOrder('2026-10-13', 161, 'E1', [[90343, 1, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');

        $this->assertSame(6, $this->qty(90343), 'the 12:30 count already reflects the truth');
    }

    public function test_a_stale_copy_of_the_vend_never_puts_an_old_baseline_back(): void
    {
        // The poll loads every vend at sweep start, then spends seconds on CityBox calls.
        $stale = Vend::withoutGlobalScopes()->find($this->vend->id);
        // Meanwhile a Stock In's read-back records a newer count.
        ChillerStockLedger::markBaseline(Vend::withoutGlobalScopes()->find($this->vend->id), Carbon::parse('2026-10-13 12:30:00'), 'frame A');
        // The poll saves its health mirror from the copy it loaded.
        $json = $stale->citybox_status_json ?? [];
        $json['poll'] = ['at' => '2026-10-13 12:31:00', 'ok' => true];
        $stale->forceFill(['citybox_status_json' => $json])->save();

        $this->assertSame('2026-10-13 12:30:00', ChillerStockLedger::baselineAt($this->vend->fresh())->toDateTimeString());

        // And an older count never moves it back.
        ChillerStockLedger::markBaseline($stale, Carbon::parse('2026-10-13 12:00:00'), 'read');
        $this->assertSame('2026-10-13 12:30:00', ChillerStockLedger::baselineAt($this->vend->fresh())->toDateTimeString());
    }

    public function test_an_order_deducted_while_the_count_waits_on_the_queue_stays_deducted(): void
    {
        Queue::fake([\App\Jobs\Vend\SyncVendChannels::class, \App\Jobs\Vend\SyncVendChannelErrorLog::class, \App\Jobs\Vend\SaveVendChannelsJson::class, \App\Jobs\Vend\SyncVendTransactionTotalsJson::class]);
        // A Stock In read-back: their count at 12:30 is 4 Lalune.
        Carbon::setTestNow('2026-10-13 12:30:00');
        $this->gw->seedStock('E1', [
            ['id' => 90343, 'name' => 'Lalune', 'qty' => 4, 'layer' => 1, 'price' => '1.70'],
            ['id' => 90404, 'name' => 'Soda', 'qty' => 6, 'layer' => 1, 'price' => '1.50'],
        ]);
        app(\App\Services\Citybox\StockPollService::class)->pushChannels($this->vend->fresh(), $this->gw->deviceStock('E1'), 'A', force: true, absolute: true, readAt: now());
        $frame = null;
        Queue::assertPushed(\App\Jobs\Vend\SyncVendChannels::class, function ($job) use (&$frame) {
            $frame = (fn () => $this->input)->call($job);

            return true;
        });

        // A customer takes one at 12:35; the minute pull deducts it before the frame job runs.
        Carbon::setTestNow('2026-10-13 12:36:00');
        $this->gw->seedOrder('2026-10-13', 210, 'E1', [[90343, 1, '1.70']], time: '12:35:00');
        $this->pull('2026-10-13');

        Carbon::setTestNow('2026-10-13 12:37:00');
        app()->call([new \App\Jobs\Vend\SyncVendChannels($frame, $this->vend->fresh()), 'handle']);

        $this->assertSame(3, $this->qty(90343), 'their 12:30 count less the 12:35 order');
        $this->assertSame('2026-10-13 12:30:00', ChillerStockLedger::baselineAt($this->vend->fresh())->toDateTimeString());

        // The next pull of the same order moves nothing.
        $this->pull('2026-10-13');
        $this->assertSame(3, $this->qty(90343));
    }

    public function test_a_test_rig_amount_moves_stock_but_is_never_a_sale(): void
    {
        $this->gw->seedOrder('2026-10-13', 170, 'E1', [[90343, 1, '0.10']], time: '11:50:00');
        $this->pull('2026-10-13');

        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame('skip:test_amount', CityboxOrder::sole()->projection_error);
        $this->assertSame(8, $this->qty(90343), 'the goods still left the cabinet');
    }

    public function test_an_unpaid_order_is_listed_as_unpaid_and_becomes_a_sale_once_paid(): void
    {
        // Prod order 347 (2026-10-20): four units taken, Alipay never captured.
        $this->gw->seedOrder('2026-10-13', 347, 'E1', [[90343, 1, '1.70'], [90404, 2, '1.50']], status: 0, time: '11:30:00', paidAt: '0000-00-00 00:00:00', pay: 'ALIPAY_CN');
        $stats = $this->pull('2026-10-13');
        $this->assertSame(1, $stats['projected']);

        $txn = VendTransaction::withoutGlobalScopes()->sole();
        $this->assertSame(VendTransaction::SETTLEMENT_UNPAID, (int) $txn->settlement_status);
        $this->assertSame([470, 3], [(int) $txn->amount, $txn->vendTransactionItems()->count()], 'what the customer owes, item by item');
        $this->assertSame('2026-10-13 11:30:00', $txn->transaction_datetime->toDateTimeString(), 'booked when the goods left');
        $this->assertSame(SaleStatus::UNPAID, SaleStatus::payment(SaleFacts::fromRow($txn)));
        $this->assertNotNull(SaleStatus::paymentNote(SaleFacts::fromRow($txn)));
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->countsAsSale()->count(), 'never a sale');
        $this->assertSame([8, 4], [$this->qty(90343), $this->qty(90404)], 'the goods still left the cabinet');
        $this->assertNull($this->vend->fresh()->last_vend_transaction_at, 'not a sale, so not the last sale');
        $this->assertNull(CityboxOrder::sole()->projection_error);

        // CityBox captures it on the 15th: the same row turns into the sale, on the pay day.
        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->gw->seedOrder('2026-10-13', 347, 'E1', [[90343, 1, '1.70'], [90404, 2, '1.50']], status: 1, time: '11:30:00', paidAt: '2026-10-14 20:00:00', pay: 'ALIPAY_CN');
        $this->pull('2026-10-13');

        $txn->refresh();
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $txn->settlement_status);
        $this->assertSame('2026-10-14 20:00:00', $txn->transaction_datetime->toDateTimeString());
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->countsAsSale()->count());
        $this->assertContains('2026-10-13', app(DirtyDayRegistry::class)->days(), 'the day it left is rebuilt');
        $this->assertContains('2026-10-14', app(DirtyDayRegistry::class)->days(), 'the day it landed is rebuilt');
        $this->assertSame([8, 4], [$this->qty(90343), $this->qty(90404)], 'paying moves no stock');
    }

    public function test_an_unpaid_order_skipped_before_2026_10_21_is_listed_on_the_next_pull(): void
    {
        $this->gw->seedOrder('2026-10-13', 348, 'E1', [[90343, 1, '1.70']], status: 0, time: '11:30:00', paidAt: '0000-00-00 00:00:00');
        $this->pull('2026-10-13');
        // What the old projector left behind: no row, marked skip:status, payload unchanged since.
        VendTransaction::withoutGlobalScopes()->delete();
        CityboxOrder::sole()->forceFill(['vend_transaction_id' => null, 'projected_at' => null, 'projection_error' => 'skip:status'])->save();

        $this->pull('2026-10-13');

        $this->assertSame(VendTransaction::SETTLEMENT_UNPAID, (int) VendTransaction::withoutGlobalScopes()->sole()->settlement_status);
        $this->assertNull(CityboxOrder::sole()->projection_error);
        $this->assertSame(8, $this->qty(90343), 'stock taken once, not again');
    }

    public function test_an_unknown_device_is_kept_and_retried_once_linked(): void
    {
        $this->gw->seedOrder('2026-10-13', 180, 'E9', [[90343, 1, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        $this->assertSame('unknown_device', CityboxOrder::sole()->projection_error);
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->count());
    }

    public function test_a_failing_day_does_not_stop_the_others(): void
    {
        $this->gw->orderErrors = ['2026-10-12'];
        $this->gw->seedOrder('2026-10-13', 190, 'E1', [[90343, 1, '1.70']], time: '11:50:00');

        $stats = $this->pull('2026-10-12', '2026-10-13');
        $this->assertArrayHasKey('2026-10-12', $stats['failed_days']);
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
    }

    public function test_the_scheduled_poll_no_longer_overwrites_the_ledger_but_records_the_difference(): void
    {
        $this->gw->seedOrder('2026-10-13', 200, 'E1', [[90343, 1, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        $this->assertSame(8, $this->qty(90343));

        // Their count says 5 (an unpaid take-first-pay-later order we cannot see yet).
        $this->gw->seedStock('E1', [
            ['id' => 90343, 'name' => 'Lalune', 'qty' => 5, 'layer' => 1, 'price' => '1.80'],
            ['id' => 90404, 'name' => 'Soda', 'qty' => 6, 'layer' => 1, 'price' => '1.50'],
        ]);
        app(CityboxOpenapiSync::class)->syncAll();
        $this->assertSame(8, $this->qty(90343), 'our ledger stands');
        $this->assertSame(180, (int) VendChannel::where('vend_id', $this->vend->id)->where('product_id', $this->product(90343)->id)->value('amount'), 'price still follows their live read');

        $check = $this->vend->fresh()->citybox_status_json['stock_check'];
        $this->assertSame([['ours' => 8, 'theirs' => 5, 'confirmed' => false]], array_map(fn ($d) => array_intersect_key($d, array_flip(['ours', 'theirs', 'confirmed'])), $check['differs']));

        Carbon::setTestNow('2026-10-13 12:10:00');
        app(CityboxOpenapiSync::class)->syncAll();
        $check = $this->vend->fresh()->citybox_status_json['stock_check'];
        $this->assertTrue($check['differs'][0]['confirmed'], 'still different a check later: real');
        $this->assertSame('2026-10-13 12:00:00', $check['differs'][0]['since']);
    }

    public function test_the_stock_check_reads_their_signal_onto_the_sim_columns(): void
    {
        $this->gw->signals['E1'] = 'RSRP-好(-89dBm,31asu)';
        app(CityboxOpenapiSync::class)->syncAll();

        $vend = $this->vend->fresh();
        $this->assertSame(['telco', '4G', 4, 5], [$vend->internet_source, $vend->internet_network, (int) $vend->internet_signal, (int) $vend->internet_signal_max]);
        $this->assertSame([4, -89, '好 (Good)'], [$vend->citybox_status_json['signal']['bars'], $vend->citybox_status_json['signal']['rsrp_dbm'], $vend->citybox_status_json['signal']['quality']]);
    }

    public function test_with_the_ledger_off_orders_are_sales_and_the_poll_mirrors_their_count(): void
    {
        config(['citybox.stock_ledger' => false]);
        $this->gw->seedOrder('2026-10-13', 210, 'E1', [[90343, 1, '1.70']], time: '11:50:00');
        $this->pull('2026-10-13');
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
        $this->assertSame(9, $this->qty(90343));
        $this->assertSame('ledger_off', CityboxOrder::sole()->stock_note);
    }

    public function test_the_command_queues_today_and_runs_a_backfill_in_the_foreground(): void
    {
        Queue::fake([PullCityboxOrders::class]);
        $this->artisan('citybox:pull-orders')->assertSuccessful();
        Queue::assertPushed(PullCityboxOrders::class, fn ($job) => $job->dates === ['2026-10-13']);

        $this->gw->seedOrder('2026-10-11', 133, 'E1', [[90404, 1, '1.50']], time: '10:19:53');
        $this->artisan('citybox:pull-orders --from=2026-10-10 --to=2026-10-12 --now')
            ->expectsOutputToContain('3 day(s), 1 order(s) seen')
            ->assertSuccessful();
        $this->assertSame(1, VendTransaction::withoutGlobalScopes()->count());
    }
}
