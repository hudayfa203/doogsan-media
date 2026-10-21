<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Redirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressImportTest extends TestCase
{
    protected function fakeWordPress(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $posts = [
            [
                'id' => 101, 'status' => 'publish', 'date' => '2026-09-01T10:00:00', 'date_gmt' => '2026-09-01T07:00:00', 'modified_gmt' => '2026-09-01T07:00:00',
                'link' => 'https://doogsanmedia.com/2026/09/01/madaxweynaha-puntland-oo-booqday-garoowe/',
                'title' => ['rendered' => 'Madaxweynaha Puntland oo booqday Garoowe'],
                'excerpt' => ['rendered' => '<p>Madaxweynaha ayaa sheegay in dowladda ay ka shaqeyn doonto horumarinta waddooyinka iyo biyaha. [&hellip;]</p>'],
                'content' => ['rendered' => '<p>Madaxweynaha Puntland ayaa maanta booqday magaalada Garoowe, waxaana uu la kulmay odayaasha iyo ganacsatada.</p><h2>Mashaariicda cusub</h2><ul><li>Waddooyinka</li><li>Biyaha</li></ul><p><img src="https://doogsanmedia.com/wp-content/uploads/a.png" alt="Shir"></p><script>alert(1)</script>[vc_row]'],
                '_embedded' => [
                    'author' => [['name' => 'Cabdi Xasan']],
                    'wp:featuredmedia' => [['source_url' => 'https://doogsanmedia.com/wp-content/uploads/a.png', 'alt_text' => 'Garoowe', 'caption' => ['rendered' => ''], 'title' => ['rendered' => 'a']]],
                    'wp:term' => [[['taxonomy' => 'category', 'slug' => 'somalia', 'name' => 'Somalia']], [['taxonomy' => 'post_tag', 'slug' => 'garoowe', 'name' => 'Garoowe']]],
                ],
            ],
            [
                'id' => 102, 'status' => 'publish', 'date_gmt' => '2026-09-02T07:00:00', 'modified_gmt' => '2026-09-02T07:00:00',
                'link' => 'https://doogsanmedia.com/2026/09/02/parliament-approves-budget/',
                'title' => ['rendered' => 'Parliament approves the new federal budget &#8211; what it means'],
                'excerpt' => ['rendered' => '<p>The budget was approved after a long debate in the chamber.</p>'],
                'content' => ['rendered' => '<p>The parliament of Somalia has approved the federal budget for the coming year, which the government said will fund <strong>security</strong> and <a href="https://example.org">public services</a>.</p>'],
                '_embedded' => [
                    'author' => [['name' => 'Doogsan Media']],
                    'wp:term' => [[['taxonomy' => 'category', 'slug' => 'opinion', 'name' => 'Opinion'], ['taxonomy' => 'category', 'slug' => 'politics', 'name' => 'Politics']], []],
                ],
            ],
        ];

        Http::fake([
            'doogsanmedia.com/wp-json/wp/v2/posts*' => Http::response($posts, 200, ['X-WP-TotalPages' => 1]),
            'doogsanmedia.com/wp-content/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->fakeWordPress();
        $this->artisan('doogsan:import-wordpress', ['url' => 'https://doogsanmedia.com', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Article::where('import_source', 'wordpress')->count());
    }

    public function test_imports_into_the_right_newsroom_with_redirects(): void
    {
        Storage::fake('public');
        $this->fakeWordPress();
        $this->artisan('doogsan:import-wordpress', ['url' => 'https://doogsanmedia.com'])->assertSuccessful();

        $so = Article::where('import_id', '101')->firstOrFail();
        $en = Article::where('import_id', '102')->firstOrFail();
        $this->assertSame('so', $so->edition);
        $this->assertSame('en', $en->edition);
        $this->assertSame('opinion', $en->format);
        $this->assertNotNull($so->featured_media_id);
        $this->assertNull($so->t('en'), 'stories are never auto-translated');

        $t = $so->t('so');
        $this->assertSame('published', $t->status);
        $this->assertStringNotContainsString('<script', $t->body_html);
        $this->assertStringNotContainsString('vc_row', $t->body_html);
        $this->assertStringContainsString('<h2', $t->body_html);
        $this->assertSame(['Garoowe'], $t->tags->pluck('name')->all());
        $this->assertStringContainsString('&ndash;', htmlentities($en->t('en')->title));

        $redirect = Redirect::where('from_path', '/2026/09/01/madaxweynaha-puntland-oo-booqday-garoowe')->firstOrFail();
        $this->get('/2026/09/01/madaxweynaha-puntland-oo-booqday-garoowe/')->assertRedirect($redirect->to_url)->assertStatus(301);
        $this->get($redirect->to_url)->assertOk()->assertSee('Madaxweynaha Puntland oo booqday Garoowe');

        // Re-running never duplicates.
        $this->artisan('doogsan:import-wordpress', ['url' => 'https://doogsanmedia.com'])->assertSuccessful();
        $this->assertSame(2, Article::where('import_source', 'wordpress')->count());
        $this->assertSame(2, ArticleTranslation::count() - ArticleTranslation::whereHas('article', fn ($q) => $q->whereNull('import_source'))->count());
    }

    public function test_misfiled_story_can_be_moved_to_the_other_newsroom(): void
    {
        Storage::fake('public');
        $this->fakeWordPress();
        $this->artisan('doogsan:import-wordpress', ['url' => 'https://doogsanmedia.com', '--edition' => 'en', '--no-images' => true])->assertSuccessful();
        $article = Article::where('import_id', '101')->firstOrFail();
        $this->assertSame('en', $article->edition);
        $oldPath = parse_url($article->t('en')->url(), PHP_URL_PATH);

        $this->actingAs($this->admin())->post(route('admin.articles.move-edition', $article))->assertRedirect();
        $article->refresh();
        $this->assertSame('so', $article->edition);
        $this->assertNotNull($article->t('so'));
        $this->assertNull($article->t('en'));
        $this->get($oldPath)->assertStatus(301);
    }

    public function test_imports_from_a_wordpress_export_file_with_local_images(): void
    {
        Storage::fake('public');
        Http::fake(); // nothing should be fetched over the network
        $uploads = sys_get_temp_dir().'/wp-uploads-'.uniqid();
        mkdir($uploads.'/2026/09', 0777, true);
        file_put_contents($uploads.'/2026/09/shir.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $this->artisan('doogsan:import-wordpress', [
            'url' => 'https://doogsanmedia.com',
            '--file' => base_path('tests/fixtures/wordpress-export.xml'),
            '--uploads' => $uploads,
        ])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(2, Article::where('import_source', 'wordpress')->count(), 'drafts and attachments are skipped');
        $so = Article::where('import_id', '101')->firstOrFail();
        $this->assertSame('so', $so->edition);
        $this->assertNotNull($so->featured_media_id);
        $this->assertSame(2, substr_count($so->t('so')->body_html, '<p>'));
        $this->assertStringContainsString('<br>', $so->t('so')->body_html);
        $this->assertSame('en', Article::where('import_id', '102')->value('edition'));
        $this->get('/2026/09/02/parliament-approves-budget/')->assertStatus(301);
    }
}
