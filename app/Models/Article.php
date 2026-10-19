<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'edition', 'format', 'category_id', 'author_id', 'featured_media_id', 'live_event_id', 'created_by',
        'assignee_id', 'deadline_at', 'priority', 'is_breaking', 'is_sample', 'video_url', 'audio_url', 'location',
        'import_source', 'import_id',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'is_breaking' => 'boolean',
            'is_sample' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function coAuthors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'article_author');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_media_id');
    }

    public function liveEvent(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function gallery(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'article_media')
            ->withPivot(['role', 'position', 'caption_en', 'caption_so'])
            ->orderByPivot('position');
    }

    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'article_id', 'related_article_id')
            ->withPivot('position')->orderByPivot('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class)->latest();
    }

    public function t(?string $locale = null): ?ArticleTranslation
    {
        $locale ??= app()->getLocale();

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();
    }

    public function formatLabel(?string $locale = null): string
    {
        return config("doogsan.formats.{$this->format}.".($locale ?? app()->getLocale()))
            ?? ucfirst($this->format);
    }

    /** Title in any available language (admin listings). */
    public function displayTitle(): string
    {
        $t = $this->t('en') ?? $this->t('so') ?? $this->translations->first();

        return $t?->title ?? __('Untitled');
    }
}
