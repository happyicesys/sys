<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\HolidayDay;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardSettlement\Payout\BankingCalendar;
use App\Services\CardSettlement\Payout\SettlementPayoutResolver;
use App\Support\OperatorScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sales Transactions "Settlement Date" column (2026-09-12): the banking day the
 * acquirer pays a card sale out, and the gateway it comes through.
 *
 * Derived, never stored — from the matched settlement report line's OWN
 * transaction date plus the card type's schedule. Two things are easy to get
 * wrong and are pinned here: T+N counts BANKING days (a Friday sale does not
 * settle on the Saturday, and the National Day pair pushes a whole week out),
 * and a sale whose rail has no mapped schedule must show nothing at all rather
 * than borrow NETS's.
 */
class CardSettlementPayoutDateTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private int $vendId;

    private int $okErrorId;

    private PaymentMethod $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Operator::withoutGlobalScopes()->create([
            'code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true,
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Kent office', 'profile_id' => 1, 'status_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 2097, 'name' => 'Machine 2097', 'operator_id' => $this->operator->id,
            'customer_id' => $customerId, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->okErrorId = VendChannelError::create(['code' => 0, 'desc' => 'No Malfunction (0)'])->id;
        $this->card = PaymentMethod::create([
            'code' => PaymentMethod::CODE_CARD_TERMINAL, 'name' => 'Card Terminal', 'is_active' => true,
        ]);

        // The 2026 National Day pair: 9 Aug is a Sunday, so 10 Aug is the
        // observed holiday. Both are in prod's holiday_days.
        HolidayDay::create(['date' => '2026-08-09', 'is_public' => true, 'name' => 'National Day']);
        HolidayDay::create(['date' => '2026-08-10', 'is_public' => true, 'name' => 'National Day (Observed)']);
        // A school holiday is NOT a bank holiday.
        HolidayDay::create(['date' => '2026-09-04', 'is_public' => false, 'is_school' => true, 'name' => 'Term break']);
    }

    // ---------------------------------------------------------------- calendar

    public function test_t_plus_n_counts_banking_days_and_rolls_over_weekends(): void
    {
        $calendar = app(BankingCalendar::class);

        // Friday 2026-09-04.
        $this->assertSame('2026-09-07', $calendar->addBankingDays('2026-09-04', 1)->toDateString());
        $this->assertSame('2026-09-08', $calendar->addBankingDays('2026-09-04', 2)->toDateString());

        // Mid-week, nothing in the way.
        $this->assertSame('2026-09-02', $calendar->addBankingDays('2026-09-01', 1)->toDateString());
        $this->assertSame('2026-09-03', $calendar->addBankingDays('2026-09-01', 2)->toDateString());
    }

    public function test_public_holidays_push_the_payout_out_but_school_holidays_do_not(): void
    {
        $calendar = app(BankingCalendar::class);

        // Friday 7 Aug → Sat, Sun (National Day), Mon (observed) → Tuesday 11th.
        $this->assertSame('2026-08-11', $calendar->addBankingDays('2026-08-07', 1)->toDateString());
        $this->assertSame('2026-08-12', $calendar->addBankingDays('2026-08-07', 2)->toDateString());

        // 4 Sep is a school holiday only — a normal banking day.
        $this->assertSame('2026-09-04', $calendar->addBankingDays('2026-09-03', 1)->toDateString());
    }

    public function test_a_weekend_capture_at_t_plus_zero_still_lands_on_a_banking_day(): void
    {
        // Saturday. "Same day" to a bank means the next day it is open.
        $this->assertSame(
            '2026-09-07',
            app(BankingCalendar::class)->addBankingDays('2026-09-05', 0)->toDateString()
        );
    }

    /**
     * The one genuinely contested rule, and it is not a corner case: 41.9 % of
     * the Aug 2026 purchase lines were captured on a weekend or public holiday.
     * Both readings are pinned so the live one is a visible choice, flipped by
     * `payout_calendar.non_banking_origin` alone if a DBS statement disagrees.
     */
    public function test_the_non_banking_origin_setting_decides_where_a_weekend_sale_lands(): void
    {
        $friday = '2026-09-04';
        $saturday = '2026-09-05';
        $sunday = '2026-09-06';

        // Live default: count forward from the capture date, so the weekend's
        // takings arrive with Friday's.
        config(['card_settlement.payout_calendar.non_banking_origin' => 'transaction_date']);
        $calendar = new BankingCalendar;
        $this->assertSame('2026-09-07', $calendar->addBankingDays($friday, 1)->toDateString());
        $this->assertSame('2026-09-07', $calendar->addBankingDays($saturday, 1)->toDateString());
        $this->assertSame('2026-09-07', $calendar->addBankingDays($sunday, 1)->toDateString());

        // The alternative: roll T to the next banking day first, so a weekend
        // sale sits one day behind Friday's.
        config(['card_settlement.payout_calendar.non_banking_origin' => 'next_banking_day']);
        $calendar = new BankingCalendar;
        $this->assertSame('2026-09-07', $calendar->addBankingDays($friday, 1)->toDateString());
        $this->assertSame('2026-09-08', $calendar->addBankingDays($saturday, 1)->toDateString());
        $this->assertSame('2026-09-08', $calendar->addBankingDays($sunday, 1)->toDateString());
    }

    public function test_a_misconfigured_resolver_class_fails_loudly_rather_than_blanking_the_column(): void
    {
        config(['card_settlement.payout_terms_resolvers.bogus' => \stdClass::class]);

        $this->expectException(\InvalidArgumentException::class);
        app(SettlementPayoutResolver::class)->for('bogus', 'EFTPOS', 'DBS Card', '2026-09-01');
    }

    // ---------------------------------------------------------------- resolver

    public function test_the_resolver_combines_the_card_type_schedule_with_the_calendar(): void
    {
        $resolver = app(SettlementPayoutResolver::class);

        $nets = $resolver->for('nets', 'EFTPOS', 'DBS PayLah', '2026-09-01');
        $this->assertSame('2026-09-02', $nets->date->toDateString());
        $this->assertSame('260902', $nets->shortDate());
        $this->assertSame('COS', $nets->gateway());
        $this->assertStringContainsString('EFTPOS / DBS PayLah — COS', $nets->describe());
        $this->assertStringContainsString('T+1 banking day from 2026-09-01', $nets->describe());
        $this->assertStringContainsString('MDR 0.8% (full back in)', $nets->describe());

        $visa = $resolver->for('nets', 'Scheme Credit/Debit', 'VISA', '2026-09-01');
        $this->assertSame('2026-09-03', $visa->date->toDateString());
        $this->assertSame('DBS CARD CENTER', $visa->gateway());
    }

    public function test_the_line_date_drives_the_payout_not_the_files_cutover_date(): void
    {
        // The NETS business day cuts over ~22:30, so one file spans two calendar
        // dates and its late rows settle a day after its early ones.
        $resolver = app(SettlementPayoutResolver::class);

        $this->assertSame('260903', $resolver->for('nets', 'EFTPOS', 'DBS Card', '2026-09-02')->shortDate());
        $this->assertSame('260904', $resolver->for('nets', 'EFTPOS', 'DBS Card', '2026-09-03')->shortDate());
    }

    public function test_an_unknown_provider_or_card_type_resolves_to_nothing(): void
    {
        $resolver = app(SettlementPayoutResolver::class);

        // No parser, no schedule, no guess — a Midtrans/other-country rail.
        $this->assertNull($resolver->for('midtrans', 'CREDIT_CARD', 'VISA', '2026-09-01'));
        $this->assertNull($resolver->for('nets', 'SOME NEW RAIL', null, '2026-09-01'));
        $this->assertNull($resolver->for('nets', 'EFTPOS', 'DBS Card', null));
        $this->assertNull($resolver->for(null, 'EFTPOS', 'DBS Card', '2026-09-01'));
    }

    // ------------------------------------------------------------------- grid

    public function test_the_grid_shows_the_payout_date_and_gateway_for_a_matched_card_sale(): void
    {
        $eftpos = $this->cardSale('EFTPOS-SALE', '2026-09-01 17:09:00');
        $this->settlementLine($eftpos, 'EFTPOS', 'DBS Card', '2026-09-01');

        $visa = $this->cardSale('VISA-SALE', '2026-09-01 17:10:00');
        $this->settlementLine($visa, 'Scheme Credit/Debit', 'VISA', '2026-09-01');

        $wechat = $this->cardSale('WECHAT-SALE', '2026-09-01 17:11:00');
        $this->settlementLine($wechat, 'CROSS BORDER', 'WeChat Pay', '2026-09-01');

        // Matched, but its report line falls on a Friday: T+1 is the Monday.
        $friday = $this->cardSale('FRIDAY-SALE', '2026-09-04 17:12:00');
        $this->settlementLine($friday, 'EFTPOS', 'DBS Card', '2026-09-04');

        // No report line claims this one yet.
        $this->cardSale('UNMATCHED-SALE', '2026-09-01 17:13:00');

        $rows = $this->gridRows();

        $this->assertSame('260902', $rows['EFTPOS-SALE']['settlement_payout_date']);
        $this->assertSame('COS', $rows['EFTPOS-SALE']['settlement_gateway']);

        $this->assertSame('260903', $rows['VISA-SALE']['settlement_payout_date']);
        $this->assertSame('DBS CARD CENTER', $rows['VISA-SALE']['settlement_gateway']);

        $this->assertSame('260902', $rows['WECHAT-SALE']['settlement_payout_date']);
        $this->assertSame('POS', $rows['WECHAT-SALE']['settlement_gateway']);

        $this->assertSame('260907', $rows['FRIDAY-SALE']['settlement_payout_date']);

        $this->assertNull($rows['UNMATCHED-SALE']['settlement_payout_date']);
        $this->assertNull($rows['UNMATCHED-SALE']['settlement_gateway']);
    }

    public function test_a_gateway_sale_has_no_settlement_date_at_all(): void
    {
        $omise = PaymentMethod::create([
            'code' => 201, 'name' => 'Omise (Paynow)', 'payment_gateway_id' => 2, 'is_active' => true,
        ]);
        $this->cardSale('QR-SALE', '2026-09-01 17:14:00', $omise);

        $row = $this->gridRows()['QR-SALE'];

        $this->assertNull($row['settlement_payout_date']);
        $this->assertNull($row['settlement_gateway']);
        $this->assertNull($row['settlement_payout_note']);
    }

    // ---------------------------------------------------------------- helpers

    private function cardSale(string $orderId, string $at, ?PaymentMethod $method = null): VendTransaction
    {
        return VendTransaction::create([
            'order_id' => $orderId,
            'vend_id' => $this->vendId,
            'operator_id' => $this->operator->id,
            'transaction_datetime' => CarbonImmutable::parse($at),
            'amount' => 160,
            'qty' => 1,
            'vend_channel_code' => 16,
            'vend_channel_id' => 0,
            'vend_channel_error_id' => $this->okErrorId,
            'gst_vat_rate' => 0,
            'interface_type' => 0,
            'payment_method_id' => ($method ?? $this->card)->id,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
    }

    private function settlementLine(VendTransaction $txn, string $product, string $issuer, string $date): void
    {
        static $report = null;
        static $n = 0;

        $report ??= CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'MCONNECT_H06228_STDRPT01_20260902_NEW.csv',
            'cutover_date' => '2026-09-01',
            'status' => 'review',
        ]);

        CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => ++$n,
            'txn_type' => 'Purchase',
            'product' => $product,
            'card_issuer' => $issuer,
            'terminal_id' => '23082824',
            'transaction_date' => $date,
            'transaction_time' => '17:08:45',
            'amount_cents' => 160,
            'sequence_no' => (string) (1000 + $n),
            'fingerprint' => sha1('fp'.$n),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'vend_id' => $this->vendId,
            'matched_vend_transaction_id' => $txn->id,
        ]);
    }

    /** @return array<string, array> grid rows keyed by order id */
    private function gridRows(): array
    {
        Permission::findOrCreate('read transactions', 'web');
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo('read transactions');
        OperatorScope::flush();

        $rows = [];
        $this->actingAs($user)
            ->get('/vends/transactions?'.http_build_query([
                'date_from' => '2026-08-01 00:00:00',
                'date_to' => '2026-09-30 23:59:59',
                'operators' => [$this->operator->id],
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$rows) {
                $page->component('Vend/Transaction');
                foreach ($page->toArray()['props']['vendTransactions']['data'] as $row) {
                    $rows[$row['order_id']] = $row;
                }
            });

        return $rows;
    }
}
