<?php

namespace App\Http\Controllers;

use App\Http\Resources\TelcoResource;
use App\Models\Telco;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TelcoController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Telco/Index', [
            'telcos' => TelcoResource::collection(
                Telco::query()
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

        Telco::create($request->all());

        return redirect()->route('telcos');
    }

    public function update(Request $request, $telcoId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $telco = Telco::findOrFail($telcoId);
        $telco->update($request->all());

        return redirect()->route('telcos');
    }

    public function delete($telcoId)
    {
        $telco = Telco::findOrFail($telcoId);
        $telco->delete();

        return redirect()->route('telcos');
    }
}
