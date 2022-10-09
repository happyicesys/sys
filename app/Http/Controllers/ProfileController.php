<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\ProfileResource;
use App\Models\Country;
use App\Models\Profile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        // init
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Profile/Index', [
            'profiles' => ProfileResource::collection(
                Profile::with([
                    'address',
                    'address.country',
                    'baseCurrency',
                    'contact',
                    'contact.phoneCountry',
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->uen, function($query, $search) {
                        $query->where('uen', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get())
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'contact.phone_num' => 'required',
            'address.postcode' => 'required|digits:6',
            'address.unit_num' => 'required',
            'address.street_name' => 'required',
        ]);

        $profile = Profile::create($request->all());
        $profile->contact()->create($request->except(['contact.country'])['contact']);
        $profile->address()->create($request->except(['address.country'])['address']);

        return redirect()->route('profiles');
    }

    public function update(Request $request, $profileId)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required',
            'contact.phone_num' => 'required',
            'address.postcode' => 'required|digits:6',
            'address.unit_num' => 'required',
            'address.street_name' => 'required',
        ]);

        $profile = Profile::findOrFail($profileId);

        $profile->update($request->all());
        $profile->contact()->firstOrNew()->fill($request->except(['contact.country'])['contact'])->save();
        $profile->address()->firstOrNew()->fill($request->except(['address.country'])['address'])->save();
        // $profile->contact()->updateOrCreate($request->except(['contact.country'])['contact']);
        // $profile->address()->updateOrCreate($request->except(['address.country'])['address']);

        return redirect()->route('profiles');
    }

    public function delete($profileId)
    {
        $profile = Profile::findOrFail($profileId);
        $profile->delete();

        return redirect()->route('profiles');
    }
}
