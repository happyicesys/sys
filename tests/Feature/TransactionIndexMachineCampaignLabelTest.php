<?php

namespace Tests\Feature;

use App\Models\HappyHourCampaign;
use App\Models\Operator;
use App\Models\User;
use App\Models\VendTransaction;
use App\Services\Sales\MachineCampaignLabels;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sales Transactions → the label under Site: what the machine runs NOW, as on the Operation
 * Dashboard — yellow "HappyHour" or pink UI Setting campaign names (MachineCampaignLabels).
 */
class TransactionIndexMachineCampaignLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_machine_shows_happy_hour_or_its_ui_setting_campaigns(): void
    {
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $customerId = DB::table('customers')->insertGetId(['name' => 'Site', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $freezer = fn (int $code, int $app) => DB::table('vends')->insertGetId([
            'code' => $code, 'name' => "Freezer {$code}", 'operator_id' => $operator->id, 'customer_id' => $customerId,
            'machine_type' => 'smart_freezer', 'apk_version_code' => $app, 'is_active' => 1, 'is_testing' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $running = $freezer(50001, 30);   // in Happy Hour, no UI Setting
        $uiBound = $freezer(50002, 30);   // in Happy Hour, but bound to a UI Setting
        $oldApp = $freezer(50003, 20);    // in Happy Hour, app too old to run it
        $plain = $freezer(50004, 30);     // nothing

        $hh = HappyHourCampaign::create(['name' => 'HH 30% - Default', 'operator_id' => $operator->id, 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 30]);
        $hh->vends()->attach([$running, $uiBound, $oldApp]);
        // A paused campaign puts nobody in Happy Hour.
        HappyHourCampaign::create(['name' => 'Paused', 'operator_id' => $operator->id, 'window_start' => '00:00:00', 'window_end' => '23:59:00', 'discount_pct' => 20, 'is_active' => false])
            ->vends()->attach([$plain]);

        $settingId = DB::table('apk_settings')->insertGetId(['name' => 'Rocket Yog', 'settings_parameter_json' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('apk_setting_vend')->insert(['apk_setting_id' => $settingId, 'vend_id' => $uiBound, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['3 Cornetto at $12', true, now()->addMonth()], ['2 Magnum at $10', true, null], ['Expired', true, now()->subDays(2)], ['Switched off', false, null]] as [$name, $active, $end]) {
            $campaignId = DB::table('campaigns')->insertGetId(['name' => $name, 'is_active' => $active, 'end_at' => $end, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('apk_setting_campaign')->insert(['apk_setting_id' => $settingId, 'campaign_id' => $campaignId, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->assertSame([
            $running => ['happy_hour' => true, 'campaigns' => []],
            $uiBound => ['happy_hour' => false, 'campaigns' => ['2 Magnum at $10', '3 Cornetto at $12']],
            $oldApp => ['happy_hour' => false, 'campaigns' => []],
            $plain => ['happy_hour' => false, 'campaigns' => []],
        ], app(MachineCampaignLabels::class)->forVends([$running, $uiBound, $oldApp, $plain, null]));

        // The page carries it on every row of that machine.
        foreach ([$running => 'A', $uiBound => 'B'] as $vendId => $orderId) {
            VendTransaction::create([
                'order_id' => $orderId, 'vend_id' => $vendId, 'operator_id' => $operator->id, 'transaction_datetime' => now(),
                'amount' => 300, 'qty' => 1, 'vend_channel_code' => 11, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
                'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
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
                    $rows[$row['order_id']] = $row['machine_campaigns'];
                }
            });

        $this->assertSame(['happy_hour' => true, 'campaigns' => []], $rows['A']);
        $this->assertSame(['happy_hour' => false, 'campaigns' => ['2 Magnum at $10', '3 Cornetto at $12']], $rows['B']);
    }
}
