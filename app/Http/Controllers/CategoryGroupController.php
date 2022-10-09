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

class CategoryGroupController extends Controller
{
    public function index(Request $request)
    {
        $classname = $request->classname ? $request->classname : get_class(new Customer());
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('CategoryGroup/Index', [
            'categoryGroups' => CategoryGroupResource::collection(
                CategoryGroup::with([
                    'categories'
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->categories, function($query, $search) {
                        $query->whereHas('categories', function($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'categories' => CategoryResource::collection(
                Category::query()
                    ->where('classname', $classname)
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

        CategoryGroup::create($request->all());

        return redirect()->route('category-groups');
    }

    public function update(Request $request, $categoryGroupId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $categoryGroup = CategoryGroup::findOrFail($categoryGroupId);
        $categoryGroup->update($request->all());

        return redirect()->route('category-groups');
    }

    public function delete($categoryGroupId)
    {
        $categoryGroup = CategoryGroup::findOrFail($categoryGroupId);
        $categoryGroup->delete();

        return redirect()->route('category-groups');
    }
}
