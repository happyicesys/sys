<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;

trait SearchAddress{

    public $endpointUrl = 'https://developers.onemap.sg/commonapi/search';

    public function getAddressResult($searchParams)
    {
        $response = Http::get($this->endpointUrl, [
            'searchVal' => $searchParams,
            'returnGeom' => 'Y',
            'getAddrDetails' => 'Y',
        ])->collect();

        foreach($response as $searchResult)
        {
            if(is_array($searchResult)) {
                $results = $searchResult[0];
            }
        }

        if(is_array($results)) {
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