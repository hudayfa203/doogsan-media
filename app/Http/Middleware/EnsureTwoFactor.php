<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with 2FA enabled must pass the challenge each session. Privileged
 * roles must enrol when DOOGSAN_ENFORCE_2FA=true.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasTwoFactorEnabled() && ! $request->session()->get('2fa_passed')) {
            return redirect()->route('admin.2fa.challenge');
        }

        if ($user && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()
            && ! $request->routeIs('admin.security*', 'admin.logout')) {
            return redirect()->route('admin.security')->with('warning', __('Your role requires two-factor authentication. Please enable it to continue.'));
        }

        return $next($request);
    }
}
