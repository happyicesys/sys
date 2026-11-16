<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Models\CustomerSettlement;
use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Services\Commission\RemovedSiteLocationFeeAccrual;
use App\Services\CustomerSummaryAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A removed site's last month may be locked early; its location fee is then
 * posted to Payment History at once instead of on the 1st of the next month
 * (Brian, 2026-11-16: Micasa Condo's Nov waiver had no Nov charge to cancel).
 */
class RemovedSiteLocationFeeAccrualTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 15:00:00');
        $this->operator = Operator::create(['code' => 'HIPL', 'name' => 'Happy Ice', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function site(?string $removed = '2026-11-11'): Customer
    {
        return Customer::create([
            'code' => 'C'.random_int(1000, 9999), 'name' => 'Micasa Condo', 'is_active' => true,
            'operator_id' => $this->operator->id, 'removed_date' => $removed,
        ]);
    }

    private function row(Customer $c, array $over = []): CustomerPeriodSummary
    {
        return CustomerPeriodSummary::create(array_merge([
            'customer_id' => $c->id,
            'operator_id' => $this->operator->id,
            'year_month' => '2026-11-01',
            'is_current_month' => true,
            'location_fees_cents' => 517,
            'external_subsidize_cents' => 0,
            'locked_at' => now(),
            'is_locked' => true,
        ], $over));
    }

    private function locFees(Customer $c)
    {
        return CustomerSettlement::where('customer_id', $c->id)
            ->where('entry_type', CustomerSettlement::TYPE_LOCATION_FEE)->get();
    }

    public function test_locked_removed_month_posts_its_location_fee_on_the_removed_date()
    {
        $c = $this->site();
        $row = app(RemovedSiteLocationFeeAccrual::class)->accrue($this->row($c)->load('customer'));

        $this->assertNotNull($row);
        $this->assertSame(517, $row->amount_cents);
        $this->assertSame('2026-11-11', $row->entry_date->toDateString());
        $this->assertSame('Location Fees November 2026', $row->item);
        $this->assertSame(CustomerSettlement::SOURCE_REMOVED_LOCK, $row->source);
        $this->assertStringStartsWith('LF-', $row->fresh()->reference_no);
    }

    public function test_posts_once_and_the_monthly_job_then_skips_the_site()
    {
        $c = $this->site();
        $s = $this->row($c)->load('customer');
        app(RemovedSiteLocationFeeAccrual::class)->accrue($s);
        $this->assertNull(app(RemovedSiteLocationFeeAccrual::class)->accrue($s));

        Carbon::setTestNow('2026-12-01 02:30:00');
        $this->artisan('settlement:generate-monthly', ['--customer' => $c->id])->assertSuccessful();

        $this->assertCount(1, $this->locFees($c));
    }

    public function test_waits_for_every_row_of_a_split_month_and_sums_them()
    {
        $c = $this->site();
        $a = $this->row($c, ['location_fees_cents' => 300, 'period_start' => '2026-11-01', 'period_end' => '2026-11-05']);
        $b = $this->row($c, ['location_fees_cents' => 200, 'external_subsidize_cents' => 50, 'period_start' => '2026-11-06', 'period_end' => '2026-11-11', 'locked_at' => null, 'is_locked' => false]);

        $this->assertNull(app(RemovedSiteLocationFeeAccrual::class)->accrue($a->load('customer')));

        $b->update(['locked_at' => now(), 'is_locked' => true]);
        $row = app(RemovedSiteLocationFeeAccrual::class)->accrue($b->load('customer'));
        $this->assertSame(450, $row->amount_cents);
    }

    public function test_nothing_owed_posts_nothing()
    {
        $c = $this->site();
        $this->assertNull(app(RemovedSiteLocationFeeAccrual::class)->accrue($this->row($c, ['location_fees_cents' => 0])->load('customer')));
        $this->assertCount(0, $this->locFees($c));
    }

    public function test_lock_and_unlock_endpoints_post_and_take_back_the_charge()
    {
        $c = $this->site();
        $c->forceFill([
            'contract_commission_type' => CustomerSummaryAggregator::CONTRACT_TYPE_RENTAL,
            'contract_commission_value' => 150,
            'active_date' => '2026-01-01',
        ])->save();
        $s = $this->row($c, ['locked_at' => null, 'is_locked' => false]);
        $user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        $user->givePermissionTo(Permission::findOrCreate('admin-access customers', 'web'));
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        $this->actingAs($user)->post("/customers/summary/{$s->id}/lock")->assertSessionHasNoErrors();
        $this->assertNotNull($s->fresh()->locked_at);
        $this->assertCount(1, $this->locFees($c));
        // The charge is the figure the lock froze (rental prorated to the Removed Date).
        $this->assertSame($s->fresh()->location_fees_cents, $this->locFees($c)->first()->amount_cents);
        $this->assertGreaterThan(0, $this->locFees($c)->first()->amount_cents);

        $this->actingAs($user)->post("/customers/summary/{$s->id}/unlock")->assertSessionHasNoErrors();
        $this->assertCount(0, $this->locFees($c));
    }

    public function test_unlock_after_the_month_ended_keeps_the_charge()
    {
        $c = $this->site();
        $s = $this->row($c)->load('customer');
        app(RemovedSiteLocationFeeAccrual::class)->accrue($s);

        Carbon::setTestNow('2026-12-02 10:00:00');
        $this->assertSame(0, app(RemovedSiteLocationFeeAccrual::class)->retract($s));
        $this->assertCount(1, $this->locFees($c));
    }

    public function test_backfill_command_posts_already_locked_removed_months()
    {
        $c = $this->site();
        $this->row($c);
        $other = $this->site(null);
        $this->row($other);

        $this->artisan('settlement:accrue-removed-locked')->assertSuccessful();
        $this->assertCount(0, $this->locFees($c));

        $this->artisan('settlement:accrue-removed-locked', ['--apply' => true])->assertSuccessful();
        $this->assertCount(1, $this->locFees($c));
        $this->assertCount(0, $this->locFees($other));
    }
}
