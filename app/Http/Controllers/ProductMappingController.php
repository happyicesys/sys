<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductMappingResource;
use App\Models\ProductMapping;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductMappingController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('ProductMapping/Index', [
            'productMappings' => ProductMappingResource::collection(
                ProductMapping::query()
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

        ProductMapping::create($request->all());

        return redirect()->route('product-mappings');
    }

    public function update(Request $request, $productMappingId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $productMapping = ProductMapping::findOrFail($productMappingId);
        $productMapping->update($request->all());

        return redirect()->route('product-mappings');
    }

    public function delete($productMappingId)
    {
        $productMapping = ProductMapping::findOrFail($productMappingId);
        $productMapping->delete();

        return redirect()->route('product-mappings');
    }
}
