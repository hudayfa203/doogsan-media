<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('type', 20)->index(); // image, video, audio, document
            $table->string('mime_type', 120);
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('variants')->nullable(); // responsive sizes {480: path, …}
            $table->string('title')->nullable();
            $table->string('alt_en')->nullable();
            $table->string('alt_so')->nullable();
            $table->text('caption_en')->nullable();
            $table->text('caption_so')->nullable();
            $table->string('credit')->nullable();
            $table->string('copyright')->nullable();
            $table->string('license')->nullable();
            $table->string('usage_notes')->nullable();
            $table->string('folder')->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title_en')->nullable();
            $table->string('title_so')->nullable();
            $table->text('bio_en')->nullable();
            $table->text('bio_so')->nullable();
            $table->foreignId('photo_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('x_handle')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('desk')->nullable();
            $table->boolean('is_staff')->default(true);
            $table->boolean('show_on_team_page')->default(true);
            $table->boolean('is_sample')->default(false);
            $table->unsignedInteger('team_order')->default(100);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('locale', 5)->nullable()->index(); // null = section exists in both editions
            $table->string('key')->unique();
            $table->string('color', 9)->nullable();
            $table->unsignedInteger('position')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('in_navigation')->default(true);
            $table->timestamps();
        });

        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->unique(['category_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('live_events', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('scheduled')->index(); // scheduled, live, closed
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->boolean('show_on_homepage')->default(true);
            $table->boolean('is_sample')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('live_event_user', function (Blueprint $table) {
            $table->foreignId('live_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['live_event_id', 'user_id']);
        });

        Schema::create('live_event_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('authors')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('headline')->nullable();
            $table->text('body');
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('embed_url')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_key_event')->default(false);
            $table->string('status', 20)->default('published'); // draft, published
            $table->timestamp('posted_at')->index();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('edition', 5)->index(); // newsroom that originated the story (en|so)
            $table->string('format', 30)->default('news')->index();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('authors')->nullOnDelete();
            $table->foreignId('featured_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('live_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deadline_at')->nullable();
            $table->unsignedTinyInteger('priority')->default(3); // 1 = top, 5 = low
            $table->boolean('is_breaking')->default(false);
            $table->boolean('is_sample')->default(false)->index(); // removable review content
            $table->string('video_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_author', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'author_id']);
        });

        Schema::create('article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('slug');
            $table->text('standfirst')->nullable();
            $table->longText('body')->nullable();       // editor JSON document
            $table->longText('body_html')->nullable();  // sanitized render
            $table->longText('search_text')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('first_published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('unpublish_at')->nullable();
            $table->timestamp('content_updated_at')->nullable(); // public "Updated" stamp
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->foreignId('og_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('is_machine_translated')->default(false);
            $table->foreignId('translation_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'locale']);
            $table->unique(['locale', 'slug']);
            $table->index(['locale', 'status', 'published_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['status', 'unpublish_at']);
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->foreignId('article_translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_translation_id', 'tag_id']);
        });

        Schema::create('article_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('gallery'); // gallery, inline
            $table->unsignedInteger('position')->default(0);
            $table->text('caption_en')->nullable();
            $table->text('caption_so')->nullable();
        });

        Schema::create('article_related', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_article_id')->constrained('articles')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['article_id', 'related_article_id']);
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('reason', 30)->default('save'); // save, autosave, status, rollback, correction
            $table->string('title');
            $table->text('standfirst')->nullable();
            $table->longText('body')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('status', 20);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['article_translation_id', 'version']);
        });

        Schema::create('article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5)->nullable();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('article_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('correction'); // correction, update, clarification
            $table->text('note');
            $table->timestamp('published_at')->useCurrent();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['workflow_transitions', 'article_corrections', 'article_comments', 'article_revisions',
            'article_related', 'article_media', 'article_tag', 'article_translations', 'article_author',
            'articles', 'live_event_updates', 'live_event_user', 'live_events', 'tags',
            'category_translations', 'categories', 'authors', 'media_assets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
