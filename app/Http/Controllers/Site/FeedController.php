<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ArticleTranslation;
use App\Models\Author;
use App\Models\CategoryTranslation;
use App\Models\PageTranslation;
use App\Services\HomepageService;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function robots()
    {
        $custom = setting('robots_txt');
        $body = $custom ?: "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /*/search\nDisallow: /*/raadi\n";
        if (app()->environment('staging') || setting('block_indexing')) {
            $body = "User-agent: *\nDisallow: /\n";
        }
        $body .= "\nSitemap: ".route('sitemap.index')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemapIndex()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_keys(editions()) as $l) {
            $xml .= '<sitemap><loc>'.e(lroute('sitemap', [], $l)).'</loc></sitemap>';
            $xml .= '<sitemap><loc>'.e(lroute('sitemap.news', [], $l)).'</loc></sitemap>';
        }

        return response($xml.'</sitemapindex>', 200, ['Content-Type' => 'application/xml']);
    }

    public function sitemap()
    {
        $locale = edition();
        $xml = Cache::remember("sitemap.{$locale}", 900, function () use ($locale) {
            $urls = [[lroute('home'), now()->toAtomString(), 'hourly']];
            foreach (['latest', 'live', 'tv', 'videos', 'authors', 'team', 'contact'] as $r) {
                $urls[] = [lroute($r), null, 'daily'];
            }
            foreach (CategoryTranslation::where('locale', $locale)->get() as $c) {
                $urls[] = [lroute('section', ['slug' => $c->slug]), null, 'hourly'];
            }
            foreach (config('doogsan.formats') as $f) {
                $urls[] = [lroute('section', ['slug' => $f['slug'][$locale]]), null, 'daily'];
            }
            foreach (PageTranslation::where('locale', $locale)->where('is_published', true)->get() as $p) {
                $urls[] = [$p->url(), $p->updated_at?->toAtomString(), 'monthly'];
            }
            foreach (Author::all() as $a) {
                $urls[] = [lroute('author', ['slug' => $a->slug]), null, 'weekly'];
            }
            ArticleTranslation::live($locale)->where('noindex', false)->orderByDesc('published_at')->limit(5000)
                ->get(['slug', 'locale', 'published_at', 'content_updated_at'])
                ->each(function ($t) use (&$urls) {
                    $urls[] = [$t->url(), ($t->content_updated_at ?? $t->published_at)->toAtomString(), 'monthly'];
                });

            $out = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($urls as [$loc, $mod, $freq]) {
                $out .= '<url><loc>'.e($loc).'</loc>'.($mod ? "<lastmod>{$mod}</lastmod>" : '')."<changefreq>{$freq}</changefreq></url>";
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** Google News sitemap: stories from the last 48 hours. */
    public function newsSitemap()
    {
        $locale = edition();
        $items = ArticleTranslation::live($locale)->where('noindex', false)->where('published_at', '>=', now()->subHours(48))
            ->orderByDesc('published_at')->limit(1000)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';
        foreach ($items as $t) {
            $xml .= '<url><loc>'.e($t->url()).'</loc><news:news><news:publication><news:name>Doogsan Media</news:name><news:language>'.$locale
                .'</news:language></news:publication><news:publication_date>'.$t->published_at->toAtomString().'</news:publication_date><news:title>'
                .e($t->title).'</news:title></news:news></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    public function rss()
    {
        $locale = edition();
        $items = HomepageService::stories($locale)->limit(40)->get();
        $xml = view('site.rss', compact('items', 'locale'))->render();

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    public function manifest()
    {
        return response()->json([
            'name' => 'Doogsan Media',
            'short_name' => 'Doogsan',
            'description' => 'News from Puntland, Somalia and the world in English and Somali.',
            'start_url' => '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#04261f',
            'theme_color' => '#04261f',
            'icons' => [
                ['src' => asset('images/brand/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('images/brand/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => asset('images/brand/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
