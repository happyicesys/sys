<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\PaymentGatewayLog;
use App\Models\RefundTicket;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\Refund\RefundMatchingService;
use App\Services\Refund\RefundRegion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Brian, 2026-11-03: the public /refund form asked Indonesian customers for a Singapore PayNow number,
 * in "$" with two decimals, in English, and refused 5-digit machine IDs. It now follows the machine's
 * country (RefundRegion). Rupiah has two units: vend_transactions ×100 (Rp 20,000 = 2000000), Midtrans'
 * payment_gateway_logs.amount whole (20000.00). Singapore must not move at all.
 */
class RefundFormIndonesiaTest extends TestCase
{
    use RefreshDatabase;

    private function indonesia(): Operator
    {
        $country = Country::create(['name' => 'Indonesia', 'code' => 'ID', 'currency_name' => 'IDR', 'currency_symbol' => 'Rp', 'currency_exponent' => 2, 'is_currency_exponent_hidden' => true]);

        return Operator::create(['code' => 'HIID', 'name' => 'Happy Ice Indonesia', 'gst_vat_rate' => 11, 'timezone' => 'Asia/Jakarta', 'country_id' => $country->id]);
    }

    private function singapore(): Operator
    {
        $country = Country::create(['name' => 'Singapore', 'code' => 'SG', 'currency_name' => 'SGD', 'currency_symbol' => 'S$', 'currency_exponent' => 2, 'is_currency_exponent_hidden' => false]);

        return Operator::create(['code' => 'HIPL', 'name' => 'Happy Ice', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore', 'country_id' => $country->id]);
    }

    private function machine(string $code, ?Operator $operator): Vend
    {
        $customer = Customer::create(['code' => 'C'.$code, 'name' => 'Site '.$code, 'is_active' => true]);

        return Vend::create(['code' => $code, 'name' => 'M'.$code, 'is_active' => true, 'customer_id' => $customer->id, 'operator_id' => $operator?->id]);
    }

    private function sale(Vend $vend, int $cents, string $orderId, ?int $logId = null): VendTransaction
    {
        return VendTransaction::create([
            'order_id' => $orderId,
            'vend_id' => $vend->id,
            'transaction_datetime' => Carbon::now(),
            'amount' => $cents,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
            'payment_gateway_log_id' => $logId,
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
    }

    private function gatewayLog(string $machine, string $amount, string $orderId): PaymentGatewayLog
    {
        return PaymentGatewayLog::create([
            'vend_code' => $machine,
            'order_id' => $orderId,
            'amount' => $amount,
            'status' => PaymentGatewayLog::STATUS_APPROVE,
            'approved_at' => Carbon::now(),
            'method' => 'gopay',
        ]);
    }

    private function manualClaim(string $machine, array $overrides = []): array
    {
        return array_merge([
            'machineID' => $machine,
            'is_manual' => true,
            'entered_amount' => 20000,
            'approx_time' => '14:00',
            'manual_pay_method' => 'QRIS / e-wallet',
            'manual_items_summary' => 'Ice cream x1',
            'reason_text' => 'Nothing came out',
            'contact_email' => 'customer@example.com',
            'photos' => [UploadedFile::fake()->image('proof.jpg')],
        ], $overrides);
    }

    public function test_an_indonesian_machine_opens_in_rupiah_and_bahasa_with_no_paynow(): void
    {
        $this->machine('10019', $this->indonesia());

        $this->get('/refund?machineID=10019')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Refund/Form')
            ->where('machineFound', true)
            ->where('region.symbol', 'Rp')
            ->where('region.whole_units', true)
            ->where('region.methods', ['local_transfer', 'paypal'])
            ->where('i18n.locale', 'id'));
    }

    public function test_a_customer_with_no_login_can_load_the_bahasa_wording(): void
    {
        $this->assertGuest();

        $this->getJson('/i18n/id.json')->assertOk()->assertJsonPath('Product did not dispense', 'Produk tidak keluar');
    }

    public function test_a_singapore_machine_is_unchanged(): void
    {
        $this->indonesia();
        $this->machine('2112', $this->singapore());

        $this->get('/refund?machineID=2112')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('region.symbol', '$')
            ->where('region.whole_units', false)
            ->where('region.methods', ['paynow', 'paypal'])
            ->where('region.providers', [])
            ->where('i18n.locale', 'en'));
    }

    public function test_a_machine_with_no_operator_follows_a_one_country_server(): void
    {
        $this->indonesia();
        $this->machine('10036', null);

        $this->get('/refund?machineID=10036')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('region.symbol', 'Rp')
            ->where('i18n.locale', 'id'));
    }

    public function test_a_machine_with_no_operator_keeps_the_default_on_a_mixed_server(): void
    {
        $this->indonesia();
        $this->singapore();
        $this->machine('2999', null);

        $this->get('/refund?machineID=2999')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('region.symbol', '$')
            ->where('region.methods', ['paynow', 'paypal']));
    }

    public function test_a_five_digit_machine_typed_by_hand_brings_its_region(): void
    {
        $this->machine('10019', $this->indonesia());

        $this->postJson('/refund/resolve', ['machineID' => '10019'])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('region.whole_units', true);
    }

    public function test_rupiah_finds_the_sale_and_the_gateway_row_is_not_listed_twice(): void
    {
        $vend = $this->machine('10019', $this->indonesia());
        $log = $this->gatewayLog('10019', '20000.00', 'ORD-SALE');
        $this->sale($vend, 2000000, 'ORD-SALE', $log->id);

        $response = $this->postJson('/refund/candidates', ['machineID' => '10019', 'day' => 'today', 'amount' => '20000']);

        $response->assertOk()->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.source', 'transaction')
            ->assertJsonPath('candidates.0.amount_cents', 2000000);
    }

    public function test_rupiah_finds_a_payment_whose_sale_has_not_arrived_at_its_true_value(): void
    {
        $this->machine('10019', $this->indonesia());
        $this->gatewayLog('10019', '20000.00', 'ORD-GW');

        $response = $this->postJson('/refund/candidates', ['machineID' => '10019', 'day' => 'today', 'amount' => '20000']);

        $response->assertOk()->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.source', 'gateway')
            ->assertJsonPath('candidates.0.amount_cents', 2000000)
            ->assertJsonPath('candidates.0.items.0.unit_price_cents', 2000000);
        $this->assertSame(20000.0, (float) $response->json('candidates.0.amount'));
    }

    public function test_a_ticket_on_a_gateway_payment_claims_the_true_rupiah(): void
    {
        $this->machine('10019', $this->indonesia());
        $log = $this->gatewayLog('10019', '20000.00', 'ORD-GW');

        $this->postJson('/refund', [
            'machineID' => '10019',
            'payment_gateway_log_id' => $log->id,
            'contact_email' => 'customer@example.com',
            'reason_code' => 'not_dispensed',
            'refund_method' => 'local_transfer',
            'payout_provider' => 'GoPay',
            'payout_account' => '081234567890',
            'payout_name' => 'Budi Santoso',
            'photos' => [UploadedFile::fake()->image('proof.jpg')],
        ])->assertOk();

        $this->assertSame(2000000, (int) RefundTicket::firstOrFail()->claimed_amount_cents);
    }

    public function test_a_singapore_gateway_amount_is_still_cents(): void
    {
        $this->machine('2112', $this->singapore());
        $this->gatewayLog('2112', '350', 'ORD-SG');

        $c = app(RefundMatchingService::class)->candidates('2112', 'today', 350)['candidates'];

        $this->assertCount(1, $c);
        $this->assertSame(350, $c[0]['amount_cents']);
    }

    public function test_an_e_wallet_payout_is_stored_as_one_line(): void
    {
        $this->machine('10019', $this->indonesia());

        $this->postJson('/refund', $this->manualClaim('10019', [
            'refund_method' => 'local_transfer',
            'payout_provider' => 'GoPay',
            'payout_account' => '0812-3456 7890',
            'payout_name' => 'Budi Santoso',
        ]))->assertOk();

        $ticket = RefundTicket::firstOrFail();
        $this->assertSame(RefundTicket::METHOD_LOCAL_TRANSFER, $ticket->refund_method);
        $this->assertSame('GoPay 081234567890 (Budi Santoso)', $ticket->payout_destination);
        $this->assertSame(2000000, (int) $ticket->entered_amount_cents);
    }

    public function test_another_bank_is_named_by_the_customer(): void
    {
        $this->machine('10019', $this->indonesia());

        $this->postJson('/refund', $this->manualClaim('10019', [
            'refund_method' => 'local_transfer',
            'payout_provider' => RefundRegion::OTHER_PROVIDER,
            'payout_provider_other' => 'Bank Jago',
            'payout_account' => '1234567890',
            'payout_name' => 'Budi',
        ]))->assertOk();

        $this->assertSame('Bank Jago 1234567890 (Budi)', RefundTicket::firstOrFail()->payout_destination);
    }

    public function test_a_partial_e_wallet_payout_is_refused(): void
    {
        $this->machine('10019', $this->indonesia());

        $this->postJson('/refund', $this->manualClaim('10019', ['refund_method' => 'local_transfer', 'payout_provider' => 'GoPay', 'payout_account' => 'abc', 'payout_name' => 'Budi']))
            ->assertStatus(422)->assertJsonValidationErrors('payout_account');
        $this->postJson('/refund', $this->manualClaim('10019', ['refund_method' => 'local_transfer', 'payout_provider' => 'GoPay', 'payout_account' => '081234567890']))
            ->assertStatus(422)->assertJsonValidationErrors('payout_name');
        $this->postJson('/refund', $this->manualClaim('10019', ['refund_method' => 'local_transfer', 'payout_account' => '081234567890', 'payout_name' => 'Budi']))
            ->assertStatus(422)->assertJsonValidationErrors('payout_provider');

        $this->assertSame(0, RefundTicket::count());
    }

    public function test_each_machine_is_only_offered_its_own_methods(): void
    {
        $this->machine('10019', $this->indonesia());
        $this->machine('2112', $this->singapore());

        $this->postJson('/refund', $this->manualClaim('10019', ['refund_method' => 'paynow', 'payout_destination' => '91234567']))
            ->assertStatus(422)->assertJsonValidationErrors('refund_method');
        $this->postJson('/refund', $this->manualClaim('2112', ['entered_amount' => 3.5, 'refund_method' => 'local_transfer', 'payout_provider' => 'GoPay', 'payout_account' => '081234567890', 'payout_name' => 'Budi']))
            ->assertStatus(422)->assertJsonValidationErrors('refund_method');
    }

    public function test_paypal_still_works_in_indonesia(): void
    {
        $this->machine('10019', $this->indonesia());

        $this->postJson('/refund', $this->manualClaim('10019', ['refund_method' => 'paypal', 'payout_destination' => 'budi@example.com']))->assertOk();

        $this->assertSame('budi@example.com', RefundTicket::firstOrFail()->payout_destination);
    }

    public function test_the_largest_rupiah_sale_fits_and_singapore_keeps_its_limit(): void
    {
        $this->machine('10019', $this->indonesia());
        $this->machine('2112', $this->singapore());

        $this->postJson('/refund', $this->manualClaim('10019', ['entered_amount' => 320000, 'refund_method' => 'paypal', 'payout_destination' => 'budi@example.com']))->assertOk();
        $this->postJson('/refund', $this->manualClaim('2112', ['entered_amount' => 320000, 'refund_method' => 'paypal', 'payout_destination' => 'a@example.com']))
            ->assertStatus(422)->assertJsonValidationErrors('entered_amount');
    }

    public function test_amounts_read_in_the_machine_money(): void
    {
        $this->indonesia();
        $rupiah = RefundRegion::forServer();

        $this->assertSame('Rp 20,000', $rupiah->label(2000000));
        $this->assertSame(2000000, $rupiah->gatewayCents('20000.00'));
        $this->assertSame(20000, $rupiah->gatewayAmount(2000000));
    }
}
