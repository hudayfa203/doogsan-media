<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPlacement;
use App\Models\BreakingAlert;
use App\Models\BroadcastChannel;
use App\Models\LiveEvent;
use App\Services\AnalyticsService;
use App\Services\HomepageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PulseController extends Controller
{
    /** Everything the live layer needs in one small, cacheable response. */
    public function pulse(string $locale)
    {
        app()->setLocale($locale);
        $data = Cache::remember("pulse.{$locale}", 15, function () use ($locale) {
            $latest = HomepageService::stories($locale)->limit(1)->first();
            $tv = app(HomepageService::class)->liveChannel();

            return [
                'alerts' => BreakingAlert::current($locale)->with('article.translations')->get()->map->toPublicArray()->values(),
                'latest_id' => $latest?->id,
                'latest_at' => $latest?->published_at?->toIso8601String(),
                'tv_on_air' => (bool) $tv,
                'live_events' => LiveEvent::active($locale)->get(['id', 'title', 'slug', 'locale'])->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'url' => $e->url()]),
            ];
        });

        return response()->json($data + ['server_time' => now()->toIso8601String()])
            ->header('Cache-Control', 'public, max-age=10');
    }

    public function latest(Request $request, string $locale)
    {
        app()->setLocale($locale);
        $after = (int) $request->integer('after');
        $items = HomepageService::stories($locale)->when($after, fn ($q) => $q->where('id', '>', $after))->limit(10)->get();

        return response()->json($items->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'url' => $t->url(),
            'standfirst' => $t->standfirst,
            'category' => $t->article->category?->nameFor($locale),
            'image' => $t->article->featuredMedia?->url(480),
            'author' => $t->article->author?->name,
            'published_at' => $t->published_at?->toIso8601String(),
            'is_breaking' => $t->article->is_breaking,
        ]));
    }

    public function liveUpdates(Request $request, LiveEvent $event)
    {
        $after = $request->date('after');
        $updates = $event->updates()->with('author', 'media')
            ->when($after, fn ($q) => $q->where('updated_at', '>', $after))->limit(50)->get();

        return response()->json([
            'status' => $event->status,
            'updates' => $updates->map->toBroadcastArray(),
            'ids' => $event->updates()->pluck('live_event_updates.id'),
        ]);
    }

    public function tvStatus()
    {
        $c = BroadcastChannel::where('is_enabled', true)->get()->first(fn ($c) => $c->isOnAir());

        return response()->json(['on_air' => (bool) $c, 'channel' => $c?->slug]);
    }

    public function beacon(Request $request, AnalyticsService $analytics)
    {
        $payload = json_decode($request->getContent(), true) ?: $request->all();
        if (is_array($payload)) {
            $analytics->record($request, $payload);
        }

        return response()->noContent();
    }

    public function adImpression(AdPlacement $ad)
    {
        $ad->increment('impressions');

        return response()->noContent();
    }
}
