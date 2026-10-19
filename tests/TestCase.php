<?php

namespace Tests;

use App\Models\Article;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Services\BodyRenderer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function userWithRole(string $role, array $newsrooms = null): User
    {
        return User::create([
            'name' => Str::headline($role).' '.Str::random(4),
            'email' => Str::random(8).'@test.doogsanmedia.com',
            'password' => 'Password-123456',
            'role_id' => Role::where('key', $role)->value('id'),
            'newsrooms' => $newsrooms,
            'is_active' => true,
        ]);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@doogsanmedia.com')->firstOrFail();
    }

    /** A published story in one edition only. */
    protected function publishedStory(string $edition, string $title, array $extra = []): Article
    {
        $article = Article::create($extra + [
            'edition' => $edition, 'format' => 'news', 'priority' => 3,
            'category_id' => Category::where('key', 'puntland')->value('id'),
        ]);
        $doc = BodyRenderer::docFromParagraphs([str_repeat('Reporting paragraph. ', 12)]);
        $r = app(BodyRenderer::class)->render($doc, $edition);
        $article->translations()->create([
            'locale' => $edition, 'title' => $title, 'slug' => Str::slug($title), 'standfirst' => 'Standfirst',
            'body' => json_encode($doc), 'body_html' => $r['html'], 'search_text' => $r['text'],
            'status' => 'published', 'published_at' => now()->subMinute(),
        ]);

        return $article->fresh('translations');
    }
}
