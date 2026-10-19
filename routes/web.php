<?php

use App\Http\Controllers\Site\ArticleController;
use App\Http\Controllers\Site\AudienceController;
use App\Http\Controllers\Site\FeedController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\ListingController;
use App\Http\Controllers\Site\LiveController;
use App\Http\Controllers\Site\MediaCenterController;
use App\Http\Controllers\Site\PeopleController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Site\SectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root & global endpoints
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'root'])->name('root');
Route::get('/robots.txt', [FeedController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [FeedController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/manifest.webmanifest', [FeedController::class, 'manifest'])->name('manifest');
Route::get('/go/ad/{ad}', [AudienceController::class, 'adClick'])->whereNumber('ad')->name('ad.click');

/*
|--------------------------------------------------------------------------
| Language editions: /en and /so
|--------------------------------------------------------------------------
| Every edition gets the same pages through its own localized URL
| segments (config/doogsan.php → segments). Route names are prefixed
| with the edition code: en.latest, so.latest, …  Use lroute('latest').
*/
foreach (array_keys(config('doogsan.editions')) as $locale) {
    $s = fn (string $key) => config("doogsan.segments.{$key}.{$locale}");

    Route::prefix($locale)
        ->name("{$locale}.")
        ->middleware("site.locale:{$locale}")
        ->group(function () use ($s) {
            Route::get('/', [HomeController::class, 'index'])->name('home');

            Route::get($s('latest'), [ListingController::class, 'latest'])->name('latest');
            Route::get($s('breaking'), [ListingController::class, 'breaking'])->name('breaking');
            Route::get($s('article').'/{slug}', [ArticleController::class, 'show'])->name('article');
            Route::get($s('article').'/{slug}/print', [ArticleController::class, 'print'])->name('article.print');
            Route::get($s('topic').'/{slug}', [ListingController::class, 'tag'])->name('tag');

            Route::get($s('live'), [LiveController::class, 'index'])->name('live');
            Route::get($s('live').'/{slug}', [LiveController::class, 'show'])->name('live.show');
            Route::get($s('tv'), [MediaCenterController::class, 'tv'])->name('tv');

            Route::get($s('media'), [MediaCenterController::class, 'index'])->name('media');
            Route::get($s('videos'), [MediaCenterController::class, 'videos'])->name('videos');
            Route::get($s('videos').'/{slug}', [MediaCenterController::class, 'video'])->name('video');
            Route::get($s('documentaries'), [MediaCenterController::class, 'documentaries'])->name('documentaries');
            Route::get($s('podcasts'), [MediaCenterController::class, 'podcasts'])->name('podcasts');
            Route::get($s('galleries'), [MediaCenterController::class, 'galleries'])->name('galleries');

            Route::get($s('authors'), [PeopleController::class, 'authors'])->name('authors');
            Route::get($s('authors').'/{slug}', [PeopleController::class, 'author'])->name('author');
            Route::get($s('team'), [PeopleController::class, 'team'])->name('team');

            Route::get($s('search'), [SearchController::class, 'index'])->name('search');
            Route::get($s('archive'), [SearchController::class, 'archive'])->name('archive');

            Route::get($s('contact'), [AudienceController::class, 'contact'])->name('contact');
            Route::post($s('contact'), [AudienceController::class, 'sendContact'])->middleware('throttle:forms')->name('contact.send');
            Route::get($s('tip'), [AudienceController::class, 'tip'])->name('tip');
            Route::post($s('tip'), [AudienceController::class, 'sendTip'])->middleware('throttle:forms')->name('tip.send');
            Route::get($s('newsletter'), [AudienceController::class, 'newsletter'])->name('newsletter');
            Route::post($s('newsletter'), [AudienceController::class, 'subscribe'])->middleware('throttle:forms')->name('newsletter.subscribe');
            Route::get($s('newsletter').'/confirm/{token}', [AudienceController::class, 'confirm'])->name('newsletter.confirm');
            Route::get($s('newsletter').'/unsubscribe/{token}', [AudienceController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

            Route::get('rss.xml', [FeedController::class, 'rss'])->name('rss');
            Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
            Route::get('news-sitemap.xml', [FeedController::class, 'newsSitemap'])->name('sitemap.news');

            // Last: /en/politics, /en/opinion, /en/about — sections, formats & pages.
            Route::get('{slug}', [SectionController::class, 'show'])
                ->where('slug', '[a-z0-9\-]+')->name('section');
        });
}
