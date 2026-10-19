<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $fillable = [
        'disk', 'path', 'type', 'mime_type', 'original_name', 'size_bytes', 'width', 'height',
        'duration_seconds', 'variants', 'title', 'alt_en', 'alt_so', 'caption_en', 'caption_so',
        'credit', 'copyright', 'license', 'usage_notes', 'folder', 'uploaded_by', 'source_url',
    ];

    protected function casts(): array
    {
        return ['variants' => 'array'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(?int $width = null): string
    {
        $path = $this->path;

        if ($width && is_array($this->variants)) {
            $best = collect($this->variants)
                ->sortKeys()
                ->first(fn ($p, $w) => (int) $w >= $width);
            $path = $best ?? collect($this->variants)->sortKeys()->last() ?? $path;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk($this->disk)->url($path);
    }

    public function srcset(): ?string
    {
        if (! is_array($this->variants) || empty($this->variants)) {
            return null;
        }

        return collect($this->variants)
            ->map(fn ($path, $w) => (str_starts_with($path, '/') ? asset(ltrim($path, '/')) : Storage::disk($this->disk)->url($path))." {$w}w")
            ->implode(', ');
    }

    public function altFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) ($this->{"alt_{$locale}"} ?: $this->alt_en ?: $this->title ?: '');
    }

    public function captionFor(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->{"caption_{$locale}"} ?: $this->caption_en;
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function humanSize(): string
    {
        $b = $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($b < 1024) {
                return round($b, 1).' '.$u;
            }
            $b /= 1024;
        }

        return round($b, 1).' TB';
    }
}
