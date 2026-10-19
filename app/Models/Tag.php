<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['locale', 'name', 'slug'];

    public function translations(): BelongsToMany
    {
        return $this->belongsToMany(ArticleTranslation::class, 'article_tag');
    }

    public static function findOrCreateFor(string $locale, string $name): self
    {
        $name = trim($name);

        return static::firstOrCreate(
            ['locale' => $locale, 'slug' => Str::slug($name) ?: Str::random(8)],
            ['name' => $name],
        );
    }
}
