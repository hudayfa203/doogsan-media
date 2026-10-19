@extends('layouts.admin')
@section('title', $ad->exists ? 'Edit campaign' : 'New campaign')

@section('content')
<x-admin.page-header eyebrow="Advertising" :title="$ad->exists ? 'Edit: '.$ad->name : 'New campaign'">
    <a href="{{ route('admin.ads.index') }}" class="btn btn-ghost">← All campaigns</a>
</x-admin.page-header>

<form method="post" action="{{ $ad->exists ? route('admin.ads.update', $ad) : route('admin.ads.store') }}" class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_20rem]">
    @csrf @if ($ad->exists) @method('PUT') @endif
    <section class="panel"><div class="panel-body space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="label">Campaign name</label><input name="name" value="{{ old('name', $ad->name) }}" required maxlength="120" class="input"></div>
            <div><label class="label">Advertiser</label><input name="advertiser" value="{{ old('advertiser', $ad->advertiser) }}" maxlength="120" class="input"></div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="label">Slot</label><select name="slot" class="select" required>@foreach (\App\Models\AdPlacement::SLOTS as $k => $label)<option value="{{ $k }}" @selected(old('slot', $ad->slot) === $k)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="label">Edition</label><select name="locale" class="select"><option value="">Both editions</option>@foreach (editions() as $l => $e)<option value="{{ $l }}" @selected(old('locale', $ad->locale) === $l)>{{ $e['name'] }} only</option>@endforeach</select></div>
        </div>
        <x-admin.media-input name="media_id" :value="old('media_id', $ad->media_id)" label="Creative (image)" />
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="label">Click-through URL</label><input name="target_url" type="url" value="{{ old('target_url', $ad->target_url) }}" maxlength="255" class="input" placeholder="https://"></div>
            <div><label class="label">Alt text</label><input name="alt_text" value="{{ old('alt_text', $ad->alt_text) }}" maxlength="190" class="input" placeholder="Describes the advert for screen readers"></div>
        </div>
        <p class="hint">Adverts are labelled “Advertisement / Xayaysiis” on the site and links open with rel="sponsored".</p>
    </div></section>
    <aside class="panel"><div class="panel-body space-y-4">
        <div><label class="label">Starts (EAT)</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at', $ad->starts_at ? local_time($ad->starts_at)->format('Y-m-d\TH:i') : '') }}" class="input"></div>
        <div><label class="label">Ends (EAT)</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at', $ad->ends_at ? local_time($ad->ends_at)->format('Y-m-d\TH:i') : '') }}" class="input"></div>
        <div><label class="label">Rotation weight</label><input type="number" name="weight" min="1" max="10" value="{{ old('weight', $ad->weight ?? 1) }}" class="input num"><p class="hint">Higher weight is shown more often when several campaigns share a slot.</p></div>
        <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_active" value="1" class="check" @checked(old('is_active', $ad->is_active))> Active</label>
        @if ($ad->exists)
            <div class="grid grid-cols-3 gap-2 rounded-lg bg-[var(--color-ink-850)] p-3 text-center text-xs">
                <div><p class="num text-base font-bold text-white">{{ number_format($ad->impressions) }}</p>Impr.</div>
                <div><p class="num text-base font-bold text-white">{{ number_format($ad->clicks) }}</p>Clicks</div>
                <div><p class="num text-base font-bold text-white">{{ $ad->ctr() }}%</p>CTR</div>
            </div>
        @endif
        <button class="btn btn-primary w-full">Save campaign</button>
    </div></aside>
</form>
@endsection
