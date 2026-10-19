<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'slug', 'title_en', 'title_so', 'bio_en', 'bio_so', 'photo_id', 'email',
        'x_handle', 'linkedin_url', 'desk', 'is_staff', 'show_on_team_page', 'team_order', 'is_sample',
    ];

    protected function casts(): array
    {
        return ['is_staff' => 'boolean', 'show_on_team_page' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function titleFor(string $locale): ?string
    {
        return $this->{"title_{$locale}"} ?: $this->title_en;
    }

    public function bioFor(string $locale): ?string
    {
        return $this->{"bio_{$locale}"} ?: $this->bio_en;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
