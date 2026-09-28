<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use App\Support\CardSettlement\PayoutTerms;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Transactions > Txn, Revenue & Settlement (Brian, 2026-09-28): the machine's
 * figure, what the rail confirms (revenue, before MDR), what reaches the bank
 * (after MDR). Drives the real page and pins every figure in cents.
 */
class TxnRevenueSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private int $vendId;

    private PaymentMethod $card;

    private PaymentMethod $cash;

    private PaymentMethod $omise;

    private CardSettlementReport $report;

    private int $rowNo = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 5073, 'name' => 'Machine 5073', 'operator_id' => $this->operator->id,
            'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->card = PaymentMethod::create(['code' => PaymentMethod::CODE_CARD_TERMINAL, 'name' => 'Card Terminal', 'is_active' => true]);
        $this->cash = PaymentMethod::create(['code' => 0, 'name' => 'Cash', 'is_active' => true]);
        $this->omise = PaymentMethod::create(['code' => 201, 'name' => 'Omise (Paynow)', 'is_active' => true, 'payment_gateway_id' => 2]);
        $this->report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
    }

    private function sale(string $orderId, int $cents, PaymentMethod $method, array $attrs = []): VendTransaction
    {
        return VendTransaction::unguarded(fn () => VendTransaction::withoutGlobalScopes()->create($attrs + [
            'order_id' => $orderId, 'vend_id' => $this->vendId, 'operator_id' => $this->operator->id,
            'transaction_datetime' => '2026-09-25 14:00:00', 'amount' => $cents, 'qty' => 1, 'success_qty' => 1,
            'vend_channel_id' => 0, 'vend_channel_code' => 11, 'gst_vat_rate' => 0, 'interface_type' => 0,
            'is_found_in_transaction' => true, 'payment_method_id' => $method->id,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]));
    }

    private function line(VendTransaction $sale, int $cents, string $product, array $extra = []): CardSettlementRow
    {
        return CardSettlementRow::create($extra + [
            'card_settlement_report_id' => $this->report->id, 'row_no' => ++$this->rowNo, 'txn_type' => 'Purchase',
            'terminal_id' => '23082801', 'product' => $product, 'card_issuer' => 'DBS Card',
            'transaction_date' => '2026-09-25', 'transaction_time' => '13:59:40', 'time_is_partial' => false,
            'amount_cents' => $cents, 'fingerprint' => sha1(uniqid('', true)),
            'status' => CardSettlementRow::STATUS_MATCHED, 'matched_vend_transaction_id' => $sale->id,
        ]);
    }

    private function omiseCharge(VendTransaction $sale, int $status, array $data): void
    {
        $log = PaymentGatewayLog::create([
            'order_id' => $sale->order_id, 'amount' => $sale->amount / 100, 'payment_gateway_id' => 2,
            'operator_payment_gateway_id' => 1, 'status' => $status, 'response' => ['data' => $data],
        ]);
        $sale->forceFill(['payment_gateway_log_id' => $log->id])->save();
    }

    /** @return array{0: array<string, array>, 1: array} rows keyed by order id, totals */
    private function page(array $query = [], ?User $user = null): array
    {
        Permission::findOrCreate('read transactions-revenue-settlement', 'web');
        $user ??= User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo('read transactions-revenue-settlement');
        OperatorScope::flush();

        $rows = [];
        $totals = [];
        $this->actingAs($user)
            ->get('/vends/txn-revenue-settlement?'.http_build_query($query + [
                'date_from' => '2026-09-25', 'date_to' => '2026-09-25', 'operators' => [$this->operator->id],
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$rows, &$totals) {
                $page->component('Vend/TxnRevenueSettlement');
                $props = $page->toArray()['props'];
                foreach ($props['rows'] as $row) {
                    $rows[$row['order_id']] = $row;
                }
                $totals = $props['totals'];
            });

        return [$rows, $totals];
    }

    public function test_the_line_from_machine_to_rail_to_bank(): void
    {
        // Visa/Mastercard: 2.5% netted off, T+2 via DBS Card Centre.
        $visa = $this->sale('VISA', 590, $this->card, ['card_settlement_synced_at' => now()]);
        $this->line($visa, 590, 'Scheme Credit/Debit');
        // NETS EFTPOS: 0.8% billed separately — the full amount is banked.
        $eftpos = $this->sale('EFTPOS', 250, $this->card, ['card_settlement_synced_at' => now()]);
        $this->line($eftpos, 250, 'EFTPOS');
        // Omise: fee and net are Omise's own figures.
        $qr = $this->sale('QR', 240, $this->omise);
        $this->omiseCharge($qr, PaymentGatewayLog::STATUS_APPROVE, ['amount' => 240, 'fee' => 6, 'fee_vat' => 1, 'net' => 233]);
        // Cash: no rail, nothing to match.
        $this->sale('CASH', 170, $this->cash);

        [$rows, $totals] = $this->page();

        $this->assertSame('matched', $rows['VISA']['revenue_state']);
        $this->assertSame(590, $rows['VISA']['revenue_cents']);
        $this->assertSame(15, $rows['VISA']['mdr_cents']);        // 590 × 2.5% = 14.75 → 15
        $this->assertSame(575, $rows['VISA']['bank_in_cents']);   // netted off
        $this->assertSame('2.5%', $rows['VISA']['mdr_rate']);
        $this->assertTrue($rows['VISA']['mdr_is_estimate']);
        $this->assertSame('DBS CARD CENTER', $rows['VISA']['settlement_gateway']);
        $this->assertSame('260929', $rows['VISA']['settlement_date']); // Fri 25th, T+2 banking = Tue 29th

        $this->assertSame(2, $rows['EFTPOS']['mdr_cents']);       // 250 × 0.8%
        $this->assertSame(250, $rows['EFTPOS']['bank_in_cents']); // full back in
        $this->assertSame('COS', $rows['EFTPOS']['settlement_gateway']);

        $this->assertSame('matched', $rows['QR']['revenue_state']);
        $this->assertSame(240, $rows['QR']['revenue_cents']);
        $this->assertSame(7, $rows['QR']['mdr_cents']);
        $this->assertSame(233, $rows['QR']['bank_in_cents']);
        $this->assertFalse($rows['QR']['mdr_is_estimate']);
        $this->assertNull($rows['QR']['settlement_date']);

        $this->assertSame('none', $rows['CASH']['revenue_state']);
        $this->assertNull($rows['CASH']['revenue_cents']);
        $this->assertNull($rows['CASH']['bank_in_cents']);

        // Totals are the same figures summed, and only ever shrink along the line.
        $this->assertSame(1250, $totals['txn']['cents']);
        $this->assertSame(1080, $totals['revenue']['cents']);
        $this->assertSame(24, $totals['settlement']['mdr_cents']);
        $this->assertSame(1056, $totals['settlement']['net_cents']);
        $this->assertSame(575 + 250 + 233, $totals['settlement']['bank_in_cents']);
        $this->assertSame(170, $totals['txn']['cash']['cents']);
    }

    public function test_money_the_rail_returned_is_not_revenue(): void
    {
        $reversed = $this->sale('REVERSED', 300, $this->card, ['card_settlement_synced_at' => now()]);
        $this->line($reversed, 300, 'EFTPOS', ['reversed_by_row_id' => 999]);
        $voided = $this->sale('NA', 300, $this->card, [
            'is_refunded' => true, 'auto_refund_source' => AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED,
            'card_settlement_state' => 'not_captured',
        ]);
        $omiseRefund = $this->sale('QRREF', 200, $this->omise);
        $this->omiseCharge($omiseRefund, PaymentGatewayLog::STATUS_REFUND, ['amount' => 200]);

        [$rows, $totals] = $this->page();

        foreach (['REVERSED', 'NA', 'QRREF'] as $order) {
            $this->assertSame('refunded', $rows[$order]['revenue_state'], $order);
            $this->assertNull($rows[$order]['revenue_cents'], $order);
            $this->assertNull($rows[$order]['bank_in_cents'], $order);
        }
        $this->assertSame(0, $totals['revenue']['cents']);
        $this->assertSame(3, $totals['revenue']['states']['refunded']['count']);
    }

    public function test_a_re_vended_sale_kept_its_money_and_the_retry_brought_only_the_top_up(): void
    {
        // 5073, 2026-09-21: $1.70 charged and failed (money kept as reader
        // credit), then a $2.40 sale paid by that credit plus a $0.70 top-up.
        $failed = $this->sale('FAILED', 170, $this->card, [
            'is_refunded' => true, 'auto_refund_source' => AutoRefundSource::RETAINED_CREDIT_REVEND,
            'card_settlement_synced_at' => now(),
        ]);
        $this->line($failed, 170, 'Scheme Credit/Debit');
        $retry = $this->sale('RETRY', 240, $this->card, ['is_retained_credit_settlement' => true, 'card_settlement_synced_at' => now()]);
        $this->line($retry, 70, 'Scheme Credit/Debit');
        $pureCredit = $this->sale('CREDIT', 170, $this->card, ['is_retained_credit_settlement' => true]);

        [$rows, $totals] = $this->page();

        $this->assertSame('matched', $rows['FAILED']['revenue_state']);
        $this->assertSame(170, $rows['FAILED']['revenue_cents']);
        $this->assertSame(70, $rows['RETRY']['revenue_cents']);
        $this->assertSame(-170, $rows['RETRY']['revenue_diff_cents']);
        $this->assertSame('retained', $rows['CREDIT']['revenue_state']);
        $this->assertNull($rows['CREDIT']['revenue_cents']);
        $this->assertSame(240, $totals['revenue']['cents']);
    }

    public function test_card_sales_the_report_has_not_ruled_on(): void
    {
        $this->sale('WAIT', 200, $this->card);
        $this->sale('NOLINE', 200, $this->card, ['card_settlement_state' => 'not_captured']);
        $this->sale('AURESYS', 200, $this->card, ['card_settlement_state' => 'uncovered']);

        [$rows] = $this->page();

        $this->assertSame('pending', $rows['WAIT']['revenue_state']);
        $this->assertNull($rows['WAIT']['revenue_cents']);
        $this->assertSame('not_found', $rows['NOLINE']['revenue_state']);
        $this->assertSame(0, $rows['NOLINE']['revenue_cents']);
        $this->assertSame('unverifiable', $rows['AURESYS']['revenue_state']);
    }

    public function test_filters_narrow_by_rail_and_revenue_state(): void
    {
        $visa = $this->sale('VISA', 590, $this->card, ['card_settlement_synced_at' => now()]);
        $this->line($visa, 590, 'Scheme Credit/Debit');
        $this->sale('WAIT', 200, $this->card);
        $this->sale('CASH', 170, $this->cash);

        [$rows] = $this->page(['rails' => ['card'], 'revenue_states' => ['pending']]);
        $this->assertSame(['WAIT'], array_keys($rows));

        [$rows, $totals] = $this->page(['rails' => ['cash']]);
        $this->assertSame(['CASH'], array_keys($rows));
        $this->assertSame(170, $totals['txn']['cents']);
    }

    public function test_another_operators_sales_never_reach_the_page_or_its_totals(): void
    {
        $other = Operator::withoutGlobalScopes()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
        $otherVend = DB::table('vends')->insertGetId([
            'code' => 9001, 'name' => 'Machine 9001', 'operator_id' => $other->id, 'is_testing' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sale('MINE', 100, $this->cash);
        $this->sale('THEIRS', 900, $this->cash, ['vend_id' => $otherVend, 'operator_id' => $other->id]);

        // Asking for the other operator explicitly must not widen the ceiling.
        [$rows, $totals] = $this->page(['operators' => [$this->operator->id, $other->id]]);

        $this->assertSame(['MINE'], array_keys($rows));
        $this->assertSame(100, $totals['txn']['cents']);
    }

    public function test_testing_machines_are_left_out(): void
    {
        DB::table('vends')->where('id', $this->vendId)->update(['is_testing' => 1]);
        $this->sale('BENCH', 100, $this->cash);

        [$rows, $totals] = $this->page();

        $this->assertSame([], $rows);
        $this->assertSame(0, $totals['txn']['cents']);
    }

    public function test_page_needs_its_own_permission(): void
    {
        $user = User::factory()->create(['operator_id' => $this->operator->id]);

        $this->actingAs($user)->get('/vends/txn-revenue-settlement')->assertForbidden();
    }

    public function test_csv_export_carries_the_three_groups(): void
    {
        $visa = $this->sale('VISA', 590, $this->card, ['card_settlement_synced_at' => now()]);
        $this->line($visa, 590, 'Scheme Credit/Debit');
        Permission::findOrCreate('export transactions-revenue-settlement', 'web');
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo('export transactions-revenue-settlement');
        OperatorScope::flush();

        $csv = $this->actingAs($user)
            ->get('/vends/txn-revenue-settlement/export-csv?'.http_build_query([
                'date_from' => '2026-09-25', 'date_to' => '2026-09-25', 'operators' => [$this->operator->id],
            ]))
            ->assertOk()
            ->streamedContent();

        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
        $head = $lines[0];
        $this->assertSame('5.90', $lines[1][array_search('Txn Amount', $head, true)]);
        $this->assertSame('5.90', $lines[1][array_search('Revenue Amount', $head, true)]);
        $this->assertSame('5.75', $lines[1][array_search('Bank-in Amount', $head, true)]);
        $this->assertSame('0.15', $lines[1][array_search('MDR Amount', $head, true)]);
    }

    public function test_mdr_arithmetic_is_integer_cents(): void
    {
        $gst = new PayoutTerms('AURESYS', 2, mdrBasisPoints: 200, mdrPlusGst: true, mdrDeducted: true);
        // 1000 × 2% = 20, + 9% GST = 1.8 → 2
        $this->assertSame(22, $gst->mdrCents(1000, 900));
        $this->assertSame('2% + GST', $gst->mdrRateLabel());

        $unpriced = new PayoutTerms('COS', 1);
        $this->assertNull($unpriced->mdrCents(1000, 900));
    }
}
