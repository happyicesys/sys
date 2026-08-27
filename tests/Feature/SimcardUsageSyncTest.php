<?php

namespace Tests\Feature;

use App\Models\Simcard;
use App\Models\Telco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * simcards:sync-usage (Brian, 2026-08-27): every 10 min, pull live sim status
 * from telco usage APIs (VoicePing first) onto simcards.usage_* for the Index
 * "Status" column. Only telcos carrying usage_provider are polled; requests
 * chunk to the provider's max (VoicePing: 50); HTTP 429 logs a warning and
 * backs off to the next run; the cron write never touches updated_at, which
 * the "Updated By" column reads as "when a human last edited this row".
 */
class SimcardUsageSyncTest extends TestCase
{
    use RefreshDatabase;

    private function voicePingTelco(): Telco
    {
        return Telco::create(['name' => 'VoicePing 500MB', 'usage_provider' => 'voiceping']);
    }

    /** @param  \Illuminate\Http\Client\Request  $request */
    private function requestedSimNos($request): array
    {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return explode(',', $query['simNo'] ?? '');
    }

    private function entry(string $simNo, array $packages, string $simStatus = 'Normal', string $code = 'ok'): array
    {
        return [
            'simNo' => $simNo,
            'code' => $code,
            'status' => 200,
            'data' => [
                'simNo' => $simNo,
                'simStatus' => $simStatus,
                'packageList' => $packages,
            ],
        ];
    }

    public function test_syncs_current_package_onto_simcard_without_touching_updated_at(): void
    {
        $telco = $this->voicePingTelco();
        $sim = Simcard::create(['code' => '89852342022427674493', 'telco_id' => $telco->id]);
        $editedAt = $sim->fresh()->updated_at;

        Http::fake([
            'usage.voiceping.com/*' => Http::response([
                $this->entry('89852342022427674493', [
                    // An older expired package first — the Activated one must win.
                    ['status' => 'Expired', 'activeTime' => '20260601080000', 'expireTime' => '20260701080000', 'usedTotalData' => 499.10],
                    ['status' => 'Activated', 'activeTime' => '20260825083522', 'expireTime' => '20260925160000', 'usedTotalData' => 8.02],
                ]),
            ]),
        ]);

        $this->travel(5)->minutes();
        $this->artisan('simcards:sync-usage')->assertExitCode(0);

        $sim->refresh();
        $this->assertSame('Activated', $sim->usage_status);
        $this->assertSame('2026-08-25 08:35:22', $sim->usage_active_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 16:00:00', $sim->usage_expire_at->format('Y-m-d H:i:s'));
        $this->assertSame(8.02, $sim->usage_used_mb);
        $this->assertNotNull($sim->usage_synced_at);
        // The cron must not masquerade as a human edit.
        $this->assertTrue($sim->updated_at->equalTo($editedAt));
    }

    public function test_only_mapped_telcos_are_polled_and_requests_chunk(): void
    {
        config(['simcard_usage.providers.voiceping.max_per_request' => 2]);

        $voiceping = $this->voicePingTelco();
        $starhub = Telco::create(['name' => 'Starhub (ICCID)']); // no usage API

        foreach (['1111', '2222', '3333'] as $code) {
            Simcard::create(['code' => $code, 'telco_id' => $voiceping->id]);
        }
        $unmapped = Simcard::create(['code' => '9999', 'telco_id' => $starhub->id]);
        $inactive = Simcard::create(['code' => '8888', 'telco_id' => $voiceping->id, 'is_active' => 0]);

        Http::fake([
            'usage.voiceping.com/*' => function ($request) {
                return Http::response(array_map(
                    fn ($simNo) => $this->entry($simNo, [
                        ['status' => 'Activated', 'activeTime' => '20260825083522', 'expireTime' => '20260925160000', 'usedTotalData' => 1.00],
                    ]),
                    $this->requestedSimNos($request),
                ));
            },
        ]);

        $this->artisan('simcards:sync-usage')->assertExitCode(0);

        Http::assertSentCount(2); // 3 sims / max 2 per request
        Http::assertSent(fn ($request) => ! in_array('9999', $this->requestedSimNos($request)));
        $this->assertNull($unmapped->fresh()->usage_synced_at);
        $this->assertNull($inactive->fresh()->usage_synced_at);
        $this->assertSame('Activated', Simcard::where('code', '1111')->first()->usage_status);
    }

    public function test_rate_limit_logs_warning_and_leaves_snapshot_untouched(): void
    {
        $telco = $this->voicePingTelco();
        $sim = Simcard::create(['code' => '1111', 'telco_id' => $telco->id]);

        Http::fake(['usage.voiceping.com/*' => Http::response('slow down', 429)]);
        Log::spy();

        $this->artisan('simcards:sync-usage')->assertExitCode(1);

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn ($message) => str_contains($message, 'rate limited')
        );
        Log::shouldNotHaveReceived('error');
        $this->assertNull($sim->fresh()->usage_status);
    }

    public function test_per_sim_api_error_keeps_previous_snapshot(): void
    {
        $telco = $this->voicePingTelco();
        $sim = Simcard::create(['code' => '1111', 'telco_id' => $telco->id]);
        $sim->forceFill(['usage_status' => 'Activated', 'usage_used_mb' => 3.50])->save();

        Http::fake([
            'usage.voiceping.com/*' => Http::response([
                $this->entry('1111', [], code: 'error'),
            ]),
        ]);

        $this->artisan('simcards:sync-usage')->assertExitCode(0);

        $sim->refresh();
        $this->assertSame('Activated', $sim->usage_status);
        $this->assertSame(3.5, $sim->usage_used_mb);
        $this->assertNull($sim->usage_synced_at); // stale sync stamp = the tell
    }

    public function test_card_with_no_package_falls_back_to_sim_status(): void
    {
        $telco = $this->voicePingTelco();
        $sim = Simcard::create(['code' => '1111', 'telco_id' => $telco->id]);

        Http::fake([
            'usage.voiceping.com/*' => Http::response([
                $this->entry('1111', [], simStatus: 'Normal'),
            ]),
        ]);

        $this->artisan('simcards:sync-usage')->assertExitCode(0);

        $sim->refresh();
        $this->assertSame('Normal', $sim->usage_status);
        $this->assertNull($sim->usage_active_at);
        $this->assertNull($sim->usage_used_mb);
    }
}
