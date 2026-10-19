<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ArticleTranslation;
use App\Services\BodyRenderer;
use App\Services\HomepageService;
use App\Support\Seo;

class ArticleController extends Controller
{
    protected function find(string $slug): ArticleTranslation
    {
        $t = ArticleTranslation::where('locale', edition())->where('slug', $slug)
            ->with(['article.category.translations', 'article.author.photo', 'article.coAuthors', 'article.featuredMedia',
                'article.gallery', 'article.liveEvent', 'article.translations', 'tags', 'corrections'])
            ->first();

        // Preview for logged-in newsroom staff; everyone else sees only live stories.
        $canPreview = auth()->user()?->hasPermission('articles.view');
        if (! $t || (! $t->isLive() && ! $canPreview) || $t->article?->trashed()) {
            abort(404);
        }

        return $t;
    }

    public function show(string $slug, Seo $seo, BodyRenderer $renderer)
    {
        $t = $this->find($slug);
        $a = $t->article;
        $locale = $t->locale;

        // TOC for long reads is regenerated from the stored document.
        $toc = $t->body ? $renderer->render($t->body, $locale)['toc'] : [];

        $related = $a->related()->with('translations')->get()
            ->map(fn ($r) => $r->t($locale))->filter(fn ($rt) => $rt?->isLive());
        if ($related->count() < 4) {
            $more = HomepageService::stories($locale)
                ->where('id', '!=', $t->id)->whereNotIn('id', $related->pluck('id'))
                ->whereHas('article', fn ($q) => $q->where('category_id', $a->category_id))
                ->limit(4 - $related->count())->get();
            $related = $related->merge($more);
        }
        $related->loadMissing('article.featuredMedia', 'article.category.translations');

        $latest = HomepageService::stories($locale)->where('id', '!=', $t->id)->limit(6)->get();

        $alternates = $a->translations->filter(fn ($x) => $x->isLive())->mapWithKeys(fn ($x) => [$x->locale => $x->url()])->all();
        $image = $a->featuredMedia?->url(1600);
        $authors = collect([$a->author])->merge($a->coAuthors)->filter()->unique('id');

        $seo->set([
            'title' => $t->seo_title ?: $t->title,
            'description' => $t->seo_description ?: $t->standfirst,
            'canonical' => $t->canonical_url ?: $t->url(),
            'image' => $t->ogMedia?->url(1600) ?? $image,
            'type' => 'article',
            'noindex' => $t->noindex || ! $t->isLive(),
            'alternates' => $alternates,
            'publishedTime' => $t->published_at?->toIso8601String(),
            'modifiedTime' => ($t->content_updated_at ?? $t->published_at)?->toIso8601String(),
            'section' => $a->category?->nameFor($locale),
            'content' => ['type' => 'article', 'id' => $t->id],
        ]);
        $seo->addJsonLd([
            '@type' => $a->format === 'opinion' ? 'OpinionNewsArticle' : ($a->format === 'analysis' ? 'AnalysisNewsArticle' : 'NewsArticle'),
            'headline' => mb_substr($t->title, 0, 110),
            'description' => $t->standfirst,
            'image' => $image ? [$image] : [],
            'datePublished' => $t->published_at?->toIso8601String(),
            'dateModified' => ($t->content_updated_at ?? $t->published_at)?->toIso8601String(),
            'inLanguage' => $locale,
            'mainEntityOfPage' => $t->url(),
            'articleSection' => $a->category?->nameFor($locale),
            'keywords' => $t->tags->pluck('name')->implode(', '),
            'author' => $authors->map(fn ($au) => ['@type' => 'Person', 'name' => $au->name, 'url' => lroute('author', ['slug' => $au->slug])])->values()->all(),
            'publisher' => $seo->publisher(),
        ]);
        $crumbs = [[__('Home'), lroute('home')]];
        if ($a->category) {
            $crumbs[] = [$a->category->nameFor($locale), lroute('section', ['slug' => $a->category->t($locale)?->slug ?? $a->category->key])];
        }
        $crumbs[] = [$t->title, $t->url()];
        $seo->breadcrumbs($crumbs);

        return view('site.article', compact('t', 'a', 'toc', 'related', 'latest', 'alternates', 'authors'));
    }

    public function print(string $slug)
    {
        $t = $this->find($slug);

        return view('site.article-print', ['t' => $t, 'a' => $t->article]);
    }
}
