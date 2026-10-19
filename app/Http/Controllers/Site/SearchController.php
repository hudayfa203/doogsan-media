<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Services\HomepageService;
use App\Services\SearchService;
use App\Support\Seo;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search, Seo $seo)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120',
            'lang' => 'nullable|in:en,so,all',
            'section' => 'nullable|string|max:60',
            'author' => 'nullable|string|max:120',
            'format' => 'nullable|string|max:30',
            'type' => 'nullable|in:articles,videos,live',
            'tag' => 'nullable|string|max:80',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'sort' => 'nullable|in:relevance,newest,oldest',
        ]);
        $filters['lang'] ??= edition();

        $results = $search->search($filters);

        $seo->set(['title' => filled($filters['q'] ?? null) ? __('Search: :q', ['q' => $filters['q']]) : __('Search'), 'noindex' => true, 'canonical' => lroute('search')]);

        return view('site.search', [
            'results' => $results,
            'filters' => $filters,
            'categories' => Category::active()->with('translations')->whereNull('parent_id')->get(),
            'authors' => Author::orderBy('name')->get(['name', 'slug']),
        ]);
    }

    public function archive(Request $request, Seo $seo)
    {
        $year = (int) $request->integer('year') ?: null;
        $month = (int) $request->integer('month') ?: null;
        $locale = edition();
        $tz = config('doogsan.timezone');

        $q = HomepageService::stories($locale);
        if ($year) {
            $start = \Illuminate\Support\Carbon::create($year, $month ?: 1, 1, 0, 0, 0, $tz);
            $end = $month ? $start->copy()->endOfMonth() : $start->copy()->endOfYear();
            $q->whereBetween('published_at', [$start->utc(), $end->utc()]);
        }
        $stories = $q->paginate(30)->withQueryString();

        $months = \App\Models\ArticleTranslation::live($locale)->get(['published_at'])
            ->groupBy(fn ($t) => $t->published_at->setTimezone($tz)->format('Y-m'))->map->count()->sortKeysDesc();

        $seo->set(['title' => __('Archive'), 'canonical' => lroute('archive'), 'noindex' => (bool) $year]);

        return view('site.archive', compact('stories', 'months', 'year', 'month'));
    }
}
