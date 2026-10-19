@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<x-admin.page-header eyebrow="Administration" title="Users" subtitle="Newsroom accounts, their roles and which newsroom (Somali, English or both) they work in.">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-admin.icon name="plus" /> New account</a>
</x-admin.page-header>

<div class="mb-4 flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
    <a href="{{ route('admin.users.index', request()->only('q')) }}" class="tab {{ ! request('role') ? 'is-active' : '' }}">All <span class="num ml-1 text-[var(--color-ink-500)]">{{ $roles->sum('users_count') }}</span></a>
    @foreach ($roles as $r)
        @if ($r->users_count)
            <a href="{{ route('admin.users.index', ['role' => $r->id] + request()->only('q')) }}" class="tab {{ request('role') == $r->id ? 'is-active' : '' }}">{{ $r->name }} <span class="num ml-1 text-[var(--color-ink-500)]">{{ $r->users_count }}</span></a>
        @endif
    @endforeach
</div>

<form class="mb-4 flex flex-wrap gap-2">
    @if (request('role'))<input type="hidden" name="role" value="{{ request('role') }}">@endif
    <input name="q" value="{{ request('q') }}" placeholder="Name or email…" class="input !w-72">
    <button class="btn btn-secondary">Search</button>
</form>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th>Name</th><th>Role</th><th>Newsroom</th><th>2FA</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            @php $rooms = $u->newsroomEditions(); @endphp
            <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                <td>
                    <a href="{{ route('admin.users.edit', $u) }}" class="font-semibold text-white hover:text-[var(--color-signal)]">{{ $u->name }}</a>
                    <div class="text-xs text-[var(--color-ink-400)]">{{ $u->email }}@if ($u->author) · <span title="Linked journalist profile">byline: {{ $u->author->name }}</span>@endif</div>
                </td>
                <td class="text-[var(--color-ink-200)]">{{ $u->role?->name ?? '—' }}</td>
                <td>
                    @if (count($rooms) === count(editions()))
                        <span class="pill pill-draft">Both</span>
                    @else
                        @foreach ($rooms as $l)<span class="pill pill-scheduled">{{ editions()[$l]['name'] }}</span>@endforeach
                    @endif
                </td>
                <td>@if ($u->hasTwoFactorEnabled())<span class="pill pill-published">On</span>@elseif ($u->requiresTwoFactor())<span class="pill pill-editing">Required</span>@else<span class="text-xs text-[var(--color-ink-500)]">Off</span>@endif</td>
                <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ $u->last_login_at ? local_time($u->last_login_at)->format('d M Y H:i') : 'Never' }}</td>
                <td>@if ($u->is_active)<span class="text-xs font-bold text-[var(--color-signal)]">Active</span>@else<span class="text-xs font-bold text-[var(--color-ink-400)]">Disabled</span>@endif</td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-secondary btn-sm">Edit</a>
                    @if ($u->is_active && $u->id !== auth()->id())
                        <form method="post" action="{{ route('admin.users.destroy', $u) }}" class="inline" data-confirm="Disable {{ $u->name }}? They will no longer be able to sign in.">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Disable</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-12 text-center text-sm text-[var(--color-ink-400)]">No accounts match.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4 text-sm">{{ $users->links('admin.partials.pagination') }}</div>
@endsection
