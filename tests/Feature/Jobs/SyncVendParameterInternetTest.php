<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Vend\SyncVendParameter;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Link telemetry promoted out of the VENDER packet, APK v302 / small-board v134.
 *
 * The load-bearing cases here are not the happy path. They are:
 *  - a machine on an older APK must be completely unaffected (that is roughly
 *    the whole fleet on day one, each posting a packet every ~5 minutes), and
 *  - a field the APK could not read on ONE poll must not destroy the last good
 *    value, or the vends list flaps every five minutes.
 */
class SyncVendParameterInternetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * No Model::unguard() here on purpose: every attribute these tests set is
     * already in Vend::$fillable, and unguard() sets a STATIC that would stay
     * off for every test class running after this one in the same process.
     */
    private function vend(string $code = '2612', array $attributes = []): Vend
    {
        return Vend::create(array_merge([
            'code' => $code,
            'name' => 'Test Vend '.$code,
        ], $attributes));
    }

    private function packet(array $extra = []): array
    {
        return array_merge([
            'Type' => 'VENDER',
            'Vid' => 2612,
            'CHGEStat' => 3,
            'CoinCnt' => 3690,
            'TEMP' => -183,
            'Ver' => 904,
        ], $extra);
    }

    // Named handlePacket, not run(): PHPUnit's TestCase::run() is final, and a
    // private run() here is a fatal at class-load time — which killed the WHOLE
    // suite, since the runner loads every test file before executing any.
    private function handlePacket(array $packet, Vend $vend): void
    {
        (new SyncVendParameter($packet, $vend))->handle();
        $vend->refresh();
    }

    private function linked(string $code = '2612'): Vend
    {
        return $this->vend($code, [
            'internet_source' => 'telco',
            'internet_provider' => 'Maxis',
            'internet_signal' => 4,
            'internet_signal_max' => 5,
            'internet_network' => '4G',
        ]);
    }

    public function test_it_promotes_a_full_internet_object()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet([
            'Internet' => [
                'Source' => 'telco',
                'Provider' => 'Digi',
                'Signal' => 4,
                'SignalMax' => 5,
                'Network' => '4G',
            ],
        ]), $vend);

        $this->assertSame('telco', $vend->internet_source);
        $this->assertSame('Digi', $vend->internet_provider);
        $this->assertSame(4, $vend->internet_signal);
        $this->assertSame(5, $vend->internet_signal_max);
        $this->assertSame('4G', $vend->internet_network);
        $this->assertNotNull($vend->internet_updated_at);
    }

    /**
     * THE REGRESSION THAT MATTERS. A v301 packet carries no Internet key at all.
     * That must be a no-op, not a clear.
     */
    public function test_a_packet_without_the_key_leaves_existing_values_alone()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet(), $vend);

        $this->assertSame('telco', $vend->internet_source);
        $this->assertSame('Maxis', $vend->internet_provider);
        $this->assertSame(4, $vend->internet_signal);
        $this->assertSame('4G', $vend->internet_network);
    }

    public function test_an_old_packet_still_writes_the_rest_of_the_telemetry()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet(), $vend);

        $this->assertSame(-183, (int) $vend->temp);
        $this->assertNull($vend->internet_source);
    }

    /**
     * Same link, a field missing from ONE packet: keep what we had. Android
     * withholds the SSID intermittently, and a flapping Provider column is worse
     * than a slightly stale one.
     */
    public function test_a_field_omitted_on_one_packet_keeps_its_previous_value()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'telco', 'Signal' => 3, 'SignalMax' => 5],
        ]), $vend);

        $this->assertSame('Maxis', $vend->internet_provider);
        $this->assertSame('4G', $vend->internet_network);
        $this->assertSame(3, $vend->internet_signal);
    }

    /** A different kind of link replaces the row wholesale, so Network clears. */
    public function test_changing_source_replaces_every_field()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'wifi', 'Provider' => 'HappyIce', 'Signal' => 2, 'SignalMax' => 5],
        ]), $vend);

        $this->assertSame('wifi', $vend->internet_source);
        $this->assertSame('HappyIce', $vend->internet_provider);
        $this->assertSame(2, $vend->internet_signal);
        $this->assertNull($vend->internet_network);
    }

    public function test_lan_reports_no_signal_rather_than_a_fake_full_bar()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet(['Internet' => ['Source' => 'lan']]), $vend);

        $this->assertSame('lan', $vend->internet_source);
        $this->assertNull($vend->internet_signal);
        $this->assertNull($vend->internet_signal_max);
        $this->assertNull($vend->internet_provider);
        $this->assertNull($vend->internet_network);
    }

    public function test_zero_bars_is_stored_and_not_confused_with_unknown()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'telco', 'Signal' => 0, 'SignalMax' => 5],
        ]), $vend);

        $this->assertSame(0, $vend->internet_signal);
        $this->assertNotNull($vend->internet_signal);
    }

    public function test_a_malformed_internet_member_is_ignored()
    {
        $bads = [
            'scalar' => ['Internet' => 'telco'],
            'empty' => ['Internet' => []],
            'no source' => ['Internet' => ['Provider' => 'Digi']],
        ];

        $code = 3000;
        foreach ($bads as $label => $bad) {
            $vend = $this->vend((string) $code++);

            $this->handlePacket($this->packet($bad), $vend);

            $this->assertNull($vend->internet_source, $label);
            $this->assertNull($vend->internet_provider, $label);
        }
    }

    public function test_an_oversized_provider_truncates_instead_of_failing_the_save()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'wifi', 'Provider' => str_repeat('A', 400), 'Signal' => 2, 'SignalMax' => 5],
        ]), $vend);

        $this->assertSame(64, strlen($vend->internet_provider));
        // The temperature from the same packet must still have landed - a bad
        // link field is never allowed to take the rest of the write with it.
        $this->assertSame(-183, (int) $vend->temp);
    }

    /** A bar count above its own declared scale is not trustworthy. */
    public function test_a_signal_above_its_own_scale_is_rejected()
    {
        $vend = $this->vend();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'telco', 'Signal' => 31, 'SignalMax' => 5, 'Network' => '4G'],
        ]), $vend);

        $this->assertSame('telco', $vend->internet_source);
        $this->assertNull($vend->internet_signal);
        $this->assertNull($vend->internet_signal_max);
    }

    /** Signal readable but its scale not: the stored scale keeps its value too. */
    public function test_a_signal_without_its_scale_keeps_the_stored_scale()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'telco', 'Signal' => 3],
        ]), $vend);

        $this->assertSame(3, $vend->internet_signal);
        $this->assertSame(5, $vend->internet_signal_max);
    }

    /** Bars above the stored scale with no new scale: incoherent, keep the old reading. */
    public function test_a_signal_above_the_stored_scale_without_a_new_scale_is_dropped()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'telco', 'Signal' => 9],
        ]), $vend);

        $this->assertSame(4, $vend->internet_signal);
        $this->assertSame(5, $vend->internet_signal_max);
    }

    public function test_one_bad_signal_does_not_destroy_the_last_good_reading()
    {
        $vend = $this->linked();

        $this->handlePacket($this->packet([
            'Internet' => ['Source' => 'telco', 'Signal' => 9999, 'SignalMax' => 5],
        ]), $vend);

        $this->assertSame(4, $vend->internet_signal);
        $this->assertSame(5, $vend->internet_signal_max);
    }

    public function test_the_whole_packet_still_lands_in_parameter_json()
    {
        $vend = $this->vend();
        $internet = ['Source' => 'telco', 'Provider' => 'Digi', 'Signal' => 4, 'SignalMax' => 5, 'Network' => '4G'];

        $this->handlePacket($this->packet(['Internet' => $internet]), $vend);

        $this->assertSame($internet, $vend->parameter_json['Internet']);
    }
}
