<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Category;
use App\Models\LiveEvent;
use App\Models\User;
use App\Services\ArticleService;
use App\Services\BodyRenderer;
use App\Services\WorkflowService;
use App\Support\TextDiff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Newsroom article management. English and Somali are two independent
 * publications: each story is created in one newsroom (articles.edition)
 * and appears only in that edition. A version for the other edition exists
 * only when an editor with translation rights deliberately creates it.
 */
class ArticleController extends Controller
{
    public function __construct(protected ArticleService $articles, protected WorkflowService $workflow) {}

    protected function guardNewsroom(Request $request, string $edition): void
    {
        abort_unless($request->user()->worksIn($edition), 403, __('You are not assigned to the :e newsroom.', ['e' => editions()[$edition]['name']]));
    }

    protected function authorizeEdit(Request $request, Article $article, string $edition): void
    {
        $this->guardNewsroom($request, $edition);
        $u = $request->user();
        $own = in_array($u->id, [$article->created_by, $article->assignee_id], true) || $article->author?->user_id === $u->id;
        abort_unless($u->hasPermission('articles.edit_any') || ($own && $u->hasPermission('articles.edit_own')), 403, __('You can only edit your own stories.'));
    }

    /** Edition newsroom: that edition's stories only. */
    public function index(Request $request, string $edition)
    {
        $this->guardNewsroom($request, $edition);

        $q = ArticleTranslation::query()->where('locale', $edition)
            ->with(['article.category.translations', 'article.author', 'article.featuredMedia', 'article.assignee', 'article.translations', 'locker'])
            ->whereHas('article', fn ($a) => $request->input('filter') === 'trash' ? $a->onlyTrashed() : $a)
            ->latest('updated_at');

        if ($s = trim((string) $request->input('q'))) {
            $q->where('title', 'like', "%{$s}%");
        }
        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        $q->whereHas('article', function ($a) use ($request) {
            if ($request->input('filter') === 'trash') {
                $a->onlyTrashed();
            }
            $request->input('category') && $a->where('category_id', $request->input('category'));
            $request->input('author') && $a->where('author_id', $request->input('author'));
            $request->input('format') && $a->where('format', $request->input('format'));
            match ($request->input('filter')) {
                'mine' => $a->where(fn ($w) => $w->where('created_by', $request->user()->id)->orWhere('assignee_id', $request->user()->id)),
                'overdue' => $a->whereNotNull('deadline_at')->where('deadline_at', '<', now()),
                'breaking' => $a->where('is_breaking', true),
                'translated' => $a->whereHas('translations', fn ($t) => $t->where('locale', '!=', $request->route('edition'))),
                default => null,
            };
        });

        return view('admin.articles.index', [
            'edition' => $edition,
            'stories' => $q->paginate(25)->withQueryString(),
            'categories' => Category::forEdition($edition)->with('translations')->orderBy('position')->get(),
            'authors' => Author::orderBy('name')->get(['id', 'name']),
            'counts' => ArticleTranslation::where('locale', $edition)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    /** Combined management view across both publications. */
    public function all(Request $request)
    {
        $q = ArticleTranslation::query()
            ->with(['article.category.translations', 'article.author', 'article.featuredMedia', 'locker'])
            ->whereIn('locale', $request->user()->newsroomEditions())
            ->when($request->input('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('edition'), fn ($q, $e) => $q->where('locale', $e))
            ->whereHas('article')
            ->latest('updated_at');

        return view('admin.articles.all', ['stories' => $q->paginate(30)->withQueryString()]);
    }

    /** Explicit translation overview — only stories editors chose to publish in both editions. */
    public function translations(Request $request)
    {
        $pending = ArticleTranslation::with('article')->where('is_machine_translated', true)->whereNull('translation_approved_by')->latest()->get();
        $paired = Article::has('translations', '>=', 2)->with('translations', 'category.translations')->latest('updated_at')->paginate(30);

        return view('admin.articles.translations', compact('pending', 'paired'));
    }

    public function create(Request $request, string $edition)
    {
        $this->guardNewsroom($request, $edition);
        $article = new Article(['edition' => $edition, 'format' => 'news', 'priority' => 3]);
        $article->author_id = Author::where('user_id', $request->user()->id)->value('id');

        return $this->form($article, $edition);
    }

    public function edit(Request $request, string $edition, Article $article)
    {
        $this->authorizeEdit($request, $article, $edition);
        $article->load(['translations.tags', 'translations.locker', 'translations.corrections', 'coAuthors', 'related.translations', 'gallery', 'featuredMedia', 'comments.user']);

        // Opening the other edition of a story = deliberately creating a translation.
        if (! $article->t($edition) && $edition !== $article->edition) {
            abort_unless($request->user()->hasPermission('translations.manage'), 403, __('Only editors with translation rights can create a version in another edition.'));
        }

        return $this->form($article, $edition);
    }

    protected function form(Article $article, string $edition)
    {
        $user = request()->user();
        $t = $article->exists ? $article->translations->firstWhere('locale', $edition) : null;
        $source = $article->exists ? $article->translations->firstWhere('locale', '!=', $edition) : null;

        $data = [
            'exists' => (bool) $t,
            'title' => $t?->title ?? '',
            'slug' => $t?->slug ?? '',
            'standfirst' => $t?->standfirst ?? '',
            'body' => $t?->body ? json_decode($t->body, true) : null,
            'seo_title' => $t?->seo_title ?? '',
            'seo_description' => $t?->seo_description ?? '',
            'canonical_url' => $t?->canonical_url ?? '',
            'noindex' => (bool) $t?->noindex,
            'tags' => $t ? $t->tags->pluck('name')->implode(', ') : '',
            'unpublish_at' => $t?->unpublish_at ? local_time($t->unpublish_at)->format('Y-m-d\TH:i') : '',
            'is_machine_translated' => (bool) $t?->is_machine_translated,
            'mt_approved' => (bool) $t?->translation_approved_by,
            'status' => $t?->status ?? 'new',
            'version' => $t?->version ?? 0,
            'scheduled_at' => $t?->scheduled_at ? local_time($t->scheduled_at)->format('Y-m-d\TH:i') : '',
            'url' => $t?->url(),
            'is_live' => $t?->isLive() ?? false,
            'locked_by' => $t && $t->isLockedFor($user) ? $t->locker?->name : null,
            'targets' => $t ? $this->workflow->allowedTargets($t, $user) : [],
            'checklist' => $t ? $this->workflow->checklist($t) : [],
            'corrections' => $t ? $t->corrections->map(fn ($c) => ['kind' => $c->kind, 'note' => $c->note, 'at' => news_date($c->published_at)])->all() : [],
        ];

        return view('admin.articles.edit', [
            'article' => $article,
            'edition' => $edition,
            'data' => $data,
            'source' => $source,
            'counterpart' => $article->exists ? $article->translations->firstWhere('locale', $edition === 'en' ? 'so' : 'en') : null,
            'categories' => Category::forEdition($edition)->with('translations')->orderBy('position')->get(),
            'authors' => Author::orderBy('name')->get(['id', 'name']),
            'users' => User::where('is_active', true)->orderBy('name')->get()->filter(fn ($u) => $u->worksIn($edition))->values(),
            'liveEvents' => LiveEvent::where('locale', $edition)->whereIn('status', ['scheduled', 'live'])->get(['id', 'title']),
            'related' => $article->exists ? $article->related->map(fn ($r) => ['id' => $r->id, 'title' => $r->t($edition)?->title ?? $r->displayTitle()])->values() : collect(),
            'gallery' => $article->exists ? $article->gallery->map(fn ($m) => ['id' => $m->id, 'url' => $m->url(480)])->values() : collect(),
        ]);
    }

    protected function rules(string $edition): array
    {
        return [
            'format' => ['required', Rule::in(array_keys(config('doogsan.formats')))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->whereNull('locale')->orWhere('locale', $edition))],
            'author_id' => 'nullable|exists:authors,id',
            'co_authors' => 'nullable|array',
            'co_authors.*' => 'exists:authors,id',
            'featured_media_id' => 'nullable|exists:media_assets,id',
            'priority' => 'required|integer|between:1,5',
            'is_breaking' => 'nullable|boolean',
            'assignee_id' => 'nullable|exists:users,id',
            'deadline_at' => 'nullable|date',
            'video_url' => 'nullable|url|max:255',
            'audio_url' => 'nullable|url|max:255',
            'location' => 'nullable|string|max:120',
            'live_event_id' => ['nullable', Rule::exists('live_events', 'id')->where('locale', $edition)],
            'related' => 'nullable|array',
            'related.*' => 'integer|exists:articles,id',
            'gallery' => 'nullable|array',
            'gallery.*' => 'integer|exists:media_assets,id',
            'title' => 'required|string|max:250',
            'slug' => 'nullable|string|max:120|regex:/^[a-z0-9\-]*$/',
            'standfirst' => 'nullable|string|max:600',
            'body' => 'nullable|string',
            'seo_title' => 'nullable|string|max:120',
            'seo_description' => 'nullable|string|max:320',
            'canonical_url' => 'nullable|url|max:255',
            'noindex' => 'nullable|boolean',
            'tags' => 'nullable|string|max:500',
            'unpublish_at' => 'nullable|date',
            'is_machine_translated' => 'nullable|boolean',
            'version' => 'nullable|integer',
            'revision_note' => 'nullable|string|max:190',
        ];
    }

    /** Shape a validated request into ArticleService::save() input for one edition. */
    protected function payload(array $v, string $edition): array
    {
        $tz = config('doogsan.timezone');
        $shared = collect($v)->only(['format', 'category_id', 'author_id', 'co_authors', 'featured_media_id', 'priority', 'is_breaking',
            'assignee_id', 'deadline_at', 'video_url', 'audio_url', 'location', 'live_event_id', 'related', 'gallery'])->all();
        if (! empty($shared['deadline_at'])) {
            $shared['deadline_at'] = Carbon::parse($shared['deadline_at'], $tz)->utc();
        }
        $shared['related'] = $v['related'] ?? [];
        $shared['gallery'] = $v['gallery'] ?? [];

        $t = collect($v)->only(['title', 'slug', 'standfirst', 'seo_title', 'seo_description', 'canonical_url', 'noindex', 'tags', 'is_machine_translated', 'version', 'revision_note'])->all();
        $t['body'] = isset($v['body']) ? json_decode($v['body'], true) : null;
        $t['unpublish_at'] = ! empty($v['unpublish_at']) ? Carbon::parse($v['unpublish_at'], $tz)->utc() : null;

        return $shared + ['translations' => [$edition => $t]];
    }

    public function store(Request $request, string $edition)
    {
        $this->guardNewsroom($request, $edition);
        $v = $request->validate($this->rules($edition));
        $data = $this->payload($v, $edition) + ['edition' => $edition];
        $article = $this->articles->save(null, $data, $request->user());

        return $this->respond($request, $article, $edition, __('Story created as a draft in the :e newsroom.', ['e' => editions()[$edition]['name']]));
    }

    public function update(Request $request, string $edition, Article $article)
    {
        $this->authorizeEdit($request, $article, $edition);
        if (! $article->t($edition) && $edition !== $article->edition) {
            abort_unless($request->user()->hasPermission('translations.manage'), 403);
        }
        $v = $request->validate($this->rules($edition));
        $article = $this->articles->save($article, $this->payload($v, $edition), $request->user());

        if ($request->filled('then_transition') && ($t = $article->t($edition))) {
            $schedule = $request->filled('schedule_at') ? Carbon::parse($request->input('schedule_at'), config('doogsan.timezone'))->utc() : null;
            $this->workflow->transition($t, $request->input('then_transition'), $request->user(), $request->input('transition_note'), $schedule);

            return $this->respond($request, $article, $edition, __('Saved and moved to “:s”.', ['s' => __('status.'.$request->input('then_transition'))]));
        }

        return $this->respond($request, $article, $edition, __('Saved.'));
    }

    protected function respond(Request $request, Article $article, string $edition, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $article->id, 'message' => $message,
                'version' => $article->t($edition)?->version, 'edit_url' => story_edit_url($article, $edition)]);
        }

        return redirect()->to(story_edit_url($article, $edition))->with('status', $message);
    }

    public function autosave(Request $request, Article $article)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(editions()))], 'title' => 'required|string|max:250', 'standfirst' => 'nullable|string|max:600', 'body' => 'nullable']);
        $this->authorizeEdit($request, $article, $data['locale']);
        $t = $article->t($data['locale']);
        if (! $t || $t->status === 'published') {
            return response()->json(['ok' => false, 'skipped' => $t ? 'published' : 'unsaved']);
        }
        $saved = $this->articles->saveTranslation($article, $data['locale'], [
            'title' => $data['title'], 'standfirst' => $data['standfirst'] ?? null,
            'body' => is_string($data['body'] ?? null) ? json_decode($data['body'], true) : ($data['body'] ?? null),
        ], $request->user(), 'autosave');

        return response()->json(['ok' => true, 'version' => $saved->version, 'saved_at' => now()->toIso8601String()]);
    }

    public function lock(Request $request, Article $article, string $locale)
    {
        $t = $article->t($locale);
        if (! $t) {
            return response()->json(['ok' => true]);
        }
        $ok = $this->articles->lock($t, $request->user());

        return response()->json(['ok' => $ok, 'locked_by' => $ok ? null : $t->locker?->name, 'version' => $t->version]);
    }

    public function unlock(Request $request, Article $article, string $locale)
    {
        if ($t = $article->t($locale)) {
            $this->articles->unlock($t, $request->user());
        }

        return response()->json(['ok' => true]);
    }

    public function transition(Request $request, Article $article, string $locale)
    {
        $this->guardNewsroom($request, $locale);
        $data = $request->validate(['to' => ['required', Rule::in(ArticleTranslation::STATUSES)], 'note' => 'nullable|string|max:190', 'schedule_at' => 'nullable|date']);
        $t = $article->t($locale) ?? abort(404);
        $schedule = ! empty($data['schedule_at']) ? Carbon::parse($data['schedule_at'], config('doogsan.timezone'))->utc() : null;
        $this->workflow->transition($t, $data['to'], $request->user(), $data['note'] ?? null, $schedule);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'status' => $t->fresh()->status])
            : back()->with('status', __('Status changed to :s.', ['s' => __('status.'.$data['to'])]));
    }

    public function approveTranslation(Request $request, Article $article, string $locale)
    {
        $t = $article->t($locale) ?? abort(404);
        $t->update(['translation_approved_by' => $request->user()->id]);
        AuditLog::record('translation.approved', "Approved machine-assisted translation of “{$t->title}” ({$locale})", $t);

        return back()->with('status', __('Translation approved for publication.'));
    }

    public function duplicate(Request $request, Article $article)
    {
        $copy = $this->articles->duplicate($article->load('translations.tags'), $request->user());

        return redirect()->to(story_edit_url($copy))->with('status', __('Duplicated as a new draft.'));
    }

    public function destroy(Request $request, Article $article)
    {
        if ($article->translations()->where('status', 'published')->exists()) {
            return back()->withErrors(['article' => __('Archive (unpublish) the live version before deleting.')]);
        }
        $article->delete();
        AuditLog::record('article.deleted', "Deleted article #{$article->id} {$article->displayTitle()}", $article);

        return redirect()->route('admin.articles.index', $article->edition)->with('status', __('Article moved to trash.'));
    }

    public function preview(Request $request, Article $article, string $locale)
    {
        $t = $article->t($locale) ?? abort(404);

        return redirect()->to($t->url().'?preview=1');
    }

    public function history(Request $request, Article $article, string $locale)
    {
        $t = $article->t($locale) ?? abort(404);
        $revisions = $t->revisions()->with('user')->limit(60)->get();
        $a = $revisions->firstWhere('id', (int) $request->input('a')) ?? $revisions->get(1);
        $b = $revisions->firstWhere('id', (int) $request->input('b')) ?? $revisions->first();
        $plain = fn ($r) => strip_tags(str_replace(['</p>', '<br>', '</h2>', '</h3>', '</li>'], "\n", $r->body_html ?? ''));

        return view('admin.articles.history', [
            'article' => $article, 't' => $t, 'revisions' => $revisions, 'a' => $a, 'b' => $b,
            'diff' => $a && $b ? TextDiff::html($plain($a), $plain($b)) : null,
            'titleDiff' => $a && $b ? TextDiff::html($a->title, $b->title) : null,
            'transitions' => $t->transitions()->with('user')->get(),
        ]);
    }

    public function rollback(Request $request, Article $article, ArticleRevision $revision)
    {
        $t = $revision->translation;
        abort_unless($t->article_id === $article->id, 404);
        $this->articles->rollback($t, $revision, $request->user());

        return redirect()->to(story_edit_url($article, $t->locale))->with('status', __('Restored version :v.', ['v' => $revision->version]));
    }

    public function correction(Request $request, Article $article, string $locale)
    {
        $data = $request->validate(['kind' => 'required|in:correction,update,clarification', 'note' => 'required|string|max:1000']);
        $t = $article->t($locale) ?? abort(404);
        $this->articles->addCorrection($t, $data['kind'], $data['note'], $request->user());

        return back()->with('status', __('Notice published on the story.'));
    }

    public function comment(Request $request, Article $article)
    {
        $data = $request->validate(['body' => 'required|string|max:2000', 'locale' => 'nullable|in:en,so']);
        $article->comments()->create($data + ['user_id' => $request->user()->id]);
        safe_broadcast(new \App\Events\NewsroomActivity($request->user()->name, 'commented on “'.$article->displayTitle().'”', $article->id));

        return back();
    }

    public function resolveComment(ArticleComment $comment)
    {
        $comment->update(['resolved_at' => $comment->resolved_at ? null : now()]);

        return back();
    }

    /** JSON search for pickers (related stories, homepage picks, breaking links). */
    public function lookup(Request $request)
    {
        $s = trim((string) $request->input('q'));

        return ArticleTranslation::query()
            ->when($request->input('locale'), fn ($q, $l) => $q->where('locale', $l))
            ->when($request->boolean('live'), fn ($q) => $q->live())
            ->when($s, fn ($q) => $q->where('title', 'like', "%{$s}%"))
            ->latest('updated_at')->limit(12)->get(['id', 'article_id', 'title', 'locale', 'status'])
            ->map(fn ($t) => ['id' => $t->article_id, 'title' => $t->title, 'locale' => $t->locale, 'status' => $t->status]);
    }
}
