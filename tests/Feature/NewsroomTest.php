<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Category;
use App\Services\WorkflowService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NewsroomTest extends TestCase
{
    public function test_admin_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_can_sign_in(): void
    {
        $this->post('/admin/login', ['email' => 'admin@doogsanmedia.com', 'password' => 'Test-Admin-Password-1'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Executive Overview');
    }

    public function test_somali_newsroom_creates_somali_only_draft(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/newsroom/so/articles', [
                'format' => 'news', 'priority' => 3, 'title' => 'Sheeko cusub oo tijaabo ah',
                'category_id' => Category::where('key', 'puntland')->value('id'),
                'body' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Qoraal.']]]]]),
            ])->assertRedirect();

        $a = Article::latest('id')->first();
        $this->assertSame('so', $a->edition);
        $this->assertSame(['so'], $a->translations->pluck('locale')->all());
        $this->assertSame('draft', $a->t('so')->status);
        $this->get('/so')->assertDontSee('Sheeko cusub oo tijaabo ah');
    }

    public function test_journalist_assigned_to_english_cannot_open_somali_newsroom(): void
    {
        $j = $this->userWithRole('journalist', ['en']);
        $this->actingAs($j)->get('/admin/newsroom/en/articles')->assertOk();
        $this->actingAs($j)->get('/admin/newsroom/so/articles')->assertForbidden();
    }

    public function test_journalist_cannot_publish_but_editor_can(): void
    {
        $a = $this->publishedStory('en', 'Workflow story');
        $t = $a->t('en');
        $t->update(['status' => 'approved', 'published_at' => null]);
        $a->update(['author_id' => \App\Models\Author::create(['name' => 'Test Writer', 'slug' => 'test-writer'])->id]);

        $journalist = $this->userWithRole('journalist');
        $this->assertNotContains('published', app(WorkflowService::class)->allowedTargets($t->fresh(), $journalist));

        $editor = $this->userWithRole('news-editor');
        app(WorkflowService::class)->transition($t->fresh(), 'published', $editor);
        $this->assertTrue($t->fresh()->isLive());
        $this->get('/en')->assertSee('Workflow story');
    }

    public function test_publishing_blocked_by_checklist(): void
    {
        $a = Article::create(['edition' => 'en', 'format' => 'news', 'priority' => 3]);
        $t = $a->translations()->create(['locale' => 'en', 'title' => 'Thin', 'slug' => 'thin', 'status' => 'approved']);

        $this->expectException(ValidationException::class);
        app(WorkflowService::class)->transition($t, 'published', $this->admin());
    }

    public function test_scheduled_story_publishes_when_due(): void
    {
        $a = $this->publishedStory('so', 'Sheeko jadwalaysan');
        $t = $a->t('so');
        $t->update(['status' => 'scheduled', 'scheduled_at' => now()->subMinute(), 'published_at' => now()->subMinute()]);

        $result = app(WorkflowService::class)->runSchedule();
        $this->assertSame(1, $result['published']);
        $this->assertSame('published', $t->fresh()->status);
    }

    public function test_breaking_alert_is_edition_specific(): void
    {
        $this->actingAs($this->admin())->post('/admin/breaking', [
            'locale' => 'so', 'headline' => 'War deg-deg ah oo tijaabo', 'priority' => 1, 'duration' => 60,
        ])->assertRedirect();

        $this->getJson('/api/so/pulse')->assertJsonPath('alerts.0.headline', 'War deg-deg ah oo tijaabo');
        $this->getJson('/api/en/pulse')->assertJsonCount(0, 'alerts');
    }

    public function test_body_renderer_strips_unsafe_content(): void
    {
        $html = app(\App\Services\BodyRenderer::class)->render(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
        ]])['html'];
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_sample_content_installs_and_removes_cleanly(): void
    {
        $real = $this->publishedStory('en', 'Real newsroom story');
        $svc = app(\App\Services\SampleContent::class);
        $svc->install('Sample-Pass-123');
        $this->assertGreaterThan(30, Article::where('is_sample', true)->count());
        $svc->remove();
        $this->assertSame(0, Article::where('is_sample', true)->count());
        $this->assertNotNull(Article::find($real->id));
    }
}
