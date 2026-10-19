@extends('layouts.admin')
@section('title', 'Sections')

@section('content')
<x-admin.page-header eyebrow="Content" title="Sections" subtitle="News sections with their own name and address in each edition. Order here sets the order in navigation and filters.">
    <a href="{{ route('admin.tags.index') }}" class="btn btn-secondary"><x-admin.icon name="tag" /> Tags</a>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><x-admin.icon name="plus" /> New section</a>
</x-admin.page-header>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th class="w-6"></th><th>Key</th>@foreach (editions() as $l => $e)<th>{{ $e['name'] }}</th>@endforeach<th>Edition</th><th>Parent</th><th class="text-right">Stories</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($categories as $c)
            <tr>
                <td><span class="block h-3 w-3 rounded-full" style="background: {{ $c->color ?: 'var(--color-ink-600)' }}"></span></td>
                <td class="font-mono text-xs text-[var(--color-ink-400)]">{{ $c->key }}</td>
                @foreach (editions() as $l => $e)
                    @php $t = $c->t($l); @endphp
                    <td lang="{{ $l }}">@if ($t)<a href="{{ lroute('section', ['slug' => $t->slug], $l) }}" target="_blank" class="font-semibold text-white hover:text-[var(--color-signal)]">{{ $t->name }}</a><p class="font-mono text-[0.6875rem] text-[var(--color-ink-500)]">/{{ $l }}/{{ $t->slug }}</p>@else<span class="text-[var(--color-ink-500)]">—</span>@endif</td>
                @endforeach
                <td class="text-xs">{{ $c->locale ? editions()[$c->locale]['name'].' only' : 'Both' }}</td>
                <td class="text-xs text-[var(--color-ink-400)]">{{ $c->parent?->nameFor('en') ?? '—' }}</td>
                <td class="num text-right">{{ number_format($c->articles_count) }}</td>
                <td class="whitespace-nowrap text-xs">
                    <span class="pill {{ $c->is_active ? 'pill-published' : 'pill-archived' }}">{{ $c->is_active ? 'Active' : 'Hidden' }}</span>
                    @if ($c->in_navigation)<span class="pill pill-new">In menu</span>@endif
                </td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ route('admin.categories.edit', $c) }}" class="btn btn-secondary btn-sm">Edit</a>
                    @unless ($c->articles_count)
                        <form method="post" action="{{ route('admin.categories.destroy', $c) }}" class="inline" data-confirm="Delete this section?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-6 text-center text-sm text-[var(--color-ink-400)]">No sections yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
