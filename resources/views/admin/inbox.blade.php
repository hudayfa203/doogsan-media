@extends('layouts.admin')
@section('title', 'Messages & Tips')

@section('content')
<x-admin.page-header eyebrow="Audience" title="Messages & Tips" subtitle="Contact-form messages and confidential news tips from both editions. Tip contents are encrypted and every opening is recorded in the audit log." />

<div class="mb-4 flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
    <a href="{{ route('admin.inbox.index', ['tab' => 'messages']) }}" class="tab {{ $tab === 'messages' ? 'is-active' : '' }}">Messages @if ($counts['messages'])<span class="pill pill-submitted ml-1">{{ $counts['messages'] }} new</span>@endif</a>
    <a href="{{ route('admin.inbox.index', ['tab' => 'tips']) }}" class="tab {{ $tab === 'tips' ? 'is-active' : '' }}">News tips @if ($counts['tips'])<span class="pill pill-live ml-1">{{ $counts['tips'] }} new</span>@endif</a>
</div>

@php $statuses = $tab === 'tips' ? ['new', 'reviewing', 'assigned', 'closed'] : ['new', 'read', 'replied', 'archived']; @endphp
<div class="mb-4 flex flex-wrap gap-2 text-xs">
    <a href="{{ route('admin.inbox.index', ['tab' => $tab]) }}" class="btn btn-sm {{ ! request('status') ? 'btn-secondary' : 'btn-ghost' }}">All</a>
    @foreach ($statuses as $s)<a href="{{ route('admin.inbox.index', ['tab' => $tab, 'status' => $s]) }}" class="btn btn-sm {{ request('status') === $s ? 'btn-secondary' : 'btn-ghost' }}">{{ ucfirst($s) }}</a>@endforeach
</div>

<section class="panel overflow-x-auto">
    @if ($tab === 'tips')
        <table class="table">
            <thead><tr><th>Tip</th><th>Ed.</th><th>Location</th><th>Anonymity</th><th>Status</th><th>Assigned</th><th>Received</th><th></th></tr></thead>
            <tbody>
            @forelse ($tips as $tip)
                <tr class="{{ $tip->status === 'new' ? 'font-semibold' : '' }}">
                    <td class="num">#{{ $tip->id }} <span class="ml-1 text-xs font-normal text-[var(--color-ink-400)]">contents hidden until opened</span></td>
                    <td class="text-[0.625rem] font-extrabold uppercase">{{ $tip->locale }}</td>
                    <td class="text-xs">{{ $tip->location ?: '—' }}</td>
                    <td class="text-xs">{{ $tip->wants_anonymity ? 'Requested' : 'No' }}</td>
                    <td><span class="pill {{ $tip->status === 'new' ? 'pill-live' : ($tip->status === 'closed' ? 'pill-archived' : 'pill-editing') }}">{{ ucfirst($tip->status) }}</span></td>
                    <td class="text-xs">{{ $tip->assignee?->name ?? '—' }}</td>
                    <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time($tip->created_at)->format('d M H:i') }}</td>
                    <td class="text-right"><a href="{{ route('admin.inbox.tip', $tip) }}" class="btn btn-secondary btn-sm">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-6 text-sm text-[var(--color-ink-400)]">No tips.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $tips->links('admin.partials.pagination') }}</div>
    @else
        <table class="table">
            <thead><tr><th>From</th><th>Subject</th><th>Dept.</th><th>Ed.</th><th>Status</th><th>Received</th></tr></thead>
            <tbody>
            @forelse ($messages as $m)
                <tr class="{{ $m->status === 'new' ? 'font-semibold' : '' }}">
                    <td><a href="{{ route('admin.inbox.message', $m) }}" class="text-white hover:text-[var(--color-signal)]">{{ $m->name }}</a><p class="text-xs font-normal text-[var(--color-ink-400)]">{{ $m->email }}</p></td>
                    <td><a href="{{ route('admin.inbox.message', $m) }}" class="hover:text-[var(--color-signal)]">{{ \Illuminate\Support\Str::limit($m->subject ?: \Illuminate\Support\Str::limit($m->message, 60), 80) }}</a></td>
                    <td class="text-xs">{{ ucfirst($m->department ?? 'general') }}</td>
                    <td class="text-[0.625rem] font-extrabold uppercase">{{ $m->locale }}</td>
                    <td><span class="pill {{ $m->status === 'new' ? 'pill-submitted' : ($m->status === 'replied' ? 'pill-published' : 'pill-archived') }}">{{ ucfirst($m->status) }}</span></td>
                    <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time($m->created_at)->format('d M H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-sm text-[var(--color-ink-400)]">No messages.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $messages->links('admin.partials.pagination') }}</div>
    @endif
</section>
@endsection
