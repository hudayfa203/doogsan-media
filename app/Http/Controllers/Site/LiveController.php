<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LiveEvent;
use App\Support\Seo;

class LiveController extends Controller
{
    public function index(Seo $seo)
    {
        $locale = edition();
        $active = LiveEvent::where('locale', $locale)->where('status', 'live')->with(['cover', 'updates' => fn ($q) => $q->limit(3)])->latest('starts_at')->get();
        $upcoming = LiveEvent::where('locale', $locale)->where('status', 'scheduled')->orderBy('starts_at')->limit(6)->get();
        $past = LiveEvent::where('locale', $locale)->where('status', 'closed')->with('cover')->latest('ended_at')->paginate(9);

        $seo->set([
            'title' => __('Live Coverage'),
            'description' => __('Minute-by-minute reporting from the Doogsan Media newsroom.'),
            'canonical' => lroute('live'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('live', [], $l)])->all(),
        ]);

        return view('site.live-index', compact('active', 'upcoming', 'past'));
    }

    public function show(string $slug, Seo $seo)
    {
        $event = LiveEvent::where('locale', edition())->where('slug', $slug)
            ->with(['cover', 'category.translations', 'updates.author', 'updates.media'])->firstOrFail();

        $seo->set([
            'title' => ($event->isLive() ? __('LIVE').': ' : '').$event->title,
            'description' => $event->summary,
            'canonical' => $event->url(),
            'image' => $event->cover?->url(1600),
            'type' => 'article',
            'content' => ['type' => 'live', 'id' => $event->id],
        ]);
        $seo->addJsonLd([
            '@type' => 'LiveBlogPosting',
            'headline' => $event->title,
            'description' => $event->summary,
            'coverageStartTime' => $event->starts_at?->toIso8601String(),
            'coverageEndTime' => $event->ended_at?->toIso8601String(),
            'url' => $event->url(),
            'publisher' => $seo->publisher(),
            'liveBlogUpdate' => $event->updates->take(20)->map(fn ($u) => [
                '@type' => 'BlogPosting', 'headline' => $u->headline ?: mb_substr(strip_tags($u->body), 0, 100),
                'datePublished' => $u->posted_at?->toIso8601String(), 'articleBody' => strip_tags($u->body),
            ])->values()->all(),
        ]);

        return view('site.live-show', ['event' => $event]);
    }
}
