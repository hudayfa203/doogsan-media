<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\HomepageService;
use App\Support\Seo;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Root domain → preferred edition: saved choice, then Accept-Language, then default. */
    public function root(Request $request)
    {
        $editions = array_keys(editions());
        $choice = $request->cookie('edition');

        if (! in_array($choice, $editions, true)) {
            $choice = $request->getPreferredLanguage($editions);
            $header = strtolower((string) $request->header('Accept-Language'));
            if (! $header || ! str_contains($header, (string) $choice)) {
                $choice = config('doogsan.default_edition');
            }
        }

        return redirect("/{$choice}", 302)->header('Vary', 'Accept-Language, Cookie');
    }

    public function index(HomepageService $home, Seo $seo)
    {
        $locale = edition();
        $data = $home->build($locale);

        $seo->set([
            'description' => lsetting('site_description', __('Doogsan Media brings you trusted, independent news from Puntland, Somalia, Africa and the world — in English and Somali.')),
            'canonical' => lroute('home'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('home', [], $l)])->all(),
        ]);
        $seo->addJsonLd(['@type' => 'WebSite', 'name' => 'Doogsan Media', 'url' => lroute('home'), 'inLanguage' => $locale,
            'potentialAction' => ['@type' => 'SearchAction', 'target' => lroute('search').'?q={q}', 'query-input' => 'required name=q']]);
        $seo->addJsonLd($seo->publisher());

        return view('site.home', $data + ['tvChannel' => $data['show_live_tv'] ? $home->liveChannel() : null]);
    }
}
