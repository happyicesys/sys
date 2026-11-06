<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\CardRefund;
use App\Models\Product;
use App\Models\RefundTicket;
use App\Models\RemoteCardTerminal;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\Freezer\FreezerStockLedger;
use App\Services\SmartFreezer\FreezerReceipt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * AI Recognition as a control panel (Brian, 2026-11-03): a person says what left the cabinet, and the
 * sale's items, the stock and the money follow. The sale was 2 × Cone at 200c + 1 × Bar at 300c = 700c.
 */
class FreezerSaleCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private FakeRemoteCardTerminalGateway $gateway;

    private Vend $vend;

    private User $admin;

    private Product $cone;

    private Product $bar;

    private Product $lolly;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'preauth', 'payrallel.ai_capture' => true]);
        $this->gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $this->gateway);

        foreach (['read ai-recognition', 'charge ai-recognition', 'verify refunds'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $this->admin = User::factory()->create(['operator_id' => 1, 'name' => 'Admin One']);
        $this->admin->givePermissionTo(['read ai-recognition', 'charge ai-recognition', 'verify refunds']);

        foreach ([['cone', 'CONE', 'Cone', 11, 200], ['bar', 'BAR', 'Bar', 12, 300], ['lolly', 'LOL', 'Lolly', 13, 250]] as [$field, $code, $name, $channel, $price]) {
            $this->{$field} = Product::withoutGlobalScopes()->create(['code' => $code, 'name' => $name, 'operator_id' => 1]);
            VendChannel::create(['vend_id' => $this->vend->id, 'code' => $channel, 'product_id' => $this->{$field}->id, 'qty' => 10, 'capacity' => 20, 'amount' => $price, 'is_active' => 1]);
        }
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

    private function intent(array $attributes = []): CardPaymentIntent
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
            'state' => CardPaymentIntent::STATE_CAPTURED,
            'captured_cents' => 700,
            'provider_txn_id' => 'HOLD-1',
            'capture_txn_id' => 'C-CHARGE-1',
            'captured_at' => Carbon::parse('2026-11-04 20:00:00'),
            'door_closed_at' => Carbon::parse('2026-11-04 19:50:00'),
        ]);
    }

    private function sale(array $attributes = [], int $txnSrc = 1): VendTransaction
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
            'vend_transaction_json' => ['SFREF' => $this->sessionRef(), 'TXN_SRC' => $txnSrc, 'transf_info' => [
                ['SId' => 11, 'SErr' => 0, 'goods_id' => $this->cone->id, 'Price' => 200],
                ['SId' => 11, 'SErr' => 0, 'goods_id' => $this->cone->id, 'Price' => 200],
                ['SId' => 12, 'SErr' => 0, 'goods_id' => $this->bar->id, 'Price' => 300],
            ]],
        ]);
        foreach ([[$this->cone, 200], [$this->cone, 200], [$this->bar, 300]] as [$product, $price]) {
            VendTransactionItem::create(['vend_transaction_id' => $sale->id, 'product_id' => $product->id, 'unit_price_amount' => $price, 'vend_channel_id' => 0, 'is_refunded' => false]);
        }

        return $sale;
    }

    private function recognition(VendTransaction $sale, array $attributes = []): SmartFreezerRecognition
    {
        return SmartFreezerRecognition::create($attributes + [
            'vend_id' => $this->vend->id,
            'trade_id' => 'SDK1',
            'session_ref' => $this->sessionRef(),
            'status' => SmartFreezerRecognition::STATUS_COMPLETED,
            'order_status' => 0,
            'callback_verified' => true,
            'completed_at' => Carbon::now(),
            'vend_transaction_id' => $sale->id,
            'verdict' => 'mixed',
            'verdict_lines' => [
                ['product_id' => $this->cone->id, 'code' => '1', 'paid' => 2, 'taken' => 1, 'delta' => -1],
                ['product_id' => $this->bar->id, 'code' => '2', 'paid' => 1, 'taken' => 1, 'delta' => 0],
                ['product_id' => $this->lolly->id, 'code' => '3', 'paid' => 0, 'taken' => 1, 'delta' => 1],
            ],
        ]);
    }

    /** @param array<int, int> $taken */
    private function correct(SmartFreezerRecognition $recognition, array $taken, int $cents, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->admin)->postJson("/ai-recognition/{$recognition->id}/correction", ['taken' => $taken, 'amount_cents' => $cents]);
    }

    private function qty(Product $product): int
    {
        return (int) VendChannel::where('vend_id', $this->vend->id)->where('product_id', $product->id)->value('qty');
    }

    /** @return array<int, int> */
    private function rows(VendTransaction $sale): array
    {
        return VendTransactionItem::where('vend_transaction_id', $sale->id)->get()->countBy('product_id')->sortKeys()->all();
    }

    private function holdOpen(): CardPaymentIntent
    {
        return $this->intent(['state' => CardPaymentIntent::STATE_AWAITING_AI, 'captured_cents' => null, 'capture_txn_id' => null, 'captured_at' => null]);
    }

    private function heldSale(): VendTransaction
    {
        return $this->sale(['settlement_status' => VendTransaction::SETTLEMENT_PENDING, 'audit_pending_at' => Carbon::now()]);
    }

    public function test_the_editor_shows_each_products_photo(): void
    {
        $this->cone->thumbnail()->create(['type' => 1, 'full_url' => 'https://spaces/cone.jpg', 'local_url' => 'https://spaces/cone.jpg']);
        $recognition = $this->recognition($this->sale());

        $lines = collect($this->actingAs($this->admin)->getJson("/ai-recognition/{$recognition->id}/correction")->assertOk()->json('editor.lines'))->keyBy('product_id');

        $this->assertSame('https://spaces/cone.jpg', $lines[$this->cone->id]['photo']);
        $this->assertNull($lines[$this->bar->id]['photo'], 'a product with no photo shows the placeholder');
    }

    public function test_an_open_hold_charges_the_count_and_items_and_stock_follow(): void
    {
        $this->holdOpen();
        $sale = $this->heldSale();
        $recognition = $this->recognition($sale);

        // 1 × Cone 200 + 1 × Bar 300 + 1 × Lolly at the shelf's 250.
        $this->correct($recognition, [$this->cone->id => 1, $this->bar->id => 1, $this->lolly->id => 1], 750)
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('mode', 'hold');

        $this->assertSame([['capture', '50001-SF1', 750]], $this->gateway->callsOf('capture'));
        $sale->refresh();
        $this->assertSame(750, (int) $sale->amount);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status);
        $this->assertNull($sale->audit_pending_at);
        $this->assertSame([$this->cone->id => 1, $this->bar->id => 1, $this->lolly->id => 1], $this->rows($sale));
        $this->assertSame([11, 10, 9], [$this->qty($this->cone), $this->qty($this->bar), $this->qty($this->lolly)], 'one Cone back, one Lolly out');
        $this->assertSame(250, (int) VendTransactionItem::where('vend_transaction_id', $sale->id)->where('product_id', $this->lolly->id)->value('unit_price_amount'));
    }

    public function test_one_adjust_on_an_open_hold_also_shows_as_adjusted(): void
    {
        $this->holdOpen();
        $sale = $this->heldSale();
        $recognition = $this->recognition($sale);

        $this->correct($recognition, [$this->cone->id => 1, $this->bar->id => 1, $this->lolly->id => 1], 750)->assertOk();

        $meta = (array) $sale->fresh()->meta_json;
        $this->assertSame(750, (int) $meta['correction']['value_cents']);
        $this->assertSame('Admin One', $meta['correction']['by']['name']);
        $this->assertCount(1, $meta['corrections']);
        $this->assertSame([11, 10, 9], [$this->qty($this->cone), $this->qty($this->bar), $this->qty($this->lolly)], 'recording it moves the stock once');
        $this->assertSame([['capture', '50001-SF1', 750]], $this->gateway->callsOf('capture'));
    }

    public function test_an_open_hold_counted_as_nothing_taken_is_released_and_the_stock_goes_back(): void
    {
        $this->holdOpen();
        $sale = $this->heldSale();

        $this->correct($this->recognition($sale), [], 0)->assertOk();

        $this->assertCount(1, $this->gateway->callsOf('void'));
        $this->assertSame([], $this->gateway->callsOf('capture'));
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $sale->fresh()->settlement_status);
        $this->assertSame([12, 11], [$this->qty($this->cone), $this->qty($this->bar)]);
    }

    // 50002, 2026-11-06: a release at 0 showed no adjustment, so the person adjusted again.
    public function test_one_adjust_that_releases_the_hold_also_shows_as_adjusted(): void
    {
        $this->holdOpen();
        $sale = $this->heldSale();
        $recognition = $this->recognition($sale);

        $this->correct($recognition, [], 0)->assertOk();

        $meta = (array) $sale->fresh()->meta_json;
        $this->assertSame(0, (int) $meta['correction']['value_cents']);
        $this->assertSame([], $meta['correction']['lines']);
        $this->assertSame('Admin One', $meta['correction']['by']['name']);
        $this->assertCount(1, $meta['corrections']);
        $this->assertSame([12, 11], [$this->qty($this->cone), $this->qty($this->bar)], 'recording it puts the stock back once');
        $this->assertCount(1, $this->gateway->callsOf('void'));

        $this->actingAs($this->admin)->get('/ai-recognition?date_from=&date_to=')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.correction.by', 'Admin One')
                ->where('recognitions.data.0.correction.value_cents', 0));
    }

    public function test_a_hold_the_ai_may_still_answer_cannot_be_corrected(): void
    {
        $this->holdOpen()->update(['door_closed_at' => Carbon::now()]);
        $sale = $this->heldSale();
        $recognition = $this->recognition($sale, ['verdict' => null, 'verdict_lines' => null, 'order_status' => null]);

        $this->correct($recognition, [$this->cone->id => 2, $this->bar->id => 1], 700)
            ->assertStatus(422)->assertJsonFragment(['message' => __('The AI still has time to answer this session.')]);
        $this->assertSame([], $this->gateway->callsOf('capture'));
    }

    /**
     * 50001, 2026-11-03: 4 paid at 880c, captured 1130c. Here 700c paid, an AI-added Lolly captured
     * at 950c. The person says the cart was right: no money moves until Refund is pressed.
     */
    public function test_an_overcharge_fixes_items_and_stock_now_and_the_difference_is_refunded_on_request(): void
    {
        $intent = $this->intent(['captured_cents' => 950]);
        $sale = $this->sale(['amount' => 950, 'meta_json' => ['audit' => ['source' => 'smart_freezer_ai', 'cart_cents' => 700, 'charged_cents' => 950, 'stock_moved' => [$this->lolly->id => -1]]]]);
        VendTransactionItem::create(['vend_transaction_id' => $sale->id, 'product_id' => $this->lolly->id, 'unit_price_amount' => 250, 'vend_channel_id' => 0, 'is_refunded' => false]);

        $this->correct($this->recognition($sale), [$this->cone->id => 2, $this->bar->id => 1], 700)
            ->assertOk()->assertJsonPath('mode', 'charged')->assertJsonPath('to_refund_cents', 250)->assertJsonPath('owed_cents', 0);

        $this->assertSame([], $this->gateway->callsOf('refund'), 'money goes back only when a person presses Refund');
        $sale->refresh();
        $this->assertSame(950, (int) $sale->amount, 'the amount stays what was collected');
        $this->assertSame([$this->cone->id => 2, $this->bar->id => 1], $this->rows($sale));
        $this->assertSame(11, $this->qty($this->lolly), 'the Lolly never left');
        $this->assertSame([], (array) $sale->meta_json['audit']['stock_moved']);
        $this->assertSame('Admin One', $sale->meta_json['correction']['by']['name']);

        $this->actingAs($this->admin)->getJson("/card-refunds/intents/{$intent->id}")->assertJsonPath('panel.correction_refund_cents', 250);
        $this->actingAs($this->admin)->postJson("/card-refunds/intents/{$intent->id}/correction", ['amount_cents' => 250])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame([['refund', 'C-CHARGE-1', 250]], array_map(fn ($c) => array_slice($c, 0, 3), $this->gateway->callsOf('refund')));
        $sale->refresh();
        $this->assertSame(700, (int) $sale->amount, 'the sale now counts what the customer paid');
        $this->assertSame(0, VendTransactionItem::where('vend_transaction_id', $sale->id)->where('is_refunded', true)->count());
        $this->assertSame(CardRefund::KIND_CORRECTION, CardRefund::sole()->kind);

        $this->actingAs($this->admin)->postJson("/card-refunds/intents/{$intent->id}/correction", ['amount_cents' => 250])->assertStatus(422);
        $this->assertCount(1, $this->gateway->callsOf('refund'));
    }

    public function test_an_undercharge_is_recorded_as_owed_and_nothing_is_charged(): void
    {
        $intent = $this->intent();
        $sale = $this->sale();

        $this->correct($this->recognition($sale), [$this->cone->id => 2, $this->bar->id => 1, $this->lolly->id => 1], 950)
            ->assertOk()->assertJsonPath('owed_cents', 250)->assertJsonPath('to_refund_cents', 0);

        $this->assertSame([], $this->gateway->callsOf('capture'));
        $this->assertSame(250, $intent->fresh()->owed_cents);
        $this->assertSame(700, (int) $sale->fresh()->amount);
        $this->assertSame(9, $this->qty($this->lolly));

        // Corrected back: nothing owed, the Lolly returns.
        $this->correct($this->recognition($sale, ['trade_id' => 'SDK2']), [$this->cone->id => 2, $this->bar->id => 1], 700)->assertOk();
        $this->assertNull($intent->fresh()->owed_cents);
        $this->assertSame(10, $this->qty($this->lolly));
    }

    public function test_correcting_again_moves_only_the_difference_and_rebuild_agrees(): void
    {
        $this->intent();
        $sale = $this->sale();
        $recognition = $this->recognition($sale);

        $this->correct($recognition, [$this->cone->id => 1, $this->bar->id => 1], 500)->assertOk();
        $this->assertSame(11, $this->qty($this->cone));
        $this->correct($recognition, [$this->bar->id => 1], 300)->assertOk();
        $this->assertSame(12, $this->qty($this->cone));
        $this->correct($recognition, [$this->cone->id => 2, $this->bar->id => 1], 700)->assertOk();
        $this->assertSame(10, $this->qty($this->cone));

        $sale->refresh();
        $this->assertCount(3, $sale->meta_json['corrections']);
        $this->assertSame([$this->cone->id => 2, $this->bar->id => 1], app(FreezerStockLedger::class)->unitsOut($sale));
    }

    public function test_a_qr_or_nets_sale_corrects_items_and_stock_only(): void
    {
        $sale = $this->sale(txnSrc: 0);

        $this->correct($this->recognition($sale), [$this->cone->id => 1, $this->bar->id => 1], 500)
            ->assertOk()->assertJsonPath('mode', 'paid')->assertJsonPath('to_refund_cents', 0);

        $sale->refresh();
        $this->assertSame(700, (int) $sale->amount);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status);
        $this->assertSame([$this->cone->id => 1, $this->bar->id => 1], $this->rows($sale));
        $this->assertSame(11, $this->qty($this->cone));
        $this->assertSame([], $this->gateway->callsOf('capture'));
        $this->assertSame([], $this->gateway->callsOf('refund'));
    }

    public function test_counted_as_nothing_taken_after_a_charge_refunds_it_all_and_the_invoice_says_so(): void
    {
        $intent = $this->intent();
        $sale = $this->sale();

        $this->correct($this->recognition($sale), [], 0)->assertOk()->assertJsonPath('to_refund_cents', 700);
        $this->assertSame([], $this->rows($sale));
        $this->assertSame([12, 11], [$this->qty($this->cone), $this->qty($this->bar)]);

        $receipt = app(FreezerReceipt::class)->state($this->vend->fresh(), $this->sessionRef());
        $this->assertSame([0, 0, 700], array_column($receipt['lines'], 'amount_cents'), 'two not-taken lines, then what was still charged');

        $this->actingAs($this->admin)->postJson("/card-refunds/intents/{$intent->id}/correction", ['amount_cents' => 700])->assertOk();
        $sale->refresh();
        $this->assertTrue((bool) $sale->is_refunded);
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $sale->settlement_status);
    }

    public function test_a_stale_page_changes_nothing(): void
    {
        $this->intent();
        $sale = $this->sale();

        $this->correct($this->recognition($sale), [$this->cone->id => 1], 999)->assertStatus(422);

        $this->assertSame([$this->cone->id => 2, $this->bar->id => 1], $this->rows($sale));
        $this->assertSame(10, $this->qty($this->cone));
        $this->assertArrayNotHasKey('correction', (array) $sale->fresh()->meta_json);
    }

    public function test_a_testing_mode_sale_values_at_twenty_cents_and_moves_no_stock(): void
    {
        $this->intent(['captured_cents' => 60, 'amount_cents' => 60]);
        $sale = $this->sale(['amount' => 60, 'vend_transaction_json' => ['SFREF' => $this->sessionRef(), 'TXN_SRC' => 1, 'TEST' => 1, 'transf_info' => [
            ['SId' => 11, 'SErr' => 0, 'goods_id' => $this->cone->id, 'Price' => 20],
            ['SId' => 11, 'SErr' => 0, 'goods_id' => $this->cone->id, 'Price' => 20],
            ['SId' => 12, 'SErr' => 0, 'goods_id' => $this->bar->id, 'Price' => 20],
        ]]]);

        $this->correct($this->recognition($sale), [$this->cone->id => 1, $this->lolly->id => 1], 40)->assertOk()->assertJsonPath('to_refund_cents', 20);

        $this->assertSame([10, 10, 10], [$this->qty($this->cone), $this->qty($this->bar), $this->qty($this->lolly)]);
    }

    public function test_an_extra_taken_during_a_happy_hour_slot_is_valued_at_the_promo_price(): void
    {
        $campaign = \App\Models\HappyHourCampaign::create(['name' => 'HH', 'operator_id' => 1, 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30]);
        \App\Models\HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $this->vend->id, 'product_id' => $this->lolly->id,
            'slot_date' => '2026-11-04', 'position' => 0, 'starts_at' => '2026-11-04 19:00:00', 'ends_at' => '2026-11-04 20:00:00',
            'original_price' => 250, 'promo_price' => 180, 'discount_pct' => 30, 'rank' => 1,
        ]);
        $this->intent();
        $sale = $this->sale();

        $this->actingAs($this->admin)->getJson("/ai-recognition/{$this->recognition($sale)->id}/correction")->assertOk();
        $this->correct(SmartFreezerRecognition::sole(), [$this->cone->id => 2, $this->bar->id => 1, $this->lolly->id => 1], 880)
            ->assertOk()->assertJsonPath('owed_cents', 180);
    }

    public function test_a_claim_or_an_item_refund_on_the_sale_blocks_a_correction(): void
    {
        $intent = $this->intent();
        $sale = $this->sale();
        $recognition = $this->recognition($sale);

        $claim = RefundTicket::create(['reference' => 'RF-1', 'vend_id' => $this->vend->id, 'vend_transaction_id' => $sale->id, 'order_id' => 'ORD-1', 'status' => RefundTicket::STATUS_SUBMITTED, 'claimed_amount_cents' => 200]);
        $this->correct($recognition, [$this->cone->id => 1], 200)->assertStatus(422);
        $claim->forceFill(['status' => RefundTicket::STATUS_REJECTED])->save();

        CardRefund::create(['card_payment_intent_id' => $intent->id, 'vend_transaction_id' => $sale->id, 'kind' => CardRefund::KIND_ITEM, 'amount_cents' => 200, 'state' => CardRefund::STATE_REFUNDED]);
        $this->correct($recognition, [$this->cone->id => 1], 200)->assertStatus(422);

        $this->assertSame([$this->cone->id => 2, $this->bar->id => 1], $this->rows($sale));
    }

    public function test_it_needs_the_charge_permission(): void
    {
        $this->intent();
        $recognition = $this->recognition($this->sale());
        $reader = User::factory()->create(['operator_id' => 1]);
        $reader->givePermissionTo('read ai-recognition');

        $this->correct($recognition, [], 0, $reader)->assertForbidden();
        $this->actingAs($reader)->getJson("/ai-recognition/{$recognition->id}/correction")->assertForbidden();
    }

    public function test_another_operators_admin_cannot_reach_the_sale(): void
    {
        $this->intent();
        $sale = $this->sale();
        $recognition = $this->recognition($sale);
        $other = User::factory()->create(['operator_id' => 2]);
        $other->givePermissionTo(['read ai-recognition', 'charge ai-recognition']);

        $this->correct($recognition, [], 0, $other)->assertNotFound();
        $this->assertSame(10, $this->qty($this->cone));
    }

    public function test_the_editor_starts_from_the_ai_on_a_hold_and_from_the_sale_after_a_charge(): void
    {
        $this->holdOpen();
        $sale = $this->heldSale();
        $recognition = $this->recognition($sale);

        $lines = fn () => collect($this->actingAs($this->admin)->getJson("/ai-recognition/{$recognition->id}/correction")->assertOk()->json('editor.lines'))->keyBy('product_id');
        $this->assertSame([1, 1, 1], [$lines()[$this->cone->id]['taken'], $lines()[$this->bar->id]['taken'], $lines()[$this->lolly->id]['taken']]);
        $this->assertSame(250, $lines()[$this->lolly->id]['unit_cents']);

        $this->correct($recognition, [$this->cone->id => 2, $this->bar->id => 1], 700)->assertOk();
        $this->assertSame([2, 1, 0], [$lines()[$this->cone->id]['taken'], $lines()[$this->bar->id]['taken'], $lines()[$this->lolly->id]['taken']]);
    }

    public function test_the_table_shows_the_correction_and_what_is_still_to_refund(): void
    {
        $this->intent(['captured_cents' => 950]);
        $sale = $this->sale(['amount' => 950]);
        $recognition = $this->recognition($sale);
        $this->correct($recognition, [$this->cone->id => 2, $this->bar->id => 1], 700)->assertOk();

        $this->actingAs($this->admin)->get('/ai-recognition?date_from=')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.can_correct', true)
                ->where('recognitions.data.0.correction.by', 'Admin One')
                ->where('recognitions.data.0.correction.value_cents', 700)
                ->where('recognitions.data.0.correction.to_refund_cents', 250)
                ->where('recognitions.data.0.correction.lines.0.name', 'Cone')
                ->where('recognitions.data.0.verdict', 'mixed'));
    }
}
