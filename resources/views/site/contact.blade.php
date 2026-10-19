@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <div class="grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="kicker">Doogsan Media</p>
            <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Contact Us') }}</h1>
            <p class="dek mt-4">{{ __('We read every message. For news tips, use our secure tip form.') }}</p>
            <dl class="mt-8 space-y-5 text-sm">
                <div><dt class="kicker">{{ __('General enquiries') }}</dt><dd class="mt-1 text-base font-bold"><a href="mailto:{{ setting('contact_email', config('doogsan.contact_email')) }}" class="hover:underline">{{ setting('contact_email', config('doogsan.contact_email')) }}</a></dd></div>
                @foreach (['editorial_email' => __('Newsroom'), 'advertising_email' => __('Advertising'), 'corrections_email' => __('Corrections')] as $k => $label)
                    @if (setting($k))<div><dt class="kicker">{{ $label }}</dt><dd class="mt-1 font-bold"><a href="mailto:{{ setting($k) }}" class="hover:underline">{{ setting($k) }}</a></dd></div>@endif
                @endforeach
                @if (setting('contact_phone'))<div><dt class="kicker">{{ __('Phone') }}</dt><dd class="mt-1 font-bold">{{ setting('contact_phone') }}</dd></div>@endif
                @if (setting('contact_address'))<div><dt class="kicker">{{ __('Address') }}</dt><dd class="mt-1">{{ setting('contact_address') }}</dd></div>@endif
            </dl>
            <a href="{{ lroute('tip') }}" class="btn btn-ghost mt-8">{{ __('Submit a news tip securely') }} →</a>
        </div>
        <div class="lg:col-span-7">
            @include('site.partials.flash')
            <form method="post" action="{{ lroute('contact.send') }}" class="grid gap-5 rounded-sm border border-[var(--rule)] bg-[var(--surface)] p-6 sm:grid-cols-2 md:p-8">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div><label class="label" for="name">{{ __('Your name') }}</label><input id="name" name="name" value="{{ old('name') }}" required class="field"></div>
                <div><label class="label" for="email">{{ __('Email address') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="field"></div>
                <div><label class="label" for="department">{{ __('Department') }}</label>
                    <select id="department" name="department" class="field">
                        @foreach (['general' => __('General'), 'editorial' => __('Newsroom'), 'advertising' => __('Advertising'), 'corrections' => __('Corrections'), 'technical' => __('Website / technical')] as $k => $l)
                            <option value="{{ $k }}" @selected(old('department', request('department')) === $k)>{{ $l }}</option>
                        @endforeach
                    </select></div>
                <div><label class="label" for="subject">{{ __('Subject') }}</label><input id="subject" name="subject" value="{{ old('subject') }}" class="field"></div>
                <div class="sm:col-span-2"><label class="label" for="message">{{ __('Message') }}</label><textarea id="message" name="message" rows="7" required class="field">{{ old('message') }}</textarea></div>
                <div class="sm:col-span-2 flex items-center justify-between gap-4">
                    <p class="text-xs text-[var(--muted)]">{{ __('We use your details only to reply.') }} <a href="{{ lroute('page', ['slug' => edition() === 'so' ? 'asturnaanta' : 'privacy']) }}" class="underline">{{ __('Privacy Policy') }}</a></p>
                    <button class="btn btn-primary">{{ __('Send message') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
