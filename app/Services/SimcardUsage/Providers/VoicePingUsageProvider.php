<?php

namespace App\Services\SimcardUsage\Providers;

use App\Contracts\SimcardUsage\SimcardUsageProvider;
use App\Services\SimcardUsage\DTO\SimcardUsageData;
use App\Services\SimcardUsage\RateLimitedException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * VoicePing sim-info API (usage.voiceping.com). One GET, up to 50 comma-joined
 * ICCIDs (simcards.code):
 *
 *   GET /api/sim-info?simNo=8985...,8985...
 *
 * Response: array of per-sim entries —
 *   { simNo, code: "ok", status: 200, data: {
 *       simStatus: "Normal",
 *       packageList: [ { status: "Activated", activeTime: "yyyyMMddHHmmss",
 *                        expireTime, usedTotalData (MB float), ... } ],
 *   } }
 *
 * The Status column mirrors VoicePing's own portal: the CURRENT package's
 * status/activeTime/expireTime/usedTotalData, not the card-level fields (those
 * carry the 2028 card expiry, not the running 30-day package).
 */
class VoicePingUsageProvider implements SimcardUsageProvider
{
    /**
     * @param  array{endpoint:string, max_per_request?:int, timeout?:int, timezone?:string}  $config
     */
    public function __construct(
        protected array $config,
    ) {}

    public function key(): string
    {
        return 'voiceping';
    }

    public function maxPerRequest(): int
    {
        return (int) ($this->config['max_per_request'] ?? 50);
    }

    public function fetch(array $simNos): array
    {
        $endpoint = $this->config['endpoint'] ?? '';
        $timeout = (int) ($this->config['timeout'] ?? 15);

        $response = Http::timeout($timeout)->acceptJson()->get($endpoint, [
            'simNo' => implode(',', $simNos),
        ]);

        if ($response->status() === 429) {
            throw new RateLimitedException('VoicePing sim-info API returned HTTP 429 (rate limited).');
        }

        if (! $response->successful()) {
            throw new RuntimeException("VoicePing sim-info API returned HTTP {$response->status()}.");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('VoicePing sim-info API returned a non-JSON response.');
        }

        $results = [];
        foreach ($json as $entry) {
            $simNo = (string) ($entry['simNo'] ?? '');
            $data = $entry['data'] ?? null;

            // Per-sim errors (unknown ICCID etc.) are absent from the result;
            // the sync layer then leaves that row's last snapshot untouched.
            if ($simNo === '' || ($entry['code'] ?? '') !== 'ok' || ! is_array($data)) {
                continue;
            }

            $package = $this->currentPackage($data['packageList'] ?? []);

            $results[$simNo] = new SimcardUsageData(
                simNo: $simNo,
                // A card with no package at all still has a card-level status.
                status: (string) ($package['status'] ?? $data['simStatus'] ?? '') ?: null,
                activeAt: $this->parseTime($package['activeTime'] ?? null),
                expireAt: $this->parseTime($package['expireTime'] ?? null),
                usedMb: isset($package['usedTotalData']) ? (float) $package['usedTotalData'] : null,
            );
        }

        return $results;
    }

    /**
     * The package the VoicePing portal shows: prefer an Activated one (latest
     * activeTime wins if several), else fall back to the latest by expireTime
     * (so a just-expired card still reads "Expired" rather than blank).
     *
     * @param  array<int, array<string, mixed>>  $packages
     * @return array<string, mixed>
     */
    protected function currentPackage(array $packages): array
    {
        if (! $packages) {
            return [];
        }

        $activated = array_filter($packages, fn ($p) => ($p['status'] ?? '') === 'Activated');
        $pool = $activated ?: $packages;

        usort($pool, fn ($a, $b) => strcmp(
            (string) ($b[$activated ? 'activeTime' : 'expireTime'] ?? ''),
            (string) ($a[$activated ? 'activeTime' : 'expireTime'] ?? ''),
        ));

        return $pool[0];
    }

    /** VoicePing timestamps are bare "yyyyMMddHHmmss" strings in UTC+8. */
    protected function parseTime(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{14}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat(
            'YmdHis',
            $value,
            $this->config['timezone'] ?? config('app.timezone'),
        )->setTimezone(config('app.timezone'));
    }
}
