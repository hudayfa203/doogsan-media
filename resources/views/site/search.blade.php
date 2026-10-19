@extends('layouts.site')

@section('content')
@php $type = $filters['type'] ?? 'articles'; $tz = config('doogsan.timezone'); @endphp
<section class="wrap pt-8 md:pt-12">
    <h1 class="headline text-[2.4rem] md:text-[3.25rem] leading-none">{{ __('Search') }}</h1>
    <form method="get" action="{{ lroute('search') }}" class="mt-6" x-data="{ more: {{ collect($filters)->except(['q', 'lang', 'type'])->filter()->isNotEmpty() ? 'true' : 'false' }} }">
        <div class="flex gap-2">
            <label class="sr-only" for="q">{{ __('Search terms') }}</label>
            <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" type="search" class="field !py-3.5 !text-lg" placeholder="{{ __('Search Doogsan Media') }}" autofocus>
            <button class="btn btn-primary !px-6">{{ __('Search') }}</button>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
            @foreach (['articles' => __('Stories'), 'videos' => __('Video & audio'), 'live' => __('Live coverage')] as $k => $label)
                <label class="cursor-pointer rounded-full border px-3.5 py-1.5 font-bold {{ $type === $k ? 'border-[var(--fg)] bg-[var(--fg)] text-[var(--bg)]' : 'border-[var(--rule)]' }}">
                    <input type="radio" name="type" value="{{ $k }}" class="sr-only" @checked($type === $k) onchange="this.form.submit()">{{ $label }}
                </label>
            @endforeach
            <select name="lang" class="field !w-auto !py-1.5 !rounded-full text-sm font-bold" onchange="this.form.submit()" aria-label="{{ __('Language') }}">
                <option value="{{ edition() }}" @selected(($filters['lang'] ?? '') === edition())>{{ editions()[edition()]['native'] }}</option>
                @foreach (editions() as $l => $e)@if ($l !== edition())<option value="{{ $l }}" @selected(($filters['lang'] ?? '') === $l)>{{ $e['native'] }}</option>@endif @endforeach
                <option value="all" @selected(($filters['lang'] ?? '') === 'all')>{{ __('Both languages') }}</option>
            </select>
            <button type="button" @click="more = !more" class="ml-auto font-bold text-[var(--accent)]">{{ __('Filters') }} <span x-text="more ? '−' : '+'"></span></button>
        </div>
        <div x-show="more" x-cloak class="mt-4 grid gap-4 rounded-sm bg-[var(--bg-2)] p-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2"><label class="label">{{ __('Section') }}</label>
                <select name="section" class="field"><option value="">{{ __('All sections') }}</option>@foreach ($categories as $c)<option value="{{ $c->key }}" @selected(($filters['section'] ?? '') === $c->key)>{{ $c->nameFor() }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Format') }}</label>
                <select name="format" class="field"><option value="">{{ __('Any') }}</option>@foreach (config('doogsan.formats') as $k => $f)<option value="{{ $k }}" @selected(($filters['format'] ?? '') === $k)>{{ $f[edition()] }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Journalist') }}</label>
                <select name="author" class="field"><option value="">{{ __('Anyone') }}</option>@foreach ($authors as $a)<option value="{{ $a->slug }}" @selected(($filters['author'] ?? '') === $a->slug)>{{ $a->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="field"></div>
            <div><label class="label">{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="field"></div>
            <div><label class="label">{{ __('Sort') }}</label>
                <select name="sort" class="field">@foreach (['relevance' => __('Most relevant'), 'newest' => __('Newest first'), 'oldest' => __('Oldest first')] as $k => $l)<option value="{{ $k }}" @selected(($filters['sort'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2 lg:col-span-5"><button class="btn btn-primary">{{ __('Apply') }}</button><a href="{{ lroute('search') }}" class="btn btn-ghost">{{ __('Reset') }}</a></div>
        </div>
    </form>

    <div class="mt-8 border-t-[3px] border-[var(--rule-strong)] pt-4">
        @if (filled($filters['q'] ?? null) || $results->total())
            <p class="meta">{{ trans_choice('search.results', $results->total(), ['n' => number_format($results->total())]) }}@if (filled($filters['q'] ?? null)) — “{{ $filters['q'] }}”@endif</p>
        @endif
        <div class="divide-y divide-[var(--rule)]">
            @forelse ($results as $r)
                @if ($type === 'articles')
                    <x-story :t="$r" variant="side" class="py-6" />
                @elseif ($type === 'videos')
                    <div class="py-6 grid grid-cols-[1fr_10rem] gap-4">@include('site.partials.video-card', ['v' => $r])</div>
                @else
                    <a href="{{ $r->url() }}" class="block py-6 group"><p class="meta">{{ news_date($r->starts_at) }} · {{ $r->status === 'live' ? __('Live') : __('Ended') }}</p><h3 class="headline headline-md mt-1 group-hover:underline">{{ $r->title }}</h3></a>
                @endif
            @empty
                <div class="py-16 text-center">
                    <p class="headline headline-md">{{ filled($filters['q'] ?? null) ? __('No results found.') : __('Search stories, video and live coverage.') }}</p>
                    @if (filled($filters['q'] ?? null))<p class="mt-2 text-[var(--muted)]">{{ __('Try different words, check spelling, or search both languages.') }}</p>@endif
                </div>
            @endforelse
        </div>
        {{ $results->links() }}
    </div>
</section>
@endsection
