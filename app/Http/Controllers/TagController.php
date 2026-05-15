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
        // For Customer-scoped tags we now keep `name` as the user-typed
        // display string and use a derived snake_case `slug` for uniqueness.
        // For every other classname we keep the legacy behaviour where the
        // `name` column itself stores the snake_case form (Vend/Product/etc.
        // code still matches labels by name) — see Tag::booted().
        $isDisplayNameScope = in_array(
            $request->classname,
            \App\Models\Tag::DISPLAY_NAME_CLASSNAMES,
            true
        );

        if ($isDisplayNameScope) {
            // Trim only — preserve the casing/spacing the user typed.
            $request->merge([
                'name' => $this->trimName($request->input('name')),
                // Pre-compute slug so we can validate uniqueness on it
                // BEFORE hitting the DB. Mirrors Tag::makeSlug().
                'slug' => \App\Models\Tag::makeSlug($request->input('name')),
            ]);

            $request->validate([
                'name' => ['required'],
                'slug' => [
                    'required',
                    \Illuminate\Validation\Rule::unique('tags', 'slug')
                        ->where(fn ($q) => $q->where('classname', $request->classname)),
                ],
            ]);
        } else {
            // Legacy path: normalise into snake_case and validate against
            // `name` (matches the existing Product / Campaign label tooling).
            $request->merge([
                'name' => $this->normaliseTagName($request->input('name')),
            ]);

            $request->validate([
                'name' => [
                    'required',
                    \Illuminate\Validation\Rule::unique('tags', 'name')
                        ->where(fn ($q) => $q->where('classname', $request->classname)),
                ],
            ]);
        }

        Tag::create($request->all());

        return redirect()->back();
    }

    public function update(Request $request, $tagId)
    {
        $isDisplayNameScope = in_array(
            $request->classname,
            \App\Models\Tag::DISPLAY_NAME_CLASSNAMES,
            true
        );

        if ($isDisplayNameScope) {
            $request->merge([
                'name' => $this->trimName($request->input('name')),
                'slug' => \App\Models\Tag::makeSlug($request->input('name')),
            ]);

            $request->validate([
                'name' => ['required'],
                'slug' => [
                    'required',
                    \Illuminate\Validation\Rule::unique('tags', 'slug')
                        ->ignore($tagId)
                        ->where(fn ($q) => $q->where('classname', $request->classname)),
                ],
            ]);
        } else {
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
        }

        $tag = Tag::findOrFail($tagId);
        $tag->update($request->all());

        return redirect()->back();
    }

    /**
     * Legacy snake_case normaliser used by non-Customer tag flows
     * (Product, Campaign, etc.) where `name` itself is the slug.
     * Kept identical to Tag::makeSlug() so validation and DB state agree.
     */
    protected function normaliseTagName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return strtolower(trim(preg_replace('/\s+/', '_', $value)));
    }

    /**
     * Trim outer whitespace and collapse internal whitespace runs to a
     * single space for display-name tag scopes — we preserve casing and
     * the user's wording, only tidying up obvious whitespace mistakes.
     */
    protected function trimName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return trim(preg_replace('/\s+/', ' ', $value));
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
