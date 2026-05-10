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
                    // bindings_count powers the "disable Delete when in-use"
                    // guard on Tag/Index.vue. withCount adds a virtual column
                    // computed via a single COUNT subquery per row — cheap.
                    ->withCount('tagBindings')
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
        // Normalise to the same snake_case form the Tag model's `name` mutator
        // will store, BEFORE running uniqueness checks. Without this step,
        // "VIP Customer" and "vip customer" both get snake-cased to
        // "vip_customer" by the mutator and would collide silently at insert
        // time (or worse, slip through if the DB has no unique constraint).
        $request->merge([
            'name' => $this->normaliseTagName($request->input('name')),
        ]);

        // Tag names must be unique WITHIN a polymorphic scope (classname), not
        // globally — otherwise a "vip" Product Label would block creation of a
        // "vip" Customer Tag, even though they live on different models.
        $request->validate([
            'name' => [
                'required',
                \Illuminate\Validation\Rule::unique('tags', 'name')
                    ->where(fn ($q) => $q->where('classname', $request->classname)),
            ],
        ]);

        Tag::create($request->all());

        return redirect()->back();
    }

    public function update(Request $request, $tagId)
    {
        // Same normalisation + scoping rule as create().
        $request->merge([
            'name' => $this->normaliseTagName($request->input('name')),
        ]);

        $request->validate([
            'name' => [
                'required',
                \Illuminate\Validation\Rule::unique('tags', 'name')
                    ->ignore($tagId)
                    ->where(fn ($q) => $q->where('classname', $request->classname)),
            ],
        ]);

        $tag = Tag::findOrFail($tagId);
        $tag->update($request->all());

        return redirect()->back();
    }

    /**
     * Mirror the Tag model's `name` setter:
     *   - trim outer whitespace
     *   - collapse internal whitespace runs to a single underscore
     *   - lowercase
     * Validation calls this BEFORE checking uniqueness so users see a clear
     * "name already exists" error instead of a 500 from the DB.
     */
    protected function normaliseTagName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return strtolower(trim(preg_replace('/\s+/', '_', $value)));
    }

    public function delete($tagId)
    {
        $tag = Tag::findOrFail($tagId);

        // Prohibit deletion while the tag is still bound to any record.
        // Previously this method cascade-deleted all tag_bindings rows, which
        // silently stripped tags off live customers/products. We now reject
        // the delete and tell the user how many records still reference the
        // tag — they must unbind it from the owning model's edit page first.
        // Mirrors the "in-use guard" pattern used in VendPrefixController.
        $bindingsCount = $tag->tagBindings()->count();
        if ($bindingsCount > 0) {
            $modelLabel = $tag->classname
                ? substr(strrchr($tag->classname, '\\'), 1)
                : 'record';
            return redirect()->back()->withErrors([
                'delete' => "Cannot delete tag \"{$tag->name}\" — it is still attached to {$bindingsCount} {$modelLabel}(s). Remove the tag from those records first.",
            ]);
        }

        $tag->delete();

        return redirect()->back();
    }
}
