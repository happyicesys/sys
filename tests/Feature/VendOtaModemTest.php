<?php

namespace Tests\Feature;

use App\Jobs\Vend\RecordVendOtaModem;
use App\Models\Vend;
use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * OTA updater + square-module state on the big-board 307+ "P" heartbeat
 * (OtaFail, OtaErr, ModemFw, ModemPdp) → vends.ota_fail_streak, ota_last_error,
 * modem_firmware, modem_pdp (apk/mark1-apk/UNRELEASED_V307.md).
 *
 * Pinned: older builds (no keys) queue nothing; an unchanged reading queues
 * nothing; OtaErr travels with OtaFail (absent = cleared); modem keys are kept
 * when a beat omits them; the job writes only what changed.
 */
class VendOtaModemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-04 12:00:00');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function heartbeat(int $code, array $extra = []): void
    {
        $service = new VendDataService;
        $input = [
            'f' => '120', 't' => '5', 'm' => (string) $code, 'g' => '20',
            'p' => base64_encode(json_encode(array_merge(['Type' => 'P'], $extra))),
        ];
        $std = $service->standardizedVendData($input, 'http');
        $service->processVendData($std, $service->decodeVendData($std), '10.0.0.1', 'http');
    }

    public function test_a_307_heartbeat_queues_its_fields_and_an_older_one_queues_nothing(): void
    {
        Queue::fake();
        $vend = Vend::forceCreate(['code' => 2330]);

        $this->heartbeat(2330, ['OfflineRestartCount' => 0, 'TrdQ' => 0]);
        Queue::assertNotPushed(RecordVendOtaModem::class);

        $this->heartbeat(2330, [
            'OtaFail' => 3,
            'OtaErr' => 'poll: 4 attempts failed, last: timeout',
            'ModemFw' => 'LuatOS-Air_V4021_RDA8910_TTS_NOVOLTE_FLOAT',
            'ModemPdp' => 'IPV4V6/redone',
        ]);

        Queue::assertPushedOn('low', RecordVendOtaModem::class, fn (RecordVendOtaModem $job) => $job->vendId === $vend->id
            && $job->fields === [
                'ota_fail_streak' => 3,
                'ota_last_error' => 'poll: 4 attempts failed, last: timeout',
                'modem_firmware' => 'LuatOS-Air_V4021_RDA8910_TTS_NOVOLTE_FLOAT',
                'modem_pdp' => 'IPV4V6/redone',
            ]
            && $job->reportedAt === '2026-10-04 12:00:00');
    }

    public function test_an_unchanged_reading_queues_nothing_and_a_change_queues_again(): void
    {
        Queue::fake();
        Vend::forceCreate(['code' => 2330]);
        $healthy = ['OtaFail' => 0, 'ModemFw' => 'LuatOS-Air_V4021', 'ModemPdp' => 'IP/vp'];

        $this->heartbeat(2330, $healthy);
        $this->heartbeat(2330, $healthy);
        Queue::assertPushed(RecordVendOtaModem::class, 1);

        $this->heartbeat(2330, ['OtaFail' => 1, 'OtaErr' => 'poll: timeout'] + $healthy);
        Queue::assertPushed(RecordVendOtaModem::class, 2);
    }

    public function test_ota_error_is_cleared_when_a_beat_reports_no_error(): void
    {
        Queue::fake();
        Vend::forceCreate(['code' => 2330]);

        $this->heartbeat(2330, ['OtaFail' => 0]);

        Queue::assertPushed(RecordVendOtaModem::class, fn (RecordVendOtaModem $job) => array_key_exists('ota_last_error', $job->fields)
            && $job->fields['ota_last_error'] === null
            && ! array_key_exists('modem_firmware', $job->fields));
    }

    public function test_values_are_bounded_and_junk_is_ignored(): void
    {
        Queue::fake();
        Vend::forceCreate(['code' => 2330]);

        $this->heartbeat(2330, [
            'OtaFail' => 999999,
            'OtaErr' => str_repeat('x', 500),
            'ModemFw' => ['not', 'a', 'string'],
            'ModemPdp' => '   ',
        ]);

        Queue::assertPushed(RecordVendOtaModem::class, fn (RecordVendOtaModem $job) => $job->fields === [
            'ota_fail_streak' => 65535,
            'ota_last_error' => str_repeat('x', 160),
        ]);
    }

    public function test_the_job_writes_only_what_changed(): void
    {
        $vend = Vend::forceCreate(['code' => 2330]);
        DB::table('vends')->where('id', $vend->id)->update([
            'ota_fail_streak' => 2,
            'ota_last_error' => 'poll: timeout',
            'modem_firmware' => 'LuatOS-Air_V4021',
            'modem_pdp' => 'IPV4V6/redone',
            'ota_modem_changed_at' => '2026-10-01 00:00:00',
        ]);

        // Same values: nothing written, changed_at untouched.
        (new RecordVendOtaModem($vend->id, [
            'ota_fail_streak' => 2, 'ota_last_error' => 'poll: timeout',
        ], '2026-10-04 12:00:00'))->handle();
        $this->assertSame('2026-10-01 00:00:00', (string) DB::table('vends')->where('id', $vend->id)->value('ota_modem_changed_at'));

        // The poll recovered; a beat without modem keys leaves them alone.
        (new RecordVendOtaModem($vend->id, [
            'ota_fail_streak' => 0, 'ota_last_error' => null,
        ], '2026-10-04 12:05:00'))->handle();

        $row = DB::table('vends')->where('id', $vend->id)->first();
        $this->assertSame(0, (int) $row->ota_fail_streak);
        $this->assertNull($row->ota_last_error);
        $this->assertSame('LuatOS-Air_V4021', $row->modem_firmware);
        $this->assertSame('IPV4V6/redone', $row->modem_pdp);
        $this->assertSame('2026-10-04 12:05:00', (string) $row->ota_modem_changed_at);
    }

    public function test_the_job_ignores_columns_it_does_not_own(): void
    {
        $vend = Vend::forceCreate(['code' => 2330, 'name' => 'keep me']);

        (new RecordVendOtaModem($vend->id, ['name' => 'hijacked', 'modem_pdp' => 'IP/vp'], '2026-10-04 12:00:00'))->handle();

        $row = DB::table('vends')->where('id', $vend->id)->first();
        $this->assertSame('keep me', $row->name);
        $this->assertSame('IP/vp', $row->modem_pdp);
    }
}
