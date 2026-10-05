<?php

namespace Tests\Feature;

use App\Jobs\Ota\ServeOtaChunkOverMqtt;
use App\Jobs\Ota\ServeOtaManifestOverMqtt;
use App\Models\ApkRelease;
use App\Models\Vend;
use App\Services\Ota\OtaApkCache;
use App\Services\VendDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * OTA over MQTT (big-board 309+): when a board's HTTPS manifest poll keeps
 * failing (Air724 + VoicePing on the CMI-HK route), it asks over MQTT for the
 * manifest (OTAREQ -> OTAMANIFEST) and pulls the APK one piece at a time
 * (OTACHUNK -> OTACHUNKDATA). Pinned: same answer as GET /ota/manifest, check-in
 * recorded, only the live release at the exact version + sha is ever served,
 * from a verified copy, and the frames never land in vend_data.
 */
class OtaOverMqttTest extends TestCase
{
    use RefreshDatabase;

    private string $apk;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 15:00:00');
        config(['cache.default' => 'array']);
        Cache::flush();
        Storage::fake(config('filesystems.default'));
        File::deleteDirectory(storage_path('app/'.OtaApkCache::DIR));
        $this->apk = random_bytes(150_000);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/'.OtaApkCache::DIR));
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(int $code = 2003): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code,
            'machine_type' => Vend::MACHINE_TYPE_VENDING_MACHINE,
            'is_active' => true,
            'operator_id' => 1,
            'private_key' => 'k-'.$code,
            'apk_ver_json' => ['Type' => 'PWRON', 'apkver' => '307', 'deviceType' => 'ZC-328'],
        ])->save();

        return $vend->refresh();
    }

    private function publish(int $version = 308, ?string $bytes = null): ApkRelease
    {
        $bytes ??= $this->apk;
        $path = "sys/vends/apk/vending/com.venderroute_{$version}.apk";
        Storage::put($path, $bytes);

        return ApkRelease::create([
            'channel' => 'vending',
            'package_name' => 'com.venderroute',
            'version_code' => $version,
            'version_name' => (string) $version,
            'file_url' => "https://example.test/{$version}.apk",
            'file_path' => $path,
            'sha256' => hash('sha256', $this->apk),
            'size_bytes' => strlen($this->apk),
            'rollout_permille' => 1000,
            'status' => ApkRelease::STATUS_PUBLISHED,
        ]);
    }

    /** The JSON a board receives on CM<code>, decoded from mark1's signed frame. */
    private function lastFrameTo(int $code): array
    {
        $frames = $this->mqtt->publishedTo('CM'.$code);
        $this->assertNotEmpty($frames, "nothing published to CM{$code}");
        [, , $content] = explode(',', end($frames)['message']);

        return json_decode(base64_decode($content), true);
    }

    private function uplink(int $code, array $payload): void
    {
        $service = new VendDataService;
        $input = [
            'f' => '7', 't' => '5', 'm' => (string) $code, 'g' => '20',
            'p' => base64_encode(json_encode($payload)),
        ];
        $std = $service->standardizedVendData($input, 'mqtt');
        $service->processVendData($std, $service->decodeVendData($std), '10.0.0.1', 'mqtt');
    }

    public function test_https_manifest_endpoint_is_unchanged_by_the_shared_service(): void
    {
        $this->vend();
        $release = $this->publish();

        $this->get('/ota/manifest?vend_code=2003&versionCode=307&package=com.venderroute')
            ->assertOk()
            ->assertExactJson([
                'versionCode' => 308, 'versionName' => '308', 'url' => 'https://example.test/308.apk',
                'sha256' => $release->sha256, 'sizeBytes' => strlen($this->apk), 'mandatory' => false,
                'minSupportedVersionCode' => 0, 'rolloutPermille' => 1000,
            ]);
        $this->get('/ota/manifest?vend_code=2003&versionCode=308&package=com.venderroute')->assertNoContent();
        $this->get('/ota/manifest?vend_code=2003&versionCode=307&package=com.unknown')->assertNoContent();
        // As before the refactor, the check-in is recorded even for an unknown
        // package: the last poll above reported 307.
        $this->assertSame(307, Vend::where('code', 2003)->value('apk_version_code'));
    }

    public function test_ota_frames_are_queued_on_low_and_never_stored(): void
    {
        Queue::fake();
        $this->vend();

        $this->uplink(2003, ['Type' => 'OTAREQ', 'rid' => 1, 'ver' => 307, 'pkg' => 'com.venderroute']);
        $this->uplink(2003, ['Type' => 'OTACHUNK', 'rid' => 2, 'ver' => 308, 'sha' => str_repeat('a', 64), 'off' => 0, 'len' => 49152]);

        Queue::assertPushedOn('low', ServeOtaManifestOverMqtt::class);
        Queue::assertPushedOn('low', ServeOtaChunkOverMqtt::class);
        $this->assertSame(0, \DB::table('vend_data')->whereIn('type', ['OTAREQ', 'OTACHUNK'])->count());
    }

    public function test_manifest_over_mqtt_matches_https_and_records_the_check_in(): void
    {
        $vend = $this->vend();
        $release = $this->publish();

        (new ServeOtaManifestOverMqtt($vend->id, ['rid' => 41, 'ver' => 307, 'pkg' => 'com.venderroute']))
            ->handle(app(\App\Services\Ota\OtaManifestService::class), app(\App\Services\MqttService::class));

        $frame = $this->lastFrameTo(2003);
        $this->assertSame('OTAMANIFEST', $frame['Type']);
        $this->assertSame(41, $frame['rid']);
        $this->assertSame(308, $frame['manifest']['versionCode']);
        $this->assertSame($release->sha256, $frame['manifest']['sha256']);
        $this->assertSame(307, Vend::where('code', 2003)->value('apk_version_code'));
        $this->assertNotNull(Vend::where('code', 2003)->value('apk_checked_in_at'));
    }

    public function test_up_to_date_board_gets_a_null_manifest(): void
    {
        $vend = $this->vend();
        $this->publish();

        (new ServeOtaManifestOverMqtt($vend->id, ['rid' => 5, 'ver' => 308, 'pkg' => 'com.venderroute']))
            ->handle(app(\App\Services\Ota\OtaManifestService::class), app(\App\Services\MqttService::class));

        $frame = $this->lastFrameTo(2003);
        $this->assertSame(5, $frame['rid']);
        $this->assertNull($frame['manifest']);
    }

    private function chunk(Vend $vend, array $input): array
    {
        (new ServeOtaChunkOverMqtt($vend->id, $input + ['pkg' => 'com.venderroute']))
            ->handle(app(\App\Services\Ota\OtaManifestService::class), app(OtaApkCache::class), app(\App\Services\MqttService::class));

        return $this->lastFrameTo($vend->code);
    }

    public function test_chunks_reassemble_into_the_exact_file(): void
    {
        $vend = $this->vend();
        $release = $this->publish();
        $sha = $release->sha256;

        $rebuilt = '';
        for ($off = 0, $rid = 1; $off < strlen($this->apk); $rid++) {
            $frame = $this->chunk($vend, ['rid' => $rid, 'ver' => 308, 'sha' => $sha, 'off' => $off, 'len' => 49152]);
            $this->assertSame($rid, $frame['rid']);
            $this->assertSame($off, $frame['off']);
            $this->assertSame(strlen($this->apk), $frame['total']);
            $bytes = base64_decode($frame['data']);
            $rebuilt .= $bytes;
            $off += strlen($bytes);
        }

        $this->assertSame(hash('sha256', $this->apk), hash('sha256', $rebuilt));
    }

    public function test_only_the_live_release_at_the_exact_version_and_sha_is_served(): void
    {
        $vend = $this->vend();
        $release = $this->publish();

        $this->assertSame('not available', $this->chunk($vend, ['rid' => 1, 'ver' => 307, 'sha' => $release->sha256, 'off' => 0, 'len' => 100])['error']);
        $this->assertSame('not available', $this->chunk($vend, ['rid' => 2, 'ver' => 308, 'sha' => str_repeat('b', 64), 'off' => 0, 'len' => 100])['error']);
        $this->assertSame('bad request', $this->chunk($vend, ['rid' => 3, 'ver' => 308, 'sha' => 'nothex', 'off' => 0, 'len' => 100])['error']);
        $this->assertSame('offset beyond end', $this->chunk($vend, ['rid' => 4, 'ver' => 308, 'sha' => $release->sha256, 'off' => strlen($this->apk), 'len' => 100])['error']);

        $release->update(['status' => ApkRelease::STATUS_DRAFT]);
        $this->assertSame('not available', $this->chunk($vend, ['rid' => 5, 'ver' => 308, 'sha' => $release->sha256, 'off' => 0, 'len' => 100])['error']);
    }

    public function test_a_stored_file_that_does_not_match_its_sha_is_never_served(): void
    {
        $vend = $this->vend();
        $release = $this->publish(308, random_bytes(150_000)); // stored bytes != the release's sha

        $frame = $this->chunk($vend, ['rid' => 9, 'ver' => 308, 'sha' => $release->sha256, 'off' => 0, 'len' => 100]);

        $this->assertSame('server read failed', $frame['error']);
        $this->assertArrayNotHasKey('data', $frame);
    }

    public function test_chunk_length_is_capped(): void
    {
        $vend = $this->vend();
        $release = $this->publish();

        $frame = $this->chunk($vend, ['rid' => 1, 'ver' => 308, 'sha' => $release->sha256, 'off' => 0, 'len' => 10_000_000]);

        $this->assertSame(ServeOtaChunkOverMqtt::MAX_CHUNK_BYTES, strlen(base64_decode($frame['data'])));
    }

    public function test_a_flooding_board_is_rate_limited(): void
    {
        $vend = $this->vend();
        $release = $this->publish();
        RateLimiter::clear('ota-mqtt-chunk:'.$vend->id);

        for ($i = 0; $i < ServeOtaChunkOverMqtt::MAX_PER_MINUTE + 5; $i++) {
            (new ServeOtaChunkOverMqtt($vend->id, ['rid' => $i, 'ver' => 308, 'sha' => $release->sha256, 'off' => 0, 'len' => 10, 'pkg' => 'com.venderroute']))
                ->handle(app(\App\Services\Ota\OtaManifestService::class), app(OtaApkCache::class), app(\App\Services\MqttService::class));
        }

        $this->assertCount(ServeOtaChunkOverMqtt::MAX_PER_MINUTE, $this->mqtt->publishedTo('CM'.$vend->code));
    }
}
