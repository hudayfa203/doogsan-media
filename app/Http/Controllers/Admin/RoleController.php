<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('id')->get(),
            'permissions' => Permission::orderBy('id')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request)
    {
        $grants = $request->validate(['grants' => 'array', 'grants.*' => 'array', 'grants.*.*' => 'exists:permissions,id', 'requires_2fa' => 'array'])['grants'] ?? [];

        foreach (Role::where('key', '!=', 'super-admin')->get() as $role) {
            $role->permissions()->sync($grants[$role->id] ?? []);
            $role->update(['requires_2fa' => (bool) ($request->input("requires_2fa.{$role->id}"))]);
        }
        Role::where('key', 'super-admin')->update(['requires_2fa' => (bool) $request->input('requires_2fa.'.Role::where('key', 'super-admin')->value('id'))]);
        AuditLog::record('roles.updated', 'Updated role permissions matrix');

        return back()->with('status', __('Permissions saved.'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:roles,name', 'description' => 'nullable|string|max:190']);
        $role = Role::create($data + ['key' => Str::slug($data['name'])]);
        AuditLog::record('roles.created', "Created role {$role->name}", $role);

        return back()->with('status', __('Role created — now grant its permissions.'));
    }
}
