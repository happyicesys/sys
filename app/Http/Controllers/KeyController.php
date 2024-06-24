<?php

namespace App\Http\Controllers;

use App\Http\Resources\KeyResource;
use App\Http\Resources\VendResource;
use App\Models\Key;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KeyController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('Key/Index', [
            'keys' => KeyResource::collection(
                Key::query()
                    ->with('vend')
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
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
            ),
            'unbindedVendOptions' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'customer'
                    ])
                    ->has('customer')
                    ->whereNull('key_id')
                    ->select(
                        'id',
                        'code',
                        'customer_id',
                        'name',
                    )
                    ->orderBy('code')
                    ->get()
                ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $key = Key::create($request->all());

        if($request->vend_id) {
            $vend = Vend::findOrFail($request->vend_id);
            $vend->update([
                'key_id' => $key->id,
            ]);
        }

        return redirect()->route('keys');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = Key::findOrFail($id);
        $model->update($request->all());

        if($request->vend_id) {
            $vend = Vend::findOrFail($request->vend_id);
            $vend->update([
                'key_id' => $key->id,
            ]);
        }

        return redirect()->route('keys');
    }

    public function delete($id)
    {
        $model = Key::findOrFail($id);
        $model->delete();

        return redirect()->route('keys');
    }
}
