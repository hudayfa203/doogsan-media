<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.users.index', [
            'users' => User::with('role', 'author')
                ->when($request->input('role'), fn ($q, $r) => $q->where('role_id', $r))
                ->when($request->input('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'roles' => Role::withCount('users')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true, 'preferred_locale' => 'en']), 'roles' => Role::orderBy('id')->get()]);
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::orderBy('id')->get()]);
    }

    protected function data(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => 'required|exists:roles,id',
            'preferred_locale' => 'required|in:en,so',
            'password' => [$user ? 'nullable' : 'required', Password::min(12)->mixedCase()->numbers()],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->data($request);
        $this->guardSuperAdmin($request, (int) $data['role_id']);
        $user = User::create($data + ['is_active' => $request->boolean('is_active', true)]);
        AuditLog::record('user.created', "Created account {$user->email} ({$user->role->name})", $user);

        return redirect()->route('admin.users.index')->with('status', __('Account created.'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->data($request, $user);
        $this->guardSuperAdmin($request, (int) $data['role_id'], $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $active = $request->boolean('is_active');
        if ($user->id === $request->user()->id && ! $active) {
            return back()->withErrors(['is_active' => __('You cannot disable your own account.')]);
        }
        $before = $user->role?->name;
        $user->update($data + ['is_active' => $active]);
        AuditLog::record('user.updated', "Updated {$user->email}".($before !== $user->fresh()->role?->name ? " role {$before} → {$user->role->name}" : ''), $user);

        return redirect()->route('admin.users.index')->with('status', __('Account saved.'));
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, __('You cannot delete your own account.'));
        $user->update(['is_active' => false]);
        AuditLog::record('user.disabled', "Disabled {$user->email}", $user);

        return back()->with('status', __('Account disabled (kept for the audit trail).'));
    }

    protected function guardSuperAdmin(Request $request, int $roleId, ?User $target = null): void
    {
        $super = Role::where('key', 'super-admin')->value('id');
        if (($roleId === $super || $target?->isSuperAdmin()) && ! $request->user()->isSuperAdmin()) {
            abort(403, __('Only a Super Administrator can grant or change that role.'));
        }
    }
}
