<?php

namespace App\Http\Controllers;

use App\Http\Resources\KeyResource;
use App\Models\Key;
use App\Models\Vend;
use App\Support\VendCode;
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
                    ->with('vends')
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vend_codes, function ($query, $search) {
                        // Machine IDs carry a prefix for CityBox chillers ("C6002"), so the
                        // search runs through VendCode — it keeps the old comma-list and
                        // LIKE behaviour for plain numbers and adds prefixed terms.
                        $query->whereHas('vend', function ($query) use ($search) {
                            VendCode::whereSearch($query, (string) $search, contains: true);
                        });
                    })
                    ->when($request->sortKey, function ($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $key = Key::create($request->all());

        if ($request->vend_id) {
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

        if ($request->vend_id) {
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
