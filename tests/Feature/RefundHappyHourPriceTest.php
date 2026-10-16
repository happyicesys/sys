<?php

namespace Tests\Feature;

use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Operator;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Services\Refund\RefundItemPrice;
use App\Services\Refund\RefundTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A refund claim on a Happy Hour line pays back what the customer paid, not the list price
 * (Brian, 2026-10-16, RF-261016005: 2 × $2.40 = $4.80 paid on 2301, one unit failed, the claim read $3.40).
 */
class RefundHappyHourPriceTest extends TestCase
{
    use RefreshDatabase;

    private int $vendId;

    private int $operatorId;

    private HappyHourCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operatorId = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true])->id;
        $customerId = DB::table('customers')->insertGetId(['name' => 'Arc', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 2301, 'name' => 'VM 2301', 'operator_id' => $this->operatorId, 'customer_id' => $customerId,
            'machine_type' => 'vending_machine', 'is_active' => 1, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->campaign = HappyHourCampaign::create([
            'name' => 'Afternoon', 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30, 'price_step_cents' => 10,
        ]);
    }

    private function slot(int $original, int $promo): HappyHourSlot
    {
        return HappyHourSlot::create([
            'happy_hour_campaign_id' => $this->campaign->id, 'vend_id' => $this->vendId, 'product_id' => 868,
            'slot_date' => now()->toDateString(), 'position' => 0, 'starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30),
            'original_price' => $original, 'promo_price' => $promo, 'discount_pct' => 30, 'rank' => 1,
        ]);
    }

    private function channel(int $code, int $amount): int
    {
        return DB::table('vend_channels')->insertGetId([
            'vend_id' => $this->vendId, 'code' => $code, 'amount' => $amount, 'capacity' => 10, 'qty' => 5, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param list<array{0: int, 1: int, 2: ?int, 3: ?int}> $lines [unit price, channel id, error code, slot id] */
    private function basket(int $amount, array $lines, ?int $headerSlotId): VendTransaction
    {
        $sale = VendTransaction::create([
            'order_id' => (string) (2026101615141348476 + VendTransaction::count()), 'vend_id' => $this->vendId, 'operator_id' => $this->operatorId,
            'transaction_datetime' => now(), 'amount' => $amount, 'qty' => count($lines), 'is_multiple' => 1,
            'vend_channel_code' => 42, 'vend_channel_id' => 0, 'gst_vat_rate' => 0, 'interface_type' => 0,
            'cashless_mfg' => 'Nets', 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]);
        DB::table('vend_transactions')->where('id', $sale->id)->update(['happy_hour_slot_id' => $headerSlotId]);
        foreach ($lines as [$cents, $channelId, $errorCode, $slotId]) {
            DB::table('vend_transaction_items')->insert([
                'vend_transaction_id' => $sale->id, 'product_id' => 868, 'unit_price_amount' => $cents, 'vend_channel_id' => $channelId,
                'vend_channel_code' => '42', 'vend_channel_error_code' => $errorCode, 'happy_hour_slot_id' => $slotId, 'created_at' => now(),
            ]);
        }

        return $sale;
    }

    public function test_a_claim_on_the_failed_happy_hour_unit_is_the_promo_price(): void
    {
        $slot = $this->slot(340, 240);
        $sale = $this->basket(480, [[340, $this->channel(42, 340), 0, $slot->id], [0, DB::table('vend_channels')->value('id'), 7, $slot->id]], $slot->id);
        $failed = VendTransactionItem::where('vend_transaction_id', $sale->id)->where('vend_channel_error_code', 7)->value('id');

        $ticket = app(RefundTicketService::class)->create([
            'machineID' => '2301', 'vend_transaction_id' => $sale->id, 'selected_item_ids' => [$failed],
            'reason_code' => 'not_dispensed', 'refund_method' => 'paynow', 'payout_destination' => '96629810',
        ]);

        $this->assertSame(240, (int) $ticket->claimed_amount_cents);
        $this->assertSame([240], $ticket->items->pluck('unit_price_cents')->map(fn ($c) => (int) $c)->all());
    }

    public function test_full_price_lines_keep_their_price(): void
    {
        $channelId = $this->channel(42, 340);
        $sale = $this->basket(680, [[340, $channelId, 0, null], [0, $channelId, 7, null]], null);

        $prices = VendTransactionItem::where('vend_transaction_id', $sale->id)->get()->map(fn ($i) => RefundItemPrice::cents($i))->all();

        $this->assertSame([340, 340], $prices);
    }

    public function test_a_freezer_line_already_at_the_promo_keeps_it_and_a_board_priced_channel_takes_its_own_promo(): void
    {
        $slot = $this->slot(340, 240);
        // A freezer TRADE carries the promo it charged.
        $freezer = $this->basket(240, [[240, 0, 0, $slot->id]], $slot->id);
        // A board-price channel at $3.00 while the slot was priced off $3.40: 30% off $3.00 = $2.10.
        $board = $this->basket(210, [[0, $this->channel(43, 300), 7, $slot->id]], $slot->id);

        $price = fn (VendTransaction $s) => RefundItemPrice::cents(VendTransactionItem::where('vend_transaction_id', $s->id)->first());

        $this->assertSame(240, $price($freezer));
        $this->assertSame(210, $price($board));

        // Already repriced at tagging (HappyHourSales::repriceVendingBasket) on a channel listed above the
        // slot's original: $4.90 → $3.50 stays $3.50, never discounted twice (2848, 2026-10-16).
        $repriced = $this->basket(350, [[350, $this->channel(44, 490), 0, $slot->id]], $slot->id);
        $this->assertSame(350, $price($repriced));
    }
}
