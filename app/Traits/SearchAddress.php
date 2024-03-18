<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait SearchAddress{

    public $endpointUrl = 'https://www.onemap.gov.sg/api/common/elastic/search';

    public function getAddressResult($searchParams)
    {
        $response = Http::get($this->endpointUrl, [
            'searchVal' => $searchParams,
            'returnGeom' => 'Y',
            'getAddrDetails' => 'Y',
        ])->collect();

        $results = null;
        foreach($response as $searchResult)
        {
            if($searchResult and is_array($searchResult)) {
                $results = $searchResult[0];
            }
        }

        if($results and is_array($results)) {
            return [
                'block_num' => $results['BLK_NO'],
                'street_name' => $results['ROAD_NAME'],
                'building' => $results['BUILDING'],
                'full_address' => $results['ADDRESS'],
                'postcode' => $results['POSTAL'],
                'latitude' => $results['LATITUDE'],
                'longitude' => $results['LONGITUDE'],
            ];
        }
    }

}