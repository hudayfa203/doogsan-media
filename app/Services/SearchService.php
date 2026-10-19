<?php

namespace App\Services;

use App\Models\ArticleTranslation;
use App\Models\LiveEvent;
use App\Models\Video;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Database-backed news search with weighted relevance (headline > standfirst
 * > body). Works on MySQL, MariaDB and SQLite with no external service; the
 * query layer is isolated here so a Scout/Meilisearch driver can replace it.
 */
class SearchService
{
    public function search(array $f): LengthAwarePaginator
    {
        $type = $f['type'] ?? 'articles';
        $terms = $this->terms($f['q'] ?? '');
        $locales = ($f['lang'] ?? 'all') === 'all' ? array_keys(editions()) : [$f['lang']];
        $tz = config('doogsan.timezone');
        $from = ! empty($f['from']) ? Carbon::parse($f['from'], $tz)->startOfDay()->utc() : null;
        $to = ! empty($f['to']) ? Carbon::parse($f['to'], $tz)->endOfDay()->utc() : null;

        if ($type === 'videos') {
            $q = Video::query()->whereIn('locale', $locales)->where('status', 'published')->where('published_at', '<=', now())->with('thumbnail');
            foreach ($terms as $t) {
                $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('description', 'like', "%{$t}%"));
            }
            $from && $q->where('published_at', '>=', $from);
            $to && $q->where('published_at', '<=', $to);

            return $q->orderBy('published_at', ($f['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')->paginate(15)->withQueryString();
        }

        if ($type === 'live') {
            $q = LiveEvent::query()->whereIn('locale', $locales)->whereIn('status', ['live', 'closed'])->with('cover');
            foreach ($terms as $t) {
                $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('summary', 'like', "%{$t}%"));
            }

            return $q->latest('starts_at')->paginate(15)->withQueryString();
        }

        $q = ArticleTranslation::query()
            ->select('article_translations.*')
            ->whereIn('article_translations.locale', $locales)
            ->where('article_translations.status', 'published')
            ->where('article_translations.published_at', '<=', now())
            ->where(fn ($w) => $w->whereNull('unpublish_at')->orWhere('unpublish_at', '>', now()))
            ->join('articles', 'articles.id', '=', 'article_translations.article_id')
            ->whereNull('articles.deleted_at')
            ->with(['article.category.translations', 'article.featuredMedia', 'article.author']);

        foreach ($terms as $t) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")
                ->orWhere('standfirst', 'like', "%{$t}%")
                ->orWhere('search_text', 'like', "%{$t}%"));
        }

        if (! empty($f['section'])) {
            $q->whereHas('article.category', fn ($c) => $c->where('key', $f['section'])->orWhereHas('parent', fn ($p) => $p->where('key', $f['section'])));
        }
        if (! empty($f['format'])) {
            $q->where('articles.format', $f['format']);
        }
        if (! empty($f['author'])) {
            $q->whereHas('article.author', fn ($a) => $a->where('slug', $f['author']));
        }
        if (! empty($f['tag'])) {
            $q->whereHas('tags', fn ($tg) => $tg->where('slug', $f['tag']));
        }
        $from && $q->where('article_translations.published_at', '>=', $from);
        $to && $q->where('article_translations.published_at', '<=', $to);

        $sort = $f['sort'] ?? ($terms ? 'relevance' : 'newest');
        if ($sort === 'relevance' && $terms) {
            $score = [];
            $bindings = [];
            foreach ($terms as $t) {
                $score[] = '(CASE WHEN title LIKE ? THEN 10 ELSE 0 END) + (CASE WHEN standfirst LIKE ? THEN 4 ELSE 0 END) + (CASE WHEN search_text LIKE ? THEN 1 ELSE 0 END)';
                array_push($bindings, "%{$t}%", "%{$t}%", "%{$t}%");
            }
            $q->orderByRaw('('.implode(' + ', $score).') DESC', $bindings)->orderByDesc('article_translations.published_at');
        } else {
            $q->orderBy('article_translations.published_at', $sort === 'oldest' ? 'asc' : 'desc');
        }

        return $q->paginate(15)->withQueryString();
    }

    /** Split into up to 6 meaningful terms, escaping LIKE wildcards. */
    public function terms(string $q): array
    {
        $q = trim(mb_strtolower($q));
        if ($q === '') {
            return [];
        }

        return collect(preg_split('/\s+/u', $q))
            ->map(fn ($t) => str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $t))
            ->filter(fn ($t) => mb_strlen($t) >= 2)->unique()->take(6)->values()->all();
    }
}
