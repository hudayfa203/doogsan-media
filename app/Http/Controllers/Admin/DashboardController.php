<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\BreakingAlert;
use App\Models\ContactMessage;
use App\Models\LiveEvent;
use App\Models\NewsTip;
use App\Models\PageView;
use App\Models\Subscriber;
use App\Models\Video;
use App\Services\AnalyticsService;
use App\Services\HealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        [$start, $end] = $analytics->range($request->input('from'), $request->input('to'));
        $todayStart = now()->setTimezone(config('doogsan.timezone'))->startOfDay()->utc();

        $statusCounts = ArticleTranslation::select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status');

        $stats = [
            'published' => $statusCounts['published'] ?? 0,
            'drafts' => $statusCounts['draft'] ?? 0,
            'pending' => ($statusCounts['submitted'] ?? 0) + ($statusCounts['editing'] ?? 0),
            'approved' => $statusCounts['approved'] ?? 0,
            'scheduled' => $statusCounts['scheduled'] ?? 0,
            'breaking' => BreakingAlert::where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'today' => ArticleTranslation::where('status', 'published')->where('published_at', '>=', $todayStart)->count(),
            'today_by_locale' => ArticleTranslation::where('status', 'published')->where('published_at', '>=', $todayStart)
                ->select('locale', DB::raw('COUNT(*) as c'))->groupBy('locale')->pluck('c', 'locale'),
            'live_visitors' => $analytics->liveVisitors(),
            'subscribers' => Subscriber::active()->count(),
            'subscribers_new' => Subscriber::active()->where('confirmed_at', '>=', $start)->count(),
            'video_plays' => (int) Video::sum('play_count'),
            'video_views_period' => PageView::where('content_type', 'video')->whereBetween('created_at', [$start, $end])->count(),
        ];

        $summary = $analytics->summary($start, $end);
        $daily = $analytics->daily($start, $end);
        $topArticles = $analytics->topArticles($start, $end, 6);

        $categoryActivity = Article::query()
            ->join('article_translations as t', 't.article_id', '=', 'articles.id')
            ->where('t.status', 'published')->whereBetween('t.published_at', [$start, $end])
            ->whereNotNull('articles.category_id')
            ->select('articles.category_id', DB::raw('COUNT(*) as c'))->groupBy('articles.category_id')->orderByDesc('c')->limit(8)
            ->pluck('c', 'category_id');
        $categories = \App\Models\Category::with('translations')->whereIn('id', $categoryActivity->keys())->get()->keyBy('id');

        $journalists = Author::query()
            ->join('articles', 'articles.author_id', '=', 'authors.id')
            ->join('article_translations as t', 't.article_id', '=', 'articles.id')
            ->where('t.status', 'published')->whereBetween('t.published_at', [$start, $end])
            ->select('authors.id', 'authors.name', 'authors.slug', DB::raw('COUNT(DISTINCT t.id) as stories'), DB::raw('SUM(t.view_count) as views'))
            ->groupBy('authors.id', 'authors.name', 'authors.slug')->orderByDesc('stories')->limit(6)->get();

        $pipeline = ArticleTranslation::with('article.author', 'article.assignee', 'locker')
            ->whereIn('status', ['submitted', 'editing', 'approved'])->latest('updated_at')->limit(8)->get();
        $scheduled = ArticleTranslation::where('status', 'scheduled')->orderBy('scheduled_at')->limit(6)->get();
        $deadlines = Article::with('translations', 'assignee')->whereNotNull('deadline_at')
            ->where('deadline_at', '>=', now()->subDay())->whereHas('translations', fn ($q) => $q->whereNotIn('status', ['published', 'archived']))
            ->orderBy('deadline_at')->limit(6)->get();

        $alerts = $this->operationalAlerts();

        return view('admin.dashboard', [
            'stats' => $stats,
            'summary' => $summary,
            'daily' => $daily,
            'topArticles' => $topArticles,
            'categoryActivity' => $categoryActivity,
            'categories' => $categories,
            'journalists' => $journalists,
            'pipeline' => $pipeline,
            'scheduled' => $scheduled,
            'deadlines' => $deadlines,
            'liveEvents' => LiveEvent::where('status', 'live')->withCount('allUpdates')->get(),
            'activity' => AuditLog::with('user')->latest('created_at')->limit(12)->get(),
            'opsAlerts' => $alerts,
            'from' => $start->copy()->setTimezone(config('doogsan.timezone'))->toDateString(),
            'to' => $end->copy()->setTimezone(config('doogsan.timezone'))->toDateString(),
        ]);
    }

    /** Lightweight JSON for the dashboard's 30-second refresh. */
    public function metrics(AnalyticsService $analytics)
    {
        return response()->json([
            'live_visitors' => $analytics->liveVisitors(),
            'pending' => ArticleTranslation::whereIn('status', ['submitted', 'editing'])->count(),
            'breaking' => BreakingAlert::where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'inbox' => ContactMessage::where('status', 'new')->count() + NewsTip::where('status', 'new')->count(),
        ]);
    }

    protected function operationalAlerts(): array
    {
        $alerts = [];
        $health = app(HealthService::class)->checks();
        foreach (['scheduler', 'broadcasting', 'queue', 'storage', 'backups'] as $k) {
            if (($health[$k]['status'] ?? 'ok') !== 'ok') {
                $alerts[] = ['level' => $health[$k]['status'], 'text' => ucfirst($k).': '.$health[$k]['detail'], 'url' => route('admin.system')];
            }
        }
        $overdue = Article::whereNotNull('deadline_at')->where('deadline_at', '<', now())
            ->whereHas('translations', fn ($q) => $q->whereNotIn('status', ['published', 'archived', 'scheduled']))->count();
        if ($overdue) {
            $alerts[] = ['level' => 'warn', 'text' => "{$overdue} assignment(s) past deadline", 'url' => route('admin.articles.all')];
        }
        $mt = ArticleTranslation::where('is_machine_translated', true)->whereNull('translation_approved_by')->count();
        if ($mt) {
            $alerts[] = ['level' => 'warn', 'text' => "{$mt} machine translation(s) awaiting editor approval", 'url' => route('admin.articles.translations')];
        }
        $newTips = NewsTip::where('status', 'new')->count();
        if ($newTips) {
            $alerts[] = ['level' => 'info', 'text' => "{$newTips} new reader tip(s)", 'url' => route('admin.inbox.index', ['tab' => 'tips'])];
        }

        return $alerts;
    }
}
