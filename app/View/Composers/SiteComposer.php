<?php

namespace App\View\Composers;

use App\Models\BreakingAlert;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Services\HomepageService;
use App\Support\Seo;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SiteComposer
{
    public function compose(View $view): void
    {
        $locale = edition();

        $menus = Cache::remember("menus.{$locale}", 300, function () use ($locale) {
            $out = [];
            foreach (Menu::with('items.children', 'items.menu')->where('locale', $locale)->get() as $menu) {
                $out[$menu->location] = $menu->items->map(fn ($i) => [
                    'label' => $i->label, 'href' => $i->href($locale), 'new_tab' => $i->open_new_tab, 'highlight' => $i->highlight,
                    'children' => $i->children->map(fn ($c) => ['label' => $c->label, 'href' => $c->href($locale)])->all(),
                ])->all();
            }
            if (empty($out['primary'])) {
                $out['primary'] = Category::active()->with('translations')->where('in_navigation', true)->whereNull('parent_id')->get()
                    ->map(fn ($c) => ['label' => $c->nameFor($locale), 'href' => lroute('section', ['slug' => $c->t($locale)?->slug ?? $c->key], $locale), 'new_tab' => false, 'highlight' => false, 'children' => []])->all();
            }

            return $out;
        });

        $pulse = Cache::remember("pulse.{$locale}", 15, function () use ($locale) {
            $latest = HomepageService::stories($locale)->limit(1)->first();
            $tv = app(HomepageService::class)->liveChannel();

            return [
                'alerts' => BreakingAlert::current($locale)->with('article.translations')->get()->map->toPublicArray()->values(),
                'latest_id' => $latest?->id,
                'latest_at' => $latest?->published_at?->toIso8601String(),
                'tv_on_air' => (bool) $tv,
                'live_events' => \App\Models\LiveEvent::active($locale)->get(['id', 'title', 'slug', 'locale'])->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'url' => $e->url()]),
            ];
        });

        $features = (array) setting('features', []) + ['live_tv' => true, 'newsletter' => true, 'tips' => true, 'pwa' => true, 'dark_mode' => true];

        $view->with([
            'seo' => app(Seo::class),
            'menus' => $menus,
            'pulse' => $pulse,
            'social' => array_filter((array) setting('social_'.$locale, setting('social', []))),
            'features' => $features,
            'footerPages' => Cache::remember("footer-pages.{$locale}", 300, fn () => Page::with('translations')->where('show_in_footer', true)->get()
                ->map(fn ($p) => $p->t($locale))->filter(fn ($t) => $t?->is_published)->map(fn ($t) => ['label' => $t->title, 'href' => $t->url()])->values()->all()),
        ]);
    }
}
