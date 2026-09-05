<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Simcard;
use App\Models\Telco;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simcard Index (2026-09-05): MSISDN column + filter removed, Site and Status
 * filters added, the four machine-derived columns made sortable, and an Excel
 * export that carries the same rows as the grid.
 */
class SimcardIndexFiltersSortsExportTest extends TestCase
{
    use RefreshDatabase;

    private Telco $telco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->be(User::factory()->create());
        $this->telco = Telco::create(['name' => 'VoicePing 6GB/y']);
    }

    /** A simcard, optionally bound to one machine sitting at $siteName. */
    private function simcard(string $code, array $attributes = [], ?string $siteName = null, array $vendAttributes = []): Simcard
    {
        $sim = Simcard::create(array_merge(['code' => $code, 'telco_id' => $this->telco->id], $attributes));

        // usage_* is written by the sync service, never mass-assigned.
        $usage = array_intersect_key($attributes, array_flip([
            'usage_status', 'usage_active_at', 'usage_expire_at', 'usage_used_mb',
        ]));
        if ($usage) {
            $sim->forceFill($usage)->save();
        }

        if ($siteName !== null || $vendAttributes) {
            $customer = $siteName === null ? null : Customer::create([
                'name' => $siteName,
                'code' => 30000 + $sim->id,
                'operator_id' => auth()->user()->operator_id,
                'status_id' => Customer::STATUS_ACTIVE,
            ]);

            $vend = Vend::create([
                'code' => '9'.str_pad((string) $sim->id, 3, '0', STR_PAD_LEFT),
                'is_active' => 1,
                'operator_id' => auth()->user()->operator_id,
                'simcard_id' => $sim->id,
                'customer_id' => $customer?->id,
            ]);

            if ($vendAttributes) {
                $vend->forceFill($vendAttributes)->save();
            }
        }

        return $sim->refresh();
    }

    /**
     * Download the export and read it back. FastExcel streams the file, so the
     * response has to be spooled to disk before PhpSpreadsheet can open it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportSheet(array $query = []): array
    {
        $response = $this->get('/simcards/excel?'.http_build_query($query));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'simcard_export').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            return (new \Rap2hpoutre\FastExcel\FastExcel)->importSheets($path)[0] ?? [];
        } finally {
            @unlink($path);
        }
    }

    /** The Simcard Number of every row the index returns, in order. */
    private function codes(array $query = []): array
    {
        $response = $this->get('/simcards?'.http_build_query($query));
        $response->assertOk();

        return collect($response->viewData('page')['props']['simcards']['data'])
            ->pluck('code')->all();
    }

    // ---------------------------------------------------------------- MSISDN

    public function test_msisdn_no_longer_filters_the_index(): void
    {
        $this->simcard('111111', ['msisdn' => '6580001111']);
        $this->simcard('222222', ['msisdn' => '6580002222']);

        // Before, this narrowed to one row; the column and its box are gone.
        $this->assertCount(2, $this->codes(['msisdn' => '6580001111']));
    }

    public function test_msisdn_is_no_longer_a_sort_key_and_falls_back_to_created_at(): void
    {
        $this->simcard('111111');
        $this->simcard('222222');

        // An unrecognised key must not reach ORDER BY — it degrades to the
        // default rather than 500ing or ordering on a raw request string.
        $this->assertCount(2, $this->codes(['sortKey' => 'msisdn']));
        $this->assertCount(2, $this->codes(['sortKey' => 'code); drop table simcards; --']));
    }

    // ------------------------------------------------------------ Site filter

    public function test_site_filter_matches_the_bound_machines_site_by_name(): void
    {
        $this->simcard('111111', [], 'Watercove Condo');
        $this->simcard('222222', [], 'Bukit Batok Blk 188');

        $this->assertSame(['111111'], $this->codes(['customer' => 'Watercove']));
    }

    public function test_site_filter_matches_the_displayed_site_id(): void
    {
        $sim = $this->simcard('111111', [], 'Watercove Condo');
        $this->simcard('222222', [], 'Bukit Batok Blk 188');

        $siteId = Vend::where('simcard_id', $sim->id)->value('customer_id');

        $this->assertSame(['111111'], $this->codes([
            'customer' => (string) ($siteId + Customer::RUNNING_NUMBER_INIT),
        ]));
    }

    public function test_site_filter_excludes_unbound_and_siteless_simcards(): void
    {
        $this->simcard('111111', [], 'Watercove Condo');
        $this->simcard('222222');                 // unbound
        $this->simcard('333333', [], null, ['is_online' => 1]); // bound, no Site

        $this->assertSame(['111111'], $this->codes(['customer' => 'Watercove']));
    }

    // ---------------------------------------------------------- Status filter

    public function test_status_filter_narrows_to_one_usage_status(): void
    {
        $this->simcard('111111', ['usage_status' => 'Activated']);
        $this->simcard('222222', ['usage_status' => 'Inactive']);
        $this->simcard('333333');                 // telco with no usage API

        $this->assertSame(['111111'], $this->codes(['usage_status' => 'Activated']));
        $this->assertSame(['222222'], $this->codes(['usage_status' => 'Inactive']));
        $this->assertCount(3, $this->codes());
    }

    public function test_status_dropdown_options_come_from_the_controller(): void
    {
        $response = $this->get('/simcards');
        $response->assertOk();

        $this->assertSame(
            ['Activated', 'Inactive'],
            $response->viewData('page')['props']['usageStatusOptions']
        );
    }

    // ------------------------------------------------------------ Updated By

    /**
     * The column stacks the editor's name over the timestamp, formatted
     * yymmdd hh:ii a — the same shape Machine Settings uses.
     */
    public function test_updated_by_carries_the_name_and_a_yymmdd_timestamp(): void
    {
        $editor = User::factory()->create(['name' => 'Daniel']);
        $sim = $this->simcard('111111');
        $sim->forceFill([
            'updated_by' => $editor->id,
            'updated_at' => '2026-09-05 15:04:00',
        ])->save();

        $row = collect($this->get('/simcards')->viewData('page')['props']['simcards']['data'])
            ->firstWhere('code', '111111');

        $this->assertSame('Daniel', $row['updatedBy']['name']);
        $this->assertSame('260905 03:04 pm', $row['updated_at']);
    }

    // ----------------------------------------------------------------- Sorts

    public function test_site_sort_orders_by_the_bound_machines_site_name(): void
    {
        $this->simcard('111111', [], 'Zulu Site');
        $this->simcard('222222', [], 'Alpha Site');

        $this->assertSame(['222222', '111111'], $this->codes(['sortKey' => 'site', 'sortBy' => 'true']));
        $this->assertSame(['111111', '222222'], $this->codes(['sortKey' => 'site', 'sortBy' => 'false']));
    }

    public function test_machine_apk_sort_takes_the_higher_of_both_reporting_channels(): void
    {
        // OTA check-in column only.
        $this->simcard('111111', [], 'A Site', ['apk_version_code' => 303]);
        // PWRON frame only — must sort ABOVE 303, which a plain column sort
        // would get wrong (apk_version_code is null here).
        $this->simcard('222222', [], 'B Site', ['apk_ver_json' => ['apkver' => 304]]);
        $this->simcard('333333', [], 'C Site', ['apk_version_code' => 145]);

        $this->assertSame(
            ['333333', '111111', '222222'],
            $this->codes(['sortKey' => 'apk_version', 'sortBy' => 'true'])
        );
    }

    public function test_signal_sort_normalises_a_non_five_bar_scale(): void
    {
        // 2/4 bars normalises to 3/5, so it must outrank a raw 2/5.
        $this->simcard('111111', [], 'A Site', [
            'internet_source' => 'telco', 'internet_signal' => 2, 'internet_signal_max' => 5,
        ]);
        $this->simcard('222222', [], 'B Site', [
            'internet_source' => 'telco', 'internet_signal' => 2, 'internet_signal_max' => 4,
        ]);

        $this->assertSame(
            ['111111', '222222'],
            $this->codes(['sortKey' => 'signal', 'sortBy' => 'true'])
        );
    }

    public function test_status_sort_orders_by_usage_status(): void
    {
        $this->simcard('111111', ['usage_status' => 'Inactive']);
        $this->simcard('222222', ['usage_status' => 'Activated']);

        $this->assertSame(
            ['222222', '111111'],
            $this->codes(['sortKey' => 'usage_status', 'sortBy' => 'true'])
        );
    }

    // ---------------------------------------------------------------- Export

    public function test_excel_export_downloads_an_xlsx(): void
    {
        $this->simcard('111111', ['usage_status' => 'Activated'], 'Watercove Condo', [
            'apk_version_code' => 303,
            'is_online' => 1,
            'last_updated_at' => now(),
            'internet_source' => 'telco',
            'internet_provider' => 'StarHub',
            'internet_network' => '4G',
            'internet_signal' => 4,
            'internet_signal_max' => 5,
        ]);

        $response = $this->get('/simcards/excel');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/filename=.?Simcards_\d{8}_\d{6}\.xlsx/',
            (string) $response->headers->get('content-disposition')
        );
    }

    /**
     * The point of sharing one query builder between the page and the download:
     * a filtered export must carry the filtered rows, not the whole table.
     */
    public function test_excel_export_honours_the_page_filters(): void
    {
        $this->simcard('111111', ['usage_status' => 'Activated'], 'Watercove Condo');
        $this->simcard('222222', ['usage_status' => 'Inactive'], 'Bukit Batok Blk 188');

        $codes = collect($this->exportSheet(['usage_status' => 'Activated']))
            ->pluck('Simcard Number')->all();

        $this->assertSame(['111111'], $codes);
    }

    public function test_excel_columns_mirror_the_grid(): void
    {
        $this->simcard('111111', ['usage_status' => 'Activated'], 'Watercove Condo', [
            'apk_version_code' => 303,
            'is_online' => 1,
            'last_updated_at' => now(),
            'internet_source' => 'telco',
            'internet_provider' => 'StarHub',
            'internet_network' => '4G',
            'internet_signal' => 4,
            'internet_signal_max' => 5,
        ]);

        $row = $this->exportSheet()[0];

        $this->assertSame([
            '#', 'Simcard Number', 'Machine ID', 'Site', 'Machine APK',
            'SimCard Package', 'Signal Strength', 'Updated By', 'Status',
        ], array_keys($row));

        $this->assertSame('111111', $row['Simcard Number']);
        $this->assertStringContainsString('Watercove Condo', (string) $row['Site']);
        $this->assertSame('303', (string) $row['Machine APK']);
        $this->assertSame('VoicePing 6GB/y', $row['SimCard Package']);
        $this->assertStringContainsString('Online', (string) $row['Signal Strength']);
        $this->assertStringContainsString('StarHub 4G', (string) $row['Signal Strength']);
        $this->assertStringContainsString('4/5', (string) $row['Signal Strength']);
        $this->assertStringContainsString('Activated', (string) $row['Status']);
    }

    /**
     * Operator isolation — the export is a second door onto the same data, so
     * it gets the guard the grid has: a viewer pinned to another operator must
     * not read a foreign Site name out of the .xlsx.
     */
    public function test_export_never_carries_a_foreign_operators_site(): void
    {
        $opA = \App\Models\Operator::withoutGlobalScopes()
            ->firstOrCreate(['code' => 'OPA'], ['name' => 'OPA', 'is_active' => true]);
        $opB = \App\Models\Operator::withoutGlobalScopes()
            ->firstOrCreate(['code' => 'OPB'], ['name' => 'OPB', 'is_active' => true]);

        $sim = Simcard::create(['code' => '555555', 'telco_id' => $this->telco->id]);
        $secretSite = Customer::create([
            'name' => 'Operator A Confidential Site',
            'code' => 39999,
            'operator_id' => $opA->id,
            'status_id' => Customer::STATUS_ACTIVE,
        ]);
        Vend::create([
            'code' => '9999', 'is_active' => 1, 'operator_id' => $opA->id,
            'simcard_id' => $sim->id, 'customer_id' => $secretSite->id,
        ]);

        $this->be(User::factory()->create(['operator_id' => $opB->id]));

        $this->assertStringNotContainsString(
            'Operator A Confidential Site',
            json_encode($this->exportSheet())
        );
    }
}
