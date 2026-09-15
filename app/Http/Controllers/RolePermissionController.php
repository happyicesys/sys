<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Raw Spatie role / permission CRUD (/roles, /permissions). Neither page is
 * linked from the sidebar and RolePermissionSyncSeeder is the source of truth
 * for what exists, so this is a superadmin-only escape hatch: renaming
 * `superadmin` or deleting `update refunds` from here would lock staff out
 * until the seeder is re-run (audit M2-05). The seeder defines no
 * roles/permissions tuple to gate on, and adding one just for this page would
 * hand it to whichever roles held the tuple — so the gate is the ROLE itself
 * (Spatie's `role` middleware, registered in app/Http/Kernel.php).
 */
class RolePermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:superadmin']);
    }

    public function indexPermission(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('Permission/Index', [
            'permissions' => PermissionResource::collection(
                Permission::query()
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function createPermission(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('permissions', 'name')->where('guard_name', 'web')],
        ]);

        Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        return redirect()->route('permissions');
    }

    public function updatePermission(Request $request, $permissionId)
    {
        $permission = Permission::findOrFail($permissionId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('permissions', 'name')->where('guard_name', $permission->guard_name)->ignore($permission->id)],
        ]);

        // Only the name is editable; guard_name stays what it was so a row can
        // never be moved off the `web` guard by a stray form field.
        $permission->update(['name' => $data['name']]);

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
                    ->when($request->name, function ($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function ($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function createRole(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ]);

        Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        return redirect()->route('roles');
    }

    public function updateRole(Request $request, $roleId)
    {
        // Was `PaymentMethod::findOrFail()` — an unimported class, i.e. this
        // endpoint fatalled on every call. Fixed to the Role it edits.
        $role = Role::findOrFail($roleId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', $role->guard_name)->ignore($role->id)],
        ]);

        $role->update(['name' => $data['name']]);

        return redirect()->route('roles');
    }

    public function deleteRole($roleId)
    {
        $role = Role::findOrFail($roleId);
        $role->delete();

        return redirect()->route('roles');
    }
}
