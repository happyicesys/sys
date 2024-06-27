<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendSerialNumberResource;
use App\Http\Resources\VendResource;
use App\Models\VendSerialNumber;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendSerialNumberController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'code',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('VendSerialNumber/Index', [
            'vendSerialNumbers' => VendSerialNumberResource::collection(
                VendSerialNumber::query()
                    ->with('vend')
                    ->when($request->code, function($query, $search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vend_codes, function($query, $search) {
                        if(strpos($search, ',') !== false) {
                            $search = explode(',', $search);
                            $query->whereHas('vend', function($query) use ($search) {
                                $query->whereIn('code', $search);
                            });
                        }else {
                            $query->whereHas('vend', function($query) use ($search) {
                                $query->where('code', 'LIKE', "%{$search}%");
                            });
                        }
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            )
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $model = VendSerialNumber::create($request->all());

        return redirect()->route('vend-serial-numbers');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'code' => 'required',
        ]);

        $model = VendSerialNumber::findOrFail($id);
        $model->update($request->all());

        return redirect()->route('vend-serial-numbers');
    }

    public function delete($id)
    {
        $model = VendSerialNumber::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-serial-numbers');
    }
}
