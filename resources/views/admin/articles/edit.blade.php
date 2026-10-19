@extends('layouts.admin')
@section('title', ($article->exists && $data['exists'] ? 'Edit: '.$data['title'] : 'New story').' · '.editions()[$edition]['name'])

@section('content')
@php
    $ed = editions()[$edition];
    $other = $edition === 'en' ? 'so' : 'en';
    $isSo = $edition === 'so';
    $L = fn ($en, $so) => $isSo ? $so : $en; // editor chrome in the newsroom's language where it helps writers
    $cfg = [
        'articleId' => $article->exists && $data['exists'] ? $article->id : null,
        'activeLocale' => $edition,
        'translations' => [$edition => $data],
        'gallery' => $gallery,
        'featured' => $article->featuredMedia ? ['id' => $article->featuredMedia->id, 'url' => $article->featuredMedia->url(480)] : null,
        'placeholders' => [$edition => $L('Write the story…', 'Ku qor sheekada halkan…')],
    ];
    $targetLabels = [
        'submitted' => ['Submit for review', 'btn-primary'], 'editing' => ['Move to editing', 'btn-secondary'], 'approved' => ['Approve', 'btn-primary'],
        'published' => ['Publish now', 'btn-primary'], 'scheduled' => ['Schedule', 'btn-secondary'], 'archived' => ['Archive / unpublish', 'btn-danger'], 'draft' => ['Return to draft', 'btn-ghost'],
    ];
    $formAction = $article->exists ? route('admin.articles.update', ['edition' => $edition, 'article' => $article]) : route('admin.articles.store', $edition);
@endphp

<form method="post" action="{{ $formAction }}" x-data="storyEditor(@js($cfg))" @submit="serialize($el)" class="pb-24">
    @csrf
    @if ($article->exists) @method('PUT') @endif
    <input type="hidden" name="body">
    <input type="hidden" name="version" :value="t['{{ $edition }}'].version">

    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('admin.articles.index', $edition) }}" class="text-xs font-bold text-[var(--color-ink-400)] hover:text-white">← {{ $ed['name'] }} Newsroom</a>
            <div class="mt-1 flex items-center gap-3">
                <span class="rounded-md px-2 py-0.5 text-[0.6875rem] font-extrabold tracking-wider {{ $isSo ? 'bg-[#5cb8ff]/15 text-[#a7d8ff]' : 'bg-[var(--color-signal)]/15 text-[var(--color-signal)]' }}">{{ strtoupper($edition) }} · {{ $ed['native'] }}</span>
                <h1 class="truncate text-xl font-extrabold text-white" x-text="t['{{ $edition }}'].title || '{{ $L('New story', 'Sheeko cusub') }}'"></h1>
            </div>
            <p class="mt-1 text-xs text-[var(--color-ink-400)]">
                @if ($article->exists) Story #{{ $article->id }} · originated in the {{ editions()[$article->edition]['name'] }} newsroom @endif
                <span x-show="savedAt" x-cloak class="text-[var(--color-signal)]">· autosaved <span x-text="savedAt?.toLocaleTimeString()"></span></span>
                <span x-show="dirty" x-cloak class="text-[#ffd98a]">· unsaved changes</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($source)<button type="button" @click="side = !side" class="btn btn-secondary" :class="side && '!border-[var(--color-signal)]'">Source text ({{ strtoupper($source->locale) }})</button>@endif
            <button type="button" @click="preview = preview === 'mobile' ? 'desktop' : 'mobile'" class="btn btn-secondary" :class="preview === 'mobile' && '!border-[var(--color-signal)]'">Mobile width</button>
            @if ($data['exists'])
                <a href="{{ route('admin.articles.preview', [$article, $edition]) }}" target="_blank" class="btn btn-secondary"><x-admin.icon name="external" /> Preview</a>
                <a href="{{ route('admin.articles.history', [$article, $edition]) }}" class="btn btn-secondary">History</a>
            @endif
            <button type="submit" class="btn btn-primary">{{ $data['exists'] ? 'Save' : $L('Save draft', 'Kaydi qabyo') }}</button>
        </div>
    </div>

    {{-- Edition independence notice --}}
    @if ($article->exists)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-[var(--color-ink-700)] px-4 py-2.5 text-xs text-[var(--color-ink-300)]">
            @if ($counterpart)
                <span>This story also has a <strong>{{ editions()[$other]['name'] }}</strong> version ({{ __('status.'.$counterpart->status) }}) — edited and published independently.</span>
                @if (auth()->user()->worksIn($other))<a href="{{ story_edit_url($article, $other) }}" class="font-bold text-[var(--color-signal)]">Open {{ editions()[$other]['name'] }} version →</a>@endif
            @elseif ($data['exists'])
                <span>Published only in the <strong>{{ $ed['name'] }}</strong> edition.</span>
                @can('translations.manage')
                    @if (auth()->user()->worksIn($other))
                        <a href="{{ story_edit_url($article, $other) }}" class="font-bold text-[var(--color-signal)]" onclick="return confirm('Create a separate {{ editions()[$other]['name'] }} version of this story? It will not be published until an editor approves it.')">Create {{ editions()[$other]['name'] }} version →</a>
                    @endif
                @endcan
            @else
                <span>You are creating the <strong>{{ $ed['name'] }}</strong> version of a story that originated in the {{ editions()[$article->edition]['name'] }} newsroom. Write it fresh or translate from the source text — it will need editor approval like any story.</span>
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- ── Writing area ─────────────────────────────────────── --}}
        <div class="grid gap-6" :class="side ? 'lg:grid-cols-2' : ''">
            @if ($source)
                <div x-show="side" x-cloak class="space-y-3 rounded-lg border border-[var(--color-ink-700)] bg-[var(--color-ink-900)] p-5" lang="{{ $source->locale }}">
                    <p class="eyebrow">Source · {{ editions()[$source->locale]['name'] }} · read only</p>
                    <h2 class="font-['Newsreader_Variable',Georgia,serif] text-2xl font-semibold text-white">{{ $source->title }}</h2>
                    @if ($source->standfirst)<p class="font-['Newsreader_Variable',Georgia,serif] text-[var(--color-ink-200)]">{{ $source->standfirst }}</p>@endif
                    <div class="max-h-[60rem] overflow-y-auto font-['Newsreader_Variable',Georgia,serif] text-[0.98rem] leading-relaxed text-[var(--color-ink-200)] [&_h2]:mt-4 [&_h2]:font-bold [&_p+p]:mt-3">{!! $source->body_html !!}</div>
                </div>
            @endif

            <div class="min-w-0 space-y-4" :class="preview === 'mobile' && !side ? 'mx-auto w-full max-w-[24.5rem]' : ''" lang="{{ $edition }}">
                <div x-show="lockedBy['{{ $edition }}']" x-cloak class="rounded-lg border border-[var(--color-amber)]/50 px-4 py-2.5 text-sm text-[#ffd98a]">
                    🔒 <strong x-text="lockedBy['{{ $edition }}']"></strong> is editing this story. Saving now could overwrite their work.
                </div>
                <div x-show="t['{{ $edition }}'].status === 'published'" x-cloak class="rounded-lg border border-[var(--color-signal-dim)]/40 px-4 py-2 text-xs text-[var(--color-signal)]">
                    This story is live on {{ url('/'.$edition) }}. Saving updates the public version and marks it “Updated”. Autosave is paused for live stories.
                </div>

                <div>
                    <textarea name="title" x-model="t['{{ $edition }}'].title" @input="titleChanged('{{ $edition }}')" rows="2" required
                              class="w-full resize-none border-0 bg-transparent p-0 font-['Newsreader_Variable',Georgia,serif] text-3xl font-semibold leading-tight text-white placeholder:text-[var(--color-ink-600)] focus:ring-0"
                              placeholder="{{ $L('Headline', 'Cinwaanka sheekada') }}"></textarea>
                    <div class="mt-1 flex items-center gap-2 text-xs text-[var(--color-ink-400)]">
                        <span class="num">/{{ $edition }}/{{ seg('article', $edition) }}/</span>
                        <input name="slug" x-model="t['{{ $edition }}'].slug" @input="t['{{ $edition }}'].slugTouched = true" class="input num !w-auto flex-1 !border-transparent !bg-transparent !px-1 !py-0.5 text-xs hover:!border-[var(--color-ink-600)]" aria-label="URL slug">
                        <span class="num" :class="t['{{ $edition }}'].title.length > 110 ? 'text-[var(--color-alarm)]' : ''" x-text="t['{{ $edition }}'].title.length + ' chars'"></span>
                    </div>
                </div>
                <div>
                    <label class="label">{{ $L('Standfirst', 'Hordhac kooban') }} <span class="font-normal text-[var(--color-ink-500)]">— {{ $L('one or two sentences that sell the story', 'hal ama laba jumladood oo sheekada soo koobaya') }}</span></label>
                    <textarea name="standfirst" x-model="t['{{ $edition }}'].standfirst" rows="2" class="textarea font-['Newsreader_Variable',Georgia,serif] !text-base"></textarea>
                </div>

                {{-- Toolbar --}}
                <div class="sticky top-16 z-10 flex flex-wrap items-center gap-0.5 rounded-lg border border-[var(--color-ink-700)] bg-[var(--color-ink-900)] p-1">
                    <button type="button" class="tb-btn" :class="active('heading', { level: 2 }) && 'is-on'" @click="cmd(c => c.toggleHeading({ level: 2 }))" title="Heading">H2</button>
                    <button type="button" class="tb-btn" :class="active('heading', { level: 3 }) && 'is-on'" @click="cmd(c => c.toggleHeading({ level: 3 }))" title="Subheading">H3</button>
                    <span class="mx-1 h-5 w-px bg-[var(--color-ink-700)]"></span>
                    <button type="button" class="tb-btn" :class="active('bold') && 'is-on'" @click="cmd(c => c.toggleBold())" title="Bold"><b>B</b></button>
                    <button type="button" class="tb-btn" :class="active('italic') && 'is-on'" @click="cmd(c => c.toggleItalic())" title="Italic"><i>I</i></button>
                    <button type="button" class="tb-btn" :class="active('link') && 'is-on'" @click="insertLink()" title="Link">Link</button>
                    <span class="mx-1 h-5 w-px bg-[var(--color-ink-700)]"></span>
                    <button type="button" class="tb-btn" :class="active('bulletList') && 'is-on'" @click="cmd(c => c.toggleBulletList())">• List</button>
                    <button type="button" class="tb-btn" :class="active('orderedList') && 'is-on'" @click="cmd(c => c.toggleOrderedList())">1. List</button>
                    <button type="button" class="tb-btn" :class="active('blockquote') && 'is-on'" @click="cmd(c => c.toggleBlockquote())" title="Quote">“ ”</button>
                    <span class="mx-1 h-5 w-px bg-[var(--color-ink-700)]"></span>
                    <button type="button" class="tb-btn" @click="insertImage()">Image</button>
                    <button type="button" class="tb-btn" @click="insertYoutube()">Video</button>
                    <button type="button" class="tb-btn" @click="insertPull()">Pull quote</button>
                    <button type="button" class="tb-btn" @click="insertFact()">Fact box</button>
                    <button type="button" class="tb-btn" @click="insertTable()">Table</button>
                    <button type="button" class="tb-btn" @click="insertRelated()">Related</button>
                    <button type="button" class="tb-btn" @click="insertEmbed()">Embed</button>
                    <span class="ml-auto pr-2 num text-[0.6875rem] text-[var(--color-ink-400)]" x-text="(stats['{{ $edition }}']?.words || 0) + ' words · ' + (stats['{{ $edition }}']?.minutes || 1) + ' min'"></span>
                </div>
                <div x-ref="editor_{{ $edition }}"></div>

                <details class="panel" open>
                    <summary class="panel-head cursor-pointer"><span class="panel-title">Tags, SEO & scheduling</span></summary>
                    <div class="panel-body grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2"><label class="label">Tags</label><input name="tags" x-model="t['{{ $edition }}'].tags" class="input" placeholder="{{ $L('Comma separated: Garowe, Elections, Budget', 'Kala qoqob: Garoowe, Doorashooyinka, Miisaaniyadda') }}"></div>
                        <div><label class="label">SEO title</label><input name="seo_title" x-model="t['{{ $edition }}'].seo_title" maxlength="120" class="input" :placeholder="t['{{ $edition }}'].title"></div>
                        <div><label class="label">Canonical URL <span class="font-normal text-[var(--color-ink-500)]">(syndicated copy only)</span></label><input name="canonical_url" x-model="t['{{ $edition }}'].canonical_url" class="input" placeholder="https://"></div>
                        <div class="md:col-span-2"><label class="label">Search & social description</label><textarea name="seo_description" x-model="t['{{ $edition }}'].seo_description" rows="2" maxlength="320" class="textarea" :placeholder="t['{{ $edition }}'].standfirst"></textarea>
                            <p class="hint"><span x-text="seoDesc('{{ $edition }}').length"></span>/160 shown in search results</p></div>
                        <div class="md:col-span-2 rounded-lg bg-white p-4">
                            <p class="text-xs text-[#4d5156]">doogsanmedia.com › {{ $edition }} › {{ seg('article', $edition) }}</p>
                            <p class="mt-0.5 truncate text-lg text-[#1a0dab]" x-text="seoTitle('{{ $edition }}') + ' | Doogsan Media'"></p>
                            <p class="line-clamp-2 text-sm text-[#4d5156]" x-text="seoDesc('{{ $edition }}') || 'Add a standfirst or description.'"></p>
                        </div>
                        <div><label class="label">Unpublish automatically at (EAT)</label><input type="datetime-local" name="unpublish_at" x-model="t['{{ $edition }}'].unpublish_at" class="input"></div>
                        <div class="flex flex-col justify-end gap-2 text-sm">
                            <label class="flex items-center gap-2"><input type="hidden" name="noindex" value="0"><input type="checkbox" name="noindex" value="1" :checked="t['{{ $edition }}'].noindex" class="check"> Hide from search engines</label>
                            @if ($article->exists && $article->edition !== $edition)
                                <label class="flex items-center gap-2"><input type="hidden" name="is_machine_translated" value="0"><input type="checkbox" name="is_machine_translated" value="1" :checked="t['{{ $edition }}'].is_machine_translated" class="check"> Machine-assisted translation <span class="text-[var(--color-ink-500)]">(needs approval)</span></label>
                            @endif
                        </div>
                        <div class="md:col-span-2"><label class="label">Revision note <span class="font-normal text-[var(--color-ink-500)]">(optional, kept in history)</span></label><input name="revision_note" class="input" placeholder="e.g. Added the ministry’s response"></div>
                    </div>
                </details>
            </div>
        </div>

        {{-- ── Sidebar ──────────────────────────────────────────── --}}
        <aside class="space-y-4">
            <section class="panel">
                <div class="panel-head"><h2>Publishing · {{ $ed['name'] }}</h2><span class="pill" :class="'pill-' + t['{{ $edition }}'].status" x-text="@js(trans('status'))[t['{{ $edition }}'].status]"></span></div>
                <div class="panel-body space-y-3">
                    <template x-if="t['{{ $edition }}'].is_live && t['{{ $edition }}'].url"><a :href="t['{{ $edition }}'].url" target="_blank" class="flex items-center gap-2 text-xs font-bold text-[var(--color-signal)]"><span class="live-dot"></span> Live on {{ $ed['name'] }} site — view</a></template>
                    @if (! $data['exists'])
                        <p class="text-sm text-[var(--color-ink-300)]">Save to create this story as a <strong>draft</strong> in the {{ $ed['name'] }} newsroom. Nothing is public until it is approved and published.</p>
                    @else
                        <div>
                            <p class="eyebrow mb-2">Checklist</p>
                            <ul class="space-y-1.5 text-xs">
                                <template x-for="c in t['{{ $edition }}'].checklist" :key="c.key">
                                    <li class="flex items-start gap-2" :class="c.ok ? 'text-[var(--color-ink-300)]' : (c.required ? 'text-[#ffc9c4]' : 'text-[#ffd98a]')">
                                        <span x-text="c.ok ? '✓' : (c.required ? '✕' : '!')" class="w-3 font-bold"></span><span x-text="c.label + (c.required && !c.ok ? ' (required)' : '')"></span>
                                    </li>
                                </template>
                            </ul>
                            <p class="hint">Reflects the last save.</p>
                        </div>
                        <div x-show="t['{{ $edition }}'].targets.includes('scheduled')"><label class="label">Schedule for (EAT)</label><input type="datetime-local" name="schedule_at" x-model="t['{{ $edition }}'].scheduled_at" class="input"></div>
                        <div><label class="label">Note for the desk</label><input name="transition_note" class="input" placeholder="Optional"></div>
                        <div class="grid gap-2">
                            @foreach ($targetLabels as $target => [$label, $cls])
                                <button type="submit" name="then_transition" value="{{ $target }}" x-show="t['{{ $edition }}'].targets.includes('{{ $target }}')" class="btn {{ $cls }} w-full"
                                        @if (in_array($target, ['archived', 'draft'])) onclick="return confirm('{{ $label }}?')" @endif>{{ $label }}</button>
                            @endforeach
                            <p x-show="!t['{{ $edition }}'].targets.length" class="text-xs text-[var(--color-ink-400)]">Your role cannot change the status of this story.</p>
                        </div>
                        @can('translations.manage')
                            <template x-if="t['{{ $edition }}'].is_machine_translated && !t['{{ $edition }}'].mt_approved">
                                <button type="submit" form="approve-mt" class="btn btn-secondary w-full">Approve machine-assisted translation</button>
                            </template>
                        @endcan
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Story settings</h2></div>
                <div class="panel-body space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Format</label><select name="format" class="select">@foreach (config('doogsan.formats') as $k => $f)<option value="{{ $k }}" @selected(old('format', $article->format) === $k)>{{ $f[$edition] }}</option>@endforeach</select></div>
                        <div><label class="label">Priority</label><select name="priority" class="select">@foreach ([1 => '1 · Lead', 2 => '2 · Top', 3 => '3 · Normal', 4 => '4 · Low', 5 => '5 · Archive'] as $p => $pl)<option value="{{ $p }}" @selected(old('priority', $article->priority) == $p)>{{ $pl }}</option>@endforeach</select></div>
                    </div>
                    <div><label class="label">Section</label><select name="category_id" class="select"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $article->category_id) == $c->id)>{{ $c->nameFor($edition) }}</option>@endforeach</select></div>
                    <div><label class="label">Byline</label><select name="author_id" class="select"><option value="">—</option>@foreach ($authors as $a)<option value="{{ $a->id }}" @selected(old('author_id', $article->author_id) == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
                    <div><label class="label">Co-authors</label><select name="co_authors[]" multiple size="3" class="select">@foreach ($authors as $a)<option value="{{ $a->id }}" @selected($article->exists && $article->coAuthors->contains('id', $a->id))>{{ $a->name }}</option>@endforeach</select></div>
                    <div><label class="label">Dateline</label><input name="location" value="{{ old('location', $article->location) }}" class="input" placeholder="{{ $L('Garowe', 'Garoowe') }}"></div>
                    <label class="flex items-center gap-2 text-sm font-bold text-[#ffc9c4]"><input type="hidden" name="is_breaking" value="0"><input type="checkbox" name="is_breaking" value="1" @checked(old('is_breaking', $article->is_breaking)) class="check"> Breaking story</label>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Media</h2></div>
                <div class="panel-body space-y-4">
                    <div>
                        <label class="label">Featured image</label>
                        <input type="hidden" name="featured_media_id" :value="featured?.id">
                        <button type="button" @click="pickFeatured()" class="relative block aspect-video w-full overflow-hidden rounded-lg border border-dashed border-[var(--color-ink-600)] bg-[var(--color-ink-850)] text-xs font-bold text-[var(--color-ink-400)] hover:border-[var(--color-signal)]">
                            <template x-if="featured"><img :src="featured.url" class="absolute inset-0 h-full w-full object-cover" alt=""></template>
                            <span x-show="!featured">+ Choose from library or upload</span>
                        </button>
                        <button type="button" x-show="featured" @click="featured = null" class="mt-1 text-xs text-[var(--color-ink-400)] hover:text-white">Remove</button>
                    </div>
                    <div>
                        <label class="label">Gallery</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            <template x-for="(g, i) in gallery" :key="g.id">
                                <div class="group relative aspect-square overflow-hidden rounded"><img :src="g.url" class="h-full w-full object-cover" alt=""><input type="hidden" name="gallery[]" :value="g.id">
                                    <button type="button" @click="gallery.splice(i, 1)" class="absolute right-0.5 top-0.5 hidden rounded bg-black/70 px-1 text-xs group-hover:block">✕</button></div>
                            </template>
                            <button type="button" @click="addGallery()" class="grid aspect-square place-items-center rounded border border-dashed border-[var(--color-ink-600)] text-lg text-[var(--color-ink-400)] hover:border-[var(--color-signal)]">+</button>
                        </div>
                    </div>
                    <div><label class="label">Lead video (YouTube URL)</label><input name="video_url" value="{{ old('video_url', $article->video_url) }}" class="input" placeholder="https://youtube.com/watch?v=…"></div>
                    <div><label class="label">Audio narration (MP3 URL)</label><input name="audio_url" value="{{ old('audio_url', $article->audio_url) }}" class="input" placeholder="https://…/story.mp3"></div>
                </div>
            </section>

            <section class="panel" x-data="articlePicker(@js($related), { max: 6, locale: '{{ $edition }}' })">
                <div class="panel-head"><h2>Related reporting</h2></div>
                <div class="panel-body space-y-2">
                    <template x-for="(r, i) in items" :key="r.id">
                        <div class="flex items-center gap-2 rounded bg-[var(--color-ink-850)] px-2 py-1.5 text-xs"><input type="hidden" name="related[]" :value="r.id"><span class="flex-1 truncate" x-text="r.title"></span>
                            <button type="button" @click="move(i, -1)" class="text-[var(--color-ink-400)]">↑</button><button type="button" @click="remove(i)" class="text-[var(--color-ink-400)] hover:text-white">✕</button></div>
                    </template>
                    <div class="relative"><input x-model="q" @input.debounce.300ms="search()" class="input" placeholder="Search {{ $ed['name'] }} stories…">
                        <div x-show="open && results.length" @click.outside="open = false" class="absolute z-20 mt-1 w-full rounded-lg border border-[var(--color-ink-600)] bg-[var(--color-ink-900)] p-1 shadow-2xl">
                            <template x-for="r in results" :key="r.id"><button type="button" @click="add(r)" class="block w-full truncate rounded px-2 py-1.5 text-left text-xs hover:bg-[var(--color-ink-800)]" x-text="r.title"></button></template>
                        </div>
                    </div>
                    <div class="pt-2"><label class="label">Linked live coverage</label><select name="live_event_id" class="select"><option value="">—</option>@foreach ($liveEvents as $ev)<option value="{{ $ev->id }}" @selected($article->live_event_id == $ev->id)>{{ $ev->title }}</option>@endforeach</select></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Assignment</h2></div>
                <div class="panel-body grid gap-3">
                    <div><label class="label">Assigned to</label><select name="assignee_id" class="select"><option value="">—</option>@foreach ($users as $usr)<option value="{{ $usr->id }}" @selected(old('assignee_id', $article->assignee_id) == $usr->id)>{{ $usr->name }}</option>@endforeach</select></div>
                    <div><label class="label">Deadline (EAT)</label><input type="datetime-local" name="deadline_at" value="{{ old('deadline_at', $article->deadline_at ? local_time($article->deadline_at)->format('Y-m-d\TH:i') : '') }}" class="input"></div>
                </div>
            </section>

            @if ($data['exists'])
                @can('corrections.manage')
                    <section class="panel" x-show="t['{{ $edition }}'].status === 'published'" x-cloak>
                        <div class="panel-head"><h2>Correction / update notice</h2></div>
                        <div class="panel-body space-y-2">
                            <template x-for="c in t['{{ $edition }}'].corrections"><p class="text-xs text-[var(--color-ink-300)]"><strong class="uppercase" x-text="c.kind"></strong> · <span x-text="c.at"></span><br><span x-text="c.note"></span></p></template>
                            <select form="correction-form" name="kind" class="select"><option value="correction">Correction</option><option value="update">Update</option><option value="clarification">Clarification</option></select>
                            <textarea form="correction-form" name="note" rows="3" class="textarea" placeholder="Explain what changed and why. Shown publicly at the end of the story."></textarea>
                            <button type="submit" form="correction-form" class="btn btn-secondary w-full">Publish notice</button>
                        </div>
                    </section>
                @endcan
                <section class="panel">
                    <div class="panel-head"><h2>More</h2></div>
                    <div class="panel-body flex flex-wrap gap-2">
                        @can('articles.create')<button type="submit" form="duplicate-form" class="btn btn-secondary btn-sm">Duplicate</button>@endcan
                        @if (auth()->user()->hasPermission('articles.edit_any') && $article->translations->count() === 1)
                            <button type="submit" form="move-form" class="btn btn-secondary btn-sm">Move to {{ $edition === 'en' ? 'Somali' : 'English' }} newsroom</button>
                        @endif
                        @can('articles.delete')<button type="submit" form="delete-form" class="btn btn-danger btn-sm">Move to trash</button>@endcan
                    </div>
                </section>
            @endif
        </aside>
    </div>
</form>

@if ($data['exists'])
    <form id="approve-mt" method="post" action="{{ route('admin.articles.approve-translation', [$article, $edition]) }}">@csrf</form>
    <form id="correction-form" method="post" action="{{ route('admin.articles.correction', [$article, $edition]) }}" data-confirm="Publish this notice on the live story?">@csrf</form>
    <form id="duplicate-form" method="post" action="{{ route('admin.articles.duplicate', $article) }}">@csrf</form>
    <form id="move-form" method="post" action="{{ route('admin.articles.move-edition', $article) }}" data-confirm="Move this story to the other newsroom? Use this only for stories filed in the wrong language.">@csrf</form>
    <form id="delete-form" method="post" action="{{ route('admin.articles.destroy', $article) }}" data-confirm="Move this story to the trash?">@csrf @method('DELETE')</form>

    <section class="panel mt-6 max-w-3xl">
        <div class="panel-head"><h2>Internal notes</h2><span class="text-[0.6875rem] text-[var(--color-ink-400)]">newsroom only</span></div>
        <div class="panel-body space-y-3">
            @forelse ($article->comments->where('locale', $edition)->merge($article->comments->whereNull('locale')) as $c)
                <div class="flex gap-3 {{ $c->resolved_at ? 'opacity-50' : '' }}">
                    <span class="grid h-7 w-7 flex-none place-items-center rounded-full bg-[var(--color-ink-700)] text-[0.625rem] font-extrabold text-[var(--color-signal)]">{{ $c->user?->initials() }}</span>
                    <div class="flex-1 text-sm"><p><strong>{{ $c->user?->name }}</strong> <span class="text-xs text-[var(--color-ink-500)]">{{ $c->created_at->diffForHumans() }}</span></p><p class="mt-0.5 text-[var(--color-ink-200)]">{{ $c->body }}</p></div>
                    <form method="post" action="{{ route('admin.comments.resolve', $c) }}">@csrf @method('PATCH')<button class="btn btn-ghost btn-sm">{{ $c->resolved_at ? 'Reopen' : 'Resolve' }}</button></form>
                </div>
            @empty
                <p class="text-sm text-[var(--color-ink-400)]">No notes yet.</p>
            @endforelse
            <form method="post" action="{{ route('admin.articles.comment', $article) }}" class="flex gap-2 pt-2">
                @csrf <input type="hidden" name="locale" value="{{ $edition }}">
                <input name="body" required class="input" placeholder="Leave a note for the desk…">
                <button class="btn btn-secondary">Post</button>
            </form>
        </div>
    </section>
@endif
@endsection
