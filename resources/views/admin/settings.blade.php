@extends('layouts.admin')
@section('title', 'Settings')

@section('content')
@php
    $platforms = \App\Http\Controllers\Admin\SettingsController::SOCIAL;
    $platformNames = ['facebook' => 'Facebook', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram', 'whatsapp' => 'WhatsApp channel'];
    $logoNames = ['light' => 'Logo on dark backgrounds (footer, dark mode)', 'dark' => 'Logo on light backgrounds (masthead)', 'mark' => 'Square mark (icon, app, social)'];
@endphp
<x-admin.page-header eyebrow="Administration" title="Settings" subtitle="Branding, contact details and official social accounts for each edition." />

<form method="post" action="{{ route('admin.settings.save') }}" x-data="{ tab: (location.hash || '#editions').slice(1) }" class="space-y-6">
    @csrf
    <div class="flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
        @foreach (['editions' => 'Editions', 'branding' => 'Logos', 'social' => 'Social accounts', 'contact' => 'Contact', 'features' => 'Features'] as $k => $lbl)
            <a href="#{{ $k }}" @click="tab = '{{ $k }}'" class="tab" :class="tab === '{{ $k }}' && 'is-active'">{{ $lbl }}</a>
        @endforeach
    </div>

    {{-- Editions --}}
    <div x-show="tab === 'editions'" class="grid gap-6 lg:grid-cols-2">
        @foreach (editions() as $l => $e)
            <section class="panel" lang="{{ $l }}">
                <div class="panel-head"><h2>{{ $e['name'] }} edition <span class="num ml-1 font-normal text-[var(--color-ink-500)]">/{{ $l }}</span></h2></div>
                <div class="panel-body space-y-4">
                    <div><label class="label">Site name</label><input name="site_name_{{ $l }}" value="{{ old("site_name_{$l}", $values["site_name_{$l}"]) }}" class="input"></div>
                    <div><label class="label">Tagline</label><input name="tagline_{{ $l }}" value="{{ old("tagline_{$l}", $values["tagline_{$l}"]) }}" class="input"></div>
                    <div><label class="label">Description (search engines and social sharing)</label><textarea name="site_description_{{ $l }}" rows="3" class="textarea">{{ old("site_description_{$l}", $values["site_description_{$l}"]) }}</textarea></div>
                    <div><label class="label">Footer note</label><textarea name="footer_note_{{ $l }}" rows="2" class="textarea">{{ old("footer_note_{$l}", $values["footer_note_{$l}"]) }}</textarea></div>
                </div>
            </section>
        @endforeach
        <section class="panel lg:col-span-2">
            <div class="panel-head"><h2>Brand colour</h2></div>
            <div class="panel-body flex items-center gap-3" x-data="{ c: @js(old('brand_accent', $values['brand_accent']) ?: '#a5ea4f') }">
                <input type="color" x-model="c" class="h-10 w-14 cursor-pointer rounded border border-[var(--color-ink-600)] bg-transparent">
                <input name="brand_accent" x-model="c" class="input !w-36 num" pattern="#[0-9a-fA-F]{6}">
                <p class="hint !mt-0">Accent used for highlights. Leave the default unless the brand guide changes.</p>
            </div>
        </section>
    </div>

    {{-- Logos --}}
    <div x-show="tab === 'branding'" x-cloak class="space-y-6">
        <p class="text-sm text-[var(--color-ink-400)]">Each edition can have its own logo. Upload a PNG or SVG in the Media Library (transparent background, at least 600 px wide), then choose it here. If an edition has no logo of its own, the shared logo is used, then the built-in Doogsan Media logo.</p>
        <div class="rounded-lg border border-[var(--color-amber)]/40 bg-[color-mix(in_oklab,var(--color-amber)_10%,transparent)] px-4 py-3 text-sm text-[#ffd98a]">
            Before uploading the English-edition logo, check the spelling of the wording on it reads <strong>ENGLISH</strong>.
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            @foreach (['' => 'Shared (both editions)'] + collect(editions())->map(fn ($e) => $e['name'].' edition')->all() as $l => $title)
                <section class="panel">
                    <div class="panel-head"><h2>{{ $title }}</h2></div>
                    <div class="panel-body space-y-5">
                        @foreach (\App\Http\Controllers\Admin\SettingsController::LOGOS as $v)
                            @php $slot = $l ? "{$v}_{$l}" : $v; @endphp
                            <div>
                                <x-admin.media-input :name="'logo['.$slot.']'" :value="$logoIds[$slot] ?? null" :label="$logoNames[$v]" />
                                @if ($l)
                                    <div class="mt-2 flex items-center gap-2 text-[0.6875rem] text-[var(--color-ink-500)]">
                                        Now showing:
                                        <span class="inline-grid h-8 place-items-center rounded px-2 {{ $v === 'dark' ? 'bg-[#fbfaf6]' : 'bg-[#0b2a20]' }}"><img src="{{ brand_logo($v, $l) }}" alt="" class="max-h-6 max-w-[8rem]"></span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    {{-- Social --}}
    <div x-show="tab === 'social'" x-cloak class="space-y-4">
        <p class="text-sm text-[var(--color-ink-400)]">Only add official Doogsan Media accounts. Links are shown in the order listed — the first one is shown most prominently in that edition. Leave a field empty to hide that platform.</p>
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach (editions() as $l => $e)
                @php
                    $current = old("social.{$l}", $social[$l] ?? []);
                    $ordered = array_values(array_unique(array_merge(array_keys(array_filter($current)), $platforms)));
                @endphp
                <section class="panel">
                    <div class="panel-head"><h2>{{ $e['name'] }} edition</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ count(array_filter($current)) }} linked</span></div>
                    <div class="panel-body space-y-3">
                        @foreach ($ordered as $p)
                            <div class="flex items-center gap-3">
                                <label for="social-{{ $l }}-{{ $p }}" class="w-36 flex-none text-xs font-bold {{ ! empty($current[$p]) ? 'text-white' : 'text-[var(--color-ink-400)]' }}">{{ $platformNames[$p] ?? $p }}</label>
                                <input id="social-{{ $l }}-{{ $p }}" type="url" name="social[{{ $l }}][{{ $p }}]" value="{{ $current[$p] ?? '' }}" class="input" placeholder="https://">
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    {{-- Contact --}}
    <div x-show="tab === 'contact'" x-cloak>
        <section class="panel">
            <div class="panel-head"><h2>Contact details</h2></div>
            <div class="panel-body grid gap-4 md:grid-cols-2">
                @foreach (['contact_email' => 'General contact email', 'editorial_email' => 'Editorial desk email', 'corrections_email' => 'Corrections email', 'advertising_email' => 'Advertising email', 'mail_from_address' => 'Outgoing mail “from” address'] as $k => $lbl)
                    <div><label class="label">{{ $lbl }}</label><input type="email" name="{{ $k }}" value="{{ old($k, $values[$k]) }}" class="input"></div>
                @endforeach
                <div><label class="label">Phone</label><input name="contact_phone" value="{{ old('contact_phone', $values['contact_phone']) }}" class="input"></div>
                <div><label class="label">WhatsApp tip line</label><input name="whatsapp_tip_line" value="{{ old('whatsapp_tip_line', $values['whatsapp_tip_line']) }}" class="input" placeholder="+252…"></div>
                <div class="md:col-span-2"><label class="label">Postal address</label><textarea name="contact_address" rows="2" class="textarea">{{ old('contact_address', $values['contact_address']) }}</textarea></div>
                <div class="md:col-span-2"><label class="label">Allowed embed hosts</label><input name="embed_hosts" value="{{ old('embed_hosts', $values['embed_hosts']) }}" class="input num" placeholder="youtube.com, x.com, facebook.com, tiktok.com"><p class="hint">Comma-separated. Embeds from other sites are stripped from stories for security.</p></div>
            </div>
        </section>
    </div>

    {{-- Features --}}
    <div x-show="tab === 'features'" x-cloak>
        <section class="panel max-w-xl">
            <div class="panel-head"><h2>Public site features</h2></div>
            <div class="panel-body space-y-3">
                <input type="hidden" name="features_present" value="1">
                @foreach (['live_tv' => 'Live TV page and menu link', 'newsletter' => 'Newsletter sign-up', 'tips' => 'News tip form', 'pwa' => 'Installable app (PWA) and offline page', 'dark_mode' => 'Reader dark-mode toggle'] as $f => $lbl)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[{{ $f }}]" value="1" @checked($features[$f] ?? false) class="check"> {{ $lbl }}</label>
                @endforeach
            </div>
        </section>
    </div>

    <div class="sticky bottom-0 flex justify-end border-t border-[var(--color-ink-700)] bg-[var(--color-ink-950)]/90 py-3 backdrop-blur">
        <button class="btn btn-primary">Save settings</button>
    </div>
</form>
@endsection
