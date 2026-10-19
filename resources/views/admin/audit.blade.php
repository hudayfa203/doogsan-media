@extends('layouts.admin')
@section('title', 'Audit log')

@section('content')
<x-admin.page-header eyebrow="Administration" title="Audit log" subtitle="Who changed what, and when. Entries cannot be edited or deleted from the newsroom." />

<form class="mb-4 flex flex-wrap gap-2">
    <select name="action" class="select !w-auto"><option value="">All activity</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ \Illuminate\Support\Str::headline($a) }}</option>@endforeach</select>
    <select name="user" class="select !w-auto"><option value="">Everyone</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    <button class="btn btn-secondary">Filter</button>
    @if (request()->hasAny(['action', 'user']))<a href="{{ route('admin.audit') }}" class="btn btn-ghost">Clear</a>@endif
</form>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th>When (EAT)</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]" title="{{ $log->created_at?->toIso8601String() }}">{{ local_time($log->created_at)->format('d M Y H:i:s') }}</td>
                <td class="whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                <td><code class="num rounded bg-[var(--color-ink-850)] px-1.5 py-0.5 text-[0.6875rem] text-[var(--color-signal)]">{{ $log->action }}</code></td>
                <td class="max-w-xl text-[var(--color-ink-200)]">{{ $log->description }}</td>
                <td class="num text-xs text-[var(--color-ink-500)]">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-12 text-center text-sm text-[var(--color-ink-400)]">No activity recorded{{ request()->hasAny(['action', 'user']) ? ' for this filter' : ' yet' }}.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4 text-sm">{{ $logs->links('admin.partials.pagination') }}</div>
@endsection
