<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Admin;
use App\Support\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissionGroups' => PermissionMatrix::grouped(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        try {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'admin',
            ]);

            $role->syncPermissions($this->normalizeAndEnsurePermissions($request->permissions ?? []));

            return redirect()
                ->route('admin.roles.index')
                ->with('status', 'Role created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.roles.create')
                ->withInput()
                ->with('error', 'Failed to create role: '.$e->getMessage());
        }
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissionGroups' => PermissionMatrix::grouped(),
            'assignedAdmins' => Admin::whereRelation('roles', 'role_id', $role->id)->get(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        try {
            $role->update([
                'name' => $request->name,
            ]);

            $role->syncPermissions($this->normalizeAndEnsurePermissions($request->permissions ?? []));

            if ($request->has('admin_ids')) {
                $role->users()->sync($request->admin_ids);
            }

            return redirect()
                ->route('admin.roles.edit', $role)
                ->with('status', 'Role updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.roles.edit', $role)
                ->withInput()
                ->with('error', 'Failed to update role: '.$e->getMessage());
        }
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            abort_if($role->name === PermissionMatrix::SUPER_ADMIN_ROLE, 403, 'Cannot delete super admin role.');

            $role->delete();

            return redirect()
                ->route('admin.roles.index')
                ->with('status', 'Role deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'Failed to delete role: '.$e->getMessage());
        }
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return array<int, string>
     */
    private function normalizeAndEnsurePermissions(array $permissions): array
    {
        $permissionNames = collect($permissions)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->unique()
            ->all();

        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'admin');
        }

        return $permissionNames;
    }
}
