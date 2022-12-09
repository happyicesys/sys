<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    public function indexPermission(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Permission/Index', [
            'permissions' => PermissionResource::collection(
                Permission::query()
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

    public function createPermission(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Permission::create($request->all());

        return redirect()->route('permissions');
    }

    public function updatePermission(Request $request, $permissionId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $permission = Permission::findOrFail($permissionId);
        $permission->update($request->all());

        return redirect()->route('permissions');
    }

    public function deletePermission($permissionId)
    {
        $permission = Permission::findOrFail($permissionId);
        $permission->delete();

        return redirect()->route('permissions');
    }

    public function indexRole(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Role/Index', [
            'roles' => RoleResource::collection(
                Role::query()
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

    public function createRole(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Role::create($request->all());

        return redirect()->route('roles');
    }

    public function updateRole(Request $request, $roleId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $role = PaymentMethod::findOrFail($roleId);
        $role->update($request->all());

        return redirect()->route('roles');
    }

    public function deleteRole($roleId)
    {
        $role = Role::findOrFail($roleId);
        $role->delete();

        return redirect()->route('roles');
    }

}
