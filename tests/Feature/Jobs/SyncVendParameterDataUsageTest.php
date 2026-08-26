<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Vend\SyncVendParameter;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cumulative data-usage counters promoted out of the VENDER packet's Internet
 * object (APK v303's DataUsageLedger), plus the daily snapshot command.
 *
 * The load-bearing rules:
 *  - the counters are DEVICE-scoped: a link Source change must not wipe them;
 *  - cumulative values may legitimately DECREASE (APK reinstall resets the
 *    ledger) and the new value is accepted as truth;
 *  - DataMB is the required member — without it the whole set is skipped;
 *  - a fleet on older APKs (no Data* keys) is completely unaffected.
 */
class SyncVendParameterDataUsageTest extends TestCase
{
    use RefreshDatabase;

    private function vend(string $code = '2612', array $attributes = []): Vend
    {
        return Vend::create(array_merge([
            'code' => $code,
            'name' => 'Test Vend '.$code,
        ], $attributes));
    }

    private function packet(array $internet): array
    {
        return [
            'Type' => 'VENDER',
            'Vid' => 2612,
            'TEMP' => -183,
            'Ver' => 904,
            'Internet' => $internet,
        ];
    }

    private function handlePacket(array $packet, Vend $vend): void
    {
        (new SyncVendParameter($packet, $vend))->handle();
        $vend->refresh();
    }

    public function test_it_promotes_the_full_data_usage_set()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet([
            'Source' => 'telco',
            'DataMB' => 1843,
            'DataMobileMB' => 1790,
            'DataAppMB' => 211,
            'DataDays' => 38,
        ]), $vend);

        $this->assertSame(1843, $vend->internet_data_mb);
        $this->assertSame(1790, $vend->internet_data_mobile_mb);
        $this->assertSame(211, $vend->internet_data_app_mb);
        $this->assertSame(38, $vend->internet_data_days);
        $this->assertNotNull($vend->internet_data_updated_at);
    }

    public function test_an_old_apk_with_no_data_keys_leaves_the_columns_null()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet(['Source' => 'telco', 'Signal' => 4, 'SignalMax' => 5]), $vend);

        $this->assertNull($vend->internet_data_mb);
        $this->assertNull($vend->internet_data_updated_at);
        // The link half must still have promoted as before.
        $this->assertSame('telco', $vend->internet_source);
    }

    public function test_optional_members_keep_their_value_when_omitted()
    {
        $vend = $this->vend();
        $this->handlePacket($this->packet([
            'Source' => 'telco', 'DataMB' => 100, 'DataMobileMB' => 90, 'DataAppMB' => 10, 'DataDays' => 3,
        ]), $vend);

        // Next poll: the ROM withheld the mobile/app channels this time.
        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => 120]), $vend);

        $this->assertSame(120, $vend->internet_data_mb);
        $this->assertSame(90, $vend->internet_data_mobile_mb);
        $this->assertSame(10, $vend->internet_data_app_mb);
        $this->assertSame(3, $vend->internet_data_days);
    }

    public function test_a_decrease_is_accepted_as_a_ledger_reset()
    {
        $vend = $this->vend();
        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => 5000, 'DataDays' => 200]), $vend);

        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => 2, 'DataDays' => 0]), $vend);

        $this->assertSame(2, $vend->internet_data_mb);
        $this->assertSame(0, $vend->internet_data_days);
    }

    public function test_a_source_change_does_not_wipe_the_counters()
    {
        $vend = $this->vend();
        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => 500, 'DataMobileMB' => 480]), $vend);

        // Machine moves to Wi-Fi; the link row is replaced wholesale, but the
        // device-scoped counters must survive even when this packet omits them.
        $this->handlePacket($this->packet(['Source' => 'wifi', 'Provider' => 'HappyIce']), $vend);

        $this->assertSame('wifi', $vend->internet_source);
        $this->assertSame(500, $vend->internet_data_mb);
        $this->assertSame(480, $vend->internet_data_mobile_mb);
    }

    public function test_the_set_is_skipped_whole_without_the_required_data_mb()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMobileMB' => 480, 'DataDays' => 5]), $vend);

        $this->assertNull($vend->internet_data_mb);
        $this->assertNull($vend->internet_data_mobile_mb);
        $this->assertNull($vend->internet_data_days);
        $this->assertNull($vend->internet_data_updated_at);
    }

    public function test_out_of_range_values_are_dropped()
    {
        $vend = $this->vend();
        $this->handlePacket($this->packet([
            'Source' => 'telco', 'DataMB' => 100, 'DataMobileMB' => 90,
        ]), $vend);

        // DataMB hostile -> whole set skipped; nothing moves.
        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => -5, 'DataMobileMB' => 95]), $vend);
        $this->assertSame(100, $vend->internet_data_mb);
        $this->assertSame(90, $vend->internet_data_mobile_mb);

        // Optional member hostile -> only that member is dropped.
        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => 110, 'DataMobileMB' => 100_000_000]), $vend);
        $this->assertSame(110, $vend->internet_data_mb);
        $this->assertSame(90, $vend->internet_data_mobile_mb);
    }

    public function test_the_snapshot_command_copies_and_upserts_one_row_per_machine_per_day()
    {
        $reported = $this->vend('2612');
        $this->handlePacket($this->packet([
            'Source' => 'telco', 'DataMB' => 1843, 'DataMobileMB' => 1790, 'DataAppMB' => 211, 'DataDays' => 38,
        ]), $reported);
        $this->vend('2613'); // never reported -> must not snapshot

        $this->artisan('vend:snapshot-data-usage')->assertSuccessful();

        $rows = DB::table('vend_data_usage_snapshots')->get();
        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertEquals($reported->id, $row->vend_id);
        $this->assertSame(2612, (int) $row->vend_code);
        $this->assertSame(1843, (int) $row->total_mb);
        $this->assertSame(1790, (int) $row->mobile_mb);
        $this->assertSame(211, (int) $row->app_mb);
        $this->assertSame(38, (int) $row->ledger_days);

        // A later run the same day refreshes the day's row instead of piling up.
        $this->handlePacket($this->packet(['Source' => 'telco', 'DataMB' => 1900]), $reported);
        $this->artisan('vend:snapshot-data-usage')->assertSuccessful();

        $rows = DB::table('vend_data_usage_snapshots')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(1900, (int) $rows->first()->total_mb);
    }
}
