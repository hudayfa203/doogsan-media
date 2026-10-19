<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CategoryTranslation;
use App\Models\PageTranslation;
use App\Services\HomepageService;
use App\Support\Seo;

/**
 * Resolves /{edition}/{slug} to a section (category), a journalism format
 * (opinion, analysis…) or a corporate page, in that order.
 */
class SectionController extends Controller
{
    public function show(string $slug, Seo $seo)
    {
        $locale = edition();

        if ($ct = CategoryTranslation::where('locale', $locale)->where('slug', $slug)->with('category.children.translations', 'category.translations')->first()) {
            return $this->category($ct, $seo);
        }

        foreach (config('doogsan.formats') as $key => $format) {
            if (($format['slug'][$locale] ?? null) === $slug) {
                return $this->format($key, $seo);
            }
        }

        if ($page = PageTranslation::where('locale', $locale)->where('slug', $slug)->where('is_published', true)->with('page.translations')->first()) {
            return $this->page($page, $seo);
        }

        abort(404);
    }

    protected function category(CategoryTranslation $ct, Seo $seo)
    {
        $locale = edition();
        $cat = $ct->category;
        abort_unless($cat->is_active, 404);
        $ids = $cat->children->pluck('id')->push($cat->id);

        $stories = HomepageService::stories($locale)
            ->whereHas('article', fn ($q) => $q->whereIn('category_id', $ids))
            ->paginate(17)->withQueryString();

        $seo->set([
            'title' => $ct->seo_title ?: $ct->name,
            'description' => $ct->seo_description ?: ($ct->description ?: __('Latest :section news from Doogsan Media.', ['section' => $ct->name])),
            'canonical' => lroute('section', ['slug' => $ct->slug]).($stories->currentPage() > 1 ? '?page='.$stories->currentPage() : ''),
            'alternates' => $cat->translations->mapWithKeys(fn ($x) => [$x->locale => lroute('section', ['slug' => $x->slug], $x->locale)])->all(),
        ]);
        $seo->breadcrumbs([[__('Home'), lroute('home')], [$ct->name, lroute('section', ['slug' => $ct->slug])]]);

        return view('site.listing', [
            'heading' => $ct->name,
            'kicker' => __('Section'),
            'intro' => $ct->description,
            'stories' => $stories,
            'subsections' => $cat->children,
            'accent' => $cat->color,
        ]);
    }

    protected function format(string $key, Seo $seo)
    {
        $locale = edition();
        $stories = HomepageService::stories($locale)
            ->whereHas('article', fn ($q) => $q->where('format', $key))
            ->paginate(17)->withQueryString();
        $label = format_label($key);

        $seo->set([
            'title' => $label,
            'description' => __('format_intro.'.$key),
            'canonical' => lroute('format', ['slug' => config("doogsan.formats.{$key}.slug.{$locale}")]),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('format', ['slug' => config("doogsan.formats.{$key}.slug.{$l}")], $l)])->all(),
        ]);

        return view('site.listing', [
            'heading' => $label,
            'kicker' => __('Doogsan Journalism'),
            'intro' => __('format_intro.'.$key),
            'stories' => $stories,
            'format' => $key,
        ]);
    }

    protected function page(PageTranslation $page, Seo $seo)
    {
        $seo->set([
            'title' => $page->title,
            'description' => $page->seo_description ?: $page->summary,
            'canonical' => $page->url(),
            'alternates' => $page->page->translations->where('is_published', true)->mapWithKeys(fn ($x) => [$x->locale => $x->url()])->all(),
        ]);

        return view('site.page', ['page' => $page]);
    }
}
