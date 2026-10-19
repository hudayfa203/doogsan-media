<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Category extends Model
{
    protected $fillable = ['parent_id', 'locale', 'key', 'color', 'position', 'is_active', 'in_navigation'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'in_navigation' => 'boolean'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation(?string $locale = null): HasOne
    {
        return $this->hasOne(CategoryTranslation::class)->where('locale', $locale ?? app()->getLocale());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /** Sections available in an edition: shared ones plus that edition's own. */
    public function scopeForEdition(Builder $q, string $locale): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('locale')->orWhere('locale', $locale));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position');
    }

    public function t(?string $locale = null): ?CategoryTranslation
    {
        $locale ??= app()->getLocale();

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();
    }

    public function nameFor(?string $locale = null): string
    {
        return $this->t($locale)?->name ?? $this->t('en')?->name ?? ucfirst($this->key);
    }
}
