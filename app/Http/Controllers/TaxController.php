<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaxResource;
use App\Models\Tax;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Tax/Index', [
            'taxes' => TaxResource::collection(
                Tax::query()
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

        Tax::create($request->all());

        return redirect()->route('taxes');
    }

    public function update(Request $request, $taxId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $tax = Tax::findOrFail($taxId);
        $tax->update($request->all());

        return redirect()->route('taxes');
    }

    public function delete($taxId)
    {
        $tax = Tax::findOrFail($taxId);
        $tax->delete();

        return redirect()->route('taxes');
    }
}
