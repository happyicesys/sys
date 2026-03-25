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
                    ->with([
                        'operator',
                        'telco',
                        'vends',
                    ])
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vend_code, function($query, $search) {
                        $query->whereHas('vends', function($query) use ($search) {
                            $query->where('code', 'LIKE', "%{$search}%");
                        });
                    })
                    ->when($request->phone_number, function($query, $search) {
                        $query->where('phone_number', 'LIKE', "%{$search}%");
                    })
                    ->when($request->msisdn, function($query, $search) {
                        $query->where('msisdn', 'LIKE', "%{$search}%");
                    })
                    ->when($request->telco_id, function($query, $search) {
                        $query->where('telco_id', $search);
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        if ($search === 'vend_code') {
                            $query->orderBy(
                                \App\Models\Vend::select('code')
                                    ->whereColumn('simcard_id', 'simcards.id')
                                    ->limit(1),
                                filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'
                            );
                        } else {
                            $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                        }
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'telcos' => TelcoResource::collection(Telco::orderBy('name')->get()),
            'vends' => \App\Models\Vend::with(['vendPrefix', 'customer'])->select('id', 'code', 'simcard_id', 'name', 'vend_prefix_id', 'customer_id')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'telco_id' => 'required',
        ]);

        $simcard = Simcard::create($request->except('vend_id'));

        if ($request->vend_id) {
            \App\Models\Vend::where('id', $request->vend_id)->update(['simcard_id' => $simcard->id]);
        }

        return redirect()->route('simcards');
    }

    public function update(Request $request, $zoneId)
    {
        $request->validate([
            'code' => 'required',
            'telco_id' => 'required',
        ]);

        $simcard = Simcard::findOrFail($zoneId);
        $simcard->update($request->except('vend_id'));

        if ($request->has('vend_id')) {
            \App\Models\Vend::where('simcard_id', $simcard->id)->update(['simcard_id' => null]);
            if ($request->vend_id) {
                \App\Models\Vend::where('id', $request->vend_id)->update(['simcard_id' => $simcard->id]);
            }
        }

        return redirect()->route('simcards');
    }

    public function delete($zoneId)
    {
        $simcard = Simcard::findOrFail($zoneId);
        $simcard->delete();

        return redirect()->route('simcards');
    }
}
