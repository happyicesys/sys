<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Product;
use App\Models\RefundTicket;
use App\Models\RemoteCardTerminal;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\CardTerminal\CardRefundService;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction;
use App\Services\SmartFreezer\FreezerReceipt;
use App\Support\AutoRefundSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/** Refunding a captured T05 charge, per SKU or whole: /card-refunds. */
class CardRefundTest extends TestCase
{
    use RefreshDatabase;

    private FakeRemoteCardTerminalGateway $gateway;

    private Vend $vend;

    private User $admin;

    private Product $cone;

    private Product $bar;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $this->gateway);

        Permission::findOrCreate('verify refunds', 'web');
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->admin = User::factory()->create(['operator_id' => 1]);
        $this->admin->givePermissionTo('verify refunds');
        $this->cone = Product::withoutGlobalScopes()->create(['code' => 'CONE', 'name' => 'Cornetto', 'operator_id' => 1]);
        $this->bar = Product::withoutGlobalScopes()->create(['code' => 'MAG', 'name' => 'Magnum', 'operator_id' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sessionRef(): string
    {
        return 'SF-50001-'.Carbon::parse('2026-11-04 19:49:00')->timestamp.'-1';
    }

    private function charge(array $attributes = []): CardPaymentIntent
    {
        $terminal = RemoteCardTerminal::firstOrCreate(['vend_id' => $this->vend->id], ['provider' => 'payrallel', 'access_token' => 'tok', 'is_active' => true]);

        return CardPaymentIntent::create($attributes + [
            'vend_id' => $this->vend->id,
            'remote_card_terminal_id' => $terminal->id,
            'provider' => 'payrallel',
            'reference' => 'SF1',
            'custom_order_id' => '50001-SF1',
            'session_ref' => $this->sessionRef(),
            'mode' => CardPaymentIntent::MODE_PREAUTH,
            'amount_cents' => 700,
            'captured_cents' => 700,
            'state' => CardPaymentIntent::STATE_CAPTURED,
            'provider_txn_id' => 'HOLD-1',
            'capture_txn_id' => 'C-CHARGE-1',
            'captured_at' => Carbon::parse('2026-11-04 20:00:00'),
        ]);
    }

    /** 2 × Cornetto at $2.00 + 1 × Magnum at $3.00 = $7.00. */
    private function sale(array $attributes = []): VendTransaction
    {
        $sale = VendTransaction::withoutGlobalScopes()->create($attributes + [
            'vend_id' => $this->vend->id,
            'order_id' => 'ORD-1',
            'amount' => 700,
            'is_multiple' => true,
            'qty' => 3,
            'unit_cost' => 100,
            'gst_vat_rate' => 9,
            'transaction_datetime' => '2026-11-04 19:50:00',
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'vend_transaction_json' => ['SFREF' => $this->sessionRef(), 'TXN_SRC' => 1],
        ]);
        foreach ([[$this->cone, 200], [$this->cone, 200], [$this->bar, 300]] as [$product, $price]) {
            VendTransactionItem::create(['vend_transaction_id' => $sale->id, 'product_id' => $product->id, 'unit_price_amount' => $price, 'vend_channel_id' => 0, 'is_refunded' => false]);
        }

        return $sale;
    }

    private function refundItem(CardPaymentIntent $intent, Product $product, int $unit, int $qty, int $amount)
    {
        return $this->actingAs($this->admin)->postJson("/card-refunds/intents/{$intent->id}/item", [
            'product_id' => $product->id, 'unit_cents' => $unit, 'qty' => $qty, 'amount_cents' => $amount,
        ]);
    }

    public function test_one_sku_refund_names_the_charge_not_the_hold_and_lowers_the_sale(): void
    {
        $intent = $this->charge();
        $sale = $this->sale();

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('panel.refunded_cents', 200)->assertJsonPath('panel.refundable_cents', 500);

        $this->assertSame([['refund', 'C-CHARGE-1', 200]], $this->gateway->callsOf('refund'));
        $this->assertSame(200, $intent->fresh()->refunded_cents);
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, $intent->fresh()->state);

        $sale->refresh();
        $this->assertSame(500, (int) $sale->amount);
        $this->assertFalse((bool) $sale->is_refunded);
        $this->assertEqualsWithDelta(500 / 1.09, (float) $sale->revenue, 1);
        $this->assertSame(1, $sale->vendTransactionItems()->where('is_refunded', true)->where('product_id', $this->cone->id)->count());
        $this->assertSame(0, $sale->vendTransactionItems()->where('is_refunded', true)->where('product_id', $this->bar->id)->count());
    }

    public function test_a_sku_cannot_be_refunded_more_times_than_it_was_bought(): void
    {
        $intent = $this->charge();
        $this->sale();

        $this->refundItem($intent, $this->cone, 200, 2, 400)->assertOk();
        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertStatus(422);

        $this->assertCount(1, $this->gateway->callsOf('refund'));
    }

    public function test_full_refund_sends_what_is_left_and_marks_the_sale_refunded(): void
    {
        $intent = $this->charge();
        $sale = $this->sale();
        $this->refundItem($intent, $this->bar, 300, 1, 300)->assertOk();

        $this->actingAs($this->admin)->postJson("/card-refunds/intents/{$intent->id}/full", ['amount_cents' => 400])
            ->assertOk()->assertJsonPath('panel.refundable_cents', 0)
            ->assertJsonPath('panel.lines.0.refundable_qty', 0)
            ->assertJsonPath('panel.lines.1.refundable_qty', 0)
            ->assertJsonPath('panel.blocked_reason', 'This charge is fully refunded.');

        $this->assertSame([['refund', 'C-CHARGE-1', 300], ['refund', 'C-CHARGE-1', 400]], $this->gateway->callsOf('refund'));
        $sale->refresh();
        $this->assertTrue((bool) $sale->is_refunded);
        $this->assertSame(AutoRefundSource::PAYRALLEL_REFUND, $sale->auto_refund_source);
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $sale->settlement_status);
        $this->assertSame(3, $sale->vendTransactionItems()->where('is_refunded', true)->count());
    }

    public function test_a_stale_page_cannot_refund_a_different_amount(): void
    {
        $intent = $this->charge();
        $this->sale();

        $this->actingAs($this->admin)->postJson("/card-refunds/intents/{$intent->id}/full", ['amount_cents' => 650])->assertStatus(422);
        $this->assertSame([], $this->gateway->callsOf('refund'));
    }

    public function test_the_day_of_the_charge_is_refused_before_anything_is_sent(): void
    {
        $intent = $this->charge(['captured_at' => Carbon::parse('2026-11-05 08:00:00')]);
        $this->sale();

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertStatus(422)
            ->assertJsonPath('panel.blocked_reason', 'Payrallel cannot refund a charge on the day it was made. Available from 2026-11-06.');
        $this->assertSame([], $this->gateway->callsOf('refund'));
        $this->assertSame(0, CardRefund::count());
    }

    public function test_a_charge_with_no_capture_id_never_falls_back_to_the_hold(): void
    {
        $intent = $this->charge(['capture_txn_id' => null]);
        $this->sale();

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertStatus(422);
        $this->assertSame([], $this->gateway->callsOf('refund'));
    }

    public function test_a_refusal_moves_nothing_and_can_be_tried_again(): void
    {
        $intent = $this->charge();
        $sale = $this->sale();
        $this->gateway->failRefund = CardTerminalException::refused('Payrallel refund refused (HTTP 400): too early', 400);

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk()->assertJsonPath('ok', false);

        $this->assertSame(CardRefund::STATE_REFUSED, CardRefund::first()->state);
        $this->assertSame(0, $intent->fresh()->refunded_cents);
        $this->assertSame(700, (int) $sale->fresh()->amount);

        $this->gateway->failRefund = null;
        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk()->assertJsonPath('ok', true);
    }

    public function test_no_answer_blocks_every_further_refund_until_a_person_settles_it(): void
    {
        $intent = $this->charge();
        $sale = $this->sale();
        $this->gateway->failRefund = new CardTerminalException('Payrallel unreachable: read timed out');

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk()->assertJsonPath('ok', false);
        $refund = CardRefund::first();
        $this->assertSame(CardRefund::STATE_UNCERTAIN, $refund->state);
        $this->assertSame(700, (int) $sale->fresh()->amount);

        // Not resent, even once Payrallel answers again.
        $this->gateway->failRefund = null;
        $this->refundItem($intent, $this->bar, 300, 1, 300)->assertStatus(422);
        $this->assertCount(1, $this->gateway->callsOf('refund'));

        $this->actingAs($this->admin)->postJson("/card-refunds/{$refund->id}/resolve", ['went_through' => true])->assertOk();
        $this->assertSame(CardRefund::STATE_REFUNDED, $refund->fresh()->state);
        $this->assertSame(200, $intent->fresh()->refunded_cents);
        $this->assertSame(500, (int) $sale->fresh()->amount);
        $this->assertCount(1, $this->gateway->callsOf('refund'));
    }

    public function test_settling_an_unanswered_refund_as_not_done_frees_the_charge(): void
    {
        $intent = $this->charge();
        $this->sale();
        $this->gateway->failRefund = new CardTerminalException('Payrallel unreachable: read timed out');
        $this->refundItem($intent, $this->cone, 200, 1, 200);
        $this->gateway->failRefund = null;

        $this->actingAs($this->admin)->postJson('/card-refunds/'.CardRefund::first()->id.'/resolve', ['went_through' => false])
            ->assertOk()->assertJsonPath('panel.refundable_cents', 700)->assertJsonPath('panel.blocked_reason', null);
        $this->assertSame(0, $intent->fresh()->refunded_cents);
    }

    public function test_an_unknown_status_from_payrallel_is_not_read_as_money_returned(): void
    {
        $intent = $this->charge();
        $this->sale();
        $this->gateway->refundStatus = TerminalTransaction::PROCESSING;

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk()->assertJsonPath('ok', false);

        $this->assertSame(CardRefund::STATE_UNCERTAIN, CardRefund::first()->state);
        $this->assertSame(0, $intent->fresh()->refunded_cents);
    }

    public function test_a_claim_being_paid_another_way_blocks_the_refund(): void
    {
        $intent = $this->charge();
        $sale = $this->sale();
        $ticket = $this->ticket($sale, RefundTicket::STATUS_APPROVED);

        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertStatus(422)
            ->assertJsonPath('panel.blocked_reason', "Claim {$ticket->reference} is already being paid another way (approved). Reject it first, or the customer is paid twice.");
        $this->assertSame([], $this->gateway->callsOf('refund'));
    }

    public function test_a_t05_refund_blocks_approving_the_claim_for_a_payout(): void
    {
        $intent = $this->charge();
        $sale = $this->sale();
        $ticket = $this->ticket($sale, RefundTicket::STATUS_SUBMITTED);
        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk();

        $this->assertSame(1, $ticket->logs()->where('action', 't05_refund')->count());

        Permission::findOrCreate('verify refunds', 'web');
        $this->actingAs($this->admin)->post("/refunds/{$ticket->id}/verify")->assertSessionHasErrors('ticket');
        $this->assertSame(RefundTicket::STATUS_SUBMITTED, $ticket->fresh()->status);
    }

    public function test_it_needs_the_permission_and_stays_within_the_operator(): void
    {
        $intent = $this->charge();
        $this->sale();

        $stranger = User::factory()->create(['operator_id' => 1]);
        $this->actingAs($stranger)->postJson("/card-refunds/intents/{$intent->id}/full", ['amount_cents' => 700])->assertForbidden();
        $this->actingAs($stranger)->getJson("/card-refunds/intents/{$intent->id}")->assertForbidden();

        $this->assertSame([], $this->gateway->callsOf('refund'));
    }

    public function test_the_gateway_posts_the_charge_id_and_cents_and_reads_a_404_as_a_refusal(): void
    {
        $this->app->forgetInstance(PayrallelGateway::class);
        unset($this->app[PayrallelGateway::class]);
        config(['payrallel.base_url' => 'https://pay.example', 'payrallel.authorization_format' => '{token}']);
        $gateway = $this->app->make(PayrallelGateway::class);
        $terminal = new RemoteCardTerminal(['provider' => 'payrallel', 'access_token' => 'tok-1']);
        $terminal->id = 7;

        Http::fakeSequence('pay.example/*')
            ->push(['success' => true, 'transaction' => ['transactionId' => 'R1', 'status' => 'approved']])
            ->push(['success' => false, 'error' => 'Refund failed: : Original transaction not found or unauthorized'], 404)
            ->push(['success' => false, 'error' => 'too early'], 200)
            ->push('oops', 502);

        $answer = $gateway->refund($terminal, 'C-1', 250, '50001-SF1');
        $this->assertSame(TerminalTransaction::APPROVED, $answer->status);
        $this->assertSame('R1', $answer->providerTxnId);
        Http::assertSent(fn ($r) => $r->url() === 'https://pay.example/transactions/actions/refund'
            && $r->method() === 'POST' && $r->header('Authorization')[0] === 'tok-1'
            && $r->data() === ['transactionId' => 'C-1', 'amountInCents' => 250]);

        foreach ([false, false, true] as $mayHaveReached) {
            try {
                $gateway->refund($terminal, 'C-1', 250);
                $this->fail('expected a CardTerminalException');
            } catch (CardTerminalException $e) {
                $this->assertSame($mayHaveReached, $e->mayHaveReachedTerminal, $e->getMessage());
            }
        }
    }

    public function test_capture_keeps_the_charge_id_payrallel_answered_with(): void
    {
        $this->app->forgetInstance(PayrallelGateway::class);
        unset($this->app[PayrallelGateway::class]);
        config(['payrallel.base_url' => 'https://pay.example']);
        Http::fake(['pay.example/*' => Http::response(['success' => true, 'transaction' => ['transactionId' => 'C261', 'parentTransactionId' => '261', 'customOrderId' => null]])]);
        $terminal = new RemoteCardTerminal(['provider' => 'payrallel', 'access_token' => 't']);
        $terminal->id = 7;

        $this->assertSame('C261', $this->app->make(PayrallelGateway::class)->capture($terminal, '50001-SF1', 100));
    }

    public function test_the_migration_recovers_past_charge_ids_from_the_timeline_only_when_the_amount_agrees(): void
    {
        $good = $this->charge(['capture_txn_id' => null, 'captured_cents' => 1140, 'custom_order_id' => '50001-A', 'reference' => 'A']);
        $wrong = $this->charge(['capture_txn_id' => null, 'captured_cents' => 500, 'custom_order_id' => '50001-B', 'reference' => 'B']);
        $event = fn (string $order, int $http, string $path, string $body) => DB::table('card_payment_events')->insert([
            'custom_order_id' => $order, 'event' => 'provider.http', 'level' => 'info', 'created_at' => now(),
            'detail' => json_encode(['path' => $path, 'http_status' => $http, 'response' => $body]),
        ]);
        $answer = '{"success":true,"transaction":{"transactionId":"C261","amountInCents":1140,"customOrderId":null,"parentTransactionId":"261","gateway…[cut]';
        $event('50001-A', 200, 'transactions/actions/query', '{"success":true,"transaction":{"transactionId":"HOLD","amountInCents":1140}}');
        $event('50001-A', 200, 'transactions/card/capture', $answer);
        $event('50001-B', 200, 'transactions/card/capture', $answer);

        (require database_path('migrations/2026_11_03_170000_create_card_refunds.php'))->backfill();

        $this->assertSame('C261', $good->fresh()->capture_txn_id);
        $this->assertNull($wrong->fresh()->capture_txn_id);
    }

    public function test_the_refund_request_page_carries_the_panel_for_a_t05_sale(): void
    {
        $this->charge();
        $sale = $this->sale();
        $ticket = $this->ticket($sale, RefundTicket::STATUS_SUBMITTED);
        Permission::findOrCreate('read refunds', 'web');
        $this->admin->givePermissionTo('read refunds');

        $this->actingAs($this->admin)->get("/refunds/{$ticket->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('ticket.t05_refund.captured_cents', 700)
                ->where('ticket.t05_refund.refundable_cents', 700)
                ->where('ticket.t05_refund.blocked_reason', null)
                ->has('ticket.t05_refund.lines', 2)
                ->where('ticket.t05_refund.lines.0.name', 'Cornetto')
                ->where('ticket.t05_refund.lines.0.qty', 2)
                ->where('ticket.t05_refund.lines.0.unit_cents', 200));
    }

    public function test_a_sale_paid_another_way_shows_no_panel(): void
    {
        $this->assertNull($this->app->make(CardRefundService::class)->panelForSale($this->sale(['vend_transaction_json' => ['TXN_SRC' => 1]])));
    }

    public function test_a_single_unit_sale_refunds_at_what_was_charged(): void
    {
        $intent = $this->charge(['captured_cents' => 350]);
        $sale = VendTransaction::withoutGlobalScopes()->create([
            'vend_id' => $this->vend->id, 'order_id' => 'ORD-2', 'amount' => 350, 'is_multiple' => false, 'qty' => 1, 'product_id' => $this->bar->id,
            'transaction_datetime' => '2026-11-04 19:50:00', 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'vend_transaction_json' => ['SFREF' => $this->sessionRef(), 'TXN_SRC' => 1],
        ]);

        $this->refundItem($intent, $this->bar, 350, 1, 350)->assertOk()->assertJsonPath('panel.refundable_cents', 0);

        $this->assertTrue((bool) $sale->fresh()->is_refunded);
    }

    public function test_the_item_refund_is_capped_at_what_is_left_of_the_charge(): void
    {
        // Payrallel refused to go above the hold: $5.00 charged on a $7.00 basket.
        $intent = $this->charge(['captured_cents' => 500]);
        $this->sale();
        $this->refundItem($intent, $this->bar, 300, 1, 300)->assertOk();
        $this->refundItem($intent, $this->cone, 200, 2, 400)->assertStatus(422);
        $this->refundItem($intent, $this->cone, 200, 2, 200)->assertOk()->assertJsonPath('panel.refundable_cents', 0);

        $this->assertSame([['refund', 'C-CHARGE-1', 300], ['refund', 'C-CHARGE-1', 200]], $this->gateway->callsOf('refund'));
    }

    public function test_the_live_invoice_lists_what_the_customer_still_paid(): void
    {
        $intent = $this->charge();
        $this->sale();
        $this->refundItem($intent, $this->cone, 200, 1, 200)->assertOk();

        $state = $this->app->make(FreezerReceipt::class)->state($this->vend, $this->sessionRef());

        $this->assertSame(500, $state['total_cents']);
        $this->assertSame(['Cornetto' => 200, 'Magnum' => 300], collect($state['lines'])->pluck('amount_cents', 'name')->all());
    }

    private function ticket(VendTransaction $sale, string $status): RefundTicket
    {
        return RefundTicket::create([
            'reference' => 'RF-'.uniqid(),
            'vend_id' => $this->vend->id,
            'vend_transaction_id' => $sale->id,
            'order_id' => $sale->order_id,
            'status' => $status,
            'claimed_amount_cents' => 200,
        ]);
    }
}
