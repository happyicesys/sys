<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendCriteriaResource;
use App\Models\VendCriteria;
use App\Traits\HasWeightage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendCriteriaController extends Controller
{
    use HasWeightage;

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'sequence';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('VendCriteria/Index', [
            'vendCriterias' => VendCriteriaResource::collection(
                VendCriteria::query()
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function update(Request $request, $vendCriteriaId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $vendCriteria = VendCriteria::findOrFail($vendCriteriaId);
        $vendCriteria->update($request->all());

        $this->recalculateAllWeightage(get_class($vendCriteria));

        return redirect()->route('vend-criterias');
    }
}
