<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdPlacement extends Model
{
    public const SLOTS = [
        'home_leaderboard' => 'Homepage — leaderboard (970×90 / 728×90)',
        'home_mid' => 'Homepage — mid-page billboard (970×250)',
        'sidebar' => 'Sidebar — MPU (300×250)',
        'article_inline' => 'Article — in-story (728×90)',
        'footer' => 'Footer banner (970×90)',
    ];

    protected $fillable = [
        'name', 'slot', 'locale', 'advertiser', 'media_id', 'target_url', 'alt_text', 'weight',
        'is_active', 'starts_at', 'ends_at', 'impressions', 'clicks',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    public function scopeServing(Builder $q, string $slot, string $locale): Builder
    {
        return $q->where('slot', $slot)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('locale')->orWhere('locale', $locale))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function ctr(): float
    {
        return $this->impressions ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }
}
