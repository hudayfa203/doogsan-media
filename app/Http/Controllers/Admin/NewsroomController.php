<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleTranslation;
use App\Models\BreakingAlert;
use App\Models\LiveEvent;
use App\Models\PageView;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Landing page of one edition's newsroom: its queue, its live operations, its numbers. */
class NewsroomController extends Controller
{
    public function show(Request $request, string $edition)
    {
        abort_unless($request->user()->worksIn($edition), 403);
        $today = now()->setTimezone(config('doogsan.timezone'))->startOfDay()->utc();
        $base = ArticleTranslation::where('locale', $edition)->whereHas('article');

        return view('admin.newsroom', [
            'edition' => $edition,
            'counts' => (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            'publishedToday' => (clone $base)->where('status', 'published')->where('published_at', '>=', $today)->count(),
            'queue' => (clone $base)->whereIn('status', ['submitted', 'editing', 'approved'])->with('article.author', 'locker')->oldest('updated_at')->limit(12)->get(),
            'drafts' => (clone $base)->where('status', 'draft')->with('article.author')->latest('updated_at')->limit(8)->get(),
            'scheduled' => (clone $base)->where('status', 'scheduled')->orderBy('scheduled_at')->limit(8)->get(),
            'recent' => (clone $base)->where('status', 'published')->with('article.author')->latest('published_at')->limit(8)->get(),
            'breaking' => BreakingAlert::current($edition)->get(),
            'live' => LiveEvent::where('locale', $edition)->where('status', 'live')->withCount('allUpdates')->get(),
            'videos' => Video::where('locale', $edition)->where('status', 'published')->count(),
            'views7' => PageView::where('locale', $edition)->where('created_at', '>=', now()->subDays(7))->count(),
            'readers7' => PageView::where('locale', $edition)->where('created_at', '>=', now()->subDays(7))->distinct('visitor_hash')->count('visitor_hash'),
        ]);
    }
}
