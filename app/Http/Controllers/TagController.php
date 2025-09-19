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
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
        ]);

        $classname = $request->classname;

        return Inertia::render('Tag/Index', [
            'classname' => $classname,
            'modelName' => $classname ? substr(strrchr($classname, '\\'), 1) : null,
            'tags' => TagResource::collection(
                Tag::with([
                    'tagBindings',
                    ])
                    ->when($request->classname, function($query, $search) {
                        $query->where('classname', $search);
                    })
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:tags,name',
        ]);

        Tag::create($request->all());

        return redirect()->back();
    }

    public function update(Request $request, $tagId)
    {
        $request->validate([
            'name' => 'required|unique:tags,name,'.$tagId,
        ]);

        $tag = Tag::findOrFail($tagId);
        $tag->update($request->all());

        return redirect()->back();
    }

    public function delete($tagId)
    {
        $tag = Tag::findOrFail($tagId);

        if($tag->tagBindings) {
            foreach($tag->tagBindings as $tagBinding) {
                $tagBinding->delete();
            }
        }
        $tag->delete();

        return redirect()->back();
    }
}
