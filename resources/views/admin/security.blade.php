@extends('layouts.admin')
@section('title', 'Account security')

@section('content')
<x-admin.page-header eyebrow="Your account" title="Security" :subtitle="$user->name.' · '.$user->email" />

<div class="grid gap-6 xl:grid-cols-2">
    <section class="panel">
        <div class="panel-head">
            <h2>Two-factor authentication</h2>
            @if ($user->hasTwoFactorEnabled())<span class="pill pill-published">On</span>@elseif ($secret)<span class="pill pill-editing">Setting up</span>@else<span class="pill pill-draft">Off</span>@endif
        </div>
        <div class="panel-body space-y-4">
            @if ($recovery)
                <div class="rounded-lg border border-[var(--color-amber)]/50 bg-[color-mix(in_oklab,var(--color-amber)_10%,transparent)] p-4" x-data="{ copied: false }">
                    <p class="text-sm font-bold text-[#ffd98a]">Your recovery codes — shown once</p>
                    <p class="mt-1 text-xs text-[var(--color-ink-300)]">Each code signs you in once if you lose your phone. Store them in a password manager or print them.</p>
                    <ul class="num mt-3 grid grid-cols-2 gap-1.5 text-sm text-white" x-ref="codes">
                        @foreach ($recovery as $code)<li class="rounded bg-[var(--color-ink-850)] px-2 py-1">{{ $code }}</li>@endforeach
                    </ul>
                    <button type="button" class="btn btn-secondary btn-sm mt-3" @click="navigator.clipboard.writeText(@js(implode("\n", $recovery))); copied = true" x-text="copied ? 'Copied' : 'Copy codes'"></button>
                </div>
            @endif

            @if ($user->hasTwoFactorEnabled())
                <p class="text-sm text-[var(--color-ink-300)]">Signing in requires a 6-digit code from your authenticator app. Enabled {{ $user->two_factor_confirmed_at?->diffForHumans() }}. {{ count($user->two_factor_recovery_codes ?? []) }} recovery codes left.</p>
                <div class="flex flex-wrap gap-3">
                    <form method="post" action="{{ route('admin.security.2fa.recovery') }}" data-confirm="Create new recovery codes? Your old codes stop working.">@csrf<button class="btn btn-secondary btn-sm">New recovery codes</button></form>
                </div>
                @unless ($user->requiresTwoFactor())
                    <form method="post" action="{{ route('admin.security.2fa.disable') }}" class="border-t border-[var(--color-ink-800)] pt-4" x-data="{ open: false }">
                        @csrf
                        <button type="button" class="btn btn-ghost btn-sm" @click="open = !open" x-show="!open">Turn off two-factor…</button>
                        <div x-show="open" x-cloak class="flex flex-wrap items-end gap-2">
                            <div><label class="label">Confirm with your password</label><input type="password" name="password" required class="input !w-64" autocomplete="current-password"></div>
                            <button class="btn btn-danger">Turn off</button>
                        </div>
                    </form>
                @else
                    <p class="hint">Your role requires two-factor authentication, so it can’t be turned off.</p>
                @endunless
            @elseif ($secret)
                <ol class="list-decimal space-y-1 pl-5 text-sm text-[var(--color-ink-300)]">
                    <li>Open an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy).</li>
                    <li>Scan this QR code, or enter the key by hand.</li>
                    <li>Type the 6-digit code the app shows.</li>
                </ol>
                <div class="flex flex-wrap items-center gap-5">
                    <div x-data="qr(@js($otpauth))" class="rounded-lg bg-white p-2"><canvas x-ref="canvas" width="200" height="200" aria-label="Two-factor QR code"></canvas></div>
                    <div>
                        <p class="label">Setup key</p>
                        <code class="num block select-all break-all rounded bg-[var(--color-ink-850)] px-3 py-2 text-sm tracking-widest text-white">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
                    </div>
                </div>
                <form method="post" action="{{ route('admin.security.2fa.confirm') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div><label class="label">6-digit code</label><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required class="input num !w-40 text-center text-lg tracking-[.3em]" autofocus></div>
                    <button class="btn btn-primary">Confirm and turn on</button>
                </form>
            @else
                <p class="text-sm text-[var(--color-ink-300)]">Protect your account with a code from your phone in addition to your password. Strongly recommended for everyone who can publish.</p>
                @if ($user->requiresTwoFactor())<p class="text-sm font-semibold text-[#ffd98a]">Your role requires two-factor authentication.</p>@endif
                <form method="post" action="{{ route('admin.security.2fa.enable') }}">@csrf<button class="btn btn-primary">Set up two-factor</button></form>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Change password</h2></div>
        <form method="post" action="{{ route('admin.security.password') }}" class="panel-body space-y-4">
            @csrf
            <div><label class="label">Current password</label><input type="password" name="current_password" required class="input" autocomplete="current-password"></div>
            <div><label class="label">New password</label><input type="password" name="password" required class="input" autocomplete="new-password"><p class="hint">At least 12 characters with upper- and lower-case letters and a number.</p></div>
            <div><label class="label">Repeat new password</label><input type="password" name="password_confirmation" required class="input" autocomplete="new-password"></div>
            <button class="btn btn-secondary">Update password</button>
        </form>
    </section>
</div>
@endsection
