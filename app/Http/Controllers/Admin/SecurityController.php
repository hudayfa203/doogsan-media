<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SecurityController extends Controller
{
    public function __construct(protected TwoFactor $totp) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $pendingSecret = $user->two_factor_secret && ! $user->two_factor_confirmed_at ? $user->two_factor_secret : null;

        return view('admin.security', [
            'user' => $user,
            'otpauth' => $pendingSecret ? $this->totp->otpauthUri($pendingSecret, $user->email) : null,
            'secret' => $pendingSecret,
            'recovery' => session('recovery_codes'),
        ]);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        AuditLog::record('security.password', 'Changed own password', $request->user());

        return back()->with('status', __('Password updated.'));
    }

    public function enable(Request $request)
    {
        $request->user()->forceFill([
            'two_factor_secret' => $this->totp->generateSecret(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->totp->verify($user->two_factor_secret, $request->input('code'))) {
            return back()->withErrors(['code' => __('That code is not valid. Check your phone’s clock and try again.')]);
        }

        $codes = $this->totp->recoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes])->save();
        $request->session()->put('2fa_passed', true);
        AuditLog::record('security.2fa_enabled', 'Enabled two-factor authentication', $user);

        return back()->with('recovery_codes', $codes)->with('status', __('Two-factor authentication is on. Store your recovery codes safely.'));
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        $user = $request->user();
        if ($user->requiresTwoFactor()) {
            return back()->withErrors(['password' => __('Your role requires two-factor authentication.')]);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('security.2fa_disabled', 'Disabled two-factor authentication', $user);

        return back()->with('status', __('Two-factor authentication disabled.'));
    }

    public function regenerate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 400);
        $codes = $this->totp->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return back()->with('recovery_codes', $codes);
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->hasTwoFactorEnabled() || $request->session()->get('2fa_passed')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor');
    }

    public function verifyChallenge(Request $request)
    {
        $request->validate(['code' => 'required|string|max:40']);
        $user = $request->user();
        $code = trim($request->input('code'));

        $ok = $this->totp->verify($user->two_factor_secret, $code);
        if (! $ok && in_array($code, $user->two_factor_recovery_codes ?? [], true)) {
            $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($user->two_factor_recovery_codes, [$code]))])->save();
            AuditLog::record('security.recovery_used', 'Signed in with a recovery code', $user);
            $ok = true;
        }

        if (! $ok) {
            return back()->withErrors(['code' => __('Invalid authentication code.')]);
        }

        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('admin.dashboard'));
    }
}
