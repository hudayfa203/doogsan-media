<?php

namespace Tests\Feature;

use Tests\TestCase;

class EditionsTest extends TestCase
{
    public function test_root_redirects_to_preferred_edition(): void
    {
        $this->get('/', ['Accept-Language' => 'so-SO,so;q=0.9'])->assertRedirect('/so');
        $this->get('/', ['Accept-Language' => 'en-GB,en;q=0.9'])->assertRedirect('/en');
    }

    public function test_both_homepages_render_with_localized_chrome(): void
    {
        $this->get('/en')->assertOk()->assertSee('Latest News', false)->assertSee('Somali Digital');
        $this->get('/so')->assertOk()->assertSee('Wararkii Ugu Dambeeyay', false)->assertSee('Waxaa naqshadeeyay oo horumariyay');
    }

    public function test_localized_routes_resolve(): void
    {
        foreach (['/en/latest-news', '/so/wararkii-ugu-dambeeyay', '/en/politics', '/so/siyaasadda', '/en/live', '/so/toos',
            '/en/live-tv', '/so/tv-toos', '/en/search?q=port', '/so/raadi?q=deked', '/en/opinion', '/so/aragti', '/en/about', '/so/ku-saabsan'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/en/does-not-exist')->assertNotFound();
    }

    public function test_stories_appear_only_in_their_own_edition(): void
    {
        $en = $this->publishedStory('en', 'English only harbour story');
        $so = $this->publishedStory('so', 'Sheeko Soomaali ah oo keliya');

        $this->get('/en')->assertSee('English only harbour story')->assertDontSee('Sheeko Soomaali ah oo keliya');
        $this->get('/so')->assertSee('Sheeko Soomaali ah oo keliya')->assertDontSee('English only harbour story');

        $this->get($en->t('en')->url())->assertOk();
        $this->get('/so/warar/'.$en->t('en')->slug)->assertNotFound();
        $this->get('/en/news/'.$so->t('so')->slug)->assertNotFound();
    }

    public function test_article_page_has_structured_data_and_hreflang_only_for_existing_versions(): void
    {
        $a = $this->publishedStory('en', 'Structured data story');
        $this->get($a->t('en')->url())
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('<link rel="alternate" hreflang="en"', false)
            ->assertDontSee('<link rel="alternate" hreflang="so"', false);
    }

    public function test_feeds_and_sitemaps(): void
    {
        $this->publishedStory('so', 'Warka feed-ka');
        $this->get('/so/rss.xml')->assertOk()->assertSee('Warka feed-ka');
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/so/news-sitemap.xml')->assertOk()->assertSee('Warka feed-ka');
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
    }

    public function test_official_social_links_per_edition(): void
    {
        $this->get('/en')->assertSee('https://www.tiktok.com/@doogsanenglish', false)->assertSee('https://x.com/doogsan_en', false);
        $this->get('/so')->assertSee('https://www.tiktok.com/@doogsanmedia', false)->assertSee('https://www.facebook.com/DoogsanMedia', false);
    }
}
