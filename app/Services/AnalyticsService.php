<?php

namespace App\Services;

use App\Models\ArticleTranslation;
use App\Models\PageView;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * First-party, privacy-conscious analytics. Visitors are counted with a
 * daily-rotating salted hash (IP + user agent + date + app key); no cookie
 * is set and the raw IP is never stored.
 */
class AnalyticsService
{
    public function record(Request $request, array $payload): void
    {
        $ua = (string) $request->userAgent();
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse/i', $ua)) {
            return;
        }

        $locale = in_array($payload['locale'] ?? '', array_keys(editions()), true) ? $payload['locale'] : 'en';
        $type = in_array($payload['type'] ?? '', ['article', 'video', 'live', 'page'], true) ? $payload['type'] : 'page';
        $id = isset($payload['id']) ? (int) $payload['id'] : null;
        $ref = isset($payload['ref']) ? parse_url((string) $payload['ref'], PHP_URL_HOST) : null;
        $ownHost = parse_url(config('app.url'), PHP_URL_HOST);

        PageView::create([
            'locale' => $locale,
            'content_type' => $type,
            'content_id' => $id,
            'path' => mb_substr((string) ($payload['path'] ?? '/'), 0, 250),
            'referrer_host' => $ref && $ref !== $ownHost ? mb_substr($ref, 0, 190) : null,
            'source' => $this->source($ref, $ownHost),
            'device' => preg_match('/Mobile|Android|iPhone/i', $ua) ? 'mobile' : (preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : 'desktop'),
            'visitor_hash' => hash('sha256', $request->ip().'|'.$ua.'|'.now()->toDateString().'|'.config('app.key')),
            'created_at' => now(),
        ]);

        if ($type === 'article' && $id) {
            ArticleTranslation::whereKey($id)->increment('view_count');
        } elseif ($type === 'video' && $id) {
            Video::whereKey($id)->increment('play_count');
        }
    }

    protected function source(?string $ref, ?string $own): string
    {
        if (! $ref) {
            return 'direct';
        }
        if ($ref === $own) {
            return 'internal';
        }

        return match (true) {
            (bool) preg_match('/google\.|bing\.|duckduckgo|yahoo\.|yandex/i', $ref) => 'search',
            (bool) preg_match('/facebook|fb\.|t\.co|twitter|x\.com|tiktok|instagram|linkedin|whatsapp|telegram|youtube/i', $ref) => 'social',
            default => 'referral',
        };
    }

    public function range(?string $from, ?string $to): array
    {
        $tz = config('doogsan.timezone');
        $end = $to ? Carbon::parse($to, $tz)->endOfDay()->utc() : now();
        $start = $from ? Carbon::parse($from, $tz)->startOfDay()->utc() : now()->subDays(13)->startOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    public function summary(Carbon $start, Carbon $end): array
    {
        $base = PageView::whereBetween('created_at', [$start, $end]);

        return [
            'views' => (clone $base)->count(),
            'visitors' => (clone $base)->distinct('visitor_hash')->count('visitor_hash'),
            'by_locale' => (clone $base)->select('locale', DB::raw('COUNT(*) as c'))->groupBy('locale')->pluck('c', 'locale')->all(),
            'by_source' => (clone $base)->select('source', DB::raw('COUNT(*) as c'))->groupBy('source')->orderByDesc('c')->pluck('c', 'source')->all(),
            'by_device' => (clone $base)->select('device', DB::raw('COUNT(*) as c'))->groupBy('device')->pluck('c', 'device')->all(),
            'top_referrers' => (clone $base)->whereNotNull('referrer_host')->select('referrer_host', DB::raw('COUNT(*) as c'))->groupBy('referrer_host')->orderByDesc('c')->limit(8)->pluck('c', 'referrer_host')->all(),
        ];
    }

    /** Daily views per edition, filled with zeros for days without traffic. */
    public function daily(Carbon $start, Carbon $end): array
    {
        $tz = config('doogsan.timezone');
        $rows = PageView::whereBetween('created_at', [$start, $end])->get(['locale', 'created_at'])
            ->groupBy(fn ($v) => $v->created_at->setTimezone($tz)->toDateString().'|'.$v->locale)
            ->map->count();

        $days = [];
        for ($d = $start->copy()->setTimezone($tz)->startOfDay(); $d->lte($end->copy()->setTimezone($tz)); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = ['en' => $rows[$key.'|en'] ?? 0, 'so' => $rows[$key.'|so'] ?? 0];
        }

        return $days;
    }

    public function topArticles(Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        $counts = PageView::whereBetween('created_at', [$start, $end])->where('content_type', 'article')
            ->select('content_id', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_hash) as readers'))
            ->groupBy('content_id')->orderByDesc('views')->limit($limit)->get()->keyBy('content_id');

        return ArticleTranslation::with('article.author', 'article.category.translations')->whereIn('id', $counts->keys())->get()
            ->map(fn ($t) => tap($t, fn ($t) => $t->setAttribute('period_views', $counts[$t->id]->views)->setAttribute('period_readers', $counts[$t->id]->readers)))
            ->sortByDesc('period_views')->values();
    }

    public function liveVisitors(int $minutes = 5): int
    {
        return PageView::where('created_at', '>=', now()->subMinutes($minutes))->distinct('visitor_hash')->count('visitor_hash');
    }
}
