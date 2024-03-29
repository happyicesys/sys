<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Models\Operator;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;
        $operatorId = auth()->user()->operator_id == 1 ? $request->operator_id : auth()->user()->operator_id;

        // dd($operatorId);
        return Inertia::render('User/Index', [
            'users' => UserResource::collection(
                User::with([
                    'operator',
                    'roles',
                    'vends:id,code,name',
                    'vends.customer:id,code,name',
                ])
                ->when($request->name, function($query, $search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->when($request->email, function($query, $search) {
                    $query->where('email', 'LIKE', "%{$search}%");
                })
                ->when($operatorId, function($query, $search) {
                    $query->where('operator_id', $search);
                })
                ->when($sortKey, function($query, $search) use ($sortBy) {
                    $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                })
                ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
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

    public function update(Request $request, $userId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email,'.$userId,
            'username' => 'nullable|required_without:email|unique:users,username,'.$userId,
            'password' => 'nullable',
        ]);

        if($request->password) {
            $validated = $request->only('name', 'email', 'username', 'password', 'operator_id');
        }else {
            $validated = $request->only('name', 'email', 'username', 'operator_id');
        }

        $user = User::findOrFail($userId);

        $user->update($validated);

        // role update
        $role = Role::find($request->role_id);
        if($role) {
            $user->roles()->detach();
            $user->assignRole($role->name);
        }

        // vend list sync
        $originalVends = collect($request->vends)->transform(function($vend) {
            return $vend['id'];
        });
        $editedVends = collect($request->user['vends'])->transform(function($vend) {
            return $vend['id'];
        });

        $removeVends = $originalVends->diff($editedVends);
        $addVends = $editedVends->diff($originalVends);

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

        return redirect()->route('users');
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
