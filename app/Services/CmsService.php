<?php

namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class CmsService
{

    const AVAILABLE_PCS_ENDPOINT = '/api/items/available-pcs';

    public function getCMSQtyAvailableApi()
    {
        $url = env('CMS_URL') . self::AVAILABLE_PCS_ENDPOINT;
        $response = Http::get($url);
        return $response ? $response->json() : [];
    }
}