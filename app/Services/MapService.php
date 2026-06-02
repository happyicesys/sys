<?php

namespace App\Services;
use App\Models\Map;
use App\Models\Maps\Google;
use App\Models\Maps\Onemap;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapService
{
    public function getMapApiKeyByUser($user)
    {
        // $apiKey = $user->operator?->map_api_key;
        $apiKey = env('GOOGLE_MAPS_API_KEY');

        return $apiKey;
    }

    public function getMapService(string $name): Map
    {
        $map = Map::where('name', $name)->first();

        if ($map) {
            return $map;
        }

        switch ($name) {
            case 'onemap':
                return new Onemap();
            case 'google':
                return new Google();
            default:
                throw new \Exception('Map service not found');
        }
    }

    public function getMapServiceEndpoint(string $name): string
    {
        return $this->getMapService($name)->getEndpoint();
    }

    public function getMapServiceAddressParams(string $name): array
    {
        return $this->getMapService($name)->getAddressParams();
    }

    /**
     * Address search via the Google Geocoding API (server-side proxy).
     *
     * Returns results normalized to the SAME keys the OneMap response uses
     * (ADDRESS, BLK_NO, ROAD_NAME, BUILDING, POSTAL, LATITUDE, LONGTITUDE),
     * so the frontend SearchAddressInput / onAddressSelected mapping works
     * unchanged regardless of provider. Used for deployments with
     * MAP_PROVIDER=google (e.g. the Indonesia instance).
     *
     * @param  string  $query
     * @param  string|null  $countryCode  optional ISO-3166 alpha-2 to bias results
     */
    public function searchAddressGoogle(string $query, ?string $countryCode = null): array
    {
        $key = env('GOOGLE_MAPS_API_KEY');
        if (empty($key) || trim($query) === '') {
            return [];
        }

        try {
            $params = [
                'address' => $query,
                'key'     => $key,
            ];
            if (!empty($countryCode)) {
                $params['components'] = 'country:' . strtoupper($countryCode);
            }

            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', $params);

            if (!$response->successful()) {
                return [];
            }

            $results = $response->json('results', []);

            return collect($results)->map(function ($result) {
                $components = collect($result['address_components'] ?? []);
                $component = function (string $type) use ($components) {
                    $match = $components->first(fn ($c) => in_array($type, $c['types'] ?? [], true));
                    return $match['long_name'] ?? '';
                };

                $location = $result['geometry']['location'] ?? [];
                $lat = $location['lat'] ?? '';
                $lng = $location['lng'] ?? '';

                return [
                    'ADDRESS'    => $result['formatted_address'] ?? '',
                    'BLK_NO'     => $component('street_number'),
                    'ROAD_NAME'  => $component('route'),
                    // Google has no clean "building name"; premise is the closest.
                    'BUILDING'   => $component('premise'),
                    'POSTAL'     => $component('postal_code'),
                    'LATITUDE'   => $lat,
                    // Frontend reads the (mis-spelled) LONGTITUDE key; include the
                    // correctly-spelled one too for safety.
                    'LONGTITUDE' => $lng,
                    'LONGITUDE'  => $lng,
                ];
            })->values()->all();
        } catch (\Throwable $e) {
            Log::warning('Google geocoding failed for query "' . $query . '": ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Geocode a Singapore postcode using the OneMap API.
     * Returns ['latitude' => float|null, 'longitude' => float|null].
     */
    public function geocodePostcodeSG(string $postcode): array
    {
        try {
            $response = Http::timeout(5)->get('https://www.onemap.gov.sg/api/common/elastic/search', [
                'searchVal'      => $postcode,
                'returnGeom'     => 'Y',
                'getAddrDetails' => 'Y',
                'pageNum'        => 1,
            ]);

            if ($response->successful()) {
                $results = $response->json('results', []);
                if (!empty($results)) {
                    $first = $results[0];
                    return [
                        'latitude'  => isset($first['LATITUDE'])  ? (float) $first['LATITUDE']  : null,
                        'longitude' => isset($first['LONGITUDE']) ? (float) $first['LONGITUDE'] : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OneMap geocoding failed for postcode ' . $postcode . ': ' . $e->getMessage());
        }

        return ['latitude' => null, 'longitude' => null];
    }
}