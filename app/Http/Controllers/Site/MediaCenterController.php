<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BroadcastChannel;
use App\Models\BroadcastProgram;
use App\Models\Video;
use App\Services\HomepageService;
use App\Support\Seo;

class MediaCenterController extends Controller
{
    protected function alternates(string $route): array
    {
        return collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute($route, [], $l)])->all();
    }

    public function tv(Seo $seo)
    {
        $channels = BroadcastChannel::where('is_enabled', true)->with('poster')->get();
        $channel = $channels->first(fn ($c) => $c->isOnAir()) ?? $channels->first();
        $schedule = $channel
            ? BroadcastProgram::where('broadcast_channel_id', $channel->id)->where('ends_at', '>', now())->orderBy('starts_at')->limit(8)->get()
            : collect();
        $current = $schedule->first(fn ($p) => $p->isOnNow());
        $recent = Video::live(edition())->with('thumbnail')->whereNotNull('broadcast_program_id')->latest('published_at')->limit(4)->get();
        if ($recent->isEmpty()) {
            $recent = Video::live(edition())->with('thumbnail')->latest('published_at')->limit(4)->get();
        }

        $seo->set(['title' => __('Live TV'), 'description' => __('Watch Doogsan Media live and catch up on recent broadcasts.'),
            'canonical' => lroute('tv'), 'alternates' => $this->alternates('tv')]);

        return view('site.tv', compact('channels', 'channel', 'schedule', 'current', 'recent'));
    }

    public function index(Seo $seo)
    {
        $locale = edition();
        $featured = Video::live($locale)->with('thumbnail')->orderByDesc('is_featured')->latest('published_at')->limit(5)->get();
        $documentaries = Video::live($locale)->where('kind', 'documentary')->with('thumbnail')->latest('published_at')->limit(3)->get();
        $podcasts = Video::live($locale)->where('kind', 'podcast')->with('thumbnail')->latest('published_at')->limit(4)->get();
        $galleries = HomepageService::stories($locale)->whereHas('article', fn ($q) => $q->where('format', 'gallery'))->limit(3)->get();
        $seo->set(['title' => __('Media Centre'), 'canonical' => lroute('media'), 'alternates' => $this->alternates('media')]);

        return view('site.media-center', compact('featured', 'documentaries', 'podcasts', 'galleries'));
    }

    protected function list(string $kind, string $route, string $title, Seo $seo)
    {
        $videos = Video::live(edition())->where('kind', $kind)->with('thumbnail')->latest('published_at')->paginate(12);
        $seo->set(['title' => $title, 'canonical' => lroute($route), 'alternates' => $this->alternates($route)]);

        return view('site.videos', compact('videos', 'title', 'kind'));
    }

    public function videos(Seo $seo)
    {
        $videos = Video::live(edition())->whereIn('kind', ['video', 'interview'])->with('thumbnail')->latest('published_at')->paginate(12);
        $seo->set(['title' => __('Videos'), 'canonical' => lroute('videos'), 'alternates' => $this->alternates('videos')]);

        return view('site.videos', ['videos' => $videos, 'title' => __('Videos'), 'kind' => 'video']);
    }

    public function documentaries(Seo $seo)
    {
        return $this->list('documentary', 'documentaries', __('Documentaries'), $seo);
    }

    public function podcasts(Seo $seo)
    {
        return $this->list('podcast', 'podcasts', __('Podcasts'), $seo);
    }

    public function video(string $slug, Seo $seo)
    {
        $video = Video::live(edition())->where('slug', $slug)->with('thumbnail', 'media', 'author', 'category.translations')->firstOrFail();
        $more = Video::live(edition())->where('id', '!=', $video->id)->with('thumbnail')->latest('published_at')->limit(6)->get();

        $seo->set(['title' => $video->title, 'description' => $video->description, 'canonical' => $video->url(),
            'image' => $video->thumbnailUrl(1280), 'type' => 'video.other', 'content' => ['type' => 'video', 'id' => $video->id]]);
        $seo->addJsonLd(['@type' => 'VideoObject', 'name' => $video->title, 'description' => $video->description ?: $video->title,
            'thumbnailUrl' => [$video->thumbnailUrl(1280)], 'uploadDate' => $video->published_at?->toIso8601String(),
            'duration' => $video->duration_seconds ? 'PT'.$video->duration_seconds.'S' : null, 'embedUrl' => $video->playerUrl()]);

        return view('site.video', compact('video', 'more'));
    }

    public function galleries(Seo $seo)
    {
        $stories = HomepageService::stories(edition())->whereHas('article', fn ($q) => $q->where('format', 'gallery'))
            ->with('article.gallery')->paginate(12);
        $seo->set(['title' => __('Photo Gallery'), 'canonical' => lroute('galleries'), 'alternates' => $this->alternates('galleries')]);

        return view('site.galleries', compact('stories'));
    }
}
