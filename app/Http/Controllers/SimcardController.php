<?php

namespace App\Http\Controllers;

use App\Http\Resources\SimcardResource;
use App\Http\Resources\TelcoResource;
use App\Models\Simcard;
use App\Models\Telco;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SimcardController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('Simcard/Index', [
            'simcards' => SimcardResource::collection(
                Simcard::query()
                    ->when($request->code, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->phone_number, function($query, $search) {
                        $query->where('phone_number', 'LIKE', "%{$search}%");
                    })
                    ->when($request->telco_id, function($query, $search) {
                        $query->where('telco_id', $search);
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'telcos' => TelcoResource::collection(Telco::orderBy('name')->get()),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Simcard::create($request->all());

        return redirect()->route('simcards');
    }

    public function update(Request $request, $zoneId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $simcard = Simcard::findOrFail($zoneId);
        $simcard->update($request->all());

        return redirect()->route('simcards');
    }

    public function delete($zoneId)
    {
        $simcard = Simcard::findOrFail($zoneId);
        $simcard->delete();

        return redirect()->route('simcards');
    }
}
