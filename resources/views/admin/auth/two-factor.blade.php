@extends('admin.auth.shell')
@section('title', 'Two-factor authentication')
@section('content')
    <h1 class="text-2xl font-extrabold text-white">Two-factor check</h1>
    <p class="mt-1 text-sm text-[var(--color-ink-400)]">Enter the 6-digit code from your authenticator app, or one of your recovery codes.</p>
    @if ($errors->any())<div class="mt-5 rounded-lg border border-[var(--color-alarm)]/50 px-3 py-2 text-sm text-[#ffc9c4]">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('admin.2fa.verify') }}" class="mt-6 space-y-4">
        @csrf
        <input name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus class="input num !py-3 text-center !text-2xl tracking-[0.4em]" placeholder="000000">
        <button class="btn btn-primary w-full !py-2.5">Verify</button>
    </form>
    <form method="post" action="{{ route('admin.logout') }}" class="mt-4">@csrf<button class="text-xs font-bold text-[var(--color-ink-400)] hover:text-white">Sign out</button></form>
@endsection
