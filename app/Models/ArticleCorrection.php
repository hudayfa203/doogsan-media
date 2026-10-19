<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = ['article_translation_id', 'user_id', 'kind', 'note', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(ArticleTranslation::class, 'article_translation_id');
    }
}
