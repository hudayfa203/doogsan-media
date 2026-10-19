@extends('layouts.admin')
@section('title', 'Tags')

@section('content')
<x-admin.page-header eyebrow="Content" title="Tags" subtitle="Tags are created by editors while writing. Each edition keeps its own tags. Remove duplicates or tags no longer needed here.">
    <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">← Sections</a>
</x-admin.page-header>

<form class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Tag contains…" class="input !w-64">
    <select name="locale" class="select !w-auto"><option value="">Both editions</option>@foreach (editions() as $l => $e)<option value="{{ $l }}" @selected(request('locale') === $l)>{{ $e['name'] }}</option>@endforeach</select>
    <button class="btn btn-secondary">Filter</button>
</form>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th>Tag</th><th>Edition</th><th>Address</th><th class="text-right">Stories</th><th>Created</th><th></th></tr></thead>
        <tbody>
        @forelse ($tags as $tag)
            <tr>
                <td class="font-semibold text-white" lang="{{ $tag->locale }}">{{ $tag->name }}</td>
                <td class="text-[0.625rem] font-extrabold uppercase">{{ $tag->locale }}</td>
                <td class="font-mono text-xs text-[var(--color-ink-400)]">{{ $tag->slug }}</td>
                <td class="num text-right">{{ number_format($tag->translations_count) }}</td>
                <td class="num text-xs text-[var(--color-ink-400)]">{{ $tag->created_at ? local_time($tag->created_at)->format('d M Y') : '—' }}</td>
                <td class="text-right"><form method="post" action="{{ route('admin.tags.destroy', $tag) }}" data-confirm="Remove this tag from all stories?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Remove</button></form></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-sm text-[var(--color-ink-400)]">No tags found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $tags->links('admin.partials.pagination') }}</div>
</div>
@endsection
