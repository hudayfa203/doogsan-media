<?php

namespace App\Services;

use App\Models\ArticleTranslation;
use App\Models\BroadcastChannel;
use App\Models\HomepageLayout;
use App\Models\LiveEvent;
use App\Models\PageView;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomepageService
{
    public const SECTION_TYPES = [
        'latest' => 'Latest news stream',
        'trending' => 'Trending now',
        'live' => 'Live coverage',
        'video' => 'Featured video',
        'category' => 'Section (category)',
        'format' => 'Journalism format (Opinion, Analysis…)',
        'visual' => 'Visual storytelling (photo essays)',
        'ad' => 'Advertising slot',
        'newsletter' => 'Newsletter sign-up',
    ];

    public static function defaultLayout(): array
    {
        return [
            'lead' => null,
            'secondary' => [],
            'show_live_tv' => true,
            'sections' => [
                ['type' => 'live', 'enabled' => true],
                ['type' => 'latest', 'enabled' => true, 'limit' => 8],
                ['type' => 'ad', 'slot' => 'home_mid', 'enabled' => true],
                ['type' => 'category', 'key' => 'puntland', 'enabled' => true, 'style' => 'feature', 'limit' => 5],
                ['type' => 'category', 'key' => 'somalia', 'enabled' => true, 'style' => 'grid', 'limit' => 4],
                ['type' => 'video', 'enabled' => true, 'limit' => 4],
                ['type' => 'format', 'key' => 'opinion', 'enabled' => true, 'style' => 'columns', 'limit' => 4],
                ['type' => 'category', 'key' => 'politics', 'enabled' => true, 'style' => 'list', 'limit' => 5],
                ['type' => 'category', 'key' => 'business', 'enabled' => true, 'style' => 'grid', 'limit' => 4],
                ['type' => 'format', 'key' => 'investigation', 'enabled' => true, 'style' => 'feature', 'limit' => 3],
                ['type' => 'category', 'key' => 'africa', 'enabled' => true, 'style' => 'grid', 'limit' => 4],
                ['type' => 'category', 'key' => 'world', 'enabled' => true, 'style' => 'grid', 'limit' => 4],
                ['type' => 'visual', 'enabled' => true, 'limit' => 3],
                ['type' => 'category', 'key' => 'technology', 'enabled' => true, 'style' => 'list', 'limit' => 4],
                ['type' => 'category', 'key' => 'health', 'enabled' => true, 'style' => 'list', 'limit' => 4],
                ['type' => 'category', 'key' => 'education', 'enabled' => true, 'style' => 'list', 'limit' => 4],
                ['type' => 'category', 'key' => 'environment', 'enabled' => true, 'style' => 'grid', 'limit' => 4],
                ['type' => 'category', 'key' => 'sports', 'enabled' => true, 'style' => 'grid', 'limit' => 4],
                ['type' => 'newsletter', 'enabled' => true],
            ],
        ];
    }

    public function layout(string $locale): array
    {
        $saved = HomepageLayout::activeFor($locale)?->layout;

        return $saved ? array_replace(self::defaultLayout(), $saved) : self::defaultLayout();
    }

    public function flush(string $locale): void
    {
        Cache::forget("home.{$locale}");
        Cache::forget("pulse.{$locale}");
    }

    /** Base query for public stories in an edition, with everything cards need. */
    public static function stories(string $locale): Builder
    {
        return ArticleTranslation::query()->live($locale)
            ->whereHas('article', fn ($q) => $q->whereNull('deleted_at'))
            ->with(['article.category.translations', 'article.featuredMedia', 'article.author', 'article.liveEvent'])
            ->orderByDesc('published_at');
    }

    public function build(string $locale): array
    {
        return Cache::remember("home.{$locale}", 60, function () use ($locale) {
            $layout = $this->layout($locale);
            $used = [];

            // Lead: editor's pick, else highest-priority story of the last 36h, else newest.
            $lead = null;
            if ($layout['lead']) {
                $lead = self::stories($locale)->where('article_id', $layout['lead'])->first();
            }
            $lead ??= self::stories($locale)
                ->join('articles', 'articles.id', '=', 'article_translations.article_id')
                ->where('article_translations.published_at', '>=', now()->subHours(36))
                ->orderBy('articles.priority')->orderByDesc('articles.is_breaking')
                ->select('article_translations.*')->first()
                ?? self::stories($locale)->first();
            if ($lead) {
                $used[] = $lead->id;
            }

            $secondary = collect();
            foreach ((array) $layout['secondary'] as $articleId) {
                $s = self::stories($locale)->where('article_id', $articleId)->first();
                if ($s && ! in_array($s->id, $used, true)) {
                    $secondary->push($s);
                    $used[] = $s->id;
                }
            }
            if ($secondary->count() < 4) {
                $fill = self::stories($locale)
                    ->join('articles', 'articles.id', '=', 'article_translations.article_id')
                    ->whereNotIn('article_translations.id', $used)
                    ->where('article_translations.published_at', '>=', now()->subDays(4))
                    ->orderBy('articles.priority')->orderByDesc('article_translations.published_at')
                    ->select('article_translations.*')->limit(4 - $secondary->count())->get();
                $secondary = $secondary->merge($fill);
                $used = array_merge($used, $fill->pluck('id')->all());
            }

            $rail = self::stories($locale)->whereNotIn('id', $used)->limit(6)->get();
            $used = array_merge($used, $rail->pluck('id')->all());

            $sections = [];
            foreach ($layout['sections'] as $i => $section) {
                if (! ($section['enabled'] ?? true)) {
                    continue;
                }
                $resolved = $this->resolveSection($section, $locale, $used);
                if ($resolved !== null) {
                    $sections[] = $resolved + ['index' => $i];
                }
            }

            return [
                'lead' => $lead,
                'secondary' => $secondary,
                'rail' => $rail,
                'sections' => $sections,
                'show_live_tv' => (bool) ($layout['show_live_tv'] ?? true),
            ];
        });
    }

    protected function resolveSection(array $s, string $locale, array &$used): ?array
    {
        $limit = (int) ($s['limit'] ?? 4);

        switch ($s['type']) {
            case 'latest':
                $items = self::stories($locale)->whereNotIn('id', $used)->limit($limit)->get();
                $used = array_merge($used, $items->pluck('id')->all());

                return $items->isEmpty() ? null : ['type' => 'latest', 'items' => $items];

            case 'trending':
                $items = $this->trending($locale, $limit);

                return $items->isEmpty() ? null : ['type' => 'trending', 'items' => $items];

            case 'live':
                $events = LiveEvent::active($locale)->where('show_on_homepage', true)
                    ->with(['updates' => fn ($q) => $q->limit(4), 'cover', 'category.translations'])
                    ->latest('starts_at')->limit(2)->get();

                return $events->isEmpty() ? null : ['type' => 'live', 'items' => $events];

            case 'video':
                $videos = Video::live($locale)->with('thumbnail')
                    ->orderByDesc('is_featured')->orderByDesc('published_at')->limit($limit)->get();

                return $videos->isEmpty() ? null : ['type' => 'video', 'items' => $videos];

            case 'category':
                $items = self::stories($locale)
                    ->whereHas('article.category', fn ($q) => $q->where('key', $s['key'])->orWhereHas('parent', fn ($p) => $p->where('key', $s['key'])))
                    ->whereNotIn('id', $used)->limit($limit)->get();
                if ($items->isEmpty()) {
                    return null;
                }
                $used = array_merge($used, $items->pluck('id')->all());
                $cat = \App\Models\Category::with('translations')->where('key', $s['key'])->first();

                return ['type' => 'category', 'style' => $s['style'] ?? 'grid', 'category' => $cat,
                    'title' => $s['title'] ?? $cat?->nameFor($locale), 'items' => $items];

            case 'format':
                $items = self::stories($locale)->whereHas('article', fn ($q) => $q->where('format', $s['key']))
                    ->whereNotIn('id', $used)->limit($limit)->get();
                if ($items->isEmpty()) {
                    return null;
                }
                $used = array_merge($used, $items->pluck('id')->all());

                return ['type' => 'format', 'style' => $s['style'] ?? 'grid', 'format' => $s['key'],
                    'title' => $s['title'] ?? format_label($s['key'], $locale), 'items' => $items];

            case 'visual':
                $items = self::stories($locale)->whereHas('article', fn ($q) => $q->where('format', 'gallery'))
                    ->limit($limit)->get();

                return $items->isEmpty() ? null : ['type' => 'visual', 'items' => $items];

            case 'ad':
                return ['type' => 'ad', 'slot' => $s['slot'] ?? 'home_mid'];

            case 'newsletter':
                return ['type' => 'newsletter'];
        }

        return null;
    }

    /**
     * Most-read stories from first-party page-view analytics over 48 hours.
     * Returns nothing when there is no real traffic data yet — the homepage
     * then simply omits the module instead of inventing a ranking.
     */
    public function trending(string $locale, int $limit = 5): Collection
    {
        $ids = PageView::query()
            ->where('locale', $locale)->where('content_type', 'article')
            ->where('created_at', '>=', now()->subHours(48))
            ->select('content_id', DB::raw('COUNT(DISTINCT visitor_hash) as readers'))
            ->groupBy('content_id')->orderByDesc('readers')->limit($limit * 2)
            ->pluck('readers', 'content_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return self::stories($locale)->whereIn('id', $ids->keys())->get()
            ->sortByDesc(fn ($t) => $ids[$t->id] ?? 0)->take($limit)->values()
            ->each(fn ($t) => $t->setAttribute('readers', $ids[$t->id] ?? 0));
    }

    public function liveChannel(): ?BroadcastChannel
    {
        return BroadcastChannel::where('is_enabled', true)->where('is_live', true)->where('show_live_indicator', true)
            ->get()->first(fn ($c) => $c->isOnAir());
    }
}
