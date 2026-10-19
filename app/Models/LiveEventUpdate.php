<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveEventUpdate extends Model
{
    protected $fillable = [
        'live_event_id', 'author_id', 'user_id', 'headline', 'body', 'media_id', 'embed_url',
        'is_pinned', 'is_key_event', 'status', 'posted_at',
    ];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'is_key_event' => 'boolean', 'posted_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(LiveEvent::class, 'live_event_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    public function toBroadcastArray(): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
            'is_key_event' => $this->is_key_event,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'author' => $this->author?->name,
            'image' => $this->media?->url(960),
        ];
    }
}
