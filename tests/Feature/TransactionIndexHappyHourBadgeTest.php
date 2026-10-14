<?php

namespace Tests\Feature;

use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Operator;
use App\Models\User;
use App\Models\VendTransaction;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sales Transactions → Price Type "★ HH" badge: the Happy Hour slot that priced a single sale sits
 * on its row; on a multiple purchase it sits on the promo item rows only (HappyHourSales::badges).
 */
class TransactionIndexHappyHourBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_badge_names_the_campaign_slot_and_both_prices(): void
    {
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $customerId = DB::table('customers')->insertGetId(['name' => 'Freezer site', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $vendId = DB::table('vends')->insertGetId([
            'code' => 50001, 'name' => 'Freezer 50001', 'operator_id' => $operator->id, 'customer_id' => $customerId,
            'machine_type' => 'smart_freezer', 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $campaign = HappyHourCampaign::create([
            'name' => 'Clear slow movers', 'window_start' => '14:00:00', 'window_end' => '18:00:00', 'discount_pct' => 30,
        ]);
        $campaign->delete(); // a deleted campaign still names its past sales
        $slot = HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $vendId, 'product_id' => 7, 'slot_date' => now()->toDateString(),
            'position' => 0, 'starts_at' => now()->setTime(14, 0), 'ends_at' => now()->setTime(15, 0),
            'original_price' => 400, 'promo_price' => 280, 'discount_pct' => 30, 'rank' => 1,
        ]);

        $sale = fn (string $orderId, array $extra = []) => VendTransaction::create(array_merge([
            'order_id' => $orderId, 'vend_id' => $vendId, 'operator_id' => $operator->id, 'transaction_datetime' => now(),
            'amount' => 280, 'qty' => 1, 'vend_channel_code' => 41, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ], $extra));
        $single = $sale('SINGLE');
        $basket = $sale('BASKET', ['is_multiple' => 1, 'qty' => 2, 'amount' => 580]);
        $sale('PLAIN', ['amount' => 400]);
        DB::table('vend_transactions')->whereIn('id', [$single->id, $basket->id])->update(['happy_hour_slot_id' => $slot->id]);
        foreach ([[280, $slot->id], [300, null]] as [$cents, $slotId]) {
            DB::table('vend_transaction_items')->insert([
                'vend_transaction_id' => $basket->id, 'product_id' => 7, 'unit_price_amount' => $cents, 'vend_channel_id' => 0,
                'vend_channel_code' => '41', 'happy_hour_slot_id' => $slotId, 'created_at' => now(),
            ]);
        }

        Permission::findOrCreate('read transactions', 'web');
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo('read transactions');
        OperatorScope::flush();
        $rows = [];
        $this->actingAs($user)
            ->get('/vends/transactions?'.http_build_query([
                'date_from' => now()->subDay()->toDateTimeString(), 'date_to' => now()->addDay()->toDateTimeString(), 'operators' => [$operator->id],
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$rows) {
                foreach ($page->toArray()['props']['vendTransactions']['data'] as $row) {
                    $rows[$row['order_id']] = $row;
                }
            });

        $this->assertSame([
            'slot_id' => $slot->id, 'campaign' => 'Clear slow movers', 'discount_pct' => 30, 'label' => 'HH 30%',
            'original_price' => 400, 'promo_price' => 280, 'slot' => '14:00–15:00',
        ], $rows['SINGLE']['happy_hour']);
        $this->assertNull($rows['BASKET']['happy_hour'], 'a basket shows it on the promo line, not the header');
        $this->assertSame([$slot->id, null], array_map(fn ($i) => $i['happy_hour']['slot_id'] ?? null, $rows['BASKET']['vendTransactionItems']));
        $this->assertNull($rows['PLAIN']['happy_hour']);
    }

    public function test_the_campaign_label_filter_finds_happy_hour_sales_by_discount_and_under_any(): void
    {
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $customerId = DB::table('customers')->insertGetId(['name' => 'Site', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $vendId = DB::table('vends')->insertGetId([
            'code' => 2003, 'name' => 'VM 2003', 'operator_id' => $operator->id, 'customer_id' => $customerId,
            'machine_type' => 'vending_machine', 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $campaign = HappyHourCampaign::create(['name' => 'All machines', 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30]);
        $slot = fn (int $pct, int $product) => HappyHourSlot::create([
            'happy_hour_campaign_id' => $campaign->id, 'vend_id' => $vendId, 'product_id' => $product, 'slot_date' => now()->toDateString(),
            'position' => 0, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
            'original_price' => 400, 'promo_price' => 400 * (100 - $pct) / 100, 'discount_pct' => $pct, 'rank' => 1,
        ]);
        $thirty = $slot(30, 1);
        $twenty = $slot(20, 2);
        $sale = fn (string $orderId, array $extra = []) => VendTransaction::create(array_merge([
            'order_id' => $orderId, 'vend_id' => $vendId, 'operator_id' => $operator->id, 'transaction_datetime' => now(),
            'amount' => 280, 'qty' => 1, 'vend_channel_code' => 11, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ], $extra));
        $hh30 = $sale('HH30');
        $hh20 = $sale('HH20');
        $sale('PLAIN');
        $labelled = $sale('LABELLED', ['label_json' => [999]]);
        DB::table('vend_transactions')->where('id', $hh30->id)->update(['happy_hour_slot_id' => $thirty->id]);
        DB::table('vend_transactions')->where('id', $hh20->id)->update(['happy_hour_slot_id' => $twenty->id]);

        Permission::findOrCreate('read transactions', 'web');
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo('read transactions');
        OperatorScope::flush();
        $orders = function (string $tag) use ($user, $operator) {
            $found = [];
            $this->actingAs($user)
                ->get('/vends/transactions?'.http_build_query([
                    'date_from' => now()->subDay()->toDateTimeString(), 'date_to' => now()->addDay()->toDateTimeString(),
                    'operators' => [$operator->id], 'tag' => $tag,
                ]))
                ->assertOk()
                ->assertInertia(function (AssertableInertia $page) use (&$found) {
                    $found = collect($page->toArray()['props']['vendTransactions']['data'])->pluck('order_id')->sort()->values()->all();
                });

            return $found;
        };

        $this->assertSame(['HH20', 'HH30'], $orders('hh'));
        $this->assertSame(['HH30'], $orders('hh:30'));
        $this->assertSame(['HH20'], $orders('hh:20'));
        $this->assertSame([], $orders('hh:50'));
        $this->assertSame(['HH20', 'HH30', 'LABELLED'], $orders('any'), 'Any = a product label OR Happy Hour');
        $this->assertSame(['HH20', 'HH30', 'LABELLED', 'PLAIN'], $orders('all'));

        // The CSV / Excel exports put the same words in their Campaign Labels column.
        $this->assertSame([$thirty->id => 'HH 30%', $twenty->id => 'HH 20%'],
            \App\Support\Sales\CampaignLabelFilter::happyHourLabels([$thirty->id, $twenty->id, null]));

        // The options carry Happy Hour, one per discount a slot ran at.
        $this->actingAs($user)->get('/vends/transactions?'.http_build_query(['date_from' => now()->subDay()->toDateTimeString(), 'date_to' => now()->addDay()->toDateTimeString(), 'operators' => [$operator->id]]))
            ->assertInertia(fn ($page) => $page->where('happyHourLabelOptions', [
                ['id' => 'hh', 'name' => '★ Happy Hour (all)'],
                ['id' => 'hh:20', 'name' => '★ HH 20%'],
                ['id' => 'hh:30', 'name' => '★ HH 30%'],
            ]));
    }
}
