<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashlessProviderResource;
use App\Models\CashlessProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashlessProviderController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('CashlessProvider/Index', [
            'cashlessProviders' => CashlessProviderResource::collection(
                CashlessProvider::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        CashlessProvider::create($request->all());

        return redirect()->route('cashless-providers');
    }

    public function update(Request $request, $zoneId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $cashlessProvider = CashlessProvider::findOrFail($zoneId);
        $cashlessProvider->update($request->all());

        return redirect()->route('cashless-providers');
    }

    public function delete($zoneId)
    {
        $cashlessProvider = CashlessProvider::findOrFail($zoneId);
        $cashlessProvider->delete();

        return redirect()->route('cashless-providers');
    }
}
