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

    /**
     * Fetch a person's outstanding-invoice ("Owe") summary from CMS.
     *
     * Returns an array shaped like:
     *   ['person_id' => 8384, 'total_owe' => 2700.0,
     *    'oldest_owe_date' => '2026-01-31', 'owe_count' => 18]
     * or null when the CMS URL is unset or the request fails.
     *
     * Used by cms:sync-loc-fee-remarks to build the "since yymmdd, owe $xx"
     * note for the Site Summary "Remarks for Loc Fees" field.
     */
    public function getPersonOweSummary($personId)
    {
        $url = config('app.cms_url');
        if (!$url || !$personId) {
            return null;
        }

        $endpoint = rtrim($url, '/') . '/api/person/' . $personId . '/owe-summary';
        // Match the SSL-verify gating used elsewhere when talking to CMS:
        // verify certs in production, skip in local/staging self-signed setups.
        $verify = app()->environment('production');

        try {
            $response = Http::withOptions(['verify' => $verify])
                ->timeout(20)
                ->acceptJson()
                ->get($endpoint);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }
}