<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleTranslation extends Model
{
    public const STATUSES = ['draft', 'submitted', 'editing', 'approved', 'scheduled', 'published', 'archived'];

    protected $fillable = [
        'article_id', 'locale', 'title', 'slug', 'standfirst', 'body', 'body_html', 'search_text',
        'status', 'published_at', 'first_published_at', 'scheduled_at', 'unpublish_at', 'content_updated_at',
        'reading_minutes', 'seo_title', 'seo_description', 'og_media_id', 'canonical_url', 'noindex',
        'is_machine_translated', 'translation_approved_by', 'locked_by', 'locked_at', 'version', 'view_count',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'first_published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'locked_at' => 'datetime',
            'noindex' => 'boolean',
            'is_machine_translated' => 'boolean',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tag');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->orderByDesc('version');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(ArticleCorrection::class)->orderByDesc('published_at');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)->latest('created_at');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_media_id');
    }

    /** Publicly visible right now. */
    public function scopeLive(Builder $q, ?string $locale = null): Builder
    {
        return $q->where('status', 'published')
            ->when($locale, fn ($q) => $q->where('locale', $locale))
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('unpublish_at')->orWhere('unpublish_at', '>', now()));
    }

    public function isLive(): bool
    {
        return $this->status === 'published'
            && $this->published_at?->lte(now())
            && (! $this->unpublish_at || $this->unpublish_at->gt(now()));
    }

    public function isLockedFor(User $user): bool
    {
        return $this->locked_by
            && $this->locked_by !== $user->id
            && $this->locked_at?->gt(now()->subMinutes(10));
    }

    public function url(): string
    {
        return lroute('article', ['slug' => $this->slug], $this->locale);
    }

    public function wasUpdated(): bool
    {
        return $this->content_updated_at
            && $this->published_at
            && $this->content_updated_at->gt($this->published_at->copy()->addMinutes(5));
    }
}
