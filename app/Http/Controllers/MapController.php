<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendResource;
use App\Models\Vend;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MapController extends Controller
{
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
