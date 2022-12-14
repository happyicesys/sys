<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendResource;
use App\Models\Country;
use App\Models\Operator;
use App\Models\Vend;
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
        // dd($request->all());
        return Inertia::render('Operator/Index', [
            'countries' => CountryResource::collection(Country::orderBy('sequence')->orderBy('name')->get()),
            'operators' => OperatorResource::collection(
                Operator::with([
                    'address',
                    'address.country',
                    'country',
                    'vends',
                    'vends.latestVendBinding.customer',
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
            'unbindedVends' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'latestVendBinding.customer'
                    ])->whereDoesntHave('operators', function($query) use ($request) {
                        $query->where('operators.id', '!=', $request->operator_id);
                    })
                    ->orderBy('code')
                    ->get()
                )
            ,
            'operator' => fn() => OperatorResource::make(
                Operator::with([
                    'address',
                    'address.country',
                    'country',
                    'vends',
                    'vends.latestVendBinding.customer',
                ])
                ->when($request->name, function($query, $search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->when($request->id, function($query, $search) {
                    $query->where('id', $search);
                })
                ->first()
            )
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
        $request->validate([
            'name' => 'required',
        ]);

        $operator = Operator::findOrFail($operatorId);
        $operator->update($request->all());

        if($request->has('operator')) {
            $operator->vends()->detach();
            if($request->operator['vends']) {
                foreach($request->operator['vends'] as $vend) {
                    $operator->vends()->attach($vend['id']);
                }
            }
        }

        return redirect()->route('operators');
    }

    public function delete($operatorId)
    {
        $operator = Operator::findOrFail($operatorId);
        $operator->delete();

        return redirect()->route('operators');
    }

    public function bindVend(Request $request)
    {
        $operator = Operator::findOrFail($request->operator_id);
        $operator->vends()->attach($request->vend_id);

        return redirect()->route('operators');
    }

    public function unbindVend(Request $request)
    {
        $operator = Operator::findOrFail($request->operator_id);
        $operator->vends()->detach($request->vend_id);

        return redirect()->route('operators');
    }
}
