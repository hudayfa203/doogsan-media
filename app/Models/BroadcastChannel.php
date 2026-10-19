<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BroadcastChannel extends Model
{
    protected $fillable = [
        'name', 'slug', 'source_type', 'source_url', 'ingest_notes', 'youtube_video_id', 'is_enabled',
        'is_live', 'show_live_indicator', 'health_status', 'health_message', 'health_checked_at',
        'went_live_at', 'poster_media_id',
    ];

    protected $hidden = ['source_url', 'ingest_notes'];

    protected function casts(): array
    {
        return [
            'source_url' => 'encrypted',
            'ingest_notes' => 'encrypted',
            'is_enabled' => 'boolean',
            'is_live' => 'boolean',
            'show_live_indicator' => 'boolean',
            'health_checked_at' => 'datetime',
            'went_live_at' => 'datetime',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(BroadcastProgram::class)->orderBy('starts_at');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'poster_media_id');
    }

    public function currentProgram(): ?BroadcastProgram
    {
        return $this->programs()->where('starts_at', '<=', now())->where('ends_at', '>', now())->first();
    }

    /** A stream is "on air" only when an editor marked it live AND a source is configured. */
    public function isOnAir(): bool
    {
        return $this->is_enabled && $this->is_live && $this->hasSource();
    }

    public function hasSource(): bool
    {
        return match ($this->source_type) {
            'youtube' => filled($this->youtube_video_id),
            default => filled($this->source_url),
        };
    }

    /** Public playback URL (HLS manifest / embed URL). Never exposes ingest notes. */
    public function playbackUrl(): ?string
    {
        return match ($this->source_type) {
            'youtube' => $this->youtube_video_id ? 'https://www.youtube-nocookie.com/embed/'.$this->youtube_video_id.'?autoplay=1&mute=1' : null,
            default => $this->source_url,
        };
    }
}
