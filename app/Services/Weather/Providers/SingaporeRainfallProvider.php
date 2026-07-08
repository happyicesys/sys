<?php

namespace App\Services\Weather\Providers;

use App\Contracts\Weather\WeatherProvider;
use App\Services\Weather\DTO\WeatherFetchResult;
use App\Services\Weather\DTO\WeatherReadingData;
use App\Services\Weather\DTO\WeatherStationData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * data.gov.sg real-time rainfall (5-minute total, mm), refreshed every 5 min.
 *
 * Response shape (v2):
 *   { code: 0, errorMsg: "", data: {
 *       stations:  [ { id, deviceId, name, location:{latitude,longitude} } ],
 *       readings:  [ { timestamp, data:[ { stationId, value } ] } ],
 *       readingType: "TB1 Rainfall 5 Minute Total F",
 *       readingUnit: "mm"
 *   } }
 *
 * Not every postcode is covered by a station; proximity matching to customers
 * happens in a later round using WeatherStation lat/lng.
 */
class SingaporeRainfallProvider implements WeatherProvider
{
    /**
     * @param  array{endpoint:string, timezone?:string}  $config
     */
    public function __construct(
        protected array $config,
    ) {
    }

    public function key(): string
    {
        return 'sg';
    }

    public function region(): string
    {
        return 'Singapore';
    }

    public function fetchRainfall(): WeatherFetchResult
    {
        $endpoint = $this->config['endpoint'] ?? '';
        $timezone = $this->config['timezone'] ?? config('app.timezone');

        $response = Http::timeout(15)->retry(2, 500)->acceptJson()->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException("Rainfall API returned HTTP {$response->status()}.");
        }

        $json = $response->json();

        if (! is_array($json) || ($json['code'] ?? null) !== 0 || ! isset($json['data'])) {
            $msg = is_array($json) ? ($json['errorMsg'] ?? 'unexpected payload') : 'non-JSON response';
            throw new RuntimeException("Rainfall API error: {$msg}");
        }

        $data = $json['data'];

        $stations = [];
        foreach (($data['stations'] ?? []) as $station) {
            if (! isset($station['id'])) {
                continue;
            }
            $location = $station['location'] ?? [];
            $stations[] = new WeatherStationData(
                code: (string) $station['id'],
                name: (string) ($station['name'] ?? $station['id']),
                latitude: isset($location['latitude']) ? (float) $location['latitude'] : null,
                longitude: isset($location['longitude']) ? (float) $location['longitude'] : null,
            );
        }

        $readings = [];
        foreach (($data['readings'] ?? []) as $block) {
            if (! isset($block['timestamp'])) {
                continue;
            }
            $observedAt = Carbon::parse($block['timestamp'])->setTimezone($timezone);
            foreach (($block['data'] ?? []) as $point) {
                if (! isset($point['stationId'])) {
                    continue;
                }
                $readings[] = new WeatherReadingData(
                    stationCode: (string) $point['stationId'],
                    observedAt: $observedAt->copy(),
                    value: (float) ($point['value'] ?? 0),
                );
            }
        }

        return new WeatherFetchResult(
            stations: $stations,
            readings: $readings,
            readingType: (string) ($data['readingType'] ?? 'rainfall'),
            unit: (string) ($data['readingUnit'] ?? 'mm'),
        );
    }
}
