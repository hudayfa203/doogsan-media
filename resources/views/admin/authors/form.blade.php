@extends('layouts.admin')
@section('title', $author->exists ? 'Edit journalist' : 'New journalist')

@section('content')
<x-admin.page-header eyebrow="Journalists" :title="$author->exists ? $author->name : 'New profile'" subtitle="This is the public byline profile. Newsroom sign-in accounts are managed under Users.">
    @if ($author->exists)<a href="{{ lroute('author', ['slug' => $author->slug], 'en') }}" target="_blank" class="btn btn-secondary"><x-admin.icon name="external" /> View profile</a>@endif
    <a href="{{ route('admin.authors.index') }}" class="btn btn-ghost">← All journalists</a>
</x-admin.page-header>

<form method="post" action="{{ $author->exists ? route('admin.authors.update', $author) : route('admin.authors.store') }}" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
    @csrf
    @if ($author->exists) @method('PUT') @endif

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Identity</h2></div>
            <div class="panel-body grid gap-4 md:grid-cols-2">
                <div><label class="label">Full name</label><input name="name" value="{{ old('name', $author->name) }}" required maxlength="120" class="input !text-base font-semibold"></div>
                <div><label class="label">Profile address</label><input name="slug" value="{{ old('slug', $author->slug) }}" pattern="[a-z0-9\-]+" maxlength="120" class="input font-mono" placeholder="generated from name"></div>
                <div><label class="label">Job title (English)</label><input name="title_en" value="{{ old('title_en', $author->title_en) }}" maxlength="120" class="input" placeholder="e.g. Senior Correspondent"></div>
                <div><label class="label">Job title (Somali)</label><input name="title_so" value="{{ old('title_so', $author->title_so) }}" maxlength="120" class="input" lang="so" placeholder="tusaale: Wariye Sare"></div>
                <div><label class="label">Desk</label><input name="desk" value="{{ old('desk', $author->desk) }}" maxlength="80" class="input" placeholder="e.g. Garowe, Politics, Video"></div>
                <div><label class="label">Linked newsroom account</label><select name="user_id" class="select"><option value="">— None —</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('user_id', $author->user_id) == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Biography</h2></div>
            <div class="panel-body grid gap-4 md:grid-cols-2">
                <div><label class="label">English</label><textarea name="bio_en" rows="7" maxlength="3000" class="textarea">{{ old('bio_en', $author->bio_en) }}</textarea></div>
                <div><label class="label">Somali</label><textarea name="bio_so" rows="7" maxlength="3000" class="textarea" lang="so">{{ old('bio_so', $author->bio_so) }}</textarea></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Contact & social</h2></div>
            <div class="panel-body grid gap-4 md:grid-cols-3">
                <div><label class="label">Public email</label><input type="email" name="email" value="{{ old('email', $author->email) }}" maxlength="190" class="input"></div>
                <div><label class="label">X handle</label><input name="x_handle" value="{{ old('x_handle', $author->x_handle) }}" maxlength="60" class="input" placeholder="without @"></div>
                <div><label class="label">LinkedIn URL</label><input type="url" name="linkedin_url" value="{{ old('linkedin_url', $author->linkedin_url) }}" maxlength="255" class="input" placeholder="https://www.linkedin.com/in/…"></div>
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Photo</h2></div>
            <div class="panel-body"><x-admin.media-input name="photo_id" :value="old('photo_id', $author->photo_id)" label="Portrait" /></div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Display</h2></div>
            <div class="panel-body space-y-3">
                <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_staff" value="1" class="check" @checked(old('is_staff', $author->is_staff))> Staff journalist</label>
                <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="show_on_team_page" value="1" class="check" @checked(old('show_on_team_page', $author->show_on_team_page))> Show on team page</label>
                <div><label class="label">Team page order</label><input type="number" name="team_order" min="0" max="9999" value="{{ old('team_order', $author->team_order) }}" class="input num"></div>
                <button class="btn btn-primary w-full">{{ $author->exists ? 'Save profile' : 'Create profile' }}</button>
            </div>
        </section>
    </aside>
</form>
@endsection
