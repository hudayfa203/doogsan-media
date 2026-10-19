<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = ['key', 'show_in_footer'];

    protected function casts(): array
    {
        return ['show_in_footer' => 'boolean'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function t(?string $locale = null): ?PageTranslation
    {
        $locale ??= app()->getLocale();

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();
    }
}
