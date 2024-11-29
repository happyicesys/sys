<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $request->merge([
            'classname' => $request->classname ? $request->classname : get_class(new Product()),
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        return Inertia::render('Category/Index', [
            'categories' => CategoryResource::collection(
                Category::with([
                    'categoryGroup'
                    ])
                    ->when($request->classname, function($query, $search) {
                        $query->where('classname', $search);
                    })
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->categoryGroups, function($query, $search) {
                        $query->whereHas('categoryGroup', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::query()
                    ->when($request->classname, function($query, $search) {
                        $query->where('classname', $search);
                    })
                    ->orderBy('name')
                    ->get()
                ),
            'classname' => $request->classname,
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $request->merge([
            'classname' => $request->classname ? $request->classname : get_class(new Product()),
        ]);
        Category::create($request->all());

        return redirect()->back();
    }

    public function update(Request $request, $categoryId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $category = Category::findOrFail($categoryId);
        $category->update($request->all());

        return redirect()->back();
    }

    public function delete($categoryId)
    {
        $category = Category::findOrFail($categoryId);
        $category->delete();

        return redirect()->back();
    }
}
