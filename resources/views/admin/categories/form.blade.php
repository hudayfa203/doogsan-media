@extends('layouts.admin')
@section('title', $category->exists ? 'Edit section' : 'New section')

@section('content')
<x-admin.page-header eyebrow="Sections" :title="$category->exists ? ($category->nameFor('en') ?: $category->key) : 'New section'" subtitle="Each edition gets its own name and web address. Addresses that clash with built-in pages get “-news” added automatically.">
    <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">← All sections</a>
</x-admin.page-header>

<form method="post" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="space-y-6">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <section class="panel">
        <div class="panel-head"><h2>Settings</h2></div>
        <div class="panel-body grid gap-4 md:grid-cols-5">
            <div><label class="label">Key</label><input name="key" value="{{ old('key', $category->key) }}" required pattern="[a-z0-9\-]+" maxlength="60" class="input font-mono" placeholder="e.g. puntland"><p class="hint">Internal ID used by the homepage and menus.</p></div>
            <div><label class="label">Parent section</label><select name="parent_id" class="select"><option value="">— None —</option>@foreach ($parents as $p)<option value="{{ $p->id }}" @selected(old('parent_id', $category->parent_id) == $p->id)>{{ $p->nameFor('en') }}</option>@endforeach</select></div>
            <div><label class="label">Newsroom</label><select name="locale" class="select">
                <option value="">Both newsrooms</option>
                <option value="so" @selected(old('locale', $category->locale) === 'so')>Somali only</option>
                <option value="en" @selected(old('locale', $category->locale) === 'en')>English only</option>
            </select><p class="hint">A section can exist in one newsroom only.</p></div>
            <div><label class="label">Colour</label><input type="color" name="color" value="{{ old('color', $category->color ?: '#1f7a4d') }}" class="input h-10 !p-1"></div>
            <div><label class="label">Order</label><input type="number" name="position" min="0" max="9999" value="{{ old('position', $category->position) }}" class="input num"></div>
            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_active" value="1" class="check" @checked(old('is_active', $category->is_active))> Active on the site</label>
            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="in_navigation" value="1" class="check" @checked(old('in_navigation', $category->in_navigation))> Show in navigation</label>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach (editions() as $l => $e)
            @php $t = $category->exists ? $category->t($l) : null; @endphp
            <section class="panel" lang="{{ $l }}">
                <div class="panel-head"><h2>{{ $e['name'] }}</h2></div>
                <div class="panel-body space-y-3">
                    <div><label class="label">Name</label><input name="t[{{ $l }}][name]" value="{{ old("t.$l.name", $t?->name) }}" required maxlength="80" class="input !text-base font-semibold"></div>
                    <div><label class="label">Address</label><div class="flex items-center gap-1"><span class="text-xs text-[var(--color-ink-500)]">/{{ $l }}/</span><input name="t[{{ $l }}][slug]" value="{{ old("t.$l.slug", $t?->slug) }}" pattern="[a-z0-9\-]*" maxlength="80" class="input font-mono" placeholder="generated from name"></div></div>
                    <div><label class="label">Description</label><input name="t[{{ $l }}][description]" value="{{ old("t.$l.description", $t?->description) }}" maxlength="255" class="input"></div>
                    <div><label class="label">Search title</label><input name="t[{{ $l }}][seo_title]" value="{{ old("t.$l.seo_title", $t?->seo_title) }}" maxlength="120" class="input"></div>
                    <div><label class="label">Search description</label><textarea name="t[{{ $l }}][seo_description]" rows="2" maxlength="255" class="textarea">{{ old("t.$l.seo_description", $t?->seo_description) }}</textarea></div>
                </div>
            </section>
        @endforeach
    </div>

    <div class="flex justify-end"><button class="btn btn-primary">{{ $category->exists ? 'Save section' : 'Create section' }}</button></div>
</form>
@endsection
