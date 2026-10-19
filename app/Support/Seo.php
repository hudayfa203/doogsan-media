<?php

namespace App\Support;

/**
 * Per-request SEO state. Controllers fill it; the layout renders title,
 * description, canonical, hreflang alternates, Open Graph and JSON-LD.
 */
class Seo
{
    public ?string $title = null;

    public ?string $description = null;

    public ?string $canonical = null;

    public ?string $image = null;

    public string $type = 'website';

    public bool $noindex = false;

    /** @var array<string,string> locale => url */
    public array $alternates = [];

    /** @var array<int,array> */
    public array $jsonLd = [];

    public ?string $publishedTime = null;

    public ?string $modifiedTime = null;

    public ?string $section = null;

    /** Analytics context for the beacon: ['type' => 'article', 'id' => 12] */
    public array $content = ['type' => 'page'];

    public function set(array $values): static
    {
        foreach ($values as $k => $v) {
            if (property_exists($this, $k)) {
                $this->{$k} = $v;
            }
        }

        return $this;
    }

    public function addJsonLd(array $data): static
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $data;

        return $this;
    }

    public function fullTitle(): string
    {
        $site = (string) lsetting('site_name', 'Doogsan Media');

        return $this->title ? "{$this->title} | {$site}" : $site.' — '.lsetting('tagline', __('Independent news from Puntland, Somalia and the world'));
    }

    public function publisher(): array
    {
        return [
            '@type' => 'NewsMediaOrganization',
            'name' => 'Doogsan Media',
            'url' => url('/'),
            'logo' => ['@type' => 'ImageObject', 'url' => asset('images/brand/doogsan-logo-light.png')],
            'email' => setting('contact_email', config('doogsan.contact_email')),
            'sameAs' => array_values(array_unique(array_filter(array_merge((array) setting('social_en', []), (array) setting('social_so', []))))),
        ];
    }

    public function breadcrumbs(array $crumbs): static
    {
        return $this->addJsonLd([
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1],
            ])->all(),
        ]);
    }
}
