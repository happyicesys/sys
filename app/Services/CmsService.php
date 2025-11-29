<?php

namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class CmsService
{

    const AVAILABLE_PCS_ENDPOINT = '/api/items/available-pcs';

    public function getCMSQtyAvailableApi()
    {
        $url = config('app.cms_url');

        if (!$url) {
            return [];
        }

        $url .= self::AVAILABLE_PCS_ENDPOINT;
        $response = Http::get($url);
        return $response ? $response->json() : [];
    }
}