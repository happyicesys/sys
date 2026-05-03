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