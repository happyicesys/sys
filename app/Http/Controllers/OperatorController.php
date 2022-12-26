<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\OperatorResource;
use App\Models\Country;
use App\Models\Operator;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OperatorController extends Controller
{
    public function index(Request $request)
    {
        $timezones = DateTimeZone::listIdentifiers();
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Operator/Index', [
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'operators' => OperatorResource::collection(
                Operator::with([
                    'address',
                    'address.country',
                    'country',
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'timezones' => $timezones,
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Operator::create($request->all());

        return redirect()->route('operators');
    }

    public function update(Request $request, $operatorId)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required',
        ]);

        $operator = Operator::findOrFail($operatorId);
        $operator->update($request->all());

        return redirect()->route('operators');
    }

    public function delete($operatorId)
    {
        $operator = Operator::findOrFail($operatorId);
        $operator->delete();

        return redirect()->route('operators');
    }
}
