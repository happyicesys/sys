<?php

namespace Tests\Feature;

use App\Http\Controllers\RefundController;
use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\VendTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Refund Requests list — "Payment Method" filter (how the customer PAID, as
 * opposed to "Refund Method", the payout). It takes the same values as the
 * Transactions page dropdown: PaymentMethod ids and/or "cc:<terminal>" card
 * entries, resolved through the ticket's matched vend_transaction (which is
 * also what the list's "Pay Method" column displays).
 */
class RefundIndexPaymentMethodFilterTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethod $card;

    private PaymentMethod $paynow;

    protected function setUp(): void
    {
        parent::setUp();
        // The card id is memoised across requests; a previous test in this
        // process may have cached a different id.
        Cache::forget('payment_method_id_credit_card');

        $this->card = PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
        $this->paynow = PaymentMethod::create(['code' => 201, 'name' => 'Omise (Paynow)', 'is_active' => true]);
    }

    /** Run RefundController::buildFilteredQuery for a request and return matched ticket references. */
    private function referencesFor(array $params): array
    {
        $controller = app(RefundController::class);
        $method = (new \ReflectionClass($controller))->getMethod('buildFilteredQuery');
        $method->setAccessible(true);
        [$query] = $method->invoke($controller, Request::create('/refunds', 'GET', $params));

        return $query->pluck('reference')->sort()->values()->all();
    }

    private function ticket(string $reference, ?int $paymentMethodId, ?string $terminal = null, bool $matched = true): RefundTicket
    {
        $txnId = null;
        if ($matched) {
            $txnId = VendTransaction::create([
                'order_id' => 'ORD-'.$reference,
                'vend_id' => 1320,
                'transaction_datetime' => Carbon::now(),
                'amount' => 150,
                'qty' => 1,
                'success_qty' => 1,
                'dispensed_qty' => 1,
                'vend_channel_id' => 0,
                'gst_vat_rate' => 0,
                'payment_method_id' => $paymentMethodId,
                'cashless_mfg' => $terminal,
            ])->id;
        }

        return RefundTicket::create([
            'reference' => $reference,
            'vend_code' => '2870',
            'vend_id' => 1320,
            'vend_transaction_id' => $txnId,
            'status' => RefundTicket::STATUS_SUBMITTED,
        ]);
    }

    public function test_no_selection_applies_no_payment_method_constraint()
    {
        $this->ticket('RFD-1', $this->paynow->id);
        $this->ticket('RFD-2', $this->card->id, 'Nets');
        $this->ticket('RFD-3', null, null, false); // manual / unmatched

        $this->assertSame(['RFD-1', 'RFD-2', 'RFD-3'], $this->referencesFor([]));
        // "All" (and blanks) are ignored, same as the Transactions page.
        $this->assertSame(['RFD-1', 'RFD-2', 'RFD-3'], $this->referencesFor(['paymentMethods' => ['all']]));
        $this->assertSame(['RFD-1', 'RFD-2', 'RFD-3'], $this->referencesFor(['paymentMethods' => ['']]));
    }

    public function test_filters_by_payment_method_id_through_the_matched_transaction()
    {
        $this->ticket('RFD-1', $this->paynow->id);
        $this->ticket('RFD-2', $this->card->id, 'Nets');
        $this->ticket('RFD-3', null, null, false); // no transaction → never matches a method

        $this->assertSame(['RFD-1'], $this->referencesFor(['paymentMethods' => [$this->paynow->id]]));
        // The plain card entry = any terminal (incl. transactions that recorded none).
        $this->ticket('RFD-4', $this->card->id, null);
        $this->assertSame(['RFD-2', 'RFD-4'], $this->referencesFor(['paymentMethods' => [(string) $this->card->id]]));
        // Multiple ids OR together.
        $this->assertSame(['RFD-1', 'RFD-2', 'RFD-4'], $this->referencesFor(['paymentMethods' => [$this->paynow->id, $this->card->id]]));
    }

    public function test_cc_terminal_entries_narrow_card_to_the_named_terminal()
    {
        $this->ticket('RFD-1', $this->card->id, 'Nets');
        $this->ticket('RFD-2', $this->card->id, 'Nayax');
        $this->ticket('RFD-3', $this->card->id, null);
        // A non-card transaction that happens to carry a terminal name must not match.
        $this->ticket('RFD-4', $this->paynow->id, 'Nets');

        $this->assertSame(['RFD-1'], $this->referencesFor(['paymentMethods' => ['cc:Nets']]));
        $this->assertSame(['RFD-1', 'RFD-2'], $this->referencesFor(['paymentMethods' => ['cc:Nets', 'cc:Nayax']]));
        // Mixed: a terminal entry plus a plain method id.
        $this->assertSame(['RFD-2', 'RFD-4'], $this->referencesFor(['paymentMethods' => ['cc:Nayax', $this->paynow->id]]));
    }

    /**
     * The decode now lives in VendTransaction::applyPaymentMethodFilter, shared
     * with the Transactions page scope — pin that the scope still filters the
     * same way after the extraction.
     */
    public function test_transactions_page_scope_still_decodes_the_same_values()
    {
        $this->ticket('RFD-1', $this->card->id, 'Nets');
        $this->ticket('RFD-2', $this->card->id, 'Nayax');
        $this->ticket('RFD-3', $this->paynow->id);

        $orders = fn (array $pms) => VendTransaction::withoutGlobalScopes()
            ->filterTransactionIndex(Request::create('/transactions', 'GET', ['paymentMethods' => $pms]), true)
            ->pluck('order_id')->sort()->values()->all();

        $this->assertSame(['ORD-RFD-1', 'ORD-RFD-2', 'ORD-RFD-3'], $orders(['all']));
        $this->assertSame(['ORD-RFD-1'], $orders(['cc:Nets']));
        $this->assertSame(['ORD-RFD-1', 'ORD-RFD-3'], $orders(['cc:Nets', $this->paynow->id]));
        $this->assertSame(['ORD-RFD-1', 'ORD-RFD-2'], $orders([$this->card->id]));
    }

    public function test_index_page_exposes_options_and_echoes_the_selection()
    {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('read refunds', 'web'));
        \App\Models\CardTerminal::create(['name' => 'Nets']);
        Cache::forget('payment_methods');
        Cache::forget('card_terminal_options');

        $this->actingAs($user)
            ->get('/refunds?paymentMethods[]=cc:Nets&paymentMethods[]='.$this->paynow->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Refund/Index')
                ->where('filters.paymentMethods', ['cc:Nets', (string) $this->paynow->id])
                ->where('cardTerminalOptions.data', ['Nets'])
                ->has('paymentMethods.data', 2));
    }
}
