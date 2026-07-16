<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendStickerResource;
use App\Models\VendSticker;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendStickerController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('VendSticker/Index', [
            'vendStickers' => VendStickerResource::collection(
                VendSticker::query()
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'permissions' => [
                'create' => auth()->user()->can('create machine-stickers'),
                'update' => auth()->user()->can('update machine-stickers'),
                'delete' => auth()->user()->can('delete machine-stickers'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        VendSticker::create($request->all());

        return redirect()->route('machine-stickers');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $vendSticker = VendSticker::findOrFail($id);
        $vendSticker->update($request->all());

        return redirect()->route('machine-stickers');
    }

    public function delete($id)
    {
        $vendSticker = VendSticker::findOrFail($id);
        $vendSticker->delete();

        return redirect()->route('machine-stickers');
    }
}
