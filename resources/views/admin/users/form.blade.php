@extends('layouts.admin')
@section('title', $user->exists ? 'Edit '.$user->name : 'New account')

@section('content')
@php
    $room = old('newsroom', empty($user->newsrooms) ? 'both' : ($user->newsrooms[0] ?? 'both'));
@endphp
<x-admin.page-header eyebrow="Users" :title="$user->exists ? $user->name : 'New account'" :subtitle="$user->exists ? $user->email : 'Create a sign-in for a member of the newsroom.'">
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">← All users</a>
</x-admin.page-header>

<form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="grid gap-6 xl:grid-cols-[1fr_22rem]">
    @csrf @if ($user->exists) @method('PUT') @endif
    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Account</h2></div>
            <div class="panel-body grid gap-4 sm:grid-cols-2">
                <div><label class="label">Full name</label><input name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input"></div>
                <div><label class="label">Email (sign-in)</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="190" class="input" autocomplete="off"></div>
                <div>
                    <label class="label">{{ $user->exists ? 'New password' : 'Password' }}</label>
                    <input type="password" name="password" {{ $user->exists ? '' : 'required' }} class="input" autocomplete="new-password">
                    <p class="hint">At least 12 characters with upper- and lower-case letters and a number.{{ $user->exists ? ' Leave blank to keep the current password.' : ' Share it privately — the person should change it after first sign-in.' }}</p>
                </div>
                <div>
                    <label class="label">Admin interface language</label>
                    <select name="preferred_locale" class="select">
                        @foreach (editions() as $l => $e)<option value="{{ $l }}" @selected(old('preferred_locale', $user->preferred_locale) === $l)>{{ $e['name'] }}</option>@endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Newsroom</h2></div>
            <div class="panel-body">
                <p class="mb-3 text-sm text-[var(--color-ink-400)]">The Somali and English newsrooms are independent. Choose where this person writes and edits. Super Administrators always have access to both.</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (['so' => ['Somali Newsroom', 'Only Somali stories at /so'], 'en' => ['English Newsroom', 'Only English stories at /en'], 'both' => ['Both newsrooms', 'Somali and English']] as $val => [$lbl, $desc])
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-[var(--color-ink-600)] px-3 py-2.5 has-[:checked]:border-[var(--color-signal)]">
                            <input type="radio" name="newsroom" value="{{ $val }}" @checked($room === $val) class="check mt-0.5">
                            <span><span class="block text-sm font-bold text-white">{{ $lbl }}</span><span class="text-xs text-[var(--color-ink-400)]">{{ $desc }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Role</h2></div>
            <div class="panel-body space-y-2">
                @foreach ($roles as $r)
                    @continue($r->key === 'super-admin' && ! auth()->user()->isSuperAdmin() && $user->role_id !== $r->id)
                    <label class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 hover:bg-[var(--color-ink-850)]">
                        <input type="radio" name="role_id" value="{{ $r->id }}" @checked((int) old('role_id', $user->role_id) === $r->id) required class="check mt-0.5">
                        <span><span class="block text-sm font-semibold text-white">{{ $r->name }}</span>@if ($r->description)<span class="text-xs text-[var(--color-ink-400)]">{{ $r->description }}</span>@endif</span>
                    </label>
                @endforeach
                <a href="{{ route('admin.roles.index') }}" class="hint inline-block hover:text-[var(--color-signal)]">What can each role do? →</a>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Status</h2></div>
            <div class="panel-body space-y-3">
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="check"> Account active (can sign in)</label>
                @if ($user->exists)
                    <dl class="space-y-1 text-xs text-[var(--color-ink-400)]">
                        <div>Two-factor: <span class="text-[var(--color-ink-200)]">{{ $user->hasTwoFactorEnabled() ? 'enabled' : 'not enabled' }}</span></div>
                        <div>Last sign-in: <span class="num text-[var(--color-ink-200)]">{{ $user->last_login_at ? local_time($user->last_login_at)->format('d M Y H:i').' EAT' : 'never' }}</span></div>
                        <div>Created: <span class="num text-[var(--color-ink-200)]">{{ local_time($user->created_at)->format('d M Y') }}</span></div>
                    </dl>
                @endif
            </div>
        </section>

        <button class="btn btn-primary w-full">{{ $user->exists ? 'Save account' : 'Create account' }}</button>
    </div>
</form>
@endsection
