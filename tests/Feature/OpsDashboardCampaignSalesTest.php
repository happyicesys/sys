<?php

namespace Tests\Feature;

use App\Models\HappyHourCampaign;
use App\Models\HappyHourSlot;
use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\VendTransaction;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Operation Dashboard campaign boxes (Brian, 2026-10-17): "L7d: n pcs sold" — units the machine sold
 * under each UI Setting campaign (label_json "slug(id)") and each Happy Hour campaign (tagged sales)
 * over the last 7 days (MachineCampaignSales, VendController::attachCampaignSales).
 */
class OpsDashboardCampaignSalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    public function test_each_campaign_box_counts_its_units_sold_in_the_last_7_days(): void
    {
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $operator = Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Site 2240', 'profile_id' => 1, 'status_id' => 1, 'is_active' => 1,
            'operator_id' => $operator->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vendId = DB::table('vends')->insertGetId([
            'code' => 2240, 'name' => 'Machine 2240', 'machine_type' => 'vending_machine', 'operator_id' => $operator->id,
            'customer_id' => $customerId, 'is_active' => 1, 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $settingId = DB::table('apk_settings')->insertGetId(['name' => 'Rocket Yog', 'settings_parameter_json' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $vendId, 'created_at' => now(), 'updated_at' => now()]);
        $magnum = DB::table('campaigns')->insertGetId(['name' => '2 Magnum at $10', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $cornetto = DB::table('campaigns')->insertGetId(['name' => '3 Cornetto at $12', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$magnum, $cornetto] as $campaignId) {
            DB::table('apk_setting_campaign')->insert(['apk_setting_id' => $settingId, 'campaign_id' => $campaignId, 'created_at' => now(), 'updated_at' => now()]);
        }

        $hh = HappyHourCampaign::create(['name' => 'HH 30% - Default', 'operator_id' => $operator->id, 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30]);
        $hh->vends()->attach([$vendId]);
        $slot = HappyHourSlot::create([
            'happy_hour_campaign_id' => $hh->id, 'vend_id' => $vendId, 'product_id' => 7, 'slot_date' => now()->toDateString(),
            'position' => 0, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
            'original_price' => 400, 'promo_price' => 280, 'discount_pct' => 30, 'rank' => 1,
        ]);

        $sale = function (string $orderId, array $extra = []) use ($vendId, $operator, $customerId) {
            $row = VendTransaction::create(array_merge([
                'order_id' => $orderId, 'vend_id' => $vendId, 'customer_id' => $customerId, 'operator_id' => $operator->id,
                'transaction_datetime' => now()->subDay(), 'amount' => 1000, 'qty' => 2, 'vend_channel_code' => 11, 'vend_channel_id' => 0,
                'gst_vat_rate' => 0, 'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            ], array_diff_key($extra, ['happy_hour_slot_id' => 1])));
            if (isset($extra['happy_hour_slot_id'])) {
                DB::table('vend_transactions')->where('id', $row->id)->update(['happy_hour_slot_id' => $extra['happy_hour_slot_id']]);
            }
        };
        $sale('MAGNUM', ['label_json' => ["2-magnum({$magnum})"]]);
        $sale('MAGNUM-3', ['label_json' => ["2-magnum({$magnum})"], 'qty' => 3]);
        $sale('MAGNUM-OLD', ['label_json' => ["2-magnum({$magnum})"], 'transaction_datetime' => now()->subDays(8)]);
        $sale('MAGNUM-REFUNDED', ['label_json' => ["2-magnum({$magnum})"], 'is_refunded' => 1]);
        $sale('PLAIN');
        $sale('HH', ['qty' => 1, 'amount' => 280, 'transaction_datetime' => now(), 'happy_hour_slot_id' => $slot->id]);

        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo(Permission::findOrCreate('read vend-customers', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('admin-access vend-customers', 'web'));
        OperatorScope::flush();
        $machine = null;
        $this->actingAs($user)->get('/vends/customers?autoload=1')->assertOk()
            ->assertInertia(function (Assert $page) use (&$machine) {
                $machine = $page->toArray()['props']['vends']['data'][0]['vend'];
            });

        $this->assertSame([$magnum => 5, $cornetto => 0], collect($machine['campaigns'])->pluck('l7d_units', 'id')->all());
        $this->assertSame([$hh->id => 1], collect($machine['happy_hour_campaigns'])->pluck('l7d_units', 'id')->all());
    }
}
