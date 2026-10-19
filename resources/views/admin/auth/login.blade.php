@extends('admin.auth.shell')
@section('title', 'Sign in')
@section('content')
    <h1 class="text-2xl font-extrabold text-white">Sign in to the newsroom</h1>
    <p class="mt-1 text-sm text-[var(--color-ink-400)]">Authorised Doogsan Media staff only.</p>
    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-[var(--color-alarm)]/50 bg-[color-mix(in_oklab,var(--color-alarm)_10%,transparent)] px-3 py-2 text-sm text-[#ffc9c4]">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="{{ route('admin.login.attempt') }}" class="mt-6 space-y-4">
        @csrf
        <div><label class="label" for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input !py-2.5"></div>
        <div><label class="label" for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password" class="input !py-2.5"></div>
        <label class="flex items-center gap-2 text-sm text-[var(--color-ink-300)]"><input type="checkbox" name="remember" class="check"> Keep me signed in on this device</label>
        <button type="submit" class="btn btn-primary w-full !py-2.5">Sign in</button>
    </form>
    <p class="mt-8 text-xs text-[var(--color-ink-500)]">Sign-in attempts are rate-limited and recorded in the audit log.</p>
@endsection
