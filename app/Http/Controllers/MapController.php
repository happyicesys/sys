<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendResource;
use App\Models\Vend;
use App\Services\MapService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MapController extends Controller
{
    /**
     * Provider-aware address search used by the SearchAddressInput component.
     * OneMap is called directly from the browser (CORS-open, no key), so this
     * endpoint only serves the Google path, where the request must be proxied
     * server-side to keep the API key private and avoid CORS.
     */
    public function search(Request $request, MapService $mapService)
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $provider = env('MAP_PROVIDER');

        if ($provider === 'google') {
            $countryCode = optional($request->user()?->operator?->country)->code;
            return response()->json([
                'results' => $mapService->searchAddressGoogle($query, $countryCode),
            ]);
        }

        // No supported server-side provider configured.
        return response()->json(['results' => []]);
    }

    public function index(Request $request)
    {
        $vends = Vend::with([
                    'customer.deliveryAddress',
                ])
                ->has('customer')
                ->orderBy('code')
                ->get();

        return Inertia::render('Map/Index', [
            'vends' => VendResource::collection($vends),
        ]);
    }
}
