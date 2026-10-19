<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Services\HomepageService;
use App\Support\Seo;

class PeopleController extends Controller
{
    public function authors(Seo $seo)
    {
        $authors = Author::with('photo')->withCount('articles')->orderBy('team_order')->orderBy('name')->get();
        $seo->set(['title' => __('Our Journalists'), 'canonical' => lroute('authors'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('authors', [], $l)])->all()]);

        return view('site.authors', ['authors' => $authors, 'title' => __('Our Journalists')]);
    }

    public function team(Seo $seo)
    {
        $authors = Author::with('photo')->where('show_on_team_page', true)->where('is_staff', true)
            ->orderBy('team_order')->orderBy('name')->get()->groupBy(fn ($a) => $a->desk ?: __('Newsroom'));
        $seo->set(['title' => __('Editorial Team'), 'canonical' => lroute('team'),
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('team', [], $l)])->all()]);

        return view('site.team', ['desks' => $authors]);
    }

    public function author(string $slug, Seo $seo)
    {
        $author = Author::with('photo')->where('slug', $slug)->firstOrFail();
        $stories = HomepageService::stories(edition())
            ->whereHas('article', fn ($q) => $q->where('author_id', $author->id)->orWhereHas('coAuthors', fn ($c) => $c->whereKey($author->id)))
            ->paginate(12);

        $seo->set(['title' => $author->name, 'description' => \Illuminate\Support\Str::limit((string) $author->bioFor(edition()), 155),
            'canonical' => lroute('author', ['slug' => $author->slug]), 'image' => $author->photo?->url(480), 'type' => 'profile',
            'alternates' => collect(editions())->mapWithKeys(fn ($e, $l) => [$l => lroute('author', ['slug' => $author->slug], $l)])->all()]);
        $seo->addJsonLd(['@type' => 'Person', 'name' => $author->name, 'jobTitle' => $author->titleFor(edition()),
            'worksFor' => ['@type' => 'NewsMediaOrganization', 'name' => 'Doogsan Media'], 'url' => lroute('author', ['slug' => $author->slug])]);

        return view('site.author', compact('author', 'stories'));
    }
}
