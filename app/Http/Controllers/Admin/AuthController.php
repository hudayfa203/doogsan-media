<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            AuditLog::record('auth.failed', 'Failed sign-in for '.$credentials['email'], null, ['email' => $credentials['email']]);

            return back()->withErrors(['email' => __('These credentials do not match an active newsroom account.')])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuditLog::record('auth.login', "{$user->name} signed in", $user);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa_passed', false);

            return redirect()->route('admin.2fa.challenge');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', ($request->user()?->name ?? 'User').' signed out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
