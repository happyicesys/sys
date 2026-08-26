<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Simcard;
use App\Models\Telco;
use App\Models\User;
use App\Models\Vend;
use App\Traits\HasFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * "SimCard Package" (the telcos table, renamed in the UI) — the hidden
 * multi-select filter on Vend/CustomerIndex, matched through the machine's
 * bound SIM card (vends.simcard_id -> simcards.telco_id).
 *
 * The filter must only ever NARROW: the "All" chip and an empty selection are
 * both no-ops, per the request-filter rule in CLAUDE.md.
 */
class SimcardPackageFilterTest extends TestCase
{
    use RefreshDatabase;

    private Telco $simiot;

    private Telco $starhub;

    /** Site codes, one per package + one machine with no SIM card at all. */
    private int $simiotSite;

    private int $starhubSite;

    private int $noSimSite;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->be($user);

        $this->simiot = Telco::create(['name' => 'simiot 200MB']);
        $this->starhub = Telco::create(['name' => 'Starhub 1GB']);

        $this->simiotSite = $this->site('Simiot Site', 20001, $this->simiot);
        $this->starhubSite = $this->site('Starhub Site', 20002, $this->starhub);
        $this->noSimSite = $this->site('No SIM Site', 20003, null);
    }

    /** A site with one bound machine, optionally carrying a SIM card. */
    private function site(string $name, int $code, ?Telco $telco): int
    {
        $customer = Customer::create([
            'name' => $name,
            'code' => $code,
            'operator_id' => auth()->user()->operator_id,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);

        $simcardId = null;
        if ($telco) {
            $simcardId = Simcard::create([
                'code' => '8985220251121009'.$code,
                'telco_id' => $telco->id,
                'created_by' => auth()->id(),
            ])->id;
        }

        Vend::create([
            'code' => $code,
            'machine_type' => 'vending_machine',
            'is_active' => 1,
            'customer_id' => $customer->id,
            'simcard_id' => $simcardId,
        ]);

        return $customer->id;
    }

    /** Customer ids surviving filterVendsDB() for the given `telcos` value. */
    private function filteredSiteIds($telcos): array
    {
        $filterer = new class
        {
            use HasFilter;
        };

        $query = Customer::query()
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->select('customers.id');

        $request = new Request(array_filter(
            ['telcos' => $telcos],
            fn ($value) => $value !== null
        ));

        return $filterer->filterVendsDB($query, $request)->pluck('id')->all();
    }

    public function test_selecting_one_package_narrows_to_its_machines(): void
    {
        $ids = $this->filteredSiteIds([(string) $this->simiot->id]);

        $this->assertSame([$this->simiotSite], $ids);
    }

    public function test_selecting_several_packages_returns_the_union(): void
    {
        $ids = $this->filteredSiteIds([
            (string) $this->simiot->id,
            (string) $this->starhub->id,
        ]);

        $this->assertEqualsCanonicalizing([$this->simiotSite, $this->starhubSite], $ids);
        $this->assertNotContains($this->noSimSite, $ids);
    }

    public function test_the_all_chip_does_not_narrow(): void
    {
        $ids = $this->filteredSiteIds(['all']);

        $this->assertEqualsCanonicalizing(
            [$this->simiotSite, $this->starhubSite, $this->noSimSite],
            $ids
        );
    }

    public function test_an_empty_selection_does_not_narrow(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->simiotSite, $this->starhubSite, $this->noSimSite],
            $this->filteredSiteIds([])
        );

        $this->assertEqualsCanonicalizing(
            [$this->simiotSite, $this->starhubSite, $this->noSimSite],
            $this->filteredSiteIds(null)
        );
    }
}
