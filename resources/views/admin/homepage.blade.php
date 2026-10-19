@extends('layouts.admin')
@section('title', 'Homepage Builder — '.editions()[$locale]['name'])

@php
    $ed = editions()[$locale];
    $cats = $categories->filter(fn ($c) => ! $c->locale || $c->locale === $locale)
        ->map(fn ($c) => ['key' => $c->key, 'name' => $c->nameFor($locale)])->values();
    $formats = collect(config('doogsan.formats'))->map(fn ($f) => $f[$locale] ?? $f['en']);
    $types = \App\Services\HomepageService::SECTION_TYPES;
    $slots = \App\Models\AdPlacement::SLOTS;
    $lead = $layout['lead'] && isset($picks[$layout['lead']]) ? [$picks[$layout['lead']]] : [];
    $secondary = collect((array) $layout['secondary'])->filter(fn ($id) => isset($picks[$id]))->map(fn ($id) => $picks[$id])->values()->all();
@endphp

@section('content')
<x-admin.page-header :eyebrow="$ed['name'].' edition'" title="Homepage Builder" :subtitle="'Arrange the front page of '.url('/'.$locale).'. Changes go live immediately or at the time you schedule. Each edition has its own homepage.'">
    <a href="{{ url('/'.$locale) }}" target="_blank" class="btn btn-secondary"><x-admin.icon name="external" /> View live homepage</a>
</x-admin.page-header>

<div class="mb-5 flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
    @foreach (editions() as $l => $e)
        <a href="{{ route('admin.homepage.edit', $l) }}" class="tab {{ $l === $locale ? 'is-active' : '' }}">{{ $e['name'] }} homepage</a>
    @endforeach
</div>

<form method="post" action="{{ route('admin.homepage.save', $locale) }}"
      x-data="homepageBuilder(@js($layout), @js($types), @js($cats), @js($formats))"
      @submit="$refs.layout.value = JSON.stringify(Object.assign({}, layout, { lead: $refs.leadPicker._x_dataStack[0].items[0]?.id || null, secondary: $refs.secPicker._x_dataStack[0].items.map(i => i.id) }))"
      class="grid gap-6 xl:grid-cols-[1fr_22rem]">
    @csrf
    <input type="hidden" name="layout" x-ref="layout">

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Top of the page</h2><span class="text-xs text-[var(--color-ink-400)]">Leave empty to let the newest high-priority stories fill automatically</span></div>
            <div class="panel-body grid gap-6 lg:grid-cols-2">
                <div x-ref="leadPicker" x-data="articlePicker(@js($lead), { max: 1, locale: '{{ $locale }}', live: true })">
                    <label class="label">Lead story</label>
                    <template x-for="(r, i) in items" :key="r.id">
                        <div class="mb-2 flex items-center gap-2 rounded-lg border border-[var(--color-signal-dim)]/50 bg-[var(--color-ink-850)] px-3 py-2 text-sm"><span class="flex-1 truncate font-semibold" x-text="r.title"></span><button type="button" class="btn btn-ghost btn-sm" @click="remove(i)">✕</button></div>
                    </template>
                    <div class="relative" x-show="!items.length">
                        <input x-model="q" @input.debounce.300ms="search()" class="input" placeholder="Search published {{ $ed['name'] }} stories…">
                        <div x-show="open && results.length" @click.outside="open = false" class="absolute z-20 mt-1 w-full rounded-lg border border-[var(--color-ink-600)] bg-[var(--color-ink-900)] p-1 shadow-2xl">
                            <template x-for="r in results"><button type="button" @click="add(r)" class="block w-full truncate rounded px-2 py-1.5 text-left text-xs hover:bg-[var(--color-ink-800)]" x-text="r.title"></button></template>
                        </div>
                    </div>
                    <p class="hint">Automatic: {{ $preview['lead']?->title ? \Illuminate\Support\Str::limit($preview['lead']->title, 70) : 'no published stories yet' }}</p>
                </div>
                <div x-ref="secPicker" x-data="articlePicker(@js($secondary), { max: 4, locale: '{{ $locale }}', live: true })">
                    <label class="label">Secondary stories <span class="font-normal text-[var(--color-ink-500)]">(up to 4, in order)</span></label>
                    <template x-for="(r, i) in items" :key="r.id">
                        <div class="mb-2 flex items-center gap-1 rounded-lg bg-[var(--color-ink-850)] px-3 py-1.5 text-sm">
                            <span class="num w-5 text-xs text-[var(--color-ink-500)]" x-text="i + 1"></span>
                            <span class="flex-1 truncate" x-text="r.title"></span>
                            <button type="button" class="btn btn-ghost btn-sm" @click="move(i, -1)" aria-label="Move up">↑</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="move(i, 1)" aria-label="Move down">↓</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="remove(i)" aria-label="Remove">✕</button>
                        </div>
                    </template>
                    <div class="relative" x-show="items.length < max">
                        <input x-model="q" @input.debounce.300ms="search()" class="input" placeholder="Add a story…">
                        <div x-show="open && results.length" @click.outside="open = false" class="absolute z-20 mt-1 w-full rounded-lg border border-[var(--color-ink-600)] bg-[var(--color-ink-900)] p-1 shadow-2xl">
                            <template x-for="r in results"><button type="button" @click="add(r)" class="block w-full truncate rounded px-2 py-1.5 text-left text-xs hover:bg-[var(--color-ink-800)]" x-text="r.title"></button></template>
                        </div>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm font-bold lg:col-span-2"><input type="checkbox" class="check" x-model="layout.show_live_tv"> Show the Live TV block when a broadcast is on air</label>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Sections, top to bottom</h2><span class="num text-xs text-[var(--color-ink-400)]" x-text="layout.sections.length + ' blocks'"></span></div>
            <ol class="divide-y divide-[var(--color-ink-800)]">
                <template x-for="(s, i) in layout.sections" :key="i">
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3" :class="!s.enabled && 'opacity-50'">
                        <span class="num w-6 text-xs text-[var(--color-ink-500)]" x-text="i + 1"></span>
                        <div class="min-w-40 flex-1">
                            <p class="font-semibold text-white" x-text="label(s)"></p>
                            <p class="text-[0.6875rem] uppercase tracking-wide text-[var(--color-ink-500)]" x-text="types[s.type]"></p>
                        </div>
                        <template x-if="s.type === 'category'">
                            <select x-model="s.key" class="select !w-44"><template x-for="c in categories"><option :value="c.key" x-text="c.name" :selected="c.key === s.key"></option></template></select>
                        </template>
                        <template x-if="s.type === 'format'">
                            <select x-model="s.key" class="select !w-44"><template x-for="(name, k) in formats"><option :value="k" x-text="name" :selected="k === s.key"></option></template></select>
                        </template>
                        <template x-if="s.type === 'ad'">
                            <select x-model="s.slot" class="select !w-44">@foreach ($slots as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                        </template>
                        <template x-if="['category', 'format'].includes(s.type)">
                            <select x-model="s.style" class="select !w-28"><option value="grid">Grid</option><option value="feature">Feature</option><option value="list">List</option><option value="columns">Columns</option></select>
                        </template>
                        <template x-if="!['ad', 'newsletter', 'live'].includes(s.type)">
                            <label class="flex items-center gap-1 text-xs text-[var(--color-ink-400)]">Items <input type="number" min="1" max="12" x-model.number="s.limit" class="input !w-16"></label>
                        </template>
                        <label class="flex items-center gap-1 text-xs font-bold"><input type="checkbox" class="check" x-model="s.enabled"> On</label>
                        <div class="flex">
                            <button type="button" class="btn btn-ghost btn-sm" @click="move(i, -1)" aria-label="Move up">↑</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="move(i, 1)" aria-label="Move down">↓</button>
                            <button type="button" class="btn btn-ghost btn-sm" @click="remove(i)" aria-label="Remove">✕</button>
                        </div>
                    </li>
                </template>
            </ol>
            <div class="panel-body flex flex-wrap items-center gap-2 border-t border-[var(--color-ink-800)]">
                <span class="text-xs font-bold uppercase tracking-wide text-[var(--color-ink-500)]">Add block</span>
                @foreach ($types as $k => $v)
                    <button type="button" class="btn btn-secondary btn-sm" @click="add('{{ $k }}')">+ {{ $v }}</button>
                @endforeach
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Publish layout</h2></div>
            <div class="panel-body space-y-3">
                <div><label class="label">Version label</label><input name="label" class="input" placeholder="e.g. Election night" maxlength="120"></div>
                <div><label class="label">Go live at (EAT)</label><input type="datetime-local" name="active_from" class="input"><p class="hint">Leave empty to publish now.</p></div>
                <button class="btn btn-primary w-full">Save homepage</button>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Live now</h2></div>
            <div class="panel-body space-y-2 text-sm">
                @if ($active)
                    <p class="font-semibold">{{ $active->label ?: 'Untitled version' }}</p>
                    <p class="text-xs text-[var(--color-ink-400)]">since {{ local_time($active->active_from)->format('d M Y H:i') }} EAT · {{ $active->creator?->name ?? 'system' }}</p>
                @else
                    <p class="text-[var(--color-ink-400)]">Default layout (never customised).</p>
                @endif
                <ul class="mt-3 space-y-1 text-xs text-[var(--color-ink-300)]">
                    <li><span class="text-[var(--color-ink-500)]">Lead:</span> {{ $preview['lead']?->title ?? '—' }}</li>
                    @foreach ($preview['secondary'] as $s)<li class="truncate"><span class="text-[var(--color-ink-500)]">·</span> {{ $s->title }}</li>@endforeach
                    <li class="pt-1 text-[var(--color-ink-500)]">{{ count($preview['sections']) }} sections showing content</li>
                </ul>
            </div>
        </section>

        @if ($scheduled->count())
            <section class="panel">
                <div class="panel-head"><h2>Scheduled</h2></div>
                <ul class="divide-y divide-[var(--color-ink-800)] text-sm">
                    @foreach ($scheduled as $v)
                        <li class="flex items-center gap-2 px-4 py-2.5"><div class="flex-1"><p class="font-semibold">{{ $v->label ?: 'Untitled' }}</p><p class="num text-xs text-[var(--color-ink-400)]">{{ local_time($v->active_from)->format('d M H:i') }} EAT</p></div>
                            <form method="post" action="{{ route('admin.homepage.destroy', $v) }}" data-confirm="Cancel this scheduled layout?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Cancel</button></form></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>History</h2></div>
            <ul class="divide-y divide-[var(--color-ink-800)] text-sm">
                @forelse ($history as $v)
                    <li class="flex items-center gap-2 px-4 py-2.5"><div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $v->label ?: 'Untitled' }} @if ($active && $v->id === $active->id)<span class="pill pill-live ml-1">Live</span>@endif</p><p class="num text-xs text-[var(--color-ink-400)]">{{ local_time($v->active_from)->format('d M H:i') }} · {{ $v->creator?->name ?? 'system' }}</p></div>
                        @if (! $active || $v->id !== $active->id)<form method="post" action="{{ route('admin.homepage.destroy', $v) }}" data-confirm="Delete this old version?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form>@endif</li>
                @empty
                    <li class="px-4 py-3 text-[var(--color-ink-400)]">No saved versions yet.</li>
                @endforelse
            </ul>
        </section>
    </aside>
</form>
@endsection
