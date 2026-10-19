<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    protected $fillable = ['page_id', 'locale', 'title', 'slug', 'summary', 'body_html', 'seo_description', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function url(): string
    {
        return lroute('page', ['slug' => $this->slug], $this->locale);
    }
}
