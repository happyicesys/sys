<?php

namespace Tests\Feature;

use App\Models\CardTerminal;
use App\Models\Customer;
use App\Models\ModemType;
use App\Models\User;
use App\Models\Vend;
use App\Traits\HasFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The three Payment Device badges that are also hidden filters on the
 * Operation Dashboard: Modem (vends.modem_type_id), LCD Monitor
 * (vends.lcd_monitor_id) and Card Terminal (vends.card_terminal_id).
 *
 * Each carries an "N/A" option — sent as the string 'undefined' — meaning
 * "nothing bound", which is exactly what the badge renders as N/A. That is
 * the case the filters were added for: listing the machines whose badge is
 * blank. As with every request filter, 'all' and an absent value must NOT
 * narrow.
 */
class OpsDashboardHardwareFiltersTest extends TestCase
{
    use RefreshDatabase;

    private ModemType $modemType;

    private CardTerminal $cardTerminal;

    /** Site ids: fully fitted vs. nothing fitted. */
    private int $fittedSite;

    private int $bareSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->be(User::factory()->create());

        $this->modemType = ModemType::create(['name' => 'Quectel EC25', 'alias' => '4G Big Andr']);
        $this->cardTerminal = CardTerminal::create(['name' => 'Nayax']);

        $this->fittedSite = $this->site('Fitted Site', 30001, [
            'modem_type_id' => $this->modemType->id,
            'lcd_monitor_id' => 1,
            'card_terminal_id' => $this->cardTerminal->id,
        ]);

        $this->bareSite = $this->site('Bare Site', 30002, [
            'modem_type_id' => null,
            'lcd_monitor_id' => null,
            'card_terminal_id' => null,
        ]);
    }

    /** A site with one bound machine carrying the given hardware bindings. */
    private function site(string $name, int $code, array $hardware): int
    {
        $customer = Customer::create([
            'name' => $name,
            'code' => $code,
            'operator_id' => auth()->user()->operator_id,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);

        Vend::create(array_merge([
            'code' => $code,
            'machine_type' => 'vending_machine',
            'is_active' => 1,
            'customer_id' => $customer->id,
        ], $hardware));

        return $customer->id;
    }

    /** Customer ids surviving filterVendsDB() for the given query params. */
    private function filteredSiteIds(array $params): array
    {
        $filterer = new class
        {
            use HasFilter;
        };

        $query = Customer::query()
            ->leftJoin('vends', 'vends.customer_id', '=', 'customers.id')
            ->select('customers.id');

        return $filterer->filterVendsDB($query, new Request($params))->pluck('id')->all();
    }

    public function test_modem_filter_narrows_to_the_selected_type(): void
    {
        $this->assertSame(
            [$this->fittedSite],
            $this->filteredSiteIds(['modem_type_id' => (string) $this->modemType->id])
        );
    }

    public function test_modem_na_lists_machines_with_no_modem_type(): void
    {
        $this->assertSame(
            [$this->bareSite],
            $this->filteredSiteIds(['modem_type_id' => 'undefined'])
        );
    }

    public function test_lcd_monitor_filter_narrows_to_the_selected_mapping(): void
    {
        $this->assertSame(
            [$this->fittedSite],
            $this->filteredSiteIds(['lcd_monitor_id' => '1'])
        );
    }

    public function test_lcd_monitor_na_covers_both_unset_and_mapping_99(): void
    {
        // Mapping 99 is itself labelled 'N/A', and the badge renders it the
        // same as an unset monitor, so the dashboard's single "N/A" option has
        // to return both machines.
        $explicitNaSite = $this->site('Explicit N/A Site', 30003, [
            'modem_type_id' => $this->modemType->id,
            'lcd_monitor_id' => 99,
            'card_terminal_id' => $this->cardTerminal->id,
        ]);

        $this->assertEqualsCanonicalizing(
            [$this->bareSite, $explicitNaSite],
            $this->filteredSiteIds(['lcd_monitor_id' => 'na'])
        );

        // Vend/Index's narrower 'undefined' still means null only.
        $this->assertSame(
            [$this->bareSite],
            $this->filteredSiteIds(['lcd_monitor_id' => 'undefined'])
        );
    }

    public function test_card_terminal_filter_narrows_to_the_selected_terminal(): void
    {
        $this->assertSame(
            [$this->fittedSite],
            $this->filteredSiteIds(['cashless_mfg' => 'Nayax'])
        );
    }

    public function test_card_terminal_na_lists_machines_with_no_terminal_fitted(): void
    {
        $this->assertSame(
            [$this->bareSite],
            $this->filteredSiteIds(['cashless_mfg' => 'undefined'])
        );
    }

    public function test_all_and_absent_do_not_narrow(): void
    {
        $both = [$this->fittedSite, $this->bareSite];

        foreach (['modem_type_id', 'lcd_monitor_id', 'cashless_mfg'] as $key) {
            $this->assertEqualsCanonicalizing(
                $both,
                $this->filteredSiteIds([$key => 'all']),
                $key.' = all must not narrow'
            );

            $this->assertEqualsCanonicalizing(
                $both,
                $this->filteredSiteIds([]),
                $key.' absent must not narrow'
            );
        }
    }
}
