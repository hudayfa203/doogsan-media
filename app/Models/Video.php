<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $fillable = [
        'locale', 'kind', 'title', 'slug', 'description', 'provider', 'source_url', 'youtube_id', 'media_id',
        'thumbnail_media_id', 'category_id', 'author_id', 'broadcast_program_id', 'duration_seconds',
        'status', 'is_featured', 'is_sample', 'published_at', 'play_count',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_featured' => 'boolean', 'is_sample' => 'boolean'];
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'thumbnail_media_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function scopeLive(Builder $q, string $locale): Builder
    {
        return $q->where('locale', $locale)->where('status', 'published')->where('published_at', '<=', now());
    }

    public function durationLabel(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }
        $h = intdiv($this->duration_seconds, 3600);
        $m = intdiv($this->duration_seconds % 3600, 60);
        $s = $this->duration_seconds % 60;

        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    public function playerUrl(): ?string
    {
        return match ($this->provider) {
            'youtube' => $this->youtube_id ? 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id : null,
            'file', 'audio' => $this->media?->url() ?? $this->source_url,
            default => $this->source_url,
        };
    }

    public function thumbnailUrl(int $width = 960): ?string
    {
        if ($this->thumbnail) {
            return $this->thumbnail->url($width);
        }

        return $this->youtube_id ? "https://i.ytimg.com/vi/{$this->youtube_id}/hqdefault.jpg" : null;
    }

    public function url(): string
    {
        return lroute('video', ['slug' => $this->slug], $this->locale);
    }
}
