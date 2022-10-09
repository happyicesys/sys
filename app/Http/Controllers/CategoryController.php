<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategoryGroupResource;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $classname = $request->classname ? $request->classname : get_class(new Customer());
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('Category/Index', [
            'categories' => CategoryResource::collection(
                Category::with([
                    'categoryGroup'
                    ])
                    ->when($classname, function($query, $search) {
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
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::query()
                    ->orderBy('name')
                    ->get()
            )
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Category::create($request->all());

        return redirect()->route('categories');
    }

    public function update(Request $request, $categoryId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $category = Category::findOrFail($categoryId);
        $category->update($request->all());

        return redirect()->route('categories');
    }

    public function delete($categoryId)
    {
        $category = Category::findOrFail($categoryId);
        $category->delete();

        return redirect()->route('categories');
    }
}
