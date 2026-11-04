<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Profile;
use App\Models\RemoteCardTerminal;
use App\Models\SmartFreezerRecognition;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardTerminal\CardPaymentService;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalTransaction as T;
use App\Services\SmartFreezer\FreezerReceipt;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * The live tax invoice behind the freezer's thank-you QR (Brian, 2026-11-02): a T05 sale shows
 * "calculating" until the AI-judged charge is decided, then what was actually charged — badged
 * where the customer took more or less than they paid for; PayNow / NETS is final at once.
 */
class FreezerReceiptTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION = 'SF-50093-1791100000-7';

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

        $this->cheesecake = Product::create(['code' => 'CC-01', 'name' => 'Basque Cheesecake']);
        $this->cheesecake->forceFill(['barcode' => '9726436016148'])->save();
        $mapping = ProductMapping::create(['name' => 'Freezer', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_smart' => true, 'is_active' => true, 'operator_id' => 1]);
        ProductMappingItem::create(['product_mapping_id' => $mapping->id, 'channel_code' => '41', 'product_id' => $this->cheesecake->id]);
        $customer = Customer::create(['name' => 'Freezer Site', 'code' => 'FS3', 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);

        $this->vend = Vend::create(['code' => 50093, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'customer_id' => $customer->id, 'product_mapping_id' => $mapping->id]);
        $this->vend->forceFill(['private_key' => 'vend-key-50093'])->save();
        VendChannel::create(['vend_id' => $this->vend->id, 'code' => 41, 'product_id' => $this->cheesecake->id,
            'qty' => 5, 'capacity' => 5, 'amount' => 380, 'is_active' => 1]);
        RemoteCardTerminal::create(['vend_id' => $this->vend->id, 'provider' => RemoteCardTerminal::PROVIDER_PAYRALLEL,
            'access_token' => 'tok-50093', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function link(?string $signature = null): string
    {
        return '/r/50093/'.self::SESSION.'?s='.($signature ?? FreezerReceipt::signature($this->vend->fresh(), self::SESSION));
    }

    private function doorClosed(int $cents): CardPaymentIntent
    {
        $intent = $this->service->authorize($this->vend, 'SFB1', $cents);
        $this->gateway->answer('50093-SFB1', T::APPROVED);

        return $this->service->capture($this->service->refresh($intent), $cents, self::SESSION);
    }

    private function trade(int $units, int $paymentType = 2): VendTransaction
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), [
            'ORDRID' => '2610051200'.$units.$paymentType, 'PAY_TYPE' => $paymentType, 'TIME' => Carbon::now()->format('Y-m-d H:i:s'),
            'SErr' => 0, 'SId' => 41, 'Price' => number_format(3.80 * $units, 2, '.', ''), 'TXN_SRC' => $paymentType === 2 ? 1 : 0,
            'SFREF' => self::SESSION,
            'transf_info' => array_fill(0, $units, ['SId' => 41, 'SErr' => 0, 'Price' => 380, 'goods_id' => $this->cheesecake->id]),
        ]);

        return VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->orderByDesc('id')->firstOrFail();
    }

    private function verdict(string $verdict, int $paid, int $taken, VendTransaction $sale): void
    {
        SmartFreezerRecognition::query()->create([
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-'.uniqid(), 'session_ref' => self::SESSION,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'vend_transaction_id' => $sale->id,
            'verdict' => $verdict, 'completed_at' => Carbon::now(), 'callback_verified' => true,
            'verdict_lines' => [[
                'product_id' => $this->cheesecake->id, 'code' => $this->cheesecake->barcode,
                'paid' => $paid, 'taken' => $taken, 'delta' => $taken - $paid,
            ]],
        ]);
    }

    /** The sweep leaves a mismatch under review; an admin then charges the AI's count. */
    private function personChargesTheAiCount(): void
    {
        $this->service->reconcile();
        $this->assertSame(FreezerReceipt::REVIEW, $this->receipt()['status']);
        $intent = CardPaymentIntent::query()->where('session_ref', self::SESSION)->firstOrFail();
        $this->service->chargeByPerson($intent, $this->service->personSuggestedCents($intent), \App\Models\User::factory()->create());
    }

    private function receipt(): array
    {
        return app(FreezerReceipt::class)->state($this->vend->fresh(), self::SESSION);
    }

    public function test_a_wrong_or_missing_signature_is_a_plain_404(): void
    {
        $this->get($this->link('0000'))->assertNotFound();
        $this->get('/r/50093/'.self::SESSION)->assertNotFound();
        $this->get('/r/50093/SF-50001-1791100000-7?s='.FreezerReceipt::signature($this->vend, 'SF-50001-1791100000-7'))
            ->assertNotFound();
        $this->assertSame(0, DB::table('freezer_receipts')->count());
    }

    public function test_a_paynow_or_nets_sale_is_final_at_once(): void
    {
        $this->trade(units: 2, paymentType: 0);

        $r = $this->receipt();
        $this->assertSame(FreezerReceipt::FINAL, $r['status']);
        $this->assertSame(760, $r['total_cents']);
        $this->assertSame([['name' => 'Basque Cheesecake', 'qty' => 2, 'unit_cents' => 380, 'amount_cents' => 760, 'badge' => null]], $r['lines']);
        $this->assertNull($r['hold_cents']);

        $this->get($this->link())->assertOk()->assertSee('Basque Cheesecake')->assertSee('$7.60')->assertDontSee('Updating live');
    }

    public function test_a_t05_hold_shows_calculating_until_the_ai_decides(): void
    {
        $this->doorClosed(380);
        $this->trade(units: 1);

        $this->assertSame(FreezerReceipt::CALCULATING, $this->receipt()['status']);
        $this->get($this->link())->assertOk()->assertSee('Checking what you took')->assertSee('$3.80')->assertSee('Updating live');
    }

    public function test_an_answer_without_a_verdict_is_shown_as_under_review(): void
    {
        $this->doorClosed(380);
        $sale = $this->trade(units: 1);
        SmartFreezerRecognition::query()->create([
            'vend_id' => $this->vend->id, 'trade_id' => 'SDK-x', 'session_ref' => self::SESSION,
            'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'vend_transaction_id' => $sale->id,
            'order_status' => 501, 'completed_at' => Carbon::now(),
        ]);

        $this->assertSame(FreezerReceipt::REVIEW, $this->receipt()['status']);
    }

    public function test_taking_more_is_charged_and_badged(): void
    {
        $this->doorClosed(380);
        $sale = $this->trade(units: 1);
        $this->verdict('took_more', 1, 2, $sale);
        $this->personChargesTheAiCount();

        $r = $this->receipt();
        $this->assertSame(FreezerReceipt::FINAL, $r['status']);
        $this->assertSame(760, $r['total_cents']);
        $this->assertSame(380, $r['hold_cents']);
        $this->assertSame('1 taken extra', $r['lines'][0]['badge']);
        $this->assertSame(2, $r['lines'][0]['qty']);

        $this->get($this->link())->assertOk()->assertSee('1 taken extra')
            ->assertSee('You took more than you paid for at the screen', false);
    }

    public function test_a_paid_unit_left_behind_is_listed_and_not_charged(): void
    {
        $this->doorClosed(760);
        $sale = $this->trade(units: 2);
        $this->verdict('took_less', 2, 1, $sale);
        $this->personChargesTheAiCount();

        $r = $this->receipt();
        $this->assertSame(FreezerReceipt::FINAL, $r['status']);
        $this->assertSame(380, $r['total_cents']);
        $this->assertSame([1, 1], array_column($r['lines'], 'qty'));
        $this->assertSame('Not taken – not charged', $r['lines'][1]['badge']);
        $this->assertSame(0, $r['lines'][1]['amount_cents']);
    }

    public function test_nothing_taken_says_so_and_charges_nothing(): void
    {
        $this->doorClosed(380);
        $sale = $this->trade(units: 1);
        $this->verdict('took_less', 1, 0, $sale);
        $this->service->reconcile();

        $this->assertSame(FreezerReceipt::NOTHING, $this->receipt()['status']);
        $this->get($this->link())->assertOk()->assertSee("You didn't take anything", false)->assertSee('not charging you anything');
    }

    public function test_gst_is_shown_inside_the_total(): void
    {
        $this->trade(units: 2, paymentType: 0);
        VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->update(['gst_vat_rate' => 9]);

        $r = $this->receipt();
        $this->assertSame(697, $r['subtotal_cents']);
        $this->assertSame(63, $r['gst_cents']);
    }

    public function test_it_is_a_tax_invoice_only_with_a_gst_number_on_the_operators_company(): void
    {
        $this->trade(units: 1, paymentType: 0);
        $this->get($this->link())->assertOk()->assertSee('Receipt')->assertDontSee('Tax Invoice');

        $profile = Profile::create(['name' => 'HAPPY ICE PTE LTD', 'uen' => '201302530W', 'gst_reg_no' => '201302530W']);
        \App\Models\Operator::query()->updateOrCreate(['id' => 1], ['code' => 'HIPL', 'name' => 'HI SG', 'profile_id' => $profile->id]);

        $this->get($this->link())->assertOk()->assertSee('Tax Invoice')->assertSee('GST Reg No. 201302530W');
    }

    public function test_a_link_keeps_working_after_the_freezer_key_changes(): void
    {
        $this->trade(units: 1, paymentType: 0);
        $link = $this->link();
        $this->get($link)->assertOk();

        $this->vend->forceFill(['private_key' => 'rotated-key'])->save();

        $this->get($link)->assertOk();
        $this->assertSame(2, (int) DB::table('freezer_receipts')->value('open_count'));
    }

    public function test_the_state_endpoint_moves_when_the_charge_is_decided(): void
    {
        $this->doorClosed(380);
        $sale = $this->trade(units: 1);
        $stateUrl = '/r/50093/'.self::SESSION.'/state?s='.FreezerReceipt::signature($this->vend, self::SESSION);

        $before = $this->getJson($stateUrl)->assertOk()->assertJson(['status' => 'calculating'])->json('version');
        $this->verdict('match', 1, 1, $sale);
        $this->service->reconcile();
        $after = $this->getJson($stateUrl)->assertOk()->assertJson(['status' => 'final'])->json('version');

        $this->assertNotSame($before, $after);
        $this->assertSame(0, (int) DB::table('freezer_receipts')->value('open_count'), 'refreshes are not opens');
    }
}
