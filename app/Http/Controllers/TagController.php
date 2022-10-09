<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\TagResource;
use App\Models\Customer;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Tag/Index', [
            'customers' => CustomerResource::collection(Customer::orderBy('code')->get()),
            'tags' => TagResource::collection(
                Tag::with([
                    'tagBindings',
                    'tagBindings.customer',
                    ])
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->customers, function($query, $search) {
                        $query->whereIn('id', $search);
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

        Tag::create($request->all());

        return redirect()->route('tags');
    }

    public function update(Request $request, $tagId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $tag = Tag::findOrFail($tagId);
        $tag->update($request->all());

        return redirect()->route('tags');
    }

    public function delete($tagId)
    {
        $tag = Tag::findOrFail($tagId);
        $tag->delete();

        return redirect()->route('tags');
    }
}
