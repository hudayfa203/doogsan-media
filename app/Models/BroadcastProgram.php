<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastProgram extends Model
{
    protected $fillable = [
        'broadcast_channel_id', 'title_en', 'title_so', 'description_en', 'description_so',
        'presenter', 'starts_at', 'ends_at', 'recording_url',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(BroadcastChannel::class, 'broadcast_channel_id');
    }

    public function titleFor(?string $locale = null): string
    {
        return $this->{'title_'.($locale ?? app()->getLocale())} ?: $this->title_en;
    }

    public function descriptionFor(?string $locale = null): ?string
    {
        return $this->{'description_'.($locale ?? app()->getLocale())} ?: $this->description_en;
    }

    public function isOnNow(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }
}
