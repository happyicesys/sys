<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\RefundTicket;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Staff side of an Indonesian refund (Brian, 2026-11-03): an e-wallet or bank refund is paid by hand like
 * PayPal, the amounts are rupiah, and a Final Refund Amount can reach the real price of a sale.
 */
class RefundStaffIndonesiaTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        $country = Country::create(['name' => 'Indonesia', 'code' => 'ID', 'currency_name' => 'IDR', 'currency_symbol' => 'Rp', 'currency_exponent' => 2, 'is_currency_exponent_hidden' => true]);
        $operator = Operator::create(['code' => 'HIID', 'name' => 'Happy Ice Indonesia', 'gst_vat_rate' => 11, 'timezone' => 'Asia/Jakarta', 'country_id' => $country->id]);
        $customer = Customer::create(['code' => 'C1', 'name' => 'Site', 'is_active' => true]);
        $this->vend = Vend::create(['code' => '10019', 'name' => 'M', 'is_active' => true, 'customer_id' => $customer->id, 'operator_id' => $operator->id]);

        $user = User::factory()->create();
        foreach (['read refunds', 'update refunds', 'verify refunds', 'payout refunds'] as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $this->actingAs($user);
    }

    private function ticket(string $status = RefundTicket::STATUS_APPROVED): RefundTicket
    {
        return RefundTicket::create([
            'reference' => 'RF-'.uniqid(),
            'vend_code' => '10019',
            'vend_id' => $this->vend->id,
            'refund_method' => RefundTicket::METHOD_LOCAL_TRANSFER,
            'payout_destination' => 'GoPay 081234567890 (Budi Santoso)',
            'claimed_amount_cents' => 2000000,
            'status' => $status,
            'contact_email' => 'customer@example.com',
        ]);
    }

    public function test_an_e_wallet_refund_is_marked_done_by_hand(): void
    {
        $ticket = $this->ticket();

        $this->post('/refunds/batch/complete', ['ticket_ids' => [$ticket->id]])->assertRedirect();

        $this->assertSame(RefundTicket::STATUS_COMPLETED, $ticket->fresh()->status);
    }

    public function test_an_e_wallet_refund_can_be_flagged_insufficient_info(): void
    {
        $ticket = $this->ticket();

        $this->post('/refunds/batch/insufficient-info', ['ticket_ids' => [$ticket->id]])->assertRedirect();

        $this->assertSame(RefundTicket::STATUS_INSUFFICIENT_INFO, $ticket->fresh()->status);
    }

    public function test_the_payout_line_survives_storage_unchanged(): void
    {
        $this->assertSame('GoPay 081234567890 (Budi Santoso)', $this->ticket()->fresh()->payout_destination);
    }

    public function test_a_final_amount_reaches_the_price_of_a_large_sale(): void
    {
        $ticket = $this->ticket(RefundTicket::STATUS_SUBMITTED);

        $this->post("/refunds/{$ticket->id}/final-amount", ['final_refund_amount' => 320000])->assertSessionHasNoErrors();

        $this->assertSame(32000000, (int) $ticket->fresh()->final_refund_amount_cents);
        $this->assertStringContainsString('Rp 320,000', (string) $ticket->logs()->first()->note);
    }

    public function test_the_pages_carry_rupiah_and_the_payout_line(): void
    {
        $ticket = $this->ticket();

        $this->get('/refunds')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('region.symbol', 'Rp')
            ->where('region.whole_units', true));
        $this->get("/refunds/{$ticket->id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('region.symbol', 'Rp')
            ->where('ticket.payout_destination', 'GoPay 081234567890 (Budi Santoso)'));
    }
}
