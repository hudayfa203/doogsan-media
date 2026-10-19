@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--rule-strong)] pb-5">
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] leading-none">{{ __('Archive') }}</h1>
        @if ($year)<p class="dek mt-2">{{ $month ? __('month.'.$month).' ' : '' }}{{ $year }} · <a href="{{ lroute('archive') }}" class="underline">{{ __('All dates') }}</a></p>@endif
    </header>
    <div class="grid gap-10 py-8 lg:grid-cols-[14rem_1fr]">
        <nav aria-label="{{ __('Browse by month') }}">
            <ul class="space-y-1 text-sm">
                @foreach ($months as $ym => $count)
                    @php [$y, $m] = explode('-', $ym); @endphp
                    <li><a href="{{ lroute('archive') }}?year={{ $y }}&month={{ (int) $m }}" class="flex justify-between rounded-sm px-2 py-1.5 font-semibold hover:bg-[var(--bg-2)] {{ $year == $y && $month == (int) $m ? 'bg-[var(--bg-2)]' : '' }}"><span>{{ __('month.'.(int) $m) }} {{ $y }}</span><span class="text-[var(--muted)]">{{ $count }}</span></a></li>
                @endforeach
            </ul>
        </nav>
        <div>
            <ol class="divide-y divide-[var(--rule)]">
                @forelse ($stories as $s)
                    <li class="flex gap-4 py-3">
                        <span class="w-28 flex-none text-xs font-bold text-[var(--muted)] pt-1">{{ news_date($s->published_at, false) }}</span>
                        <div><a href="{{ $s->url() }}" class="headline headline-xs hover:underline">{{ $s->title }}</a>
                            @if ($s->article->category)<p class="kicker mt-0.5">{{ $s->article->category->nameFor() }}</p>@endif</div>
                    </li>
                @empty
                    <li class="py-8 text-[var(--muted)]">{{ __('No stories yet.') }}</li>
                @endforelse
            </ol>
            {{ $stories->links() }}
        </div>
    </div>
</section>
@endsection
