@extends('layouts.admin')
@section('title', 'Pages')

@section('content')
<x-admin.page-header eyebrow="Content" title="Information Pages" subtitle="About, editorial policy, privacy, terms and advertising pages. Each edition has its own title, address and text." />

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th>Page</th>@foreach (editions() as $l => $e)<th>{{ $e['name'] }}</th>@endforeach<th>Footer</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        @forelse ($pages as $p)
            <tr>
                <td class="font-mono text-xs text-[var(--color-ink-400)]">{{ $p->key }}</td>
                @foreach (editions() as $l => $e)
                    @php $t = $p->t($l); @endphp
                    <td lang="{{ $l }}">
                        @if ($t)
                            <p class="font-semibold text-white">{{ $t->title }}</p>
                            <p class="flex items-center gap-2 text-[0.6875rem] text-[var(--color-ink-400)]">
                                <span class="pill {{ $t->is_published ? 'pill-published' : 'pill-draft' }}">{{ $t->is_published ? 'Published' : 'Hidden' }}</span>
                                @if ($t->is_published)<a href="{{ lroute('page', ['slug' => $t->slug], $l) }}" target="_blank" class="hover:text-[var(--color-signal)]">/{{ $l }}/…/{{ $t->slug }}</a>@endif
                            </p>
                        @else
                            <span class="pill pill-draft">Not written</span>
                        @endif
                    </td>
                @endforeach
                <td>{{ $p->show_in_footer ? 'Yes' : '—' }}</td>
                <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time($p->translations->max('updated_at') ?? $p->updated_at)->format('d M Y') }}</td>
                <td class="text-right"><a href="{{ route('admin.pages.edit', $p) }}" class="btn btn-secondary btn-sm">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-sm text-[var(--color-ink-400)]">No pages. Run the database seeder to create the standard pages.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
