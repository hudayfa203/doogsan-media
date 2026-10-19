@extends('layouts.admin')
@section('title', 'Roles & Permissions')

@section('content')
<x-admin.page-header eyebrow="Administration" title="Roles & Permissions" subtitle="Tick what each role may do. Super Administrator always has every permission. Changes apply on the next page load." />

<form method="post" action="{{ route('admin.roles.update') }}">
    @csrf @method('PUT')
    <div class="panel overflow-x-auto">
        <table class="table">
            <thead class="sticky top-0 z-10 bg-[var(--color-ink-900)]">
                <tr>
                    <th class="min-w-[16rem]">Permission</th>
                    @foreach ($roles as $r)
                        <th class="text-center !normal-case !tracking-normal" title="{{ $r->description }}">
                            <span class="block text-[0.6875rem] font-extrabold text-[var(--color-ink-100)]">{{ $r->name }}</span>
                            <span class="num text-[0.625rem] font-normal text-[var(--color-ink-500)]">{{ $r->users_count }} {{ \Illuminate\Support\Str::plural('user', $r->users_count) }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr class="bg-[var(--color-ink-850)]">
                    <td class="text-xs font-bold text-[var(--color-amber)]">Require two-factor sign-in</td>
                    @foreach ($roles as $r)
                        <td class="text-center"><input type="checkbox" name="requires_2fa[{{ $r->id }}]" value="1" @checked($r->requires_2fa) class="check" aria-label="{{ $r->name }} requires 2FA"></td>
                    @endforeach
                </tr>
                @foreach ($permissions as $group => $perms)
                    <tr><td colspan="{{ $roles->count() + 1 }}" class="!pt-5 text-[0.625rem] font-extrabold uppercase tracking-[.14em] text-[var(--color-ink-500)]">{{ \Illuminate\Support\Str::headline($group) }}</td></tr>
                    @foreach ($perms as $p)
                        <tr>
                            <td><span class="text-[var(--color-ink-100)]">{{ $p->label }}</span> <code class="num ml-1 text-[0.625rem] text-[var(--color-ink-500)]">{{ $p->key }}</code></td>
                            @foreach ($roles as $r)
                                <td class="text-center">
                                    @if ($r->key === 'super-admin')
                                        <span class="text-[var(--color-signal)]" title="Always granted">●</span>
                                    @else
                                        <input type="checkbox" name="grants[{{ $r->id }}][]" value="{{ $p->id }}" @checked($r->permissions->contains('id', $p->id)) class="check" aria-label="{{ $r->name }}: {{ $p->label }}">
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="sticky bottom-0 mt-4 flex justify-end border-t border-[var(--color-ink-700)] bg-[var(--color-ink-950)]/90 py-3 backdrop-blur">
        <button class="btn btn-primary">Save permissions</button>
    </div>
</form>

<section class="panel mt-8 max-w-xl">
    <div class="panel-head"><h2>Add a role</h2></div>
    <form method="post" action="{{ route('admin.roles.store') }}" class="panel-body space-y-3">
        @csrf
        <div><label class="label">Role name</label><input name="name" required maxlength="60" class="input" placeholder="e.g. Sports Desk"></div>
        <div><label class="label">Description</label><input name="description" maxlength="190" class="input"></div>
        <button class="btn btn-secondary">Create role</button>
    </form>
</section>
@endsection
