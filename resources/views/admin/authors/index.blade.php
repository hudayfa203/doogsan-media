@extends('layouts.admin')
@section('title', 'Journalists')

@section('content')
<x-admin.page-header eyebrow="Content" title="Journalists & Contributors" subtitle="Public byline profiles shown on stories and the team page. Link a profile to a newsroom account so the journalist is added to their own stories automatically.">
    <a href="{{ route('admin.authors.create') }}" class="btn btn-primary"><x-admin.icon name="plus" /> New profile</a>
</x-admin.page-header>

<div class="panel overflow-x-auto">
    <table class="table">
        <thead><tr><th class="w-12"></th><th>Name</th><th>Role</th><th>Desk</th><th>Account</th><th class="text-right">Stories</th><th class="text-right">Views</th><th>Team page</th><th></th></tr></thead>
        <tbody>
        @forelse ($authors as $a)
            <tr>
                <td>@if ($a->photo)<img src="{{ $a->photo->url(480) }}" alt="" class="h-10 w-10 rounded-full object-cover">@else<span class="grid h-10 w-10 place-items-center rounded-full bg-[var(--color-ink-800)] text-xs font-extrabold text-[var(--color-ink-400)]">{{ mb_strtoupper(mb_substr($a->name, 0, 1)) }}</span>@endif</td>
                <td>
                    <a href="{{ lroute('author', ['slug' => $a->slug], 'en') }}" target="_blank" class="font-semibold text-white hover:text-[var(--color-signal)]">{{ $a->name }}</a>
                    @if ($a->is_sample)<span class="pill pill-editing ml-1">Sample</span>@endif
                    <p class="text-[0.6875rem] text-[var(--color-ink-500)]">{{ $a->email }}</p>
                </td>
                <td class="text-xs">{{ $a->title_en ?: '—' }}@if ($a->title_so)<p class="text-[var(--color-ink-500)]" lang="so">{{ $a->title_so }}</p>@endif</td>
                <td class="text-xs">{{ $a->desk ?: '—' }}</td>
                <td class="text-xs">@if ($a->user){{ $a->user->name }}<p class="text-[var(--color-ink-500)]">{{ $a->user->role?->name }}</p>@else<span class="text-[var(--color-ink-500)]">{{ $a->is_staff ? 'Not linked' : 'Contributor' }}</span>@endif</td>
                <td class="num text-right">{{ number_format($a->articles_count) }}</td>
                <td class="num text-right">{{ number_format((int) $a->views) }}</td>
                <td class="text-xs">{{ $a->show_on_team_page ? 'Shown' : '—' }}</td>
                <td class="whitespace-nowrap text-right">
                    <a href="{{ route('admin.authors.edit', $a) }}" class="btn btn-secondary btn-sm">Edit</a>
                    @unless ($a->articles_count)
                        <form method="post" action="{{ route('admin.authors.destroy', $a) }}" class="inline" data-confirm="Delete this profile?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-6 text-center text-sm text-[var(--color-ink-400)]">No journalist profiles yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
