<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleTranslation;
use App\Models\PageView;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics)
    {
        [$start, $end] = $analytics->range($request->input('from'), $request->input('to'));
        $tz = config('doogsan.timezone');

        $published = ArticleTranslation::where('status', 'published')->whereBetween('published_at', [$start, $end])
            ->select('locale', DB::raw('COUNT(*) as c'))->groupBy('locale')->pluck('c', 'locale');

        $byType = PageView::whereBetween('created_at', [$start, $end])
            ->select('content_type', DB::raw('COUNT(*) as c'))->groupBy('content_type')->pluck('c', 'content_type');

        $hours = PageView::whereBetween('created_at', [$start, $end])->get(['created_at'])
            ->groupBy(fn ($v) => (int) $v->created_at->setTimezone($tz)->format('G'))->map->count();

        return view('admin.analytics', [
            'summary' => $analytics->summary($start, $end),
            'daily' => $analytics->daily($start, $end),
            'top' => $analytics->topArticles($start, $end, 15),
            'published' => $published,
            'byType' => $byType,
            'hours' => collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $hours[$h] ?? 0]),
            'from' => $start->copy()->setTimezone($tz)->toDateString(),
            'to' => $end->copy()->setTimezone($tz)->toDateString(),
            'liveVisitors' => $analytics->liveVisitors(),
        ]);
    }
}
