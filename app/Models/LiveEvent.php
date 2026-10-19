<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveEvent extends Model
{
    protected $fillable = [
        'locale', 'title', 'slug', 'summary', 'cover_media_id', 'category_id', 'status',
        'starts_at', 'ended_at', 'show_on_homepage', 'is_sample', 'created_by',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ended_at' => 'datetime', 'show_on_homepage' => 'boolean'];
    }

    public function updates(): HasMany
    {
        return $this->hasMany(LiveEventUpdate::class)
            ->where('status', 'published')
            ->orderByDesc('is_pinned')->orderByDesc('posted_at');
    }

    public function allUpdates(): HasMany
    {
        return $this->hasMany(LiveEventUpdate::class)->orderByDesc('posted_at');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function journalists(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeActive(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale)->where('status', 'live');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function url(): string
    {
        return lroute('live.show', ['slug' => $this->slug], $this->locale);
    }
}
