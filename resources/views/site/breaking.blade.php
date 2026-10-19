@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <header class="border-b-[3px] border-[var(--live)] pb-5">
        <p class="kicker !text-[var(--live)] flex items-center gap-2"><span class="live-dot"></span>{{ __('Breaking') }}</p>
        <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Breaking News') }}</h1>
    </header>

    <div x-data class="py-8">
        <template x-if="$store.live.alerts.length === 0">
            <p class="text-[var(--muted)]">{{ __('There are no breaking news alerts right now.') }}</p>
        </template>
        <ul class="divide-y divide-[var(--rule)]">
            <template x-for="a in $store.live.alerts" :key="a.id">
                <li class="flex items-start gap-4 py-5">
                    <span class="badge mt-1" :class="a.priority === 1 ? 'badge-breaking' : 'badge-format'" x-text="a.priority === 1 ? '{{ __('Urgent') }}' : '{{ __('Breaking') }}'"></span>
                    <div>
                        <a :href="a.url || '#'" class="headline headline-lg hover:underline" x-text="a.headline"></a>
                        <p class="meta mt-1"><time :datetime="a.created_at" data-rel x-text="new Date(a.created_at).toLocaleTimeString('{{ edition() === 'so' ? 'so-SO' : 'en-GB' }}', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Mogadishu' })"></time></p>
                    </div>
                </li>
            </template>
        </ul>
    </div>

    @if ($stories->isNotEmpty())
        <x-section-head :title="__('Breaking stories')" class="mt-6" />
        <div class="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($stories as $s)
                <x-story :t="$s" variant="stack" :show-dek="true" />
            @endforeach
        </div>
        {{ $stories->links() }}
    @endif
</section>
@endsection
