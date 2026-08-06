<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Http\Resources\OperatorResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Models\Country;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Scopes\ProductAccessProductScope;
use App\Models\User;
use App\Support\ProductAccess;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read users']);
    }

    public function index(Request $request)
    {
        $request->merge([
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
            'operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id,
        ]);

        return Inertia::render('User/Index', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
            'users' => UserResource::collection(
                User::with([
                    'operator',
                    'phoneCountry',
                    'roles',
                    'vends:id,code,name',
                    'vends.customer:id,code,name',
                ])
                ->selectRaw('users.*')
                ->selectRaw('(SELECT roles.name FROM roles JOIN model_has_roles ON model_has_roles.role_id = roles.id WHERE model_has_roles.model_id = users.id AND model_has_roles.model_type = "App\\\Models\\\User" LIMIT 1) as role_name')
                ->when($request->is_active, function($query, $search) {
                    if($search != 'all') {
                        $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                    }
                })
                ->when($request->name, function($query, $search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->when($request->email, function($query, $search) {
                    $query->where('email', 'LIKE', "%{$search}%");
                })
                ->when($request->operator_id, function($query, $search) {
                    if($search != 'all') {
                        $query->where('operator_id', $search);
                    }
                })
                ->when($request->sortKey, function($query, $search) use ($request) {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                })
                ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                ->withQueryString()
            ),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'roles' => RoleResource::collection(Role::orderBy('name')->get()),
            'unbindedVends' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'customer:id,code,name'
                    ])->whereHas('users', function($query) use ($request) {
                        $query->whereNot('user_id', $request->user_id);
                    })
                    // ->whereNotIn('id', function($query) use ($request) {
                    //     $query->select('vend_id')
                    //         ->from('user_vend')
                    //         ->where('user_id', $request->user_id);
                    // })
                    ->orderBy('code')
                    ->get()
            )

        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email',
            'username' => 'nullable|required_without:email|unique:users,username',
            'password' => 'required',
            'operator_id' => 'required',
            'alias' => 'nullable|string|max:50',
        ]);
        $user = new User();
        $user->fill($request->all());
        $user->profile_id = 1;
        $user->save();

        $role = Role::find($request->role_id);
        if($role) {
            $user->assignRole($role->name);
        }

        return redirect()->route('users');
    }

    public function selfIndex()
    {
        return Inertia::render('User/Self/Form', [
            'user' => UserResource::make(
                auth()->user()
            )
        ]);
    }

    public function edit($id)
    {
        $user = User::with([
            'phoneCountry',
            'roles',
            'vends:id,code,name,customer_id',
            'vends.customer:id,code,name,person_id,virtual_customer_code,virtual_customer_prefix',
            // withoutGlobalScope: Product carries ProductAccessProductScope and
            // global scopes DO apply to eager loads. Without this, an admin who is
            // themselves product-restricted loads a TRUNCATED bound list, and the
            // sync() in update() then deletes every grant they could not see.
            // Must stay stripped in step with the unbindedProducts prop below.
            'accessProducts' => fn ($query) => $query
                ->withoutGlobalScope(ProductAccessProductScope::class)
                ->select('products.id', 'products.code', 'products.name'),
        ])->findOrFail($id);

        // "Access Product(s)": the subject's OPERATOR list is a hard ceiling -
        // it caps both the dropdown below and what update() will accept.
        $productCeiling = ProductAccess::operatorCeiling($user->operator_id);

        return Inertia::render('User/Edit', [
            'countries' => CountryResource::collection(
                Country::query()
                    ->orderBy('sequence')
                    ->orderBy('name')
                    ->get()
            ),
            'user' => UserResource::make($user),
            'operators' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'roles' => RoleResource::collection(Role::orderBy('name')->get()),  // Ensure roles are correctly retrieved
            'type' => 'update',
            'unbindedVends' => fn () =>
                VendResource::collection(
                    Vend::with([
                        'customer:id,code,name'
                    ])
                    ->where('operator_id', $user->operator_id)
                    ->whereHas('customer', function($query) use ($user) {
                        $query->where('is_active', true);
                    })
                    ->orderBy('code')
                    ->select('id', 'code', 'name', 'customer_id')
                    ->get()
                ),
            // Deliberately withoutGlobalScope(ProductAccessProductScope): an
            // ADMIN who is themselves product-restricted must still be able to
            // grant the full range to someone else. The operator boundary is
            // still enforced (OperatorProductFilterScope stays on, plus the
            // explicit operator_id filter), and update() re-clamps server-side.
            'unbindedProducts' => fn () =>
                ProductResource::collection(
                    Product::withoutGlobalScope(ProductAccessProductScope::class)
                        ->where('products.operator_id', $user->operator_id)
                        ->where('is_active', true)
                        ->when($productCeiling !== null, fn ($query) => $query->whereIn('products.id', $productCeiling))
                        ->orderBy('code')
                        ->get(['id', 'code', 'name'])
                ),
            'operatorProductCeiling' => $productCeiling === null ? null : [
                'operatorName' => $user->operator?->name,
                'products' => Product::withoutGlobalScopes()
                    ->whereIn('id', $productCeiling)
                    ->orderBy('code')
                    ->get(['id', 'code', 'name']),
            ],
            // 'unbindedCustomers' => fn () =>
            //     CustomerResource::collection(
            //         Customer::with([
            //             'vend:id,code,name'
            //         ])
            //         ->where('operator_id', $user->operator_id)
            //         ->whereHas('vend', function($query) use ($user) {
            //             $query->where('is_active', true);
            //         })
            //         ->orderBy('code')
            //         ->select('id', 'code', 'name', 'customer_id')
            //         ->get()
            //     ),
        ]);
    }


    public function selfUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email,'.$id,
            'username' => 'nullable|required_without:email|unique:users,username,'.$id,
            'password' => 'nullable|confirmed',
        ]);

        if($request->password and $request->password_confirmation) {
            $validated = $request->only('name', 'email', 'username', 'password');
        }else {
            $validated = $request->only('name', 'email', 'username');
        }

        $user = User::findOrFail($id);

        $user->update($validated);

        return redirect()->route('self');
    }

    public function toggleActivateDeactivate($id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return redirect()->route('users');
    }

    public function update(Request $request, $userId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email,'.$userId,
            'phone_number' => 'nullable|numeric',
            'username' => 'nullable|required_without:email|unique:users,username,'.$userId,
            'password' => 'nullable',
            'alias' => 'nullable|string|max:50',
            'product_access_mode' => 'nullable|in:all,list',
            'user.data.access_products' => 'nullable|array',
            'user.data.access_products.*.id' => 'integer',
        ]);

        if($request->password) {
            $validated = $request->only('name', 'alias', 'email', 'username', 'password', 'operator_id', 'phone_country_id', 'phone_number');
        }else {
            $validated = $request->only('name', 'alias', 'email', 'username', 'operator_id', 'phone_country_id', 'phone_number');
        }

        $user = User::findOrFail($userId);

        // Capture BEFORE update(): $validated can contain a new operator_id, and
        // every clamp below must validate the posted ids against the operator the
        // bindings were CHOSEN under, not the one being moved to. Clamping against
        // the new operator would silently detach every machine and product in the
        // same save that moves a user between operators.
        $originalOperatorId = $user->operator_id;

        $user->update($validated);

        // role update
        $role = Role::find($request->role_id);
        if($role) {
            $user->roles()->detach();
            $user->assignRole($role->name);
        }

        // vend list sync
        //
        // The BEFORE state is read from the pivot, NOT from $request->vends.
        // User/Edit.vue posts `vends` out of useForm(), which deep-clones
        // props.user.data at mount; the page then saves with preserveState and
        // redirects back to itself, so onMounted never re-runs and that clone
        // stays frozen at the first-load value. A second save without a manual
        // reload would therefore diff against a stale baseline: re-attaching an
        // already-bound machine (user_vend has NO unique key, so it duplicates)
        // and silently swallowing the removal of anything added since mount.
        // Global scopes deliberately LEFT ON: edit() eager-loads `vends` with
        // them applied, so an operator-scoped admin is shown a TRUNCATED list.
        // Reading the raw pivot here would put machines they cannot even see
        // into $originalVends, and the diff below would then detach every one
        // of them on the next save. live user_vend already holds cross-operator
        // rows, so this is not hypothetical. Same scope in, same scope out.
        //
        // Guarded on the list actually being posted. Now that $originalVends is
        // the LIVE pivot rather than an echo of the request, a POST that omits
        // user.data.vends would diff the real bindings against an empty list and
        // detach every one of them. Only User/Edit.vue sends this shape.
        $postsVendList = is_array($request->input('user.data.vends'));

        $originalVends = $postsVendList ? $user->vends()->pluck('vends.id') : collect();
        $editedVends = collect($request->input('user.data.vends', []))->map(function ($vend) {
            return is_array($vend) ? ($vend['id'] ?? null) : $vend;
        })->filter();

        $removeVends = $originalVends->diff($editedVends);

        // Clamp NEW bindings to machines belonging to this user's operator - the
        // edit screen filters the dropdown, but nothing stopped a hand-rolled POST
        // binding another operator's machine.
        //
        // Deliberately applied to ADDITIONS ONLY. user_vend legitimately contains
        // cross-operator rows today (5 of 10 on live), and clamping the whole
        // edited list would silently detach them on any save - even a name change.
        // Removals stay driven purely by what the admin actually took off the list.
        $addVends = $editedVends->diff($originalVends)->intersect(
            Vend::withoutGlobalScopes()->where('operator_id', $originalOperatorId)->pluck('id')
        );

        if($removeVends) {
            foreach($removeVends as $removeVend) {
                $user->vends()->detach($removeVend);
            }
        }
        if($addVends) {
            foreach($addVends as $addVend) {
                $user->vends()->attach($addVend);
            }
        }

        // "Access Product(s)" sync.
        //
        // GUARDED on an explicit marker that ONLY User/Edit.vue sends.
        //
        // /users/{id}/update is shared with User/Form.vue (the modal on the users
        // list), which builds its form as {...getDefaultForm(), ...props.user} -
        // i.e. it spreads the whole UserResource row. So every field the resource
        // emits, product_access_mode included, is posted from there too, and no
        // resource-derived field can distinguish the two screens. That modal also
        // posts `user` FLAT (no data wrapper), so user.data.access_products reads
        // as [] there - an unguarded sync() would wipe the user's whole allow-list
        // because someone edited a phone number.
        if ($request->boolean('manage_product_access')) {
            $ceiling = ProductAccess::operatorCeiling($originalOperatorId);

            $postedProductIds = collect($request->input('user.data.access_products', []))
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique();

            // Rows already bound are kept as-is; only NEW ids are clamped. Same
            // reasoning as the machine list above - a grant that predates a
            // product moving operator (or an operator's ceiling shrinking) must
            // not vanish because someone opened the page. It cannot grant
            // anything either way: ProductAccess::forUser() re-intersects with
            // the live operator ceiling on every read, so a stale pivot row is
            // inert until it becomes valid again.
            $existingProductIds = DB::table('product_user')
                ->where('user_id', $user->id)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id);

            $grantableProductIds = Product::withoutGlobalScopes()
                ->where('operator_id', $originalOperatorId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            $productIds = $postedProductIds->filter(
                fn ($id) => $existingProductIds->contains($id)
                    || (
                        $grantableProductIds->contains($id)
                        && ($ceiling === null || in_array($id, $ceiling, true))
                    )
            );

            // sync() DELETES every pivot row not in the posted list, and
            // edit() loads the bound list through OperatorProductFilterScope -
            // so a product that has since moved to another operator is invisible
            // on the screen, is therefore not posted back, and would be dropped
            // by an admin who only came in to change a phone number. Re-add
            // exactly the rows the screen could not have shown. Same
            // additions-only philosophy as the machine list above.
            $visibleToEditor = $user->accessProducts()
                ->withoutGlobalScope(ProductAccessProductScope::class)
                ->pluck('products.id')
                ->map(fn ($id) => (int) $id);

            $user->accessProducts()->sync(
                $productIds->merge($existingProductIds->diff($visibleToEditor))
                    ->unique()
                    ->values()
                    ->all()
            );

            $user->product_access_mode = $request->input('product_access_mode') === ProductAccess::MODE_LIST
                ? ProductAccess::MODE_LIST
                : ProductAccess::MODE_ALL;
            $user->save();

            ProductAccess::flush($user->id);
        }

        // return redirect()->route('users');
        return redirect()->route('users.edit', [$userId]);
    }

    public function delete($userId)
    {
        $user = User::findOrFail($userId);
        $user->delete();

        return redirect()->route('users');
    }

    public function bindVend(Request $request)
    {
        $user = User::findOrFail($request->operator_id);
        $user->vends()->attach($request->vend_id);

        return redirect()->route('users');
    }

    public function unbindVend(Request $request)
    {
        $user = User::findOrFail($request->operator_id);
        $user->vends()->detach($request->vend_id);

        return redirect()->route('users');
    }
}
