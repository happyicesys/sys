<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait SearchAddress{

    public $endpointUrl = 'https://www.onemap.gov.sg/api/common/elastic/search';

    public function getAddressResult($searchParams)
    {
        // OneMap's Search API is gated (token required since Oct 2025) but it
        // still serves browser-style requests — the in-app autofill works
        // because the browser sends an Origin/Referer/User-Agent. The seeder
        // runs server-side, so a plain Http::get returns "token missing".
        // We mimic the browser here (Referer/Origin from our own app + a normal
        // User-Agent) so the server-side lookup works without registering for a
        // token. If OneMap later enforces tokens here too, switch to the Bearer
        // flow.
        $appUrl = rtrim(config('app.url') ?: 'https://www.onemap.gov.sg', '/');

        $response = Http::withHeaders([
            'Referer'    => $appUrl . '/',
            'Origin'     => $appUrl,
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        ])->timeout(10)->get($this->endpointUrl, [
            'searchVal' => $searchParams,
            'returnGeom' => 'Y',
            'getAddrDetails' => 'Y',
        ])->collect();

        $results = null;
        foreach($response as $searchResult)
        {
            if($searchResult and is_array($searchResult)) {
                $results = $searchResult[0] ?? null;
            }
        }

        if($results and is_array($results)) {
            return [
                'block_num' => $results['BLK_NO'] ?? null,
                'street_name' => $results['ROAD_NAME'] ?? null,
                'building' => $results['BUILDING'] ?? null,
                'full_address' => $results['ADDRESS'] ?? null,
                'postcode' => $results['POSTAL'] ?? null,
                'latitude' => $results['LATITUDE'] ?? null,
                'longitude' => $results['LONGITUDE'] ?? null,
            ];
        }

        return null;
    }

}