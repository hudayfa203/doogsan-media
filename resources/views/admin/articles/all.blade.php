@extends('layouts.admin')
@section('title', 'All articles')

@section('content')
<x-admin.page-header eyebrow="Articles management" title="All stories — both publications" subtitle="A combined index for administrators. Each story is edited in the newsroom it belongs to." />
<form class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Headline contains…" class="input !w-64">
    <select name="edition" class="select !w-auto"><option value="">Both editions</option>@foreach (editions() as $l => $e)<option value="{{ $l }}" @selected(request('edition') === $l)>{{ $e['name'] }}</option>@endforeach</select>
    <select name="status" class="select !w-auto"><option value="">Any status</option>@foreach (\App\Models\ArticleTranslation::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __('status.'.$s) }}</option>@endforeach</select>
    <button class="btn btn-secondary">Filter</button>
</form>
<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th>Edition</th><th>Story</th><th>Status</th><th>Section</th><th>Byline</th><th>Updated</th></tr></thead>
        <tbody>
        @forelse ($stories as $t)
            <tr>
                <td><span class="rounded px-1.5 py-0.5 text-[0.625rem] font-extrabold {{ $t->locale === 'so' ? 'bg-[#5cb8ff]/15 text-[#a7d8ff]' : 'bg-[var(--color-signal)]/15 text-[var(--color-signal)]' }}">{{ strtoupper($t->locale) }}</span></td>
                <td class="max-w-lg"><a href="{{ story_edit_url($t->article_id, $t->locale) }}" class="font-semibold hover:text-[var(--color-signal)]">{{ $t->title }}</a></td>
                <td><x-admin.status :status="$t->status" /></td>
                <td class="text-[var(--color-ink-300)]">{{ $t->article?->category?->nameFor($t->locale) ?? '—' }}</td>
                <td class="text-[var(--color-ink-300)]">{{ $t->article?->author?->name ?? '—' }}</td>
                <td class="num text-xs text-[var(--color-ink-400)]">{{ $t->updated_at->diffForHumans(null, true) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-10 text-center text-[var(--color-ink-400)]">No stories yet in either newsroom.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $stories->links('admin.partials.pagination') }}</div>
@endsection
