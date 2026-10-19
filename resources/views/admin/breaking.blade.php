@extends('layouts.admin')
@section('title', 'Breaking News')

@section('content')
<x-admin.page-header eyebrow="Live" title="Breaking News Control" subtitle="Alerts appear instantly in the ticker on every page of the chosen edition and disappear automatically when they expire." />

<div class="grid gap-6 xl:grid-cols-[26rem_1fr]">
    <section class="panel border-[var(--color-alarm)]/40">
        <div class="panel-head"><h2 class="flex items-center gap-2"><span class="live-dot text-[var(--color-alarm)]"></span> Push a new alert</h2></div>
        <form method="post" action="{{ route('admin.breaking.store') }}" class="panel-body space-y-4" x-data="{ len: 0 }">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                @foreach (editions() as $l => $e)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--color-ink-600)] px-3 py-2 text-sm font-bold has-[:checked]:border-[var(--color-signal)] has-[:checked]:text-[var(--color-signal)]">
                        <input type="radio" name="locale" value="{{ $l }}" @checked($loop->first) class="check"> {{ $e['name'] }}</label>
                @endforeach
            </div>
            <div><label class="label">Headline</label><textarea name="headline" rows="3" maxlength="200" required class="textarea !text-base font-semibold" @input="len = $el.value.length" placeholder="Short, factual, present tense"></textarea><p class="hint"><span x-text="len"></span>/200</p></div>
            <div x-data="articlePicker([], { max: 1, live: true })">
                <label class="label">Link to a published story <span class="font-normal text-[var(--color-ink-500)]">(optional)</span></label>
                <template x-for="r in items"><div class="mb-2 flex items-center gap-2 rounded bg-[var(--color-ink-850)] px-2 py-1.5 text-xs"><input type="hidden" name="article_id" :value="r.id"><span class="flex-1 truncate" x-text="r.title"></span><button type="button" @click="items = []">✕</button></div></template>
                <div class="relative" x-show="!items.length"><input x-model="q" @input.debounce.300ms="search()" class="input" placeholder="Search live stories…">
                    <div x-show="open && results.length" @click.outside="open = false" class="absolute z-20 mt-1 w-full rounded-lg border border-[var(--color-ink-600)] bg-[var(--color-ink-900)] p-1 shadow-2xl">
                        <template x-for="r in results"><button type="button" @click="add(r)" class="block w-full truncate rounded px-2 py-1.5 text-left text-xs hover:bg-[var(--color-ink-800)]" x-text="'[' + r.locale + '] ' + r.title"></button></template></div></div>
            </div>
            <div><label class="label">…or any URL</label><input name="url" class="input" placeholder="/en/live/… or https://"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Priority</label><select name="priority" class="select"><option value="1">1 · Urgent</option><option value="2" selected>2 · Breaking</option><option value="3">3 · Developing</option></select></div>
                <div><label class="label">Expires after</label><select name="duration" class="select"><option value="30">30 minutes</option><option value="60">1 hour</option><option value="180" selected>3 hours</option><option value="720">12 hours</option><option value="1440">24 hours</option><option value="0">Manual removal</option></select></div>
            </div>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="is_urgent_banner" value="1" class="check mt-0.5"> <span><strong>Sitewide urgent banner</strong><br><span class="text-xs text-[var(--color-ink-400)]">Red bar above the masthead on every page. Use for major events only. Priority-1 alerts also trigger browser notifications for readers who opted in.</span></span></label>
            <button class="btn w-full bg-[var(--color-alarm)] text-white hover:bg-[#ff7268]"><x-admin.icon name="bolt" /> Publish alert now</button>
        </form>
    </section>

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>On air now</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ $current->count() }} active</span></div>
            <div class="divide-y divide-[var(--color-ink-800)]">
                @forelse ($current as $al)
                    <div class="flex flex-wrap items-start gap-4 p-4" x-data="{ edit: false }">
                        <span class="pill {{ $al->priority === 1 ? 'pill-live' : 'pill-editing' }}">P{{ $al->priority }} · {{ strtoupper($al->locale) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold" x-show="!edit">{{ $al->headline }}</p>
                            <form x-show="edit" x-cloak method="post" action="{{ route('admin.breaking.update', $al) }}" class="space-y-2">@csrf @method('PUT')
                                <input type="hidden" name="locale" value="{{ $al->locale }}"><input type="hidden" name="priority" value="{{ $al->priority }}"><input type="hidden" name="article_id" value="{{ $al->article_id }}"><input type="hidden" name="url" value="{{ $al->url }}"><input type="hidden" name="is_urgent_banner" value="{{ $al->is_urgent_banner ? 1 : 0 }}">
                                <input name="headline" value="{{ $al->headline }}" class="input"><select name="duration" class="select !w-auto"><option value="60">+1h from now</option><option value="180">+3h</option><option value="1440">+24h</option><option value="0">Manual</option></select>
                                <button class="btn btn-primary btn-sm">Update live</button></form>
                            <p class="mt-1 text-xs text-[var(--color-ink-400)]">by {{ $al->creator?->name }} · {{ $al->created_at->diffForHumans() }} · {{ $al->expires_at ? 'expires '.local_time($al->expires_at)->format('d M H:i').' EAT' : 'no expiry' }} @if ($al->is_urgent_banner)· <span class="text-[var(--color-alarm)]">sitewide banner</span>@endif</p>
                            @if ($al->link())<a href="{{ $al->link() }}" target="_blank" class="text-xs text-[var(--color-signal)] hover:underline">{{ \Illuminate\Support\Str::limit($al->link(), 70) }}</a>@endif
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="edit = !edit" class="btn btn-secondary btn-sm">Edit</button>
                            <form method="post" action="{{ route('admin.breaking.expire', $al) }}">@csrf<button class="btn btn-danger btn-sm">Remove now</button></form>
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-sm text-[var(--color-ink-400)]">No alerts are live. The ticker is hidden on the site.</p>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Recent history</h2></div>
            <table class="table"><tbody>
                @foreach ($history as $al)
                    <tr><td class="w-16"><span class="text-[0.625rem] font-extrabold uppercase">{{ $al->locale }}</span></td><td>{{ $al->headline }}</td><td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time($al->created_at)->format('d M H:i') }}</td>
                        <td class="text-right"><form method="post" action="{{ route('admin.breaking.destroy', $al) }}" data-confirm="Delete this alert permanently?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Delete</button></form></td></tr>
                @endforeach
            </tbody></table>
        </section>
    </div>
</div>
@endsection
