<?php

namespace Tests\Feature;

use App\Models\HappyHourSlot;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\OpsDashboard\MachineHappyHourTrend;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A machine's Happy Hour trend on the Operation Dashboard (Brian, 2026-10-23): daily HH sold qty (L30d / L7d averaged
 * over the days it ran Happy Hour) and its L7d Non-HH % — the campaign page's formula, in $. Pinned: units and the
 * related sales follow the campaign rules, a cancelled slot is not a day it ran, a machine with no slot has no trend.
 */
class MachineHappyHourTrendTest extends TestCase
{
    use RefreshDatabase;

    private int $campaignId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-22 15:00:00');
        $this->campaignId = DB::table('happy_hour_campaigns')->insertGetId([
            'name' => 'Default 30%', 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(int $code): Vend
    {
        $vend = new Vend;
        $vend->forceFill(['code' => $code, 'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE, 'is_active' => 1, 'operator_id' => 1])->save();

        return $vend;
    }

    private function slot(Vend $vend, string $startsAt, string $status = HappyHourSlot::STATUS_ENDED): int
    {
        return DB::table('happy_hour_slots')->insertGetId([
            'happy_hour_campaign_id' => $this->campaignId, 'vend_id' => $vend->id, 'product_id' => 1,
            'slot_date' => substr($startsAt, 0, 10), 'position' => 0, 'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addHour(), 'original_price' => 300, 'promo_price' => 200,
            'discount_pct' => 30, 'rank' => 1, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function single(Vend $vend, int $slotId, string $at, int $cents = 200): void
    {
        DB::table('vend_transactions')->insert([
            'vend_id' => $vend->id, 'operator_id' => 1, 'order_id' => uniqid('H'), 'amount' => $cents, 'qty' => 1,
            'is_multiple' => 0, 'happy_hour_slot_id' => $slotId, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'is_refunded' => 0, 'transaction_datetime' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** A basket holding one Happy Hour unit and one ordinary item, counted whole as related sales. */
    private function basket(Vend $vend, int $slotId, string $at): void
    {
        $id = DB::table('vend_transactions')->insertGetId([
            'vend_id' => $vend->id, 'operator_id' => 1, 'order_id' => uniqid('B'), 'amount' => 500, 'qty' => 2,
            'is_multiple' => 1, 'happy_hour_slot_id' => $slotId, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'is_refunded' => 0, 'transaction_datetime' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ([[200, $slotId], [300, null]] as [$price, $slot]) {
            DB::table('vend_transaction_items')->insert([
                'vend_transaction_id' => $id, 'vend_channel_code' => 11, 'vend_channel_error_code' => 0,
                'unit_price_amount' => $price, 'happy_hour_slot_id' => $slot, 'is_refunded' => 0,
                'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    public function test_daily_qty_and_non_hh_share_follow_the_campaign_rules(): void
    {
        $vend = $this->vend(2699);
        $today = $this->slot($vend, '2026-10-22 10:00:00');
        $yesterday = $this->slot($vend, '2026-10-21 10:00:00');
        $fiveDays = $this->slot($vend, '2026-10-17 10:00:00');
        $twentyDays = $this->slot($vend, '2026-10-02 10:00:00');
        $this->slot($vend, '2026-10-19 10:00:00', HappyHourSlot::STATUS_CANCELLED); // never ran

        $this->single($vend, $today, '2026-10-22 10:10:00');
        $this->basket($vend, $today, '2026-10-22 10:20:00');
        $this->single($vend, $yesterday, '2026-10-21 10:10:00');
        $this->single($vend, $fiveDays, '2026-10-17 10:10:00');
        $this->single($vend, $fiveDays, '2026-10-17 10:20:00');
        $this->single($vend, $twentyDays, '2026-10-02 10:10:00');

        $trend = app(MachineHappyHourTrend::class)->forVend($vend->id);

        $this->assertSame(['today' => 2, 'yesterday' => 1, 'seven_days' => 5, 'thirty_days' => 6], $trend['units']);
        $this->assertSame(['today' => 1, 'yesterday' => 1, 'seven_days' => 3, 'thirty_days' => 4], $trend['ran_days'], 'a cancelled slot is not a day it ran');
        $this->assertSame(['today' => 2, 'yesterday' => 1, 'seven_days' => 1.7, 'thirty_days' => 1.5], $trend['daily_units']);
        // HH $10.00 (four singles + the basket's promo unit); related $13.00 (the singles + the whole basket).
        $this->assertSame(1000, $trend['hh_revenue_7d']);
        $this->assertSame(1300, $trend['related_revenue_7d']);
        $this->assertSame(23.1, $trend['non_hh_pct_7d']);
    }

    public function test_a_day_without_happy_hour_breaks_the_line_and_no_slot_means_no_trend(): void
    {
        $vend = $this->vend(2700);
        $this->single($vend, $this->slot($vend, '2026-10-21 10:00:00'), '2026-10-21 10:10:00');

        $trend = app(MachineHappyHourTrend::class)->forVend($vend->id);

        $this->assertNull($trend['daily_units']['today'], 'no Happy Hour today: no point, not a zero');
        $this->assertSame(1, $trend['daily_units']['yesterday']);
        $this->assertSame(0.0, $trend['non_hh_pct_7d'], 'a single HH sale is all Happy Hour');
        $this->assertNull(app(MachineHappyHourTrend::class)->forVend($this->vend(2701)->id));
    }
}
