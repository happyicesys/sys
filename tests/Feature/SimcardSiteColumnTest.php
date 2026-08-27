<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Operator;
use App\Models\Simcard;
use App\Models\Telco;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simcard Index "Site" column (2026-08-27): the Site each bound machine sits at,
 * shown as displayed Site ID (customers.id + RUNNING_NUMBER_INIT) over the site
 * name. One entry per bound machine — including machines with no Site — so the
 * column stays line-for-line aligned with Machine ID.
 *
 * Also guards the Phone Number column's removal: the index no longer filters on
 * phone_number, so a phone_number query string must not narrow the results.
 */
class SimcardSiteColumnTest extends TestCase
{
    use RefreshDatabase;

    private function rowFor(string $simcardCode, array $query = []): ?array
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/simcards?'.http_build_query($query));
        $response->assertOk();

        $rows = $response->viewData('page')['props']['simcards']['data'];
        foreach ($rows as $row) {
            if ($row['code'] === $simcardCode) {
                return $row;
            }
        }

        return null;
    }

    private function sitesFor(string $simcardCode, array $query = []): ?array
    {
        return $this->rowFor($simcardCode, $query)['sites'] ?? null;
    }

    public function test_bound_machine_exposes_site_ref_id_and_name(): void
    {
        $telco = Telco::create(['name' => 'Starhub']);
        $sim = Simcard::create(['code' => '111111', 'telco_id' => $telco->id]);
        $customer = Customer::create([
            'name' => 'Blk 188 Bukit Batok',
            'code' => 10001,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);
        Vend::create([
            'code' => '9201', 'is_active' => 1,
            'simcard_id' => $sim->id, 'customer_id' => $customer->id,
        ]);

        $this->assertSame([[
            'vend_id' => Vend::where('code', '9201')->value('id'),
            'id' => $customer->id,
            'ref_id' => $customer->id + Customer::RUNNING_NUMBER_INIT,
            'name' => 'Blk 188 Bukit Batok',
        ]], $this->sitesFor('111111'));
    }

    /**
     * The customer relation is eager-loaded purely to build 'sites', so it must
     * not also ride along inside each serialized vend. What the vends payload
     * still has to carry: id (Form.vue's binding dropdown), apk_version_code +
     * apk_ver_json (Machine APK column) and internet_* (Signal Strength).
     */
    public function test_vends_payload_keeps_its_columns_but_drops_the_customer(): void
    {
        $telco = Telco::create(['name' => 'Starhub']);
        $sim = Simcard::create(['code' => '666666', 'telco_id' => $telco->id]);
        $customer = Customer::create([
            'name' => 'Some Site',
            'code' => 10003,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);
        $vendModel = Vend::create([
            'code' => '9204', 'is_active' => 1,
            'simcard_id' => $sim->id, 'customer_id' => $customer->id,
        ]);
        // forceFill, not create(): these are reported-state columns the machine
        // writes, so they are deliberately not mass-assignable on Vend.
        $vendModel->forceFill([
            'apk_version_code' => 303,
            'internet_source' => 'mobile',
            'internet_signal' => 4,
        ])->save();

        $vend = $this->rowFor('666666')['vends'][0];

        $this->assertArrayNotHasKey('customer', $vend);
        $this->assertSame(303, (int) $vend['apk_version_code']);
        $this->assertSame('mobile', $vend['internet_source']);
        $this->assertSame(4, (int) $vend['internet_signal']);
        $this->assertSame($vendModel->id, $vend['id']);
    }

    public function test_machine_with_no_site_still_gets_an_aligned_entry(): void
    {
        $telco = Telco::create(['name' => 'M1']);
        $sim = Simcard::create(['code' => '222222', 'telco_id' => $telco->id]);
        Vend::create(['code' => '9202', 'is_active' => 1, 'simcard_id' => $sim->id]);

        $sites = $this->sitesFor('222222');
        $this->assertCount(1, $sites);
        $this->assertNull($sites[0]['id']);
        $this->assertNull($sites[0]['ref_id']);
    }

    public function test_unbound_simcard_has_no_sites(): void
    {
        $telco = Telco::create(['name' => 'Singtel']);
        Simcard::create(['code' => '333333', 'telco_id' => $telco->id]);

        $this->assertSame([], $this->sitesFor('333333'));
    }

    public function test_phone_number_query_no_longer_filters_the_index(): void
    {
        $telco = Telco::create(['name' => 'Starhub']);
        Simcard::create(['code' => '444444', 'telco_id' => $telco->id, 'phone_number' => '61234567']);

        $this->assertSame([], $this->sitesFor('444444', ['phone_number' => '999999999']));
    }

    /**
     * Operator isolation. The Site column is a new place a Site NAME reaches the
     * page, so it gets the same guard the rest of the estate has: a viewer pinned
     * to operator B must never read operator A's site name off this grid. Two
     * scopes stand between them — Vend's (the machine drops out of `vends`
     * entirely) and Customer's (the relation resolves null) — and this asserts
     * the outcome, not which one fired.
     */
    public function test_foreign_operators_site_never_reaches_the_column(): void
    {
        $opA = Operator::withoutGlobalScopes()->firstOrCreate(['code' => 'OPA'], ['name' => 'OPA', 'is_active' => true]);
        $opB = Operator::withoutGlobalScopes()->firstOrCreate(['code' => 'OPB'], ['name' => 'OPB', 'is_active' => true]);

        $telco = Telco::create(['name' => 'Starhub']);
        $sim = Simcard::create(['code' => '555555', 'telco_id' => $telco->id]);
        $secretSite = Customer::create([
            'name' => 'Operator A Confidential Site',
            'code' => 10002,
            'operator_id' => $opA->id,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);
        Vend::create([
            'code' => '9203', 'is_active' => 1, 'operator_id' => $opA->id,
            'simcard_id' => $sim->id, 'customer_id' => $secretSite->id,
        ]);

        $viewer = User::factory()->create(['operator_id' => $opB->id]);
        $response = $this->actingAs($viewer)->get('/simcards');
        $response->assertOk();

        $rows = $response->viewData('page')['props']['simcards']['data'];
        $this->assertSame([], collect($rows)->firstWhere('code', '555555')['sites']);
        $this->assertStringNotContainsString('Operator A Confidential Site', json_encode($rows));
    }
}
