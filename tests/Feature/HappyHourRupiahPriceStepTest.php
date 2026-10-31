<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\HappyHourCampaign;
use App\Models\Operator;
use App\Models\User;
use App\Services\HappyHour\HappyHourPriceStep;
use App\Services\HappyHour\HappyHourPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Happy Hour rounding follows the owner's currency (Brian, 2026-10-31): a rupiah campaign rounds UP to
 * Rp 1,000 by default, offers only Rp 100 / 500 / 1,000 and never yields a decimal; cent currencies are unchanged.
 */
class HappyHourRupiahPriceStepTest extends TestCase
{
    use RefreshDatabase;

    private Operator $idr;

    private Operator $sgd;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $id = Country::create(['name' => 'Indonesia', 'code' => 'ID', 'currency_name' => 'IDR', 'currency_symbol' => 'Rp',
            'currency_exponent' => 2, 'is_currency_exponent_hidden' => true]);
        $sg = Country::create(['name' => 'Singapore', 'code' => 'SG', 'currency_name' => 'SGD', 'currency_symbol' => 'S$',
            'currency_exponent' => 2, 'is_currency_exponent_hidden' => false]);
        $this->sgd = Operator::create(['code' => 'HIPL', 'name' => 'Happy Ice', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore', 'country_id' => $sg->id]);
        $this->idr = Operator::create(['code' => 'HIID', 'name' => 'Happy Ice Indonesia', 'gst_vat_rate' => 11, 'timezone' => 'Asia/Jakarta', 'country_id' => $id->id]);
    }

    public function test_rupiah_offers_rupiah_steps_and_defaults_to_rp_1000(): void
    {
        $this->assertSame([10000, 50000, 100000], array_keys(HappyHourPriceStep::steps('IDR')));
        $this->assertSame(100000, HappyHourPriceStep::defaultStep('idr'));
        $this->assertSame(100000, HappyHourPriceStep::normalise('IDR', 10), 'a cent step becomes Rp 1,000');
        $this->assertSame(50000, HappyHourPriceStep::normalise('IDR', 50000));
        $this->assertSame(10, HappyHourPriceStep::normalise('SGD', 10), 'cent currencies keep their steps');
        $this->assertSame(10, HappyHourPriceStep::normalise('', 100000), 'an unknown currency uses the cent steps');
        $this->assertSame('IDR', HappyHourPriceStep::currencyOfOperator($this->idr->id));
    }

    public function test_a_rupiah_promo_rounds_up_to_whole_thousands(): void
    {
        $step = HappyHourPriceStep::defaultStep('IDR');
        // 33% off Rp 28,000 = Rp 18,760 -> Rp 19,000; 15% off Rp 25,000 = Rp 21,250 -> Rp 22,000.
        $this->assertSame(1900000, HappyHourPricing::promoPrice(2800000, 33, $step));
        $this->assertSame(2200000, HappyHourPricing::promoPrice(2500000, 15, $step));
        // 30% off Rp 3,000 = Rp 2,100: rounding up would remove the discount, so it rounds down to Rp 2,000.
        $this->assertSame(200000, HappyHourPricing::promoPrice(300000, 30, $step));

        foreach ([1000000, 1234500, 2700000, 3500000] as $price) {
            foreach ([5, 10, 15, 25, 33, 40, 50, 67, 90] as $pct) {
                $promo = HappyHourPricing::promoPrice($price, $pct, $step);
                if ($promo !== null) {
                    $this->assertSame(0, $promo % 100, "Rp {$price} at {$pct}% has no decimal");
                    $this->assertLessThan($price, $promo);
                }
            }
        }
    }

    public function test_saving_a_rupiah_campaign_stores_a_rupiah_step(): void
    {
        $user = $this->manager($this->idr);
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['price_step_cents' => 10]))->assertSessionHasNoErrors();
        $campaign = HappyHourCampaign::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(100000, $campaign->price_step_cents, 'a tab posting a cent step gets Rp 1,000');

        $this->actingAs($user)->post("/happy-hour-campaigns/{$campaign->id}", $this->form(['price_step_cents' => 50000]))->assertSessionHasNoErrors();
        $this->assertSame(50000, $campaign->fresh()->price_step_cents);
    }

    public function test_a_singapore_campaign_keeps_its_cent_step(): void
    {
        $user = $this->manager($this->sgd);
        $this->actingAs($user)->post('/happy-hour-campaigns', $this->form(['price_step_cents' => 10]))->assertSessionHasNoErrors();
        $this->assertSame(10, HappyHourCampaign::withoutGlobalScopes()->latest('id')->firstOrFail()->price_step_cents);
    }

    public function test_any_save_path_keeps_the_step_in_the_owners_currency(): void
    {
        $campaign = HappyHourCampaign::withoutGlobalScopes()->create([...$this->form(['price_step_cents' => 10]), 'operator_id' => $this->idr->id]);
        $this->assertSame(100000, $campaign->price_step_cents);
    }

    public function test_the_page_shows_rupiah_and_offers_rupiah_steps(): void
    {
        $this->actingAs($this->manager($this->idr))->get('/happy-hour-campaigns')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currency.symbol', 'Rp')
                ->where('currency.hidden', true)
                ->where('newPriceStep.default', 100000)
                ->where('newPriceStep.options.2.label', 'Rp 1,000'));
    }

    private function manager(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        foreach (['read', 'create', 'update', 'delete'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate("{$action} happy-hour-campaigns", 'web'));
        }

        return $user;
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rupiah test', 'is_active' => true, 'starts_on' => null, 'ends_on' => null,
            'days_mask' => HappyHourCampaign::EVERY_DAY, 'window_start' => '14:00', 'window_end' => '18:00',
            'slot_minutes' => 60, 'sku_count' => 2, 'skus_per_slot' => 1, 'selection_rule' => HappyHourCampaign::RULE_BALANCE_PCT,
            'lookback_days' => 14, 'discount_pct' => 30, 'min_balance_pct' => 15, 'min_qty' => 2,
            'price_step_cents' => 10, 'allow_below_cost' => false, 'skip_unavailable' => true, 'excluded_product_ids' => [],
            'vend_ids' => [],
        ], $overrides);
    }
}
