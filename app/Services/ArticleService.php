<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleCorrection;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArticleService
{
    public function __construct(protected BodyRenderer $renderer) {}

    /**
     * Create or update an article and any language versions present in $data.
     *
     * $data = [
     *   'format','category_id','author_id','featured_media_id','priority','is_breaking','assignee_id',
     *   'deadline_at','video_url','audio_url','location','live_event_id','co_authors'=>[], 'related'=>[], 'gallery'=>[],
     *   'translations' => ['en' => [title, slug, standfirst, body(json), seo_title, seo_description, tags, unpublish_at, ...], 'so' => [...]],
     * ]
     */
    public function save(?Article $article, array $data, User $user, string $reason = 'save'): Article
    {
        return DB::transaction(function () use ($article, $data, $user, $reason) {
            $isNew = ! $article;
            $article ??= new Article(['created_by' => $user->id]);

            if ($isNew) {
                $article->edition = $data['edition'] ?? array_key_first($data['translations'] ?? ['en' => 1]);
            }
            $article->fill(collect($data)->only([
                'format', 'category_id', 'author_id', 'featured_media_id', 'priority', 'assignee_id',
                'deadline_at', 'video_url', 'audio_url', 'location', 'live_event_id',
            ])->all());
            $article->is_breaking = (bool) ($data['is_breaking'] ?? $article->is_breaking);
            $article->save();

            if (array_key_exists('co_authors', $data)) {
                $article->coAuthors()->sync(array_filter((array) $data['co_authors']));
            }
            if (array_key_exists('related', $data)) {
                $article->related()->sync(collect((array) $data['related'])->filter()
                    ->reject(fn ($id) => (int) $id === $article->id)
                    ->values()->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
            }
            if (array_key_exists('gallery', $data)) {
                $article->gallery()->detach();
                foreach (array_values(array_filter((array) $data['gallery'])) as $i => $mediaId) {
                    $article->gallery()->attach($mediaId, ['role' => 'gallery', 'position' => $i]);
                }
            }

            foreach ($data['translations'] ?? [] as $locale => $tData) {
                if (! array_key_exists($locale, editions()) || blank($tData['title'] ?? null)) {
                    continue;
                }
                $this->saveTranslation($article, $locale, $tData, $user, $reason);
            }

            AuditLog::record($isNew ? 'article.created' : 'article.updated', ($isNew ? 'Created' : 'Updated').' article #'.$article->id.' '.$article->displayTitle(), $article);

            return $article->fresh(['translations']);
        });
    }

    public function saveTranslation(Article $article, string $locale, array $d, User $user, string $reason = 'save'): ArticleTranslation
    {
        /** @var ArticleTranslation $t */
        $t = $article->translations()->firstOrNew(['locale' => $locale]);

        if ($t->exists && $t->isLockedFor($user)) {
            throw ValidationException::withMessages([
                "translations.{$locale}.title" => __(':name is editing the :lang version. Try again when they finish.', ['name' => $t->locker?->name, 'lang' => editions()[$locale]['name']]),
            ]);
        }

        // Optimistic concurrency: refuse to overwrite a newer version.
        if ($t->exists && isset($d['version']) && (int) $d['version'] < $t->version && $reason !== 'autosave') {
            throw ValidationException::withMessages([
                "translations.{$locale}.title" => __('This version changed since you opened it (now v:v). Reload to compare before saving.', ['v' => $t->version]),
            ]);
        }

        $body = $d['body'] ?? $t->body;
        $bodyArr = is_string($body) ? json_decode($body, true) : $body;
        $rendered = $this->renderer->render($bodyArr, $locale);

        $t->fill([
            'title' => trim($d['title']),
            'standfirst' => $d['standfirst'] ?? $t->standfirst,
            'body' => $bodyArr ? json_encode($bodyArr, JSON_UNESCAPED_UNICODE) : null,
            'body_html' => $rendered['html'],
            'search_text' => mb_substr($rendered['text'], 0, 60000),
            'reading_minutes' => $rendered['minutes'],
            'seo_title' => $d['seo_title'] ?? $t->seo_title,
            'seo_description' => $d['seo_description'] ?? $t->seo_description,
            'og_media_id' => $d['og_media_id'] ?? $t->og_media_id,
            'canonical_url' => $d['canonical_url'] ?? $t->canonical_url,
            'noindex' => (bool) ($d['noindex'] ?? $t->noindex),
            'unpublish_at' => array_key_exists('unpublish_at', $d) ? ($d['unpublish_at'] ?: null) : $t->unpublish_at,
            'is_machine_translated' => (bool) ($d['is_machine_translated'] ?? $t->is_machine_translated),
        ]);

        $t->slug = $this->uniqueSlug($locale, $d['slug'] ?? null ?: ($t->slug ?: $d['title']), $t->id);

        if (! $t->exists) {
            $t->status = 'draft';
            $t->version = 1;
        } else {
            $t->version++;
        }

        if ($t->status === 'published' && $reason !== 'autosave') {
            $t->content_updated_at = now();
        }

        $t->save();

        if (array_key_exists('tags', $d)) {
            $names = is_array($d['tags']) ? $d['tags'] : explode(',', (string) $d['tags']);
            $ids = collect($names)->map(fn ($n) => trim($n))->filter()->unique()
                ->map(fn ($n) => Tag::findOrCreateFor($locale, $n)->id);
            $t->tags()->sync($ids);
        }

        // Autosaves are kept as one rolling revision per user to avoid noise.
        if ($reason === 'autosave') {
            ArticleRevision::where('article_translation_id', $t->id)->where('reason', 'autosave')->where('user_id', $user->id)->delete();
        }

        ArticleRevision::create([
            'article_translation_id' => $t->id,
            'user_id' => $user->id,
            'version' => $t->version,
            'reason' => $reason,
            'title' => $t->title,
            'standfirst' => $t->standfirst,
            'body' => $t->body,
            'body_html' => $t->body_html,
            'status' => $t->status,
            'note' => $d['revision_note'] ?? null,
        ]);

        if ($t->status === 'published') {
            app(HomepageService::class)->flush($locale);
        }

        return $t;
    }

    public function rollback(ArticleTranslation $t, ArticleRevision $rev, User $user): ArticleTranslation
    {
        return DB::transaction(function () use ($t, $rev, $user) {
            $t->update([
                'title' => $rev->title,
                'standfirst' => $rev->standfirst,
                'body' => $rev->body,
                'body_html' => $rev->body_html,
                'version' => $t->version + 1,
                'content_updated_at' => $t->status === 'published' ? now() : $t->content_updated_at,
            ]);
            ArticleRevision::create([
                'article_translation_id' => $t->id, 'user_id' => $user->id, 'version' => $t->version,
                'reason' => 'rollback', 'title' => $t->title, 'standfirst' => $t->standfirst, 'body' => $t->body,
                'body_html' => $t->body_html, 'status' => $t->status, 'note' => "Restored v{$rev->version}",
            ]);
            AuditLog::record('article.rollback', "Restored v{$rev->version} of “{$t->title}” ({$t->locale})", $t);
            app(HomepageService::class)->flush($t->locale);

            return $t;
        });
    }

    public function addCorrection(ArticleTranslation $t, string $kind, string $note, User $user): ArticleCorrection
    {
        $c = ArticleCorrection::create([
            'article_translation_id' => $t->id, 'user_id' => $user->id, 'kind' => $kind, 'note' => $note, 'published_at' => now(),
        ]);
        $t->update(['content_updated_at' => now()]);
        AuditLog::record('article.correction', ucfirst($kind)." on “{$t->title}”: {$note}", $t);

        return $c;
    }

    public function duplicate(Article $article, User $user): Article
    {
        return DB::transaction(function () use ($article, $user) {
            $copy = $article->replicate(['is_breaking']);
            $copy->created_by = $user->id;
            $copy->save();
            foreach ($article->translations as $t) {
                $n = $t->replicate(['view_count', 'published_at', 'first_published_at', 'scheduled_at', 'content_updated_at', 'locked_by', 'locked_at']);
                $n->article_id = $copy->id;
                $n->status = 'draft';
                $n->version = 1;
                $n->title = $t->title.' '.__('(copy)');
                $n->slug = $this->uniqueSlug($t->locale, $t->slug.'-copy');
                $n->save();
                $n->tags()->sync($t->tags->pluck('id'));
            }
            AuditLog::record('article.duplicated', "Duplicated #{$article->id} as #{$copy->id}", $copy);

            return $copy;
        });
    }

    public function lock(ArticleTranslation $t, User $user): bool
    {
        if ($t->isLockedFor($user)) {
            return false;
        }
        $t->forceFill(['locked_by' => $user->id, 'locked_at' => now()])->saveQuietly();

        return true;
    }

    public function unlock(ArticleTranslation $t, User $user): void
    {
        if ($t->locked_by === $user->id || $user->hasPermission('articles.publish')) {
            $t->forceFill(['locked_by' => null, 'locked_at' => null])->saveQuietly();
        }
    }

    public function uniqueSlug(string $locale, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($source, 90, '')) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;
        while (ArticleTranslation::where('locale', $locale)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
