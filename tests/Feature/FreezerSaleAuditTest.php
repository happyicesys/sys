<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\RemoteCardTerminal;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction as T;
use App\Services\Freezer\FreezerSaleAudit;
use App\Services\VendTransactionService;
use App\Support\SaleFacts;
use App\Support\SaleStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * A smart-freezer sale paid on a T05 is not a sale until the camera AI has audited the door
 * session (Brian, 2026-10-05: "the trade row cannot be calculate as sales total and qty total bcz
 * ai recognition havent done the actual audit"). Until then the row is listed but held out of
 * every sales figure; when the charge is decided, what was actually charged becomes its amount.
 *
 * QR and NETS freezer sales keep the old behaviour — they are charged in full at door close, so
 * there is nothing for an audit to settle (Brian, 2026-10-05).
 */
class FreezerSaleAuditTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION = 'SF-50092-1791100000-7';

    private FakeRemoteCardTerminalGateway $gateway;

    private CardPaymentService $service;

    private Vend $vend;

    private Product $cheesecake;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 12:00:00');
        config(['payrallel.min_query_interval_ms' => 0, 'payrallel.mode' => 'preauth', 'payrallel.ai_capture' => true]);

        $this->gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $this->gateway);
        $this->service = $this->app->make(CardPaymentService::class);

        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        PaymentMethod::firstOrCreate(['code' => 2], ['name' => 'Credit Card', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);

        // The algorithm answers in barcodes, so only a barcoded SKU can ever be audited.
        $this->cheesecake = Product::create(['code' => 'CC-01', 'name' => 'Basque Cheesecake']);
        // `barcode` is not fillable on Product — it must be forced, or it is silently dropped.
        $this->cheesecake->forceFill(['barcode' => '9726436016148'])->save();
        $mapping = ProductMapping::create(['name' => 'Freezer', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => 1]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '41', 'product_id' => $this->cheesecake->id]);
        $customer = Customer::create(['name' => 'Freezer Site', 'code' => 'FS2', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);

        $this->vend = Vend::create(['code' => 50092, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id]);
        VendChannel::create(['vend_id' => $this->vend->id, 'code' => 41, 'product_id' => $this->cheesecake->id,
            'qty' => 5, 'capacity' => 5, 'amount' => 380, 'is_active' => 1]);
        RemoteCardTerminal::create(['vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL,
            'access_token' => 'tok-50092', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A T05 hold for a 2-unit cart (760c), door closed and naming its kiosk session. */
    private function doorClosed(?string $session = self::SESSION, int $cents = 760): CardPaymentIntent
    {
        $intent = $this->service->authorize($this->vend, 'SFA1', $cents);
        $this->gateway->answer('50092-SFA1', T::APPROVED);

        return $this->service->capture($this->service->refresh($intent), $cents, $session);
    }

    /** One freezer TRADE through the real ingest: one transf_info entry per unit paid for. */
    private function trade(int $units = 2, ?string $session = self::SESSION, int $paymentType = 2): VendTransaction
    {
        $frame = [
            'ORDRID' => 'SFA'.uniqid(), 'PAY_TYPE' => $paymentType, 'TIME' => Carbon::now()->format('Y-m-d H:i:s'),
            'SErr' => 0, 'SId' => 41, 'Price' => number_format(3.80 * $units, 2, '.', ''), 'TXN_SRC' => $paymentType === 2 ? 1 : 0,
            'transf_info' => array_fill(0, $units, ['SId' => 41, 'SErr' => 0, 'Price' => 380, 'goods_id' => $this->cheesecake->id]),
        ];
        if ($session !== null) {
            $frame['SFREF'] = $session;
        }
        app(VendTransactionService::class)->create($this->vend->fresh(), $frame);

        return VendTransaction::where('vend_id', $this->vend->id)->orderByDesc('id')->firstOrFail();
    }

    /** The AI's answer for the session, against the sale it names. */
    private function verdict(string $verdict, int $taken, VendTransaction $sale): SmartFreezerRecognition
    {
        return SmartFreezerRecognition::query()->create([
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-'.uniqid(), 'session_ref' => self::SESSION,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'vend_transaction_id' => $sale->id,
            'verdict' => $verdict, 'completed_at' => Carbon::now(), 'callback_verified' => true,
            'verdict_lines' => [[
                'product_id' => $this->cheesecake->id, 'code' => $this->cheesecake->barcode,
                'paid' => 2, 'taken' => $taken, 'delta' => $taken - 2,
            ]],
        ]);
    }

    /** The AI never answered: past the grace a person keys in the charge (CardPaymentService::chargeByPerson). */
    private function chargedByPerson(int $cents): void
    {
        Carbon::setTestNow(Carbon::now()->addMinutes((int) config('payrallel.ai_person_after_minutes', 30) + 1));
        $this->service->chargeByPerson(CardPaymentIntent::sole(), $cents, \App\Models\User::factory()->create());
        $this->service->reconcile();
    }

    /**
     * Total Sales for this machine, through the very aggregate the Sales Transactions headline
     * and the nightly rollup are built from (VendTransaction::salesRawTotalsSelect), joins and
     * settlement gate included — not a stand-in for it.
     */
    private function countedSales(): int
    {
        $row = VendTransaction::query()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->leftJoin('delivery_platform_orders', 'delivery_platform_orders.vend_transaction_id', '=', 'vend_transactions.id')
            ->where('vend_transactions.vend_id', $this->vend->id)
            ->countsAsSale()
            ->select(VendTransaction::salesRawTotalsSelect())
            ->first();

        return (int) round((float) $row->success_amount);
    }

    /**
     * Total Qty for this machine, the way the page builds it: single rows that count as a sale,
     * plus the item rows of multi-unit baskets, both behind the settlement gate.
     */
    private function countedQty(): int
    {
        $singles = (int) VendTransaction::query()
            ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
            ->where('vend_transactions.vend_id', $this->vend->id)
            ->countsAsSale()
            ->selectRaw('CAST(SUM(CASE WHEN is_multiple = 0 AND '
                .\App\Support\DispenseVerdict::sqlSale('vend_channel_errors.code').' THEN 1 ELSE 0 END) AS SIGNED) as q')
            ->value('q');

        $items = (int) VendTransaction::query()
            ->leftJoin('vend_transaction_items', 'vend_transactions.id', '=', 'vend_transaction_items.vend_transaction_id')
            ->where('vend_transactions.vend_id', $this->vend->id)
            ->where('is_multiple', true)
            ->countsAsSale()
            ->selectRaw('COUNT(CASE WHEN vend_transaction_items.id IS NOT NULL AND '
                .\App\Support\DispenseVerdict::sqlSale('vend_transaction_items.vend_channel_error_code').' THEN 1 END) as q')
            ->value('q');

        return $singles + $items;
    }

    public function test_a_t05_sale_is_held_out_of_the_sales_figures_until_the_ai_audits_it(): void
    {
        $intent = $this->doorClosed();
        $this->assertSame(CardPaymentIntent::STATE_AWAITING_AI, $intent->state, 'precondition: the hold is waiting');
        $this->assertSame(self::SESSION, $intent->session_ref);
        $this->assertSame($this->vend->id, (int) $intent->vend_id);
        $sale = $this->trade();
        $this->assertSame(self::SESSION, $sale->vend_transaction_json['SFREF'] ?? null, 'precondition: the TRADE names the session');

        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, (int) $sale->settlement_status);
        $this->assertNotNull($sale->audit_pending_at);
        $this->assertTrue($sale->isAwaitingAudit());
        $this->assertSame(760, (int) $sale->amount, 'the cart the customer agreed is kept on the row');
        $this->assertSame(0, $this->countedSales(), 'not a sale until the audit is in');
        $this->assertSame(0, $this->countedQty(), 'and no qty either');
    }

    public function test_a_qr_freezer_sale_counts_immediately(): void
    {
        $sale = $this->trade(units: 2, paymentType: 0);

        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status);
        $this->assertNull($sale->audit_pending_at);
        $this->assertSame(760, $this->countedSales());
    }

    public function test_a_cabinet_with_no_barcoded_product_is_never_held(): void
    {
        $this->cheesecake->forceFill(['barcode' => null])->save();
        $this->doorClosed();
        $sale = $this->trade();

        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status,
            'the AI can never be asked about this cabinet, so there is nothing to wait for');
        $this->assertSame(760, $this->countedSales());
    }

    public function test_the_audited_charge_becomes_the_sale_amount(): void
    {
        $this->doorClosed();
        $sale = $this->trade();
        $this->verdict('took_less', 1, $sale);

        $this->service->reconcile();

        $sale->refresh();
        $this->assertSame(380, (int) $sale->amount, 'charged for the one unit the AI saw taken');
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status);
        $this->assertNull($sale->audit_pending_at);
        $this->assertSame(380, $this->countedSales());
        $this->assertSame(380, (int) CardPaymentIntent::sole()->captured_cents);
        // Revenue and GP follow the money, not the cart.
        $this->assertEqualsWithDelta(380 / (1 + ((float) $sale->gst_vat_rate / 100)), (float) $sale->revenue, 0.01);
        $this->assertSame(760, (int) $sale->meta_json['audit']['cart_cents']);
    }

    /** The qty follows the charge: paid for 2, took 1 — one unit of money and one unit of qty. */
    public function test_the_basket_follows_the_charge_when_less_was_taken(): void
    {
        $this->doorClosed();
        $sale = $this->trade();
        $this->verdict('took_less', 1, $sale);
        $this->service->reconcile();

        $this->assertSame(380, $this->countedSales());
        $this->assertSame(1, $this->countedQty(), 'one unit charged, one unit counted');
        $this->assertSame(1, VendTransactionItem::where('vend_transaction_id', $sale->id)->count());

        // Both sides stay on the row, and the paid cart is still in the TRADE itself.
        $audit = $sale->fresh()->meta_json['audit'];
        $this->assertSame([(string) $this->cheesecake->id => 2], $audit['basket_paid']);
        $this->assertSame([(string) $this->cheesecake->id => 1], $audit['basket_charged']);
        $this->assertCount(2, $sale->fresh()->vend_transaction_json['transf_info']);
    }

    /** Pay for one, take two: the extra unit is charged, so it is counted and attributed too. */
    public function test_the_basket_follows_the_charge_when_more_was_taken(): void
    {
        $this->doorClosed(cents: 380);
        $sale = $this->trade(units: 1);
        SmartFreezerRecognition::query()->create([
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-'.uniqid(), 'session_ref' => self::SESSION,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'vend_transaction_id' => $sale->id,
            'verdict' => 'took_more', 'completed_at' => Carbon::now(), 'callback_verified' => true,
            'verdict_lines' => [[
                'product_id' => $this->cheesecake->id, 'code' => $this->cheesecake->barcode,
                'paid' => 1, 'taken' => 2, 'delta' => 1,
            ]],
        ]);

        $this->service->reconcile();

        $this->assertSame(760, (int) $sale->fresh()->amount, 'charged for both units');
        $this->assertSame(760, $this->countedSales());
        $this->assertSame(2, $this->countedQty(), 'both units counted');
        $items = VendTransactionItem::where('vend_transaction_id', $sale->id)->get();
        $this->assertCount(2, $items);
        $this->assertSame([380, 380], $items->pluck('unit_price_amount')->map(fn ($c) => (int) $c)->all());
        $this->assertTrue((bool) $sale->fresh()->is_multiple, 'per-unit rows make it a multiple');
    }

    /** A charge that fell back to the cart total never moves the qty either. */
    public function test_a_cart_total_charge_leaves_the_basket_alone(): void
    {
        $this->doorClosed();
        $sale = $this->trade();
        // No verdict at all: since 2026-10-10 nothing charges such a hold on its own (the 72 h
        // backstop is gone) — a person keys in the charge on AI Recognition, here the cart.
        $this->chargedByPerson(760);

        $this->assertSame(760, (int) $sale->fresh()->amount);
        $this->assertSame(760, $this->countedSales());
        $this->assertSame(2, $this->countedQty(), 'the cart stands, basket included');
        $this->assertArrayNotHasKey('basket_charged', $sale->fresh()->meta_json['audit']);
    }

    public function test_nothing_taken_releases_the_hold_and_the_row_stops_being_a_sale(): void
    {
        $this->doorClosed();
        $sale = $this->trade();
        $this->verdict('took_less', 0, $sale);

        $this->service->reconcile();

        $sale->refresh();
        $this->assertSame(0, (int) $sale->amount);
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $sale->settlement_status,
            'a released hold collected nothing, so the row is never a sale');
        $this->assertNull($sale->audit_pending_at);
        $this->assertSame(0, $this->countedSales());
        $this->assertSame(CardPaymentIntent::STATE_VOIDED, CardPaymentIntent::sole()->state);
    }

    public function test_a_trade_arriving_after_its_charge_was_decided_is_never_left_pending(): void
    {
        $this->doorClosed();
        // No sale yet (the board is offline), so no verdict can be judged: a person keys in the
        // cart and the hold is closed before the TRADE ever lands.
        $this->chargedByPerson(760);
        $this->assertSame(CardPaymentIntent::STATE_CAPTURED, CardPaymentIntent::sole()->state);

        $sale = $this->trade();

        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, (int) $sale->settlement_status,
            'nothing left to wait for, so the sale must not be held');
        $this->assertNull($sale->audit_pending_at);
        $this->assertSame(760, $this->countedSales());
        $this->assertSame('smart_freezer_ai', $sale->meta_json['audit']['source']);
        // The ingest writes the cart's item rows AFTER this hook, so a basket written here would
        // be buried under them — two units paid for must still be exactly two rows.
        $this->assertSame(2, VendTransactionItem::where('vend_transaction_id', $sale->id)->count());
        $this->assertSame(2, $this->countedQty());
    }

    public function test_the_sweep_settles_a_held_sale_whose_charge_is_already_decided(): void
    {
        $this->doorClosed();
        $sale = $this->trade();
        $this->verdict('took_less', 1, $sale);
        $this->service->reconcile();
        $this->assertSame(380, (int) $sale->fresh()->amount);

        // The write that settles a sale can fail on its own, and a TRADE can land after its
        // charge was decided. Either way the row sits PENDING with nothing left to wait for.
        $sale->forceFill(['settlement_status' => VendTransaction::SETTLEMENT_PENDING,
            'audit_pending_at' => Carbon::now(), 'amount' => 760])->save();

        $this->service->reconcile();

        $this->assertSame(380, (int) $sale->fresh()->amount, 'the sweep puts the charged amount back');
        $this->assertNull($sale->fresh()->audit_pending_at);
        $this->assertSame(380, $this->countedSales());
    }

    /**
     * Happy Hour after an audit (merge onto main, 2026-10-28): the rebuilt basket's rows keep the
     * slot tag, and a slot totalled while the sale was held (PENDING is not counted) re-opens so
     * the minute run counts the sale.
     */
    public function test_a_settled_sale_is_tagged_again_and_reopens_its_happy_hour_totals(): void
    {
        $campaign = \App\Models\HappyHourCampaign::create(['name' => 'HH', 'operator_id' => 1,
            'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 24]);
        $slot = \App\Models\HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $this->vend->id, 'product_id' => $this->cheesecake->id,
            'slot_date' => Carbon::now()->toDateString(), 'position' => 0,
            'starts_at' => Carbon::now()->subMinutes(30), 'ends_at' => Carbon::now()->addMinutes(30),
            'original_price' => 500, 'promo_price' => 380, 'discount_pct' => 24, 'rank' => 1,
        ]);

        $this->doorClosed();
        $sale = $this->trade();
        $this->assertSame($slot->id, (int) $sale->fresh()->happy_hour_slot_id, 'tagged at ingest');
        // The slot closed and was totalled while the sale was held: 0, the held sale left out.
        $slot->forceFill(['units_sold' => 0])->save();

        $this->verdict('took_less', 1, $sale);
        $this->service->reconcile();

        $items = VendTransactionItem::where('vend_transaction_id', $sale->id)->get();
        $this->assertCount(1, $items, 'the basket follows the charge');
        $this->assertSame([$slot->id], $items->pluck('happy_hour_slot_id')->map(fn ($v) => (int) $v)->all(),
            'the rebuilt row is tagged again');
        $this->assertNull($slot->fresh()->units_sold, 're-opened, so the minute run recounts it');
    }

    public function test_a_sale_the_refund_path_owns_is_never_held(): void
    {
        $this->doorClosed();
        $sale = $this->trade();

        // A refunded row, or one left PENDING for the refund path by a dispense failure, is not
        // the audit's to move — its amount and status belong to that path.
        $sale->forceFill(['settlement_status' => VendTransaction::SETTLEMENT_REFUNDED, 'audit_pending_at' => null])->save();
        app(FreezerSaleAudit::class)->record($this->vend->fresh(), $sale->fresh());

        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, (int) $sale->fresh()->settlement_status);
        $this->assertNull($sale->fresh()->audit_pending_at);
    }

    public function test_a_held_sale_still_shows_the_machines_own_dispense_verdict(): void
    {
        $this->doorClosed();
        $sale = $this->trade(units: 1);

        // PENDING also means "a gateway row still waiting for its TRADE", which renders blank.
        // This row's TRADE is in, so its verdict must still show.
        $facts = SaleFacts::fromRow($sale->fresh()->loadMissing('vendChannelError'));
        $this->assertTrue($facts->isAwaitingAudit);
        $this->assertNotSame(SaleStatus::REASON_NO_TRADE, SaleStatus::dispenseReason($facts));
    }
}
