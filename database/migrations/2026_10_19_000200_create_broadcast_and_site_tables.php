<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Broadcasting ────────────────────────────────────────────────
        Schema::create('broadcast_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('source_type', 20); // hls, youtube, external
            $table->text('source_url')->nullable();   // encrypted cast
            $table->text('ingest_notes')->nullable(); // encrypted cast (stream keys etc.)
            $table->string('youtube_video_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_live')->default(false);
            $table->boolean('show_live_indicator')->default(true);
            $table->string('health_status', 20)->default('unknown'); // unknown, healthy, degraded, down
            $table->string('health_message')->nullable();
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamp('went_live_at')->nullable();
            $table->foreignId('poster_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('broadcast_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_channel_id')->constrained()->cascadeOnDelete();
            $table->string('title_en');
            $table->string('title_so');
            $table->text('description_en')->nullable();
            $table->text('description_so')->nullable();
            $table->string('presenter')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->string('recording_url')->nullable();
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->index();
            $table->string('kind', 20)->default('video')->index(); // video, documentary, interview, podcast
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('provider', 20); // youtube, hls, file, audio
            $table->string('source_url')->nullable();
            $table->string('youtube_id')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('authors')->nullOnDelete();
            $table->foreignId('broadcast_program_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_sample')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedBigInteger('play_count')->default(0);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        // ── Site operations ─────────────────────────────────────────────
        Schema::create('breaking_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->index();
            $table->string('headline');
            $table->string('url')->nullable();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('priority')->default(2); // 1 urgent … 3 normal
            $table->boolean('is_urgent_banner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('push_sent')->default(false);
            $table->boolean('is_sample')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('homepage_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('label')->nullable();
            $table->json('layout');
            $table->timestamp('active_from')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['locale', 'active_from']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('location', 30); // primary, footer_news, footer_company, footer_legal
            $table->string('name');
            $table->timestamps();
            $table->unique(['locale', 'location']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('link_type', 20)->default('url'); // url, route, category, page, format
            $table->string('link_value');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('open_new_tab')->default(false);
            $table->boolean('highlight')->default(false);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // about, editorial-policy, corrections, privacy, terms, advertise
            $table->boolean('show_in_footer')->default(true);
            $table->timestamps();
        });

        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('seo_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->unique(['page_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slot', 40)->index(); // home_leaderboard, home_mid, article_inline, sidebar, footer
            $table->string('locale', 5)->nullable(); // null = both editions
            $table->string('advertiser')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('target_url')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 5)->default('en');
            $table->string('token', 64)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('subject');
            $table->text('intro')->nullable();
            $table->json('article_ids')->nullable();
            $table->string('status', 20)->default('draft'); // draft, sending, sent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->string('department', 30)->default('general');
            $table->text('message');
            $table->string('status', 20)->default('new'); // new, read, replied, archived
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('news_tips', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->text('name')->nullable();     // encrypted
            $table->text('contact')->nullable();  // encrypted
            $table->boolean('wants_anonymity')->default(true);
            $table->string('location')->nullable();
            $table->text('details');              // encrypted
            $table->foreignId('media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status', 20)->default('new'); // new, reviewing, assigned, closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->index();
            $table->string('content_type', 20)->default('page'); // article, video, live, page
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('path');
            $table->string('referrer_host')->nullable();
            $table->string('source', 20)->default('direct'); // direct, search, social, referral, internal
            $table->string('device', 10)->default('desktop');
            $table->string('visitor_hash', 64)->index(); // daily-salted, not reversible
            $table->timestamp('created_at')->index();
            $table->index(['content_type', 'content_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20); // success, failed
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['backup_runs', 'audit_logs', 'page_views', 'settings', 'redirects', 'news_tips',
            'contact_messages', 'newsletter_campaigns', 'subscribers', 'ad_placements',
            'page_translations', 'pages', 'menu_items', 'menus', 'homepage_layouts',
            'breaking_alerts', 'videos', 'broadcast_programs', 'broadcast_channels'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
