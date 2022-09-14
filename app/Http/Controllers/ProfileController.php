<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;

        return Inertia::render('Profile/Index', [
            'vends' => VendResource::collection(
                Profile::with([
                    'address',
                    ])
                    ->when($request->name, fn($query, $input) => $query->where('name', 'LIKE', '%'.$input.'%'))
                    ->when($request->uen, fn($query, $input) => $query->where('uen', 'LIKE', '%'.$input.'%'))
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'filters' => $request->only(['name', 'uen', 'sortKey', 'sortBy', 'numberPerPage']),
        ]);
    }
}
