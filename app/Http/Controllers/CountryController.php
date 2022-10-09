<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Models\Country;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Country/Index', [
            'countries' => CountryResource::collection(
                Country::with([
                    'latestQuoteExchangeRate',
                    'quoteExchangeRates'
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
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'code' => 'required',
            'currency_name' => 'required',
            'currency_symbol' => 'required',
            'phone_code' => 'required',
        ]);

        Country::create($request->all());

        return redirect()->route('countries');
    }

    public function update(Request $request, $countryId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $country = Country::findOrFail($countryId);
        $country->update($request->all());

        return redirect()->route('countries');
    }

    public function updateExchangeRate(Request $request, $countryId)
    {
        $country = Country::findOrFail($countryId);
        $request->merge(['base_country_id' => auth()->user()->profile->baseCurrency->id]);
        $country->quoteExchangeRates()->create($request->all());

        return redirect()->route('countries');
    }

    public function delete($countryId)
    {
        $country = Country::findOrFail($countryId);
        $country->delete();

        return redirect()->route('countries');
    }
}
