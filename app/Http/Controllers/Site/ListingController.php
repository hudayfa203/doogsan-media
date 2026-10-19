<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BreakingAlert;
use App\Models\Tag;
use App\Services\HomepageService;
use App\Support\Seo;

class ListingController extends Controller
{
    public function latest(Seo $seo)
    {
        $stories = HomepageService::stories(edition())->paginate(20);
        $seo->set([
            'title' => __('Latest News'),
            'description' => __('Every story published by Doogsan Media, newest first.'),
            'canonical' => lroute('latest'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('latest', [], $l)])->all(),
        ]);

        return view('site.latest', ['stories' => $stories]);
    }

    public function breaking(Seo $seo)
    {
        $locale = edition();
        $alerts = BreakingAlert::current($locale)->with('article.translations')->get();
        $stories = HomepageService::stories($locale)->whereHas('article', fn ($q) => $q->where('is_breaking', true))->paginate(15);
        $seo->set([
            'title' => __('Breaking News'),
            'canonical' => lroute('breaking'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('breaking', [], $l)])->all(),
        ]);

        return view('site.breaking', compact('alerts', 'stories'));
    }

    public function tag(string $slug, Seo $seo)
    {
        $tag = Tag::where('locale', edition())->where('slug', $slug)->firstOrFail();
        $stories = HomepageService::stories(edition())->whereHas('tags', fn ($q) => $q->whereKey($tag->id))->paginate(17);
        $seo->set(['title' => $tag->name, 'canonical' => lroute('tag', ['slug' => $tag->slug]), 'noindex' => $stories->total() < 2]);

        return view('site.listing', ['heading' => $tag->name, 'kicker' => __('Topic'), 'stories' => $stories]);
    }
}
