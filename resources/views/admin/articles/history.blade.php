@extends('layouts.admin')
@section('title', 'History')

@section('content')
<x-admin.page-header eyebrow="Version history · {{ strtoupper($t->locale) }}" :title="$t->title">
    <a href="{{ story_edit_url($article, $t->locale) }}" class="btn btn-secondary">Back to editor</a>
</x-admin.page-header>

<div class="grid gap-6 xl:grid-cols-[22rem_1fr]">
    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Revisions</h2><span class="text-xs text-[var(--color-ink-400)]">pick A and B to compare</span></div>
            <form method="get" class="scroll-thin max-h-[32rem] divide-y divide-[var(--color-ink-800)] overflow-y-auto">
                @foreach ($revisions as $r)
                    <div class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <input type="radio" name="a" value="{{ $r->id }}" @checked($a?->id === $r->id) class="check" onchange="this.form.submit()" title="A (older)">
                        <input type="radio" name="b" value="{{ $r->id }}" @checked($b?->id === $r->id) class="check" onchange="this.form.submit()" title="B (newer)">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">v{{ $r->version }} <span class="pill pill-{{ $r->status }} ml-1">{{ __('status.'.$r->status) }}</span> <span class="text-xs text-[var(--color-ink-400)]">{{ $r->reason }}</span></p>
                            <p class="truncate text-xs text-[var(--color-ink-400)]">{{ $r->user?->name ?? 'System' }} · {{ local_time($r->created_at)->format('d M H:i') }} @if ($r->note)· {{ $r->note }}@endif</p>
                        </div>
                        @can('corrections.manage')
                            @if ($r->id !== $revisions->first()->id)
                                <button form="rb-{{ $r->id }}" class="btn btn-ghost btn-sm" title="Restore this version">Restore</button>
                            @endif
                        @endcan
                    </div>
                @endforeach
            </form>
            @foreach ($revisions as $r)<form id="rb-{{ $r->id }}" method="post" action="{{ route('admin.articles.rollback', [$article, $r]) }}" data-confirm="Restore version {{ $r->version }}? The current text is kept in history.">@csrf</form>@endforeach
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Workflow history</h2></div>
            <ol class="space-y-2 p-4 text-sm">
                @foreach ($transitions as $tr)
                    <li class="flex gap-2"><span class="num w-24 flex-none text-xs text-[var(--color-ink-400)]">{{ local_time($tr->created_at)->format('d M H:i') }}</span><span>{{ $tr->user?->name ?? 'Scheduler' }}: {{ $tr->from_status }} → <strong>{{ $tr->to_status }}</strong>@if ($tr->note) <span class="text-[var(--color-ink-400)]">— {{ $tr->note }}</span>@endif</span></li>
                @endforeach
            </ol>
        </section>
    </div>
    <section class="panel">
        <div class="panel-head"><h2>Comparing v{{ $a?->version }} → v{{ $b?->version }}</h2><span class="text-xs"><del>removed</del> <ins>added</ins></span></div>
        <div class="panel-body space-y-6">
            @if ($titleDiff)<div><p class="eyebrow mb-2">Headline</p><p class="text-lg leading-relaxed">{!! $titleDiff !!}</p></div>@endif
            @if ($diff)<div><p class="eyebrow mb-2">Body</p><div class="font-['Newsreader_Variable',Georgia,serif] text-base leading-relaxed text-[var(--color-ink-200)]">{!! $diff !!}</div></div>
            @else<p class="text-sm text-[var(--color-ink-400)]">Only one revision exists so far.</p>@endif
        </div>
    </section>
</div>
@endsection
