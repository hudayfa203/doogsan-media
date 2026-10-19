@extends('layouts.admin')
@section('title', 'Translations')

@section('content')
<x-admin.page-header eyebrow="Editorial" title="Translations" subtitle="English and Somali are independent publications. A story appears in both only when an editor deliberately creates and approves a second version." />

<section class="panel mb-6">
    <div class="panel-head"><h2>Machine-assisted versions awaiting approval</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ $pending->count() }}</span></div>
    <table class="table"><tbody>
        @forelse ($pending as $t)
            <tr><td class="w-12 text-[0.625rem] font-extrabold uppercase">{{ $t->locale }}</td><td>{{ $t->title }}</td><td><x-admin.status :status="$t->status" /></td>
                <td class="text-right"><a href="{{ story_edit_url($t->article_id, $t->locale) }}" class="btn btn-secondary btn-sm">Review</a></td></tr>
        @empty
            <tr><td class="text-[var(--color-ink-400)]">Nothing awaiting approval.</td></tr>
        @endforelse
    </tbody></table>
</section>

<section class="panel overflow-x-auto">
    <div class="panel-head"><h2>Stories published in both editions</h2></div>
    <table class="table">
        <thead><tr><th>Originated in</th><th>English</th><th>Somali</th></tr></thead>
        <tbody>
        @forelse ($paired as $a)
            <tr>
                <td class="text-[0.625rem] font-extrabold uppercase">{{ $a->edition }}</td>
                @foreach (['en', 'so'] as $l)
                    @php $tr = $a->translations->firstWhere('locale', $l); @endphp
                    <td class="max-w-sm"><a href="{{ story_edit_url($a, $l) }}" class="block truncate font-semibold hover:text-[var(--color-signal)]">{{ $tr?->title }}</a>@if ($tr)<x-admin.status :status="$tr->status" />@endif</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="3" class="py-8 text-center text-[var(--color-ink-400)]">No story is currently published in both editions.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<div class="mt-4">{{ $paired->links('admin.partials.pagination') }}</div>
@endsection
