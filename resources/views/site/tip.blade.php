@extends('layouts.site')

@section('content')
<section class="wrap pt-8 md:pt-12">
    <div class="grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="kicker">{{ __('Investigations') }}</p>
            <h1 class="headline text-[2.4rem] md:text-[3.25rem] mt-1 leading-none">{{ __('Submit a News Tip') }}</h1>
            <p class="dek mt-4">{{ __('Do you know something the public should know? Tell our editors. You do not have to give your name.') }}</p>
            <ul class="mt-8 space-y-4 text-sm">
                <li class="flex gap-3"><span class="mt-1 h-2 w-2 flex-none rotate-45 bg-[var(--accent-bright)]"></span>{{ __('Tips are encrypted in our database and only senior editors can read them. Every access is logged.') }}</li>
                <li class="flex gap-3"><span class="mt-1 h-2 w-2 flex-none rotate-45 bg-[var(--accent-bright)]"></span>{{ __('If your safety could be at risk, use a device and network that are not monitored by others.') }}</li>
                <li class="flex gap-3"><span class="mt-1 h-2 w-2 flex-none rotate-45 bg-[var(--accent-bright)]"></span>{{ __('We verify everything independently before publishing.') }}</li>
            </ul>
        </div>
        <div class="lg:col-span-7">
            @include('site.partials.flash')
            <form method="post" action="{{ lroute('tip.send') }}" enctype="multipart/form-data" class="grid gap-5 rounded-sm border border-[var(--rule)] bg-[var(--surface)] p-6 md:p-8" x-data="{ anon: {{ old('wants_anonymity', '1') ? 'true' : 'false' }} }">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div><label class="label" for="details">{{ __('What do you want to tell us?') }}</label><textarea id="details" name="details" rows="8" required minlength="20" class="field" placeholder="{{ __('What happened, where, when and who is involved? How do you know?') }}">{{ old('details') }}</textarea></div>
                <div><label class="label" for="location">{{ __('Location (optional)') }}</label><input id="location" name="location" value="{{ old('location') }}" class="field"></div>
                <div><label class="label" for="attachment">{{ __('Document, photo or audio (optional, max 20 MB)') }}</label><input id="attachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.mp3,.m4a" class="field !p-2 text-sm"></div>
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="hidden" name="wants_anonymity" value="0"><input type="checkbox" name="wants_anonymity" value="1" x-model="anon" class="h-4 w-4 accent-[var(--color-forest-900)]"> {{ __('I want to stay anonymous') }}</label>
                <div x-show="!anon" x-cloak class="grid gap-5 sm:grid-cols-2">
                    <div><label class="label" for="tname">{{ __('Your name') }}</label><input id="tname" name="name" value="{{ old('name') }}" class="field"></div>
                    <div><label class="label" for="tcontact">{{ __('How can we reach you?') }}</label><input id="tcontact" name="contact" value="{{ old('contact') }}" class="field" placeholder="{{ __('Phone, WhatsApp or email') }}"></div>
                </div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="consent" value="1" required class="mt-0.5 h-4 w-4 accent-[var(--color-forest-900)]"> {{ __('I understand Doogsan Media will verify this information and may contact me if I provided details.') }}</label>
                <button class="btn btn-primary justify-self-start">{{ __('Send securely') }}</button>
            </form>
        </div>
    </div>
</section>
@endsection
