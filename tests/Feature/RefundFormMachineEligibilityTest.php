<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Vend;
use App\Models\VendBinding;
use App\Services\Refund\RefundMatchingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Machine-eligibility gate on the public /refund form (Daniel's rule, shelved
 * 2026-08-24, enabled 2026-08-30 after machine 2606 — deactivated years ago —
 * was used on a live refund request): the form only accepts machines plausibly
 * still in service. Eligible = active vend attached to a Site (bound active
 * customer OR active vend_bindings row — prod's two attachment ledgers
 * disagree on machines that are actively selling, so either counts), with a
 * recent-sale safety valve so ops data drift never blocks a paying customer.
 */
class RefundFormMachineEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(bool $active, string $code): Customer
    {
        return Customer::create(['code' => $code, 'name' => 'Site '.$code, 'is_active' => $active]);
    }

    private function makeVend(string $code, bool $active, ?int $customerId = null): Vend
    {
        return Vend::create(['code' => $code, 'name' => 'M'.$code, 'is_active' => $active, 'customer_id' => $customerId]);
    }

    private function blocked(Vend $vend): bool
    {
        return app(RefundMatchingService::class)->machineBlocked($vend->fresh());
    }

    public function test_active_vend_bound_to_active_site_is_eligible()
    {
        $vend = $this->makeVend('2031', true, $this->makeCustomer(true, 'C1')->id);

        $this->assertFalse($this->blocked($vend));

        $this->postJson('/refund/resolve', ['machineID' => '2031'])
            ->assertOk()
            ->assertJson(['found' => true, 'blocked' => false]);
    }

    public function test_deactivated_vend_with_deactivated_site_is_blocked()
    {
        // The 2606 case: machine and Site both deactivated years ago.
        $vend = $this->makeVend('2606', false, $this->makeCustomer(false, 'C2')->id);

        $this->assertTrue($this->blocked($vend));

        $this->postJson('/refund/resolve', ['machineID' => '2606'])
            ->assertOk()
            ->assertJson(['found' => false, 'blocked' => true, 'machineName' => null, 'siteName' => null]);

        $this->get('/refund?machineID=2606')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Refund/Form')
                ->where('machineFound', false)
                ->where('machineBlocked', true));

        $this->postJson('/refund/machine-products', ['machineID' => '2606'])
            ->assertOk()
            ->assertJson(['found' => false, 'products' => []]);

        $this->postJson('/refund/candidates', ['machineID' => '2606', 'day' => 'today', 'amount' => 2])
            ->assertOk()
            ->assertJson(['machineFound' => false, 'candidates' => []]);
    }

    public function test_unknown_machine_stays_a_plain_not_found()
    {
        $this->postJson('/refund/resolve', ['machineID' => '9999'])
            ->assertOk()
            ->assertJson(['found' => false, 'blocked' => false]);
    }

    public function test_active_unbound_vend_with_active_binding_row_is_eligible()
    {
        // The prod drift case (2443/2748/2810/2696/2310): vends.customer_id is
        // NULL but an active vend_bindings row says the machine is deployed —
        // even where that binding's customer row was deactivated (2696/2810).
        $vend = $this->makeVend('2443', true);
        VendBinding::create([
            'vend_id' => $vend->id,
            'customer_id' => $this->makeCustomer(false, 'C3')->id,
            'is_active' => true,
            'begin_date' => now()->subMonths(3),
        ]);

        $this->assertFalse($this->blocked($vend));
    }

    public function test_active_vend_with_no_site_attachment_is_blocked()
    {
        $vend = $this->makeVend('2024', true);

        $this->assertTrue($this->blocked($vend));
    }

    public function test_deactivated_vend_with_stale_active_binding_is_blocked()
    {
        // ~76 long-dead machines still carry an active binding row; the vend
        // flag must therefore stay part of the gate.
        $vend = $this->makeVend('2605', false);
        VendBinding::create([
            'vend_id' => $vend->id,
            'customer_id' => $this->makeCustomer(false, 'C4')->id,
            'is_active' => true,
            'begin_date' => now()->subYears(2),
        ]);

        $this->assertTrue($this->blocked($vend));
    }

    public function test_recent_sale_overrides_the_flags()
    {
        // Safety valve: a sale inside the refund lookback window proves the
        // machine live whatever the flags say — data drift must never cost a
        // paying customer their refund.
        $vend = $this->makeVend('2748', true); // no attachment at all
        DB::table('vends')->where('id', $vend->id)
            ->update(['last_vend_transaction_at' => Carbon::now()->subDays(2)]);

        $this->assertFalse($this->blocked($vend));

        // ...but a sale older than the window does not.
        $lookback = (int) config('refund.match.max_lookback_days', 14);
        DB::table('vends')->where('id', $vend->id)
            ->update(['last_vend_transaction_at' => Carbon::today()->subDays($lookback + 1)]);

        $this->assertTrue($this->blocked($vend));
    }

    public function test_store_refuses_a_blocked_machine()
    {
        $this->makeVend('2606', false, $this->makeCustomer(false, 'C5')->id);

        $this->postJson('/refund', [
            'machineID' => '2606',
            'is_manual' => true,
            'entered_amount' => 2,
            'approx_time' => '3pm',
            'manual_pay_method' => 'PayNow',
            'manual_items_summary' => 'Ice cream x1',
            'reason_text' => 'Did not dispense',
            'contact_email' => 'customer@example.com',
            'photos' => [UploadedFile::fake()->image('proof.jpg')],
        ])->assertStatus(422);
    }
}
