<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakingAlert extends Model
{
    protected $fillable = [
        'locale', 'headline', 'url', 'article_id', 'priority', 'is_urgent_banner', 'is_active',
        'push_sent', 'is_sample', 'starts_at', 'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_urgent_banner' => 'boolean',
            'is_active' => 'boolean',
            'push_sent' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderBy('priority')->latest();
    }

    public function isCurrent(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->expires_at || $this->expires_at->gt(now()));
    }

    public function link(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return $this->article?->t($this->locale)?->isLive() ? $this->article->t($this->locale)->url() : null;
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'url' => $this->link(),
            'priority' => $this->priority,
            'urgent' => $this->is_urgent_banner,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
