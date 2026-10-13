<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CmsService
{
    const AVAILABLE_PCS_ENDPOINT = '/api/items/available-pcs';

    public function getCMSQtyAvailableApi()
    {
        $url = config('app.cms_url');

        if (! $url) {
            return [];
        }

        $url .= self::AVAILABLE_PCS_ENDPOINT;
        $response = Http::get($url);

        return $response ? $response->json() : [];
    }

    /**
     * CMS available pcs as [product code => qty], or null when CMS could not
     * be read (connect timeout, non-2xx, unparseable body). Never throws, so
     * a CMS outage cannot take a caller's own work down with it.
     * An unset CMS URL returns [] (nothing to read), as getCMSQtyAvailableApi does.
     */
    public function fetchQtyAvailableByCode(int $tries = 1, int $sleepMs = 0): ?array
    {
        $url = config('app.cms_url');
        if (! $url) {
            return [];
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->retry($tries, $sleepMs, throw: false)
                ->get($url.self::AVAILABLE_PCS_ENDPOINT);
        } catch (\Throwable $e) {
            Log::warning('cms available-pcs unreachable', ['error' => $e->getMessage()]);

            return null;
        }

        $rows = $response->successful() ? $response->json() : null;
        if (! is_array($rows)) {
            Log::warning('cms available-pcs bad response', ['status' => $response->status()]);

            return null;
        }

        $qtyByCode = [];
        foreach ($rows as $row) {
            if (isset($row['code'])) {
                $qtyByCode[$row['code']] = (int) ($row['qty'] ?? 0);
            }
        }

        return $qtyByCode;
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
        if (! $url || ! $personId) {
            return null;
        }

        $endpoint = rtrim($url, '/').'/api/person/'.$personId.'/owe-summary';
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

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }
}
