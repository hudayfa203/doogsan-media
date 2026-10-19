<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'parent_id', 'label', 'link_type', 'link_value', 'position', 'open_new_tab', 'highlight'];

    protected function casts(): array
    {
        return ['open_new_tab' => 'boolean', 'highlight' => 'boolean'];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position');
    }

    /** Resolve to an absolute URL inside the menu's edition. */
    public function href(?string $locale = null): string
    {
        $locale ??= $this->menu?->locale ?? app()->getLocale();

        try {
            return match ($this->link_type) {
                'route' => lroute($this->link_value, [], $locale),
                'category' => lroute('section', ['slug' => CategoryTranslation::where('locale', $locale)
                    ->whereHas('category', fn ($q) => $q->where('key', $this->link_value))->value('slug') ?? $this->link_value], $locale),
                'format' => lroute('format', ['slug' => config("doogsan.formats.{$this->link_value}.slug.{$locale}", $this->link_value)], $locale),
                'page' => lroute('page', ['slug' => PageTranslation::whereHas('page', fn ($q) => $q->where('key', $this->link_value))->where('locale', $locale)->value('slug') ?? $this->link_value], $locale),
                default => $this->link_value,
            };
        } catch (\Throwable) {
            return url('/'.$locale);
        }
    }
}
